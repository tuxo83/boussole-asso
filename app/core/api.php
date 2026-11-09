<?php
// API et MCP : clés personnelles, et les actions communes (mêmes droits que sur le site)
declare(strict_types=1);

const CLE_DUREES = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 365 => '1 an', 0 => 'Sans limite (révocable à tout moment)'];

function cle_active(array $cle): bool
{
    return !$cle['revoquee'] && ($cle['expire_le'] === null || $cle['expire_le'] >= date('Y-m-d'));
}

function cle_validite(array $cle): string
{
    return match (true) {
        (bool) $cle['revoquee'] => 'révoquée',
        $cle['expire_le'] === null => 'sans limite de durée',
        cle_active($cle) => "jusqu'au " . date('d/m/Y', strtotime($cle['expire_le'])),
        default => 'expirée',
    };
}

// Configuration MCP prête à coller dans un client MCP : adresse du serveur et clé
function mcp_configuration(string $cle): string
{
    $nom = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', (string) setting('nom_association', 'boussole')) ?: 'boussole')), '-') ?: 'boussole';
    return json_encode(['mcpServers' => [$nom => ['type' => 'http', 'url' => absolute_url('/mcp'), 'headers' => ['Authorization' => 'Bearer ' . $cle]]]],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

final class ApiErreur extends RuntimeException
{
    public function __construct(string $message, public readonly int $statut = 400)
    {
        parent::__construct($message);
    }
}

// Membre authentifié par clé d'API pour cette requête (null sinon)
function api_member(?array $set = null, bool $reset = false): ?array
{
    static $member = null;
    if ($set !== null || $reset) {
        $member = $set;
    }
    return $member;
}

function api_portee(?string $set = null): string
{
    static $portee = 'lecture';
    if ($set !== null) {
        $portee = $set;
    }
    return $portee;
}

// Clé : bsl_<préfixe>_<secret> ; on garde le préfixe (pour la retrouver) et l'empreinte du tout
function api_creer_cle(array $member, string $libelle, string $portee, int $jours): string
{
    $prefixe = bin2hex(random_bytes(4));
    $cle = 'bsl_' . $prefixe . '_' . bin2hex(random_bytes(20));
    db_query('INSERT INTO cle_api (membre_id, libelle, prefixe, empreinte, portee, expire_le) VALUES (?, ?, ?, ?, ?, IF(? = 0, NULL, CURDATE() + INTERVAL ? DAY))',
        [$member['id'], $libelle, $prefixe, hash('sha256', $cle), $portee === 'admin' && !is_admin($member) ? 'ecriture' : (in_array($portee, ['ecriture', 'admin'], true) ? $portee : 'lecture'), $jours, $jours]);
    return $cle;
}

// Authentifie la requête par l'en-tête « Authorization: Bearer <clé> »
function api_authentifier(): void
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer\s+(bsl_([a-f0-9]{8})_[a-f0-9]{40})$/', trim($header), $m)) {
        throw new ApiErreur('Clé d\'API absente ou mal formée (en-tête Authorization: Bearer …)', 401);
    }
    if (ip_blocked() || too_many_attempts('api|' . $m[2], 600, 15)) {
        throw new ApiErreur('Trop de requêtes, réessayez plus tard', 429);
    }
    $cle = db_one('SELECT * FROM cle_api WHERE prefixe = ? AND revoquee = 0 AND (expire_le IS NULL OR expire_le >= CURDATE())', [$m[2]]);
    if ($cle === null || !hash_equals($cle['empreinte'], hash('sha256', $m[1]))) {
        too_many_attempts('ip|' . client_ip(), 30);
        throw new ApiErreur('Clé d\'API invalide, expirée ou révoquée', 401);
    }
    $member = db_one('SELECT * FROM membre WHERE id = ? AND actif = 1', [$cle['membre_id']]);
    if ($member === null) {
        throw new ApiErreur('Compte inactif', 401);
    }
    db_query('UPDATE cle_api SET derniere_utilisation = NOW() WHERE id = ?', [$cle['id']]);
    api_member($member);
    api_portee($cle['portee']);
}

