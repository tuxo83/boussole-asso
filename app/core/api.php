<?php
// API et MCP : clés personnelles, et les actions communes (mêmes droits que sur le site)
declare(strict_types=1);

const CLE_DUREES = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours', 365 => '1 an'];

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
    db_query('INSERT INTO cle_api (membre_id, libelle, prefixe, empreinte, portee, expire_le) VALUES (?, ?, ?, ?, ?, CURDATE() + INTERVAL ? DAY)',
        [$member['id'], $libelle, $prefixe, hash('sha256', $cle), $portee === 'ecriture' ? 'ecriture' : 'lecture', $jours]);
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
    $cle = db_one('SELECT * FROM cle_api WHERE prefixe = ? AND revoquee = 0 AND expire_le >= CURDATE()', [$m[2]]);
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
    if (api_portee() !== 'ecriture') {
        throw new ApiErreur('Cette clé est en lecture seule', 403);
    }
}

// ---------- Actions (appelées par l'API JSON et par le MCP)

function api_moi(): array
{
    $m = current_member();
    return ['email' => $m['email'], 'prenom' => $m['prenom'], 'nom' => $m['nom'], 'droits' => member_rights($m['id']), 'portee_cle' => api_portee()];
}

function api_espace_resume(array $e): array
{
    return ['slug' => $e['slug'], 'type' => $e['type'], 'titre' => $e['titre'], 'meteo' => $e['meteo'], 'visibilite' => $e['visibilite'], 'alertes' => espace_alertes($e)];
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
        db_query('UPDATE espace SET meteo = ? WHERE id = ?', [$args['meteo'], $espace['id']]);
        journal($espace['id'], $member, 'Météo : ' . $args['meteo']);
    }
    if (isset($args['description'])) {
        if (!espace_peut_gerer($member, $espace)) {
            throw new ApiErreur('Seuls le n°1 et le n°2 modifient la description', 403);
        }
        db_query('UPDATE espace SET description = ? WHERE id = ?', [mb_substr((string) $args['description'], 0, 20000), $espace['id']]);
        journal($espace['id'], $member, 'Description modifiée');
    }
    return api_espace($slug);
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
    return db_all('SELECT id, slug, titre, parent_id, publiee, dans_menu, est_accueil FROM page' . ($voit_tout ? '' : ' WHERE publiee = 1') . ' ORDER BY ordre, titre');
}

function api_page(int $id): array
{
    $page = db_one('SELECT * FROM page WHERE id = ?', [$id]);
    if ($page === null || (!$page['publiee'] && !has_right(current_member(), 'publications'))) {
        throw new ApiErreur('Page introuvable', 404);
    }
    return ['id' => (int) $page['id'], 'slug' => $page['slug'], 'titre' => $page['titre'], 'parent_id' => $page['parent_id'], 'publiee' => (bool) $page['publiee'],
        'dans_menu' => (bool) $page['dans_menu'], 'est_accueil' => (bool) $page['est_accueil'], 'blocs' => json_decode($page['blocs'] ?? '{"blocks":[]}', true)];
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
    $parent = array_key_exists('parent_id', $args) ? page_parent_valide($args['parent_id'], $id) : ($page['parent_id'] ?? null);
    if ($page === null) {
        db_query('INSERT INTO page (slug, titre, contenu, blocs, parent_id, publiee) VALUES (?, ?, "", ?, ?, ?)', [page_slug_unique($titre), $titre, $blocs, $parent, $publiee]);
        $id = (int) db()->lastInsertId();
        journal(null, $member, "Page créée : $titre");
    } else {
        db_query('UPDATE page SET titre = ?, blocs = ?, parent_id = ?, publiee = ? WHERE id = ?', [$titre, $blocs, $parent, $publiee, $id]);
        journal(null, $member, "Page modifiée : $titre");
    }
    return api_page($id);
}

function api_membres(): array
{
    if (!has_right(current_member(), 'membres')) {
        throw new ApiErreur('Droit « membres » nécessaire', 403);
    }
    return db_all('SELECT m.email, m.prenom, m.nom, m.actif, t.libelle AS type FROM membre m LEFT JOIN type_membre t ON t.id = m.type_membre_id ORDER BY m.nom, m.email');
}
