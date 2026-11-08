<?php
// Espaces : liste, création, fiche (équipe, météo, dates clés, fichiers, journal)
declare(strict_types=1);

const FICHIER_TAILLE_MAX = 20 * 1024 * 1024;

return [
    'ordre' => 30,
    'routes' => [
        ['GET', '/espaces', function () {
            $member = current_member();
            $archives = !empty($_GET['archives']);
            $espaces = array_values(array_filter(
                db_all('SELECT * FROM espace WHERE archive = ? ORDER BY FIELD(type, "comite", "commission", "projet", "evenement"), titre', [$archives ? 1 : 0]),
                fn($espace) => espace_peut_voir($member, $espace)
            ));
            $nb_archives = count(array_filter(db_all('SELECT * FROM espace WHERE archive = 1'), fn($e) => espace_peut_voir($member, $e)));
            render('espaces/liste', ['titre' => $archives ? 'Espaces archivés' : 'Espaces', 'espaces' => $espaces, 'membre' => $member, 'archives' => $archives, 'nb_archives' => $nb_archives]);
        }],
        ['GET', '/espaces/nouveau', function () {
            $member = require_member();
            $parent = espace_parent_autorise($member, (string) ($_GET['parent'] ?? ''));
            $type = (string) ($_GET['type'] ?? '');
            render('espaces/formulaire', ['titre' => $type === 'evenement' ? 'Nouvel événement' : 'Créer', 'parent' => $parent,
                'v' => isset(ESPACE_TYPES[$type]) ? ['type' => $type] : [], 'erreurs' => []]);
        }],
        ['POST', '/espaces/nouveau', function () {
            $member = require_member();
            $parent = espace_parent_autorise($member, (string) ($_POST['parent'] ?? ''));
            [$v, $erreurs] = espace_valider($_POST);
            // Événement : date et lieu dès la création (le reste se règle ensuite sur sa page)
            $debut = null;
            if ($v['type'] === 'evenement' && trim((string) ($_POST['debut'] ?? '')) !== '') {
                $t = strtotime((string) $_POST['debut']);
                $t === false ? $erreurs['debut'] = 'Date invalide.' : $debut = date('Y-m-d H:i:s', $t);
            }
            if ($erreurs) {
                http_response_code(422);
                render('espaces/formulaire', ['titre' => 'Nouvel espace', 'parent' => $parent, 'v' => $v, 'erreurs' => $erreurs]);
                return;
            }
            $slug = slug_unique($v['titre']);
            db_query('INSERT INTO espace (type, titre, slug, description, visibilite, meteo, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$v['type'], $v['titre'], $slug, $v['description'], $v['visibilite'], $v['meteo'], $parent['id'] ?? null]);
            $id = (int) db()->lastInsertId();
            if ($v['type'] === 'evenement') {
                db_query('INSERT INTO evenement (espace_id, debut, lieu) VALUES (?, ?, ?)', [$id, $debut, mb_substr(trim((string) ($_POST['lieu'] ?? '')), 0, 200)]);
            }
            // La personne qui crée l'espace en est le n°1, sauf l'administrateur qui le crée pour d'autres
            if (!is_admin($member)) {
                db_query('INSERT INTO espace_membre (espace_id, membre_id, niveau) VALUES (?, ?, "n1")', [$id, $member['id']]);
            }
            journal($id, $member, 'Espace créé');
            redirect('/espace/' . $slug);
        }],
        ['GET', '/espace/{slug}', function (array $p) {
            $espace = espace_ou_404($p['slug']);
            $espace = espace_meteo_actualiser($espace);
            $member = current_member();
            if (!espace_peut_voir($member, $espace)) {
                $member === null ? require_member() : espace_refus();
            }
            $ev_seo = $espace['type'] === 'evenement' ? db_one('SELECT * FROM evenement WHERE espace_id = ?', [$espace['id']]) : null;
            render('espaces/fiche', [
                'titre' => $espace['titre'],
                'espace' => $espace,
                // Seuls les événements publics sont proposés aux moteurs ; le reste des espaces est interne
                'seo' => $espace['visibilite'] === 'public' && $espace['type'] === 'evenement' && !$espace['archive']
                    ? ['description' => seo_couper($espace['description']), 'type' => 'article', 'jsonld' => $ev_seo ? seo_jsonld_evenement($espace, $ev_seo) : null]
                    : ['index' => false],
                'membre' => $member,
                'parent' => $espace['parent_id'] ? db_one('SELECT * FROM espace WHERE id = ?', [$espace['parent_id']]) : null,
                'enfants' => array_filter(db_all('SELECT * FROM espace WHERE parent_id = ? AND archive = 0 AND type <> "evenement" ORDER BY titre', [$espace['id']]), fn($e) => espace_peut_voir($member, $e)),
                // Événements rattachés à cet espace : à venir d'abord, puis les passés récents
                'evenements_espace' => array_values(array_filter(db_all('SELECT s.*, e.debut, e.lieu FROM espace s LEFT JOIN evenement e ON e.espace_id = s.id
                    WHERE s.parent_id = ? AND s.type = "evenement" AND s.archive = 0 AND (e.debut IS NULL OR e.debut >= CURDATE() - INTERVAL 6 MONTH)
                    ORDER BY e.debut IS NULL, e.debut < CURDATE(), CASE WHEN e.debut >= CURDATE() THEN e.debut END, e.debut DESC', [$espace['id']]), fn($e) => espace_peut_voir($member, $e))),
                'equipe' => db_all('SELECT m.*, em.niveau, em.libelle FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? ORDER BY FIELD(em.niveau, "n1", "n2", "membre", "associe"), m.nom, m.email', [$espace['id']]),
                'jalons' => db_all('SELECT * FROM jalon WHERE espace_id = ? ORDER BY fait, date_jalon', [$espace['id']]),
                'fichiers' => db_all('SELECT f.*, m.prenom, m.nom AS nom_membre, m.email FROM fichier f LEFT JOIN membre m ON m.id = f.depose_par WHERE f.espace_id = ? ORDER BY f.depose_le DESC', [$espace['id']]),
                'journal' => espace_niveau($member, $espace) !== null || is_admin($member)
                    ? db_all('SELECT j.*, m.prenom, m.nom, m.email FROM journal j LEFT JOIN membre m ON m.id = j.membre_id WHERE j.espace_id = ? ORDER BY j.le DESC LIMIT 30', [$espace['id']])
                    : [],
                'alertes' => espace_alertes($espace),
                // Vue membre par défaut ; la vue organisateur (choix retenu par le navigateur, ou jour J d'un événement) ajoute la gestion
                'organise' => $organise = espace_peut_contribuer($member, $espace),
                'vue_orga' => $vue_orga = $organise && espace_vue_organisateur($espace),
                'peut_gerer' => $vue_orga && espace_peut_gerer($member, $espace),
                'gere_vraiment' => espace_peut_gerer($member, $espace),
                'membres_actifs' => espace_peut_gerer($member, $espace) ? db_all('SELECT email, prenom, nom FROM membre WHERE actif = 1 AND en_attente = 0 ORDER BY nom, prenom, email') : [],
                'parents_possibles' => is_admin($member)
                    ? array_column(array_filter(db_all('SELECT slug, titre, id FROM espace WHERE archive = 0 ORDER BY titre'), fn($e) => !in_array((int) $e['id'], espace_descendants((int) $espace['id']), true)), 'titre', 'slug')
                    : [],
                'peut_contribuer' => $vue_orga,
            ]);
        }],
        // Page « Gérer » : réglages, organisation, journal, archiver ou supprimer
        ['GET', '/espace/{slug}/gerer', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            render('espaces/gerer', [
                'titre' => 'Gérer · ' . $espace['titre'],
                'espace' => $espace,
                'membre' => $member,
                'parent' => $espace['parent_id'] ? db_one('SELECT * FROM espace WHERE id = ?', [$espace['parent_id']]) : null,
                'journal' => db_all('SELECT j.*, m.prenom, m.nom, m.email FROM journal j LEFT JOIN membre m ON m.id = j.membre_id WHERE j.espace_id = ? ORDER BY j.le DESC LIMIT 100', [$espace['id']]),
                'parents_possibles' => is_admin($member)
                    ? array_column(array_filter(db_all('SELECT slug, titre, id FROM espace WHERE archive = 0 AND type <> "evenement" ORDER BY titre'), fn($e) => !in_array((int) $e['id'], espace_descendants((int) $espace['id']), true)), 'titre', 'slug')
                    : [],
            ]);
        }],
        ['POST', '/espace/{slug}/modifier', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            [$v, $erreurs] = espace_valider($_POST + ['type' => $espace['type']]);
            // Déplacer : seulement l'administrateur, jamais sous lui-même ni sous un de ses sous-espaces
            $parent = $espace['parent_id'];
            if (is_admin($member) && array_key_exists('parent', $_POST)) {
                $cible = $_POST['parent'] === '' ? null : espace_par_slug((string) $_POST['parent']);
                if ($_POST['parent'] !== '' && ($cible === null || in_array((int) $cible['id'], espace_descendants((int) $espace['id']), true))) {
                    $erreurs['parent'] = 'Espace parent invalide (pas lui-même ni un de ses sous-espaces).';
                } else {
                    $parent = $cible['id'] ?? null;
                }
            }
            if ($erreurs) {
                flash('erreur', implode(' ', $erreurs));
                redirect('/espace/' . $espace['slug'] . (($_POST['retour'] ?? '') === 'gerer' ? '/gerer#organisation' : ''));
            }
            db_query('UPDATE espace SET titre = ?, description = ?, visibilite = ?, type = ?, parent_id = ? WHERE id = ?', [$v['titre'], $v['description'], $v['visibilite'], $v['type'], $parent, $espace['id']]);
            journal($espace['id'], $member, 'Espace modifié');
            redirect('/espace/' . $espace['slug'] . (($_POST['retour'] ?? '') === 'gerer' ? '/gerer#organisation' : ''));
        }],
        // Archiver : l'espace sort des listes et de l'agenda, sans rien perdre ; désarchiver le fait revenir
        ['POST', '/espace/{slug}/archiver', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            $archive = $espace['archive'] ? 0 : 1;
            db_query('UPDATE espace SET archive = ? WHERE id = ?', [$archive, $espace['id']]);
            journal($espace['id'], $member, $archive ? 'Espace archivé' : 'Espace désarchivé');
            flash('succes', $archive ? 'Espace archivé : il reste consultable dans « Espaces archivés ».' : 'Espace désarchivé.');
            redirect('/espace/' . $espace['slug']);
        }],
        // Supprimer définitivement (administrateur) : fichiers effacés du disque, sous-espaces remontés d'un niveau
        ['POST', '/espace/{slug}/supprimer', function (array $p) {
            $member = require_right('admin');
            $espace = espace_ou_404($p['slug']);
            // On retape le nom de l'espace : impossible de supprimer par un clic malheureux
            if (mb_strtolower(trim((string) ($_POST['confirmation'] ?? ''))) !== mb_strtolower(trim($espace['titre']))) {
                flash('erreur', "Nom de l'espace mal recopié : rien n'a été supprimé.");
                redirect('/espace/' . $espace['slug']);
            }
            foreach (db_all('SELECT stockage FROM fichier WHERE espace_id = ?', [$espace['id']]) as $f) {
                @unlink(BOUSSOLE_ROOT . '/stockage/' . $f['stockage']);
            }
            db_query('UPDATE espace SET parent_id = ? WHERE parent_id = ?', [$espace['parent_id'], $espace['id']]);
            db_query('DELETE FROM espace WHERE id = ?', [$espace['id']]);
            journal(null, $member, 'Espace supprimé : ' . $espace['titre']);
            flash('succes', 'Espace « ' . $espace['titre'] . ' » supprimé.');
            redirect('/espaces');
        }],
        ['POST', '/espace/{slug}/meteo', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $meteo = (string) ($_POST['meteo'] ?? '');
            if (in_array($meteo, ['soleil', 'eclaircie', 'pluie', 'orage'], true)) {
                // Un choix à la main l'emporte : la météo automatique se coupe
                db_query('UPDATE espace SET meteo = ?, meteo_auto = 0, meteo_raison = "" WHERE id = ?', [$meteo, $espace['id']]);
                journal($espace['id'], $member, 'Météo : ' . strip_tags(c_meteo($meteo)));
            }
            redirect('/espace/' . $espace['slug']);
        }],
        // Météo automatique : on coche ou décoche (qui contribue)
        ['POST', '/espace/{slug}/meteo-auto', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $auto = empty($_POST['meteo_auto']) ? 0 : 1;
            db_query('UPDATE espace SET meteo_auto = ? WHERE id = ?', [$auto, $espace['id']]);
            journal($espace['id'], $member, $auto ? 'Météo automatique activée' : 'Météo automatique coupée');
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/equipe', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $niveau = (string) ($_POST['niveau'] ?? '');
            $cible = db_one('SELECT * FROM membre WHERE email = ? AND actif = 1', [$email]);
            if ($cible === null || !isset(ESPACE_NIVEAUX[$niveau])) {
                flash('erreur', 'Membre inconnu ou niveau invalide : le membre doit d\'abord exister dans la liste des membres.');
                redirect('/espace/' . $espace['slug']);
            }
            // Un seul n°1 et un seul n°2 : l'ancien titulaire redevient membre
            if (in_array($niveau, ['n1', 'n2'], true)) {
                db_query('UPDATE espace_membre SET niveau = "membre" WHERE espace_id = ? AND niveau = ?', [$espace['id'], $niveau]);
            }
            db_query('INSERT INTO espace_membre (espace_id, membre_id, niveau, libelle) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE niveau = VALUES(niveau), libelle = VALUES(libelle)',
                [$espace['id'], $cible['id'], $niveau, mb_substr(espace_libelle_compose($_POST), 0, 100)]);
            journal($espace['id'], $member, membre_nom($cible) . ' : ' . ESPACE_NIVEAUX[$niveau]);
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/equipe/retirer', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            $cible = db_one('SELECT * FROM membre WHERE id = ?', [(int) ($_POST['membre_id'] ?? 0)]);
            if ($cible !== null) {
                db_query('DELETE FROM espace_membre WHERE espace_id = ? AND membre_id = ?', [$espace['id'], $cible['id']]);
                journal($espace['id'], $member, membre_nom($cible) . ' retiré de l\'équipe');
            }
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/jalons', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $date = (string) ($_POST['date_jalon'] ?? '');
            $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 200);
            if ($libelle === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                flash('erreur', 'Date et libellé obligatoires.');
                redirect('/espace/' . $espace['slug']);
            }
            db_query('INSERT INTO jalon (espace_id, date_jalon, libelle, destinataires) VALUES (?, ?, ?, ?)', [$espace['id'], $date, $libelle, ($_POST['destinataires'] ?? '') === 'equipe' ? 'equipe' : 'binome']);
            journal($espace['id'], $member, "Date clé ajoutée : $libelle ($date)");
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/jalons/{id}/supprimer', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $jalon = db_one('SELECT * FROM jalon WHERE id = ? AND espace_id = ?', [(int) $p['id'], $espace['id']]);
            if ($jalon !== null) {
                db_query('DELETE FROM jalon WHERE id = ?', [$jalon['id']]);
                journal($espace['id'], $member, 'Date clé supprimée : ' . $jalon['libelle']);
            }
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/jalons/{id}/fait', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $jalon = db_one('SELECT * FROM jalon WHERE id = ? AND espace_id = ?', [(int) $p['id'], $espace['id']]);
            if ($jalon !== null) {
                db_query('UPDATE jalon SET fait = 1 - fait WHERE id = ?', [$jalon['id']]);
                journal($espace['id'], $member, ($jalon['fait'] ? 'Date clé rouverte : ' : 'Date clé faite : ') . $jalon['libelle']);
            }
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/fichiers', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $file = $_FILES['fichier'] ?? null;
            if ($file === null || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > FICHIER_TAILLE_MAX || !is_uploaded_file($file['tmp_name'])) {
                flash('erreur', 'Fichier absent ou trop lourd (20 Mo maximum).');
                redirect('/espace/' . $espace['slug']);
            }
            // Nom de stockage aléatoire, hors du dossier public ; le nom d'origine n'est qu'une étiquette
            $stockage = bin2hex(random_bytes(16));
            if (!move_uploaded_file($file['tmp_name'], BOUSSOLE_ROOT . '/stockage/' . $stockage)) {
                flash('erreur', "Le fichier n'a pas pu être enregistré.");
                redirect('/espace/' . $espace['slug']);
            }
            $nom = mb_substr(preg_replace('/[\x00-\x1f\/\\\\]/', '_', (string) $file['name']), 0, 255) ?: 'fichier';
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file(BOUSSOLE_ROOT . '/stockage/' . $stockage) ?: 'application/octet-stream';
            db_query('INSERT INTO fichier (espace_id, nom, stockage, taille, type_mime, depose_par) VALUES (?, ?, ?, ?, ?, ?)',
                [$espace['id'], $nom, $stockage, (int) $file['size'], $mime, $member['id']]);
            journal($espace['id'], $member, "Fichier déposé : $nom");
            redirect('/espace/' . $espace['slug']);
        }],
        // Téléchargement : uniquement pour qui peut voir l'espace, toujours en pièce jointe
        ['GET', '/fichier/{id}', function (array $p) {
            $file = db_one('SELECT f.*, e.slug FROM fichier f JOIN espace e ON e.id = f.espace_id WHERE f.id = ?', [(int) $p['id']]);
            $espace = $file ? espace_par_slug($file['slug']) : null;
            if ($file === null || !espace_peut_voir(current_member(), $espace)) {
                http_response_code(404);
                render('erreur', ['titre' => 'Fichier introuvable']);
                return;
            }
            $path = BOUSSOLE_ROOT . '/stockage/' . $file['stockage'];
            header('Content-Type: application/octet-stream');
            header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file['nom']));
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: private, no-store');
            readfile($path);
        }],
        ['POST', '/fichier/{id}/supprimer', function (array $p) {
            $file = db_one('SELECT f.*, e.slug FROM fichier f JOIN espace e ON e.id = f.espace_id WHERE f.id = ?', [(int) $p['id']]);
            if ($file === null) {
                redirect('/espaces');
            }
            [$espace, $member] = espace_action($file['slug'], 'gerer');
            @unlink(BOUSSOLE_ROOT . '/stockage/' . $file['stockage']);
            db_query('DELETE FROM fichier WHERE id = ?', [$file['id']]);
            journal($espace['id'], $member, "Fichier supprimé : {$file['nom']}");
            redirect('/espace/' . $espace['slug']);
        }],
    ],
    'menu' => fn() => current_member() !== null ? [['libelle' => 'Espaces', 'lien' => '/espaces', 'ordre' => 90, 'zone' => 'membre']] : [],
];