function api_ecriture(): void
{
    if (!in_array(api_portee(), ['ecriture', 'admin'], true)) {
        throw new ApiErreur('Cette clé est en lecture seule', 403);
    }
}

// Réglages et membres : clé « administration » ET droit correspondant du propriétaire
function api_administration(string $droit = 'admin'): array
{
    $member = current_member();
    if (api_portee() !== 'admin') {
        throw new ApiErreur('Il faut une clé d\'API de portée « administration »', 403);
    }
    if (!has_right($member, $droit)) {
        throw new ApiErreur('Droit « ' . $droit . ' » nécessaire', 403);
    }
    return $member;
}

const REGLAGES_API = ['nom_association' => 150, 'slogan' => 200, 'pied_de_page' => 500, 'lien_adhesion' => 300, 'bandeau_image' => 80, 'bandeau_legende' => 300, 'logo' => 80,
    'couleur_principale' => 7, 'couleur_accent' => 7, 'couleur_membres' => 7, 'arrivee_cotisant' => 120, 'arrivee_non_cotisant' => 120];

// Réglages du site (administration) : texte, lien d'adhésion (https), image du bandeau (image déjà envoyée)
function api_reglages(array $args): array
{
    $member = api_administration();
    foreach (REGLAGES_API as $cle => $max) {
        if (!array_key_exists($cle, $args)) {
            continue;
        }
        $valeur = mb_substr(trim((string) $args[$cle]), 0, $max);
        $valide = match ($cle) {
            'nom_association' => $valeur !== '',
            'lien_adhesion' => $valeur === '' || preg_match('#^https://[^\s"<>]+$#', $valeur) === 1,
            'bandeau_image', 'logo' => $valeur === '' || preg_match('#^uploads/img-[a-f0-9]{24}\.(webp|png|jpg)$#', $valeur) === 1,
            'couleur_principale', 'couleur_accent', 'couleur_membres' => preg_match('/^#[0-9a-fA-F]{6}$/', $valeur) === 1,
            'arrivee_cotisant', 'arrivee_non_cotisant' => $valeur === '' || connexion_arrivee_valide($valeur),
            default => true,
        };
        if (!$valide) {
            throw new ApiErreur("Valeur refusée pour « $cle »");
        }
        setting_set($cle, $valeur);
        journal(null, $member, "Réglage modifié : $cle");
    }
    // Site en construction : bandeau et code d'accès à 6 chiffres (tiré au hasard s'il n'y en a pas)
    if (array_key_exists('site_en_construction', $args)) {
        $actif = (bool) $args['site_en_construction'];
        if ($actif && !preg_match('/^\d{6}$/', (string) setting('construction_code', ''))) {
            setting_set('construction_code', sprintf('%06d', random_int(0, 999999)));
        }
        setting_set('construction_active', $actif ? '1' : '');
        journal(null, $member, 'Site en construction : ' . ($actif ? 'activé' : 'désactivé'));
    }
    return array_combine(array_keys(REGLAGES_API), array_map(fn($c) => (string) setting($c, ''), array_keys(REGLAGES_API)))
        + ['site_en_construction' => setting('construction_active', '') === '1', 'code_acces' => setting('construction_active', '') === '1' ? (string) setting('construction_code', '') : ''];
}

