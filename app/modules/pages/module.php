<?php
// Pages du site : arborescence (pages et sous-pages), contenu en blocs, page d'accueil, images publiques
declare(strict_types=1);

return [
    'ordre' => 80,
    'routes' => [
        ['GET', '/page/{slug}', function (array $p) {
            $page = db_one('SELECT * FROM page WHERE slug = ? AND publiee = 1', [$p['slug']]);
            if ($page === null) {
                http_response_code(404);
                render('erreur', ['titre' => 'Page introuvable']);
                return;
            }
            // Page réservée : connexion demandée, puis retour à la page
            if ($page['reservee_membres']) {
                require_member();
            }
            render('pages/page', [
                'titre' => $page['titre'],
                'page' => $page,
                'seo' => [
                    'description' => ($page['description'] ?? '') ?: seo_description_auto($page['blocs']),
                    'image' => seo_image_auto($page['blocs']),
                    'type' => 'article',
                    'index' => !$page['reservee_membres'],
                ],
                'chemin' => page_ancetres($page),
                'enfants' => db_all('SELECT slug, titre, reservee_membres FROM page WHERE parent_id = ? AND publiee = 1 ORDER BY ordre, titre', [$page['id']]),
            ]);
        }],
        ['GET', '/admin/pages', function () {
            require_right('publications');
            render('pages/admin-liste', ['titre' => 'Pages du site', 'arbre' => page_arbre(db_all('SELECT * FROM page ORDER BY ordre, titre'))]);
        }],
        ['POST', '/admin/pages', function () {
            $member = require_right('publications');
            $titre = mb_substr(trim((string) ($_POST['titre'] ?? '')), 0, 200);
            if ($titre === '') {
                flash('erreur', 'Le titre est obligatoire.');
                redirect('/admin/pages');
            }
            $slug = page_slug_unique($titre);
            db_query('INSERT INTO page (slug, titre, contenu, blocs, parent_id) VALUES (?, ?, "", ?, ?)', [$slug, $titre, json_encode(['blocks' => []]), page_parent_valide($_POST['parent_id'] ?? null, null)]);
            $id = (int) db()->lastInsertId();
            journal(null, $member, "Page créée : $titre");
            redirect('/admin/pages/' . $id);
        }],
        ['GET', '/admin/pages/{id}', function (array $p) {
            require_right('publications');
            $page = page_ou_404((int) $p['id']);
            render('pages/admin-edition', [
                'titre' => 'Modifier « ' . $page['titre'] . ' »',
                'page' => $page,
                'parents' => array_filter(db_all('SELECT id, titre FROM page ORDER BY titre'), fn($autre) => (int) $autre['id'] !== (int) $page['id']),
                'blocs' => $page['blocs'] ?? json_encode(['blocks' => $page['contenu'] !== '' ? [['type' => 'paragraph', 'data' => ['text' => e($page['contenu'])]]] : []]),
            ]);
        }],
        ['POST', '/admin/pages/{id}', function (array $p) {
            $member = require_right('publications');
            $page = page_ou_404((int) $p['id']);
            $blocs = blocs_normaliser((string) ($_POST['blocs'] ?? ''));
            $titre = mb_substr(trim((string) ($_POST['titre'] ?? '')), 0, 200);
            if ($blocs === null || $titre === '') {
                flash('erreur', 'Titre obligatoire et contenu valide.');
                redirect('/admin/pages/' . $page['id']);
            }
            $accueil = empty($_POST['est_accueil']) ? 0 : 1;
            if ($accueil) {
                db_query('UPDATE page SET est_accueil = 0');
            }
            db_query('UPDATE page SET titre = ?, description = ?, blocs = ?, parent_id = ?, publiee = ?, dans_menu = ?, ordre = ?, est_accueil = ?, reservee_membres = ? WHERE id = ?', [
                $titre, mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 300) ?: null, $blocs, page_parent_valide($_POST['parent_id'] ?? null, (int) $page['id']),
                empty($_POST['publiee']) ? 0 : 1, empty($_POST['dans_menu']) ? 0 : 1, (int) ($_POST['ordre'] ?? 50), $accueil,
                empty($_POST['reservee_membres']) || $accueil ? 0 : 1, $page['id'],
            ]);
            journal(null, $member, "Page modifiée : $titre");
            flash('succes', 'Page enregistrée.');
            redirect('/admin/pages/' . $page['id']);
        }],
        ['POST', '/admin/pages/{id}/supprimer', function (array $p) {
            $member = require_right('publications');
            $page = page_ou_404((int) $p['id']);
            db_query('UPDATE page SET parent_id = ? WHERE parent_id = ?', [$page['parent_id'], $page['id']]);
            db_query('DELETE FROM page WHERE id = ?', [$page['id']]);
            journal(null, $member, "Page supprimée : {$page['titre']}");
            flash('succes', 'Page supprimée ; ses sous-pages sont remontées d\'un niveau.');
            redirect('/admin/pages');
        }],
        // Envoi d'image depuis l'éditeur : réponse JSON
        // Médiathèque : revoir les images envoyées, voir où elles servent, supprimer celles qui ne servent plus
        ['GET', '/admin/images', function () {
            require_right('publications');
            $images = db_all('SELECT i.*, m.prenom, m.nom, m.email FROM image i LEFT JOIN membre m ON m.id = i.depose_par ORDER BY i.depose_le DESC');
            foreach ($images as &$image) {
                $image['usages'] = image_usages($image['chemin']);
            }
            render('pages/admin-images', ['titre' => 'Images', 'images' => $images]);
        }],
        ['POST', '/admin/images/{id}/supprimer', function (array $p) {
            $member = require_right('publications');
            $image = db_one('SELECT * FROM image WHERE id = ?', [(int) $p['id']]);
            if ($image !== null) {
                if (image_usages($image['chemin'])) {
                    flash('erreur', "Cette image sert encore : retirez-la d'abord des pages ou des réglages indiqués.");
                    redirect('/admin/images');
                }
                @unlink(BOUSSOLE_ROOT . '/' . $image['chemin']);
                db_query('DELETE FROM image WHERE id = ?', [$image['id']]);
                journal(null, $member, 'Image supprimée : ' . $image['chemin']);
                flash('succes', 'Image supprimée.');
            }
            redirect('/admin/images');
        }],
        ['POST', '/admin/images', function () {
            $member = require_right('publications');
            [$chemin, $erreur] = image_enregistrer($_FILES['image'] ?? [], $member);
            header('Content-Type: application/json');
            echo json_encode($chemin !== null ? ['success' => 1, 'chemin' => $chemin, 'url' => url($chemin)] : ['success' => 0, 'erreur' => $erreur]);
        }],
    ],
    // Menu : pages publiées de premier niveau cochées « dans le menu »
    'menu' => fn() => array_map(
        // Page réservée : visible dans le menu avec un cadenas pour un visiteur (le lien mène à la connexion, puis à la page)
        fn($page) => ['libelle' => $page['titre'], 'lien' => '/page/' . $page['slug'], 'ordre' => 60 + (int) $page['ordre'] / 1000, 'verrou' => $page['reservee_membres'] && current_member() === null],
        db_all('SELECT slug, titre, ordre, reservee_membres FROM page WHERE publiee = 1 AND dans_menu = 1 AND parent_id IS NULL AND est_accueil = 0 ORDER BY ordre, titre')
    ),
];