function espace_ou_404(string $slug): array
{
    $espace = espace_par_slug($slug);
    if ($espace === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Espace introuvable']);
        exit;
    }
    return $espace;
}

function espace_refus(): never
{
    http_response_code(403);
    render('erreur', ['titre' => 'Cet espace est réservé à son équipe']);
    exit;
}

// Action sur un espace : membre connecté avec le droit demandé ('gerer' ou 'contribuer')
function espace_action(string $slug, string $droit): array
{
    $member = require_member();
    $espace = espace_ou_404($slug);
    $ok = $droit === 'gerer' ? espace_peut_gerer($member, $espace) : espace_peut_contribuer($member, $espace);
    if (!$ok) {
        espace_refus();
    }
    return [$espace, $member];
}

// Créer un espace : l'administrateur partout ; le n°1 ou le n°2 d'un espace, en dessous de celui-ci
function espace_parent_autorise(array $member, string $slug): ?array
{
    $parent = $slug !== '' ? espace_par_slug($slug) : null;
    if (is_admin($member) || ($parent !== null && espace_peut_gerer($member, $parent))) {
        return $parent;
    }
    espace_refus();
}

// L'espace et tous ses sous-espaces (pour interdire un déplacement en boucle)
function espace_descendants(int $id): array
{
    $ids = [$id];
    for ($i = 0; $i < count($ids); $i++) {
        foreach (db_all('SELECT id FROM espace WHERE parent_id = ?', [$ids[$i]]) as $enfant) {
            $ids[] = (int) $enfant['id'];
        }
    }
    return $ids;
}