// Ajouter un membre (droit « membres », clé « administration »)
function api_membre_ajouter(array $args): array
{
    $member = api_administration('membres');
    $email = mb_strtolower(trim((string) ($args['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ApiErreur('E-mail invalide');
    }
    if (db_one('SELECT id FROM membre WHERE email = ?', [$email]) !== null) {
        throw new ApiErreur('Ce membre existe déjà', 409);
    }
    db_query('INSERT INTO membre (email, prenom, nom, actif) VALUES (?, ?, ?, 1)', [$email, mb_substr(trim((string) ($args['prenom'] ?? '')), 0, 100), mb_substr(trim((string) ($args['nom'] ?? '')), 0, 100)]);
    journal(null, $member, 'Membre ajouté : ' . $email);
    return ['email' => $email, 'prenom' => (string) ($args['prenom'] ?? ''), 'nom' => (string) ($args['nom'] ?? '')];
}

// Modifier un membre (clé « administration », droit « membres » ; compte de démonstration : administrateur).
// Jamais par l'API : mot de passe, double authentification, clés (voir docs/regles.md).
function api_membre_modifier(array $args): array
{
    $member = api_administration('membres');
    $cible = db_one('SELECT * FROM membre WHERE email = ?', [mb_strtolower(trim((string) ($args['email'] ?? '')))]);
    if ($cible === null) {
        throw new ApiErreur('Membre introuvable', 404);
    }
    $champs = [];
    foreach (['prenom', 'nom'] as $c) {
        if (array_key_exists($c, $args)) {
            $champs[$c] = mb_substr(trim((string) $args[$c]), 0, 100);
        }
    }
    if (array_key_exists('actif', $args) && (int) $cible['id'] !== (int) $member['id']) {
        $champs['actif'] = $args['actif'] ? 1 : 0;
    }
    if (array_key_exists('compte_demo', $args)) {
        if (!is_admin($member)) {
            throw new ApiErreur('Compte de démonstration : administrateur seulement', 403);
        }
        if ((int) $cible['id'] === (int) $member['id']) {
            throw new ApiErreur('Pas sur votre propre compte', 403);
        }
        $champs['compte_demo'] = $args['compte_demo'] ? 1 : 0;
    }
    if ($champs) {
        db_query('UPDATE membre SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($champs))) . ' WHERE id = ?', [...array_values($champs), $cible['id']]);
        if (($champs['actif'] ?? 1) === 0) {
            membre_deconnecter_partout((int) $cible['id']);
        }
        if (isset($champs['compte_demo']) && $champs['compte_demo'] !== (int) $cible['compte_demo']) {
            journal_compte((int) $cible['id'], $member, $champs['compte_demo'] ? 'Marqué compte de démonstration' : 'N\'est plus un compte de démonstration');
        }
        journal(null, $member, 'Membre modifié : ' . $cible['email']);
    }
    $apres = db_one('SELECT email, prenom, nom, actif, compte_demo FROM membre WHERE id = ?', [$cible['id']]);
    return ['email' => $apres['email'], 'prenom' => $apres['prenom'], 'nom' => $apres['nom'], 'actif' => (bool) $apres['actif'], 'compte_demo' => (bool) $apres['compte_demo']];
}

// Effacer un membre définitivement (clé « administration », administrateur) : il faut redonner son e-mail dans « confirmation »
function api_membre_effacer(array $args): array
{
    $member = api_administration('admin');
    $email = mb_strtolower(trim((string) ($args['email'] ?? '')));
    $cible = db_one('SELECT * FROM membre WHERE email = ?', [$email]);
    if ($cible === null) {
        throw new ApiErreur('Membre introuvable', 404);
    }
    if ((int) $cible['id'] === (int) $member['id']) {
        throw new ApiErreur('Pas sur votre propre compte', 403);
    }
    if (mb_strtolower(trim((string) ($args['confirmation'] ?? ''))) !== $email) {
        throw new ApiErreur('Confirmation : redonnez l\'e-mail du membre à effacer');
    }
    membre_effacer($cible, $member, "à la demande d'un administrateur (API)");
    return ['efface' => $email];
}

// ---------- Actions (appelées par l'API JSON et par le MCP)

function api_moi(): array
{
    $m = current_member();
    return ['email' => $m['email'], 'prenom' => $m['prenom'], 'nom' => $m['nom'], 'droits' => member_rights($m['id']), 'portee_cle' => api_portee()];
}

function api_espace_resume(array $e): array
{
    return ['slug' => $e['slug'], 'type' => $e['type'], 'titre' => $e['titre'], 'meteo' => $e['meteo'], 'meteo_auto' => !empty($e['meteo_auto']), 'visibilite' => $e['visibilite'], 'archive' => (bool) $e['archive'], 'alertes' => espace_alertes($e)];
}

function api_espaces(): array
{
    $member = current_member();
    return array_values(array_map('api_espace_resume', array_filter(db_all('SELECT * FROM espace WHERE archive = 0 ORDER BY titre'), fn($e) => espace_peut_voir($member, $e))));
}

function api_espace(string $slug): array
{
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_voir(current_member(), $espace)) {
        throw new ApiErreur('Espace introuvable', 404);
    }
    return api_espace_resume($espace) + [
        'description' => $espace['description'],
        'equipe' => db_all('SELECT m.email, m.prenom, m.nom, em.niveau, em.libelle FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ?', [$espace['id']]),
        'dates_cles' => db_all('SELECT id, date_jalon AS date, libelle, fait FROM jalon WHERE espace_id = ? ORDER BY date_jalon', [$espace['id']]),
        'fichiers' => db_all('SELECT id, nom, taille, depose_le FROM fichier WHERE espace_id = ?', [$espace['id']]),
    ];
}

function api_espace_modifier(string $slug, array $args): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_contribuer($member, $espace)) {
        throw new ApiErreur('Espace introuvable ou droits insuffisants', 403);
    }
    if (isset($args['meteo'])) {
        if (!in_array($args['meteo'], ['soleil', 'eclaircie', 'pluie', 'orage'], true)) {
            throw new ApiErreur('Météo : soleil, eclaircie, pluie ou orage');
        }
        db_query('UPDATE espace SET meteo = ?, meteo_auto = 0, meteo_raison = "" WHERE id = ?', [$args['meteo'], $espace['id']]);
        journal($espace['id'], $member, 'Météo : ' . $args['meteo']);
    }
    if (isset($args['description'])) {
        if (!espace_peut_gerer($member, $espace)) {
            throw new ApiErreur('Seuls le n°1 et le n°2 modifient la description', 403);
        }
        db_query('UPDATE espace SET description = ? WHERE id = ?', [mb_substr((string) $args['description'], 0, 20000), $espace['id']]);
        journal($espace['id'], $member, 'Description modifiée');
    }
    // Météo automatique : d'après le responsable et les dates clés (un choix de météo à la main la coupe)
    if (isset($args['meteo_auto'])) {
        db_query('UPDATE espace SET meteo_auto = ? WHERE id = ?', [$args['meteo_auto'] ? 1 : 0, $espace['id']]);
        journal($espace['id'], $member, $args['meteo_auto'] ? 'Météo automatique activée' : 'Météo automatique coupée');
        espace_meteo_actualiser(espace_par_slug($espace['slug']));
    }
    if (isset($args['archive'])) {
        if (!espace_peut_gerer($member, $espace)) {
            throw new ApiErreur('Seuls le n°1, le n°2 et l\'administrateur archivent un espace', 403);
        }
        $archive = $args['archive'] ? 1 : 0;
        db_query('UPDATE espace SET archive = ? WHERE id = ?', [$archive, $espace['id']]);
        journal($espace['id'], $member, $archive ? 'Espace archivé' : 'Espace désarchivé');
    }
    return api_espace($slug);
}

// Créer un espace : l'administrateur partout ; le n°1 ou le n°2 d'un espace, en dessous de celui-ci
function api_espace_creer(array $args): array
{
    api_ecriture();
    $member = current_member();
    $parent = !empty($args['parent']) ? espace_par_slug((string) $args['parent']) : null;
    if (!is_admin($member) && ($parent === null || !espace_peut_gerer($member, $parent))) {
        throw new ApiErreur("Créer un espace : administrateur, ou n°1 / n°2 de l'espace parent", 403);
    }
    $type = (string) ($args['type'] ?? '');
    $titre = mb_substr(trim((string) ($args['titre'] ?? '')), 0, 200);
    $visibilite = (string) ($args['visibilite'] ?? 'equipe');
    if (!isset(ESPACE_TYPES[$type]) || $titre === '' || !isset(ESPACE_VISIBILITES[$visibilite])) {
        throw new ApiErreur('Type (' . implode(', ', array_keys(ESPACE_TYPES)) . '), titre et visibilité (' . implode(', ', array_keys(ESPACE_VISIBILITES)) . ') obligatoires');
    }
    $meteo = in_array($args['meteo'] ?? '', ['soleil', 'eclaircie', 'pluie', 'orage'], true) ? $args['meteo'] : 'eclaircie';
    $slug = slug_unique($titre);
    // Météo automatique par défaut, sauf pour un événement (pas d'équipe permanente) ou une météo donnée
    db_query('INSERT INTO espace (type, titre, slug, description, visibilite, meteo, parent_id, meteo_auto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$type, $titre, $slug, mb_substr(trim((string) ($args['description'] ?? '')), 0, 20000), $visibilite, $meteo, $parent['id'] ?? null, $type !== 'evenement' && empty($args['meteo']) ? 1 : 0]);
    $id = (int) db()->lastInsertId();
    if (!is_admin($member)) {
        db_query('INSERT INTO espace_membre (espace_id, membre_id, niveau) VALUES (?, ?, "n1")', [$id, $member['id']]);
    }
    journal($id, $member, 'Espace créé');
    return api_espace($slug);
}

// Ajouter quelqu'un à l'équipe d'un espace (ou changer son niveau) : n°1 / n°2 de l'espace ou administrateur
function api_equipe_ajouter(string $slug, string $email, string $niveau, string $libelle): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_gerer($member, $espace)) {
        throw new ApiErreur('Espace introuvable ou droits insuffisants', 403);
    }
    $personne = db_one('SELECT * FROM membre WHERE email = ? AND actif = 1', [mb_strtolower(trim($email))]);
    if ($personne === null || !isset(ESPACE_NIVEAUX[$niveau])) {
        throw new ApiErreur($personne === null ? 'Aucun membre actif avec cet e-mail' : 'Niveau : ' . implode(', ', array_keys(ESPACE_NIVEAUX)), $personne === null ? 404 : 400);
    }
    db_query('REPLACE INTO espace_membre (espace_id, membre_id, niveau, libelle) VALUES (?, ?, ?, ?)', [$espace['id'], $personne['id'], $niveau, mb_substr(trim($libelle), 0, 100)]);
    journal($espace['id'], $member, 'Équipe : ' . membre_nom($personne) . ' (' . ESPACE_NIVEAUX[$niveau] . ')');
    return api_espace($slug);
}

