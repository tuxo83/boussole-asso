<?php
// Pour tester en local avec le serveur intégré de PHP : php -S 127.0.0.1:8099 -t app tests/router-serveur-php.php
// (fonctionne aussi sur une copie déployée : -t <dossier du site>)
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$file = $root . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
