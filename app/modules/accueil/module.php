<?php
// Accueil public et feuille de style personnalisée
declare(strict_types=1);

return [
    'ordre' => 10,
    'routes' => [
        ['GET', '/', function () {
            render('accueil/accueil', [
                'presentation' => (string) setting('presentation', ''),
            ]);
        }],
        // CSS personnalisé de l'installation, servi comme une feuille de style (jamais dans le HTML)
        ['GET', '/theme.css', function () {
            header('Content-Type: text/css; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo (string) setting('css_personnalise', '');
        }],
    ],
    'menu' => fn() => [['libelle' => t('menu.accueil'), 'lien' => '/', 'ordre' => 0]],
];