// Retirer quelqu'un de l'équipe d'un espace (même droit que sur le site : qui gère l'espace) ; son compte reste
function api_equipe_retirer(string $slug, string $email): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_gerer($member, $espace)) {
        throw new ApiErreur('Espace introuvable ou droits insuffisants', 403);
    }
    $personne = db_one('SELECT m.* FROM membre m JOIN espace_membre em ON em.membre_id = m.id WHERE em.espace_id = ? AND m.email = ?', [$espace['id'], mb_strtolower(trim($email))]);
    if ($personne === null) {
        throw new ApiErreur("Personne de l'équipe avec cet e-mail : aucune", 404);
    }
    db_query('DELETE FROM espace_membre WHERE espace_id = ? AND membre_id = ?', [$espace['id'], $personne['id']]);
    journal($espace['id'], $member, membre_nom($personne) . ' retiré de l\'équipe');
    return api_espace($slug);
}

function api_evenement_regler(string $slug, array $args): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || $espace['type'] !== 'evenement' || !espace_peut_gerer($member, $espace)) {
        throw new ApiErreur('Événement introuvable ou droits insuffisants', 403);
    }
    $erreurs = evenement_enregistrer($espace, $member, $args);
    if ($erreurs) {
        throw new ApiErreur(implode(' ', $erreurs) . ' Dates au format AAAA-MM-JJ HH:MM.');
    }
    $ev = evenement_de($espace);
    return ['slug' => $slug, 'debut' => $ev['debut'], 'lieu' => $ev['lieu'], 'mode' => $ev['mode'], 'places' => $ev['places'], 'cloture' => $ev['cloture'], 'reglement' => $ev['reglement']];
}

