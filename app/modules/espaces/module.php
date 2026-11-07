<?php
// Espaces : liste, création, fiche (équipe, météo, dates clés, fichiers, journal)
declare(strict_types=1);

const FICHIER_TAILLE_MAX = 20 * 1024 * 1024;

return [
    'ordre' => 30,
    'routes' => [
        ['GET', '/espaces', function () {
            $member = current_member();
            $espaces = array_values(array_filter(
                db_all('SELECT * FROM espace WHERE archive = 0 ORDER BY FIELD(type, "comite", "commission", "projet", "evenement"), titre'),
                fn($espace) => espace_peut_voir($member, $espace)
            ));
            render('espaces/liste', ['titre' => 'Espaces', 'espaces' => $espaces, 'membre' => $member]);
        }],
        ['GET', '/espaces/nouveau', function () {
            $member = require_member();
            $parent = espace_parent_autorise($member, (string) ($_GET['parent'] ?? ''));
            render('espaces/formulaire', ['titre' => 'Nouvel espace', 'parent' => $parent, 'v' => [], 'erreurs' => []]);
        }],
        ['POST', '/espaces/nouveau', function () {
            $member = require_member();
            $parent = espace_parent_autorise($member, (string) ($_POST['parent'] ?? ''));
            [$v, $erreurs] = espace_valider($_POST);
            if ($erreurs) {
                http_response_code(422);
                render('espaces/formulaire', ['titre' => 'Nouvel espace', 'parent' => $parent, 'v' => $v, 'erreurs' => $erreurs]);
                return;
            }
            $slug = slug_unique($v['titre']);
            db_query('INSERT INTO espace (type, titre, slug, description, visibilite, meteo, parent_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$v['type'], $v['titre'], $slug, $v['description'], $v['visibilite'], $v['meteo'], $parent['id'] ?? null]);
            $id = (int) db()->lastInsertId();
            // La personne qui crée l'espace en est le n°1, sauf l'administrateur qui le crée pour d'autres
            if (!is_admin($member)) {
                db_query('INSERT INTO espace_membre (espace_id, membre_id, niveau) VALUES (?, ?, "n1")', [$id, $member['id']]);
            }
            journal($id, $member, 'Espace créé');
            redirect('/espace/' . $slug);
        }],
        ['GET', '/espace/{slug}', function (array $p) {
            $espace = espace_ou_404($p['slug']);
            $member = current_member();
            if (!espace_peut_voir($member, $espace)) {
                $member === null ? require_member() : espace_refus();
            }
            render('espaces/fiche', [
                'titre' => $espace['titre'],
                'espace' => $espace,
                'membre' => $member,
                'parent' => $espace['parent_id'] ? db_one('SELECT * FROM espace WHERE id = ?', [$espace['parent_id']]) : null,
                'enfants' => array_filter(db_all('SELECT * FROM espace WHERE parent_id = ? AND archive = 0 ORDER BY titre', [$espace['id']]), fn($e) => espace_peut_voir($member, $e)),
                'equipe' => db_all('SELECT m.*, em.niveau, em.libelle FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? ORDER BY FIELD(em.niveau, "n1", "n2", "membre", "associe"), m.nom, m.email', [$espace['id']]),
                'jalons' => db_all('SELECT * FROM jalon WHERE espace_id = ? ORDER BY fait, date_jalon', [$espace['id']]),
                'fichiers' => db_all('SELECT f.*, m.prenom, m.nom AS nom_membre, m.email FROM fichier f LEFT JOIN membre m ON m.id = f.depose_par WHERE f.espace_id = ? ORDER BY f.depose_le DESC', [$espace['id']]),
                'journal' => espace_niveau($member, $espace) !== null || is_admin($member)
                    ? db_all('SELECT j.*, m.prenom, m.nom, m.email FROM journal j LEFT JOIN membre m ON m.id = j.membre_id WHERE j.espace_id = ? ORDER BY j.le DESC LIMIT 30', [$espace['id']])
                    : [],
                'alertes' => espace_alertes($espace),
                'peut_gerer' => espace_peut_gerer($member, $espace),
                'peut_contribuer' => espace_peut_contribuer($member, $espace),
            ]);
        }],
        ['POST', '/espace/{slug}/modifier', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'gerer');
            [$v, $erreurs] = espace_valider($_POST + ['type' => $espace['type']]);
            if ($erreurs) {
                flash('erreur', implode(' ', $erreurs));
                redirect('/espace/' . $espace['slug']);
            }
            db_query('UPDATE espace SET titre = ?, description = ?, visibilite = ? WHERE id = ?', [$v['titre'], $v['description'], $v['visibilite'], $espace['id']]);
            journal($espace['id'], $member, 'Description modifiée');
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/meteo', function (array $p) {
            [$espace, $member] = espace_action($p['slug'], 'contribuer');
            $meteo = (string) ($_POST['meteo'] ?? '');
            if (in_array($meteo, ['soleil', 'eclaircie', 'pluie', 'orage'], true)) {
                db_query('UPDATE espace SET meteo = ? WHERE id = ?', [$meteo, $espace['id']]);
                journal($espace['id'], $member, 'Météo : ' . strip_tags(c_meteo($meteo)));
            }
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
                [$espace['id'], $cible['id'], $niveau, mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 100)]);
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