function espace_valider(array $input): array
{
    $v = [
        'type' => (string) ($input['type'] ?? ''),
        'titre' => mb_substr(trim((string) ($input['titre'] ?? '')), 0, 200),
        'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 20000),
        'visibilite' => (string) ($input['visibilite'] ?? 'equipe'),
        'meteo' => 'eclaircie',
    ];
    $erreurs = [];
    if (!isset(ESPACE_TYPES[$v['type']])) {
        $erreurs['type'] = 'Type invalide.';
    }
    if ($v['titre'] === '') {
        $erreurs['titre'] = 'Le titre est obligatoire.';
    }
    if (!isset(ESPACE_VISIBILITES[$v['visibilite']])) {
        $erreurs['visibilite'] = 'Visibilité invalide.';
    }
    return [$v, $erreurs];
}

// Vue organisateur : ?vue=orga / ?vue=membre la change et la retient (cookie, ce navigateur) ; un événement du jour l'ouvre d'office
function espace_vue_organisateur(array $espace): bool
{
    if (isset($_GET['vue'])) {
        $orga = $_GET['vue'] === 'orga';
        setcookie('vue_orga', $orga ? '1' : '0', ['expires' => time() + 365 * 86400, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => !empty($_SERVER['HTTPS'])]);
        return $orga;
    }
    if ($espace['type'] === 'evenement') {
        $debut = db_value('SELECT debut FROM evenement WHERE espace_id = ?', [$espace['id']]);
        if ($debut && substr((string) $debut, 0, 10) === date('Y-m-d') && ($_COOKIE['vue_orga'] ?? '') !== '0') {
            return true;
        }
    }
    return ($_COOKIE['vue_orga'] ?? '') === '1';
}