function api_date_cle_ajouter(string $slug, string $date, string $libelle): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_contribuer($member, $espace)) {
        throw new ApiErreur('Espace introuvable ou droits insuffisants', 403);
    }
    $libelle = mb_substr(trim($libelle), 0, 200);
    if ($libelle === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        throw new ApiErreur('Date (AAAA-MM-JJ) et libellé obligatoires');
    }
    db_query('INSERT INTO jalon (espace_id, date_jalon, libelle) VALUES (?, ?, ?)', [$espace['id'], $date, $libelle]);
    journal($espace['id'], $member, "Date clé ajoutée : $libelle ($date)");
    return api_espace($slug);
}

function api_pages(): array
{
    $voit_tout = has_right(current_member(), 'publications');
    return db_all('SELECT id, slug, titre, parent_id, publiee, reservee_membres, dans_menu, est_accueil FROM page' . ($voit_tout ? '' : ' WHERE publiee = 1') . ' ORDER BY ordre, titre');
}

function api_page(int $id): array
{
    $page = db_one('SELECT * FROM page WHERE id = ?', [$id]);
    if ($page === null || (!$page['publiee'] && !has_right(current_member(), 'publications'))) {
        throw new ApiErreur('Page introuvable', 404);
    }
    return ['id' => (int) $page['id'], 'slug' => $page['slug'], 'titre' => $page['titre'], 'description' => $page['description'], 'parent_id' => $page['parent_id'], 'publiee' => (bool) $page['publiee'], 'reservee_membres' => (bool) $page['reservee_membres'],
        'dans_menu' => (bool) $page['dans_menu'], 'est_accueil' => (bool) $page['est_accueil'], 'ordre' => (int) $page['ordre'], 'blocs' => json_decode($page['blocs'] ?? '{"blocks":[]}', true)];
}