function page_ou_404(int $id): array
{
    $page = db_one('SELECT * FROM page WHERE id = ?', [$id]);
    if ($page === null) {
        http_response_code(404);
        render('erreur', ['titre' => 'Page introuvable']);
        exit;
    }
    return $page;
}

function page_slug_unique(string $titre): string
{
    $base = slugify($titre);
    $slug = $base;
    for ($i = 2; db_one('SELECT id FROM page WHERE slug = ?', [$slug]) !== null; $i++) {
        $slug = "$base-$i";
    }
    return $slug;
}

// Parent valide : existe, et n'est ni la page elle-même ni l'une de ses descendantes (pas de boucle)
function page_parent_valide(mixed $parentId, ?int $pageId): ?int
{
    $parentId = (int) $parentId;
    if ($parentId <= 0 || db_one('SELECT id FROM page WHERE id = ?', [$parentId]) === null) {
        return null;
    }
    for ($id = $parentId, $tours = 0; $id !== null && $tours < 50; $tours++) {
        if ($pageId !== null && $id === $pageId) {
            return null;
        }
        $row = db_one('SELECT parent_id FROM page WHERE id = ?', [$id]);
        $id = $row && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    }
    return $parentId;
}

function page_ancetres(array $page): array
{
    $chemin = [];
    for ($id = $page['parent_id'], $tours = 0; $id !== null && $tours < 50; $tours++) {
        $parent = db_one('SELECT id, slug, titre, parent_id, publiee FROM page WHERE id = ?', [$id]);
        if ($parent === null) {
            break;
        }
        array_unshift($chemin, $parent);
        $id = $parent['parent_id'];
    }
    return $chemin;
}

// Pages rangées en arbre, pour la liste de l'administration
function page_arbre(array $pages, ?int $parent = null, int $niveau = 0): array
{
    $out = [];
    foreach ($pages as $page) {
        if (($page['parent_id'] === null ? null : (int) $page['parent_id']) === $parent && $niveau < 10) {
            $out[] = $page + ['niveau' => $niveau];
            $out = array_merge($out, page_arbre($pages, (int) $page['id'], $niveau + 1));
        }
    }
    return $out;
}

// Où une image sert-elle ? Pages (blocs, où « / » est échappé en JSON : on cherche le nom du fichier) et réglages
function image_usages(string $chemin): array
{
    $usages = array_map(fn($p) => 'page « ' . $p['titre'] . ' »', db_all('SELECT titre FROM page WHERE blocs LIKE ? OR contenu LIKE ?', ['%' . basename($chemin) . '%', '%' . basename($chemin) . '%']));
    foreach (db_all('SELECT cle FROM parametre WHERE valeur = ?', [$chemin]) as $r) {
        $usages[] = ['bandeau_image' => 'image du bandeau (réglages)', 'logo' => 'logo du site (réglages)'][$r['cle']] ?? 'réglage « ' . $r['cle'] . ' »';
    }
    return $usages;
}
