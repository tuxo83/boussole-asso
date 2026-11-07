<?php
// Pages libres (statuts, contact…) : une page par ligne de la table page, même gabarit pour toutes
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
            render('pages/page', ['titre' => $page['titre'], 'page' => $page]);
        }],
    ],
    'menu' => fn() => array_map(
        fn($page) => ['libelle' => $page['titre'], 'lien' => '/page/' . $page['slug'], 'ordre' => 60 + (int) $page['ordre'] / 1000],
        db_all('SELECT slug, titre, ordre FROM page WHERE publiee = 1 AND dans_menu = 1 ORDER BY ordre, titre')
    ),
];