// Écrire une page : contenu en blocs (format de l'éditeur) ou texte simple (un paragraphe par ligne vide)
function api_page_ecrire(?int $id, array $args): array
{
    api_ecriture();
    $member = current_member();
    if (!has_right($member, 'publications')) {
        throw new ApiErreur('Droit « publications » nécessaire', 403);
    }
    $page = $id !== null ? db_one('SELECT * FROM page WHERE id = ?', [$id]) : null;
    if ($id !== null && $page === null) {
        throw new ApiErreur('Page introuvable', 404);
    }
    $titre = mb_substr(trim((string) ($args['titre'] ?? $page['titre'] ?? '')), 0, 200);
    if ($titre === '') {
        throw new ApiErreur('Titre obligatoire');
    }
    if (isset($args['blocs'])) {
        $blocs = blocs_normaliser(is_string($args['blocs']) ? $args['blocs'] : json_encode($args['blocs']));
    } elseif (isset($args['texte'])) {
        $paragraphes = array_filter(preg_split('/\R{2,}/', trim((string) $args['texte'])), fn($p) => trim($p) !== '');
        $blocs = blocs_normaliser(json_encode(['blocks' => array_map(fn($p) => ['type' => 'paragraph', 'data' => ['text' => nl2br(e(trim($p)), false)]], array_values($paragraphes))]));
    } else {
        $blocs = $page['blocs'] ?? json_encode(['blocks' => []]);
    }
    if ($blocs === null) {
        throw new ApiErreur('Contenu en blocs invalide');
    }
    $publiee = isset($args['publiee']) ? (int) (bool) $args['publiee'] : (int) ($page['publiee'] ?? 0);
    $reservee = isset($args['reservee_membres']) ? (int) (bool) $args['reservee_membres'] : (int) ($page['reservee_membres'] ?? 0);
    $parent = array_key_exists('parent_id', $args) ? page_parent_valide($args['parent_id'], $id) : ($page['parent_id'] ?? null);
    $menu = isset($args['dans_menu']) ? (int) (bool) $args['dans_menu'] : (int) ($page['dans_menu'] ?? 0);
    $ordre = isset($args['ordre']) ? max(0, min(9999, (int) $args['ordre'])) : (int) ($page['ordre'] ?? 50);
    $accueil = isset($args['est_accueil']) ? (int) (bool) $args['est_accueil'] : (int) ($page['est_accueil'] ?? 0);
    $description = array_key_exists('description', $args) ? (mb_substr(trim(strip_tags((string) $args['description'])), 0, 300) ?: null) : ($page['description'] ?? null);
    if ($accueil) {
        $reservee = 0;
        db_query('UPDATE page SET est_accueil = 0 WHERE id <> ?', [$id ?? 0]);
    }
    if ($page === null) {
        db_query('INSERT INTO page (slug, titre, description, contenu, blocs, parent_id, publiee, reservee_membres, dans_menu, ordre, est_accueil) VALUES (?, ?, ?, "", ?, ?, ?, ?, ?, ?, ?)',
            [page_slug_unique($titre), $titre, $description, $blocs, $parent, $publiee, $reservee, $menu, $ordre, $accueil]);
        $id = (int) db()->lastInsertId();
        journal(null, $member, "Page créée : $titre");
    } else {
        db_query('UPDATE page SET titre = ?, description = ?, blocs = ?, parent_id = ?, publiee = ?, reservee_membres = ?, dans_menu = ?, ordre = ?, est_accueil = ? WHERE id = ?',
            [$titre, $description, $blocs, $parent, $publiee, $reservee, $menu, $ordre, $accueil, $id]);
        journal(null, $member, "Page modifiée : $titre");
    }
    return api_page($id);
}

