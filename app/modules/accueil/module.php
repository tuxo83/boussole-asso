<?php
// Accueil public et feuille de style personnalisée
declare(strict_types=1);

return [
    'ordre' => 10,
    'routes' => [
        ['GET', '/', function () {
            $page = db_one('SELECT * FROM page WHERE est_accueil = 1 AND publiee = 1 LIMIT 1');
            render('accueil/accueil', [
                'presentation' => (string) setting('presentation', ''),
                'page' => $page,
                'seo' => ['description' => ($page['description'] ?? '') ?: seo_description_auto($page['blocs'] ?? null), 'jsonld' => seo_jsonld_association()],
            ]);
        }],
        // Référencement : consignes aux robots et plan du site (pages publiques seulement)
        ['GET', '/robots.txt', function () {
            header('Content-Type: text/plain; charset=utf-8');
            echo seo_robots();
        }],
        ['GET', '/sitemap.xml', function () {
            if (construction_active()) {
                http_response_code(404);
                return;
            }
            header('Content-Type: application/xml; charset=utf-8');
            echo seo_sitemap();
        }],
        // CSS personnalisé de l'installation, servi comme une feuille de style (jamais dans le HTML)
        // Site en construction : saisie du code d'accès
        ['GET', '/acces', function () {
            if (!construction_active()) {
                redirect('/');
            }
            render('accueil/acces', ['titre' => 'Code d\'accès', 'suite' => acces_suite(), 'erreur' => null]);
        }],
        ['POST', '/acces', function () {
            if (!construction_active()) {
                redirect('/');
            }
            if (ip_blocked() || too_many_attempts('acces|' . client_ip(), 10, 15)) {
                http_response_code(429);
                render('accueil/acces', ['titre' => 'Code d\'accès', 'suite' => acces_suite(), 'erreur' => 'Trop d\'essais : réessayez dans 15 minutes.']);
                return;
            }
            $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
            if (!hash_equals((string) setting('construction_code', ''), $code)) {
                too_many_attempts('ip|' . client_ip(), 100, 60);
                http_response_code(422);
                render('accueil/acces', ['titre' => 'Code d\'accès', 'suite' => acces_suite(), 'erreur' => 'Code incorrect.']);
                return;
            }
            construction_ouvrir();
            redirect(acces_suite());
        }],
        ['GET', '/theme.css', function () {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo (string) setting('css_personnalise', '');
        }],
    ],
    'menu' => fn() => [['libelle' => t('menu.accueil'), 'lien' => '/', 'ordre' => 0]],
];

// Page à retrouver après le code : seulement une adresse du site (jamais un autre site)
function acces_suite(): string
{
    $suite = (string) ($_GET['suite'] ?? $_POST['suite'] ?? '/');
    return preg_match('#^/(?!/)\S*$#', $suite) && !str_contains($suite, '\\') && !str_starts_with($suite, '/acces') ? $suite : '/';
}