function api_evenements(): array
{
    evenements_cloturer_echus();
    $member = current_member();
    $list = [];
    foreach (db_all('SELECT s.* FROM espace s LEFT JOIN evenement e ON e.espace_id = s.id WHERE s.type = "evenement" AND s.archive = 0 AND (e.debut IS NULL OR COALESCE(e.fin, e.debut) >= CURDATE()) ORDER BY e.debut') as $espace) {
        if (!espace_peut_voir($member, $espace)) {
            continue;
        }
        $ev = evenement_de($espace);
        $mine = inscription_de($ev, $member);
        $list[] = ['slug' => $espace['slug'], 'titre' => $espace['titre'], 'debut' => $ev['debut'], 'lieu' => $ev['lieu'], 'mode' => $ev['mode'],
            'places' => $ev['places'], 'cloture' => $ev['cloture'], 'mon_statut' => $mine['statut'] ?? null, 'mon_rang_attente' => ($mine['statut'] ?? null) === 'attente' ? (int) $mine['rang'] : null];
    }
    return $list;
}

// S'inscrire ou se désister : toujours pour soi-même, jamais pour un autre membre
function api_inscription(string $slug, string $action): array
{
    api_ecriture();
    $espace = espace_par_slug($slug);
    if ($espace === null || $espace['type'] !== 'evenement' || !espace_peut_voir(current_member(), $espace)) {
        throw new ApiErreur('Événement introuvable', 404);
    }
    $ev = evenement_de($espace);
    $message = inscription_agir($ev, current_member(), $action);
    if (api_member() !== null) {
        journal($espace['id'], current_member(), "Inscription ($action) : $message");
    }
    return ['message' => $message, 'mon_statut' => inscription_de($ev, current_member())['statut'] ?? null];
}

function api_ma_cotisation(): array
{
    $ma = cotisation_a_jour(current_member());
    $depuis = cotisation_depuis(current_member());
    return ['periode' => cotisation_periode_libelle(cotisation_periode()), 'a_jour' => $ma !== null, 'paye_le' => $ma['paye_le'] ?? null,
        'a_jour_depuis' => $depuis !== null ? cotisation_periode_libelle($depuis) : null];
}

function api_cotisations(?int $periode): array
{
    if (!has_right(current_member(), 'cotisations')) {
        throw new ApiErreur('Droit « cotisations » nécessaire', 403);
    }
    $periode ??= cotisation_periode();
    return ['periode' => cotisation_periode_libelle($periode), 'membres' => array_map(fn($r) => ['email' => $r['email'], 'nom' => membre_nom($r), 'a_jour' => $r['paye_le'] !== null,
        'paye_le' => $r['paye_le'], 'moyen' => $r['moyen']], db_all('SELECT m.*, c.paye_le, c.moyen FROM membre m LEFT JOIN cotisation c ON c.membre_id = m.id AND c.periode = ? WHERE m.actif = 1 ORDER BY m.nom', [$periode]))];
}

function api_cotisation_marquer(string $email, string $date, string $moyen): array
{
    api_ecriture();
    if (!has_right(current_member(), 'cotisations')) {
        throw new ApiErreur('Droit « cotisations » nécessaire', 403);
    }
    $member = db_one('SELECT * FROM membre WHERE email = ?', [mb_strtolower(trim($email))]);
    if ($member === null) {
        throw new ApiErreur('Aucun membre avec cet e-mail', 404);
    }
    $date = $date !== '' ? $date : date('Y-m-d');
    if (!cotisation_marquer($member, cotisation_periode($date), $date, $moyen, current_member())) {
        throw new ApiErreur('Date AAAA-MM-JJ (pas dans le futur) et moyen : ' . implode(', ', array_keys(COTISATION_MOYENS)));
    }
    return ['email' => $member['email'], 'periode' => cotisation_periode_libelle(cotisation_periode($date)), 'paye_le' => $date, 'moyen' => $moyen];
}

// Image pour les pages : envoyée en base64, ré-encodée comme à l'envoi depuis l'éditeur (métadonnées supprimées)
function api_image_ajouter(string $base64): array
{
    api_ecriture();
    if (!has_right(current_member(), 'publications')) {
        throw new ApiErreur('Droit « publications » nécessaire', 403);
    }
    $data = base64_decode(preg_replace('/^data:[^,]*,/', '', $base64), true);
    if ($data === false || $data === '' || strlen($data) > IMAGE_TAILLE_MAX) {
        throw new ApiErreur('Image absente, mal encodée (base64) ou trop lourde (8 Mo maximum)');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'bsl');
    file_put_contents($tmp, $data);
    try {
        [$chemin, $erreur] = image_enregistrer(['tmp_name' => $tmp, 'size' => strlen($data), 'error' => UPLOAD_ERR_OK], current_member(), false);
    } finally {
        @unlink($tmp);
    }
    if ($chemin === null) {
        throw new ApiErreur($erreur);
    }
    return ['chemin' => $chemin, 'url' => absolute_url('/' . $chemin), 'bloc' => ['type' => 'image', 'data' => ['chemin' => $chemin, 'legende' => '', 'alt' => '']]];
}

// Fichier joint à un espace ou un événement (flyer, compte rendu) : mêmes droits et même limite que sur le site
function api_fichier_ajouter(string $slug, string $nom, string $base64): array
{
    api_ecriture();
    $member = current_member();
    $espace = espace_par_slug($slug);
    if ($espace === null || !espace_peut_contribuer($member, $espace)) {
        throw new ApiErreur('Espace introuvable ou droits insuffisants', 403);
    }
    $data = base64_decode(preg_replace('/^data:[^,]*,/', '', $base64), true);
    if ($data === false || $data === '' || strlen($data) > FICHIER_TAILLE_MAX) {
        throw new ApiErreur('Fichier absent, mal encodé (base64) ou trop lourd (20 Mo maximum)');
    }
    if (trim($nom) === '') {
        throw new ApiErreur('Nom du fichier obligatoire');
    }
    $id = fichier_enregistrer($espace, $member, trim($nom), strlen($data), fn(string $chemin) => file_put_contents($chemin, $data) === strlen($data));
    if ($id === null) {
        throw new ApiErreur("Le fichier n'a pas pu être enregistré", 500);
    }
    return ['id' => $id, 'nom' => trim($nom), 'url' => absolute_url('/fichier/' . $id)] + api_espace($slug);
}

// Invitation de bienvenue (clé « administration », droit « membres ») : un e-mail, ou tous ceux qui ne se sont jamais connectés
function api_inviter(string $email): array
{
    $par = api_administration('membres');
    $liste = $email !== ''
        ? db_all('SELECT * FROM membre WHERE email = ? AND actif = 1', [mb_strtolower(trim($email))])
        : db_all('SELECT * FROM membre WHERE actif = 1 AND derniere_connexion IS NULL AND en_attente = 0');
    foreach ($liste as $member) {
        membre_inviter($member, $par);
    }
    return ['invitations' => count($liste)];
}

function api_membres(): array
{
    if (!has_right(current_member(), 'membres')) {
        throw new ApiErreur('Droit « membres » nécessaire', 403);
    }
    return db_all('SELECT m.email, m.prenom, m.nom, m.actif, m.compte_demo, t.libelle AS type FROM membre m LEFT JOIN type_membre t ON t.id = m.type_membre_id ORDER BY m.nom, m.email');
}
