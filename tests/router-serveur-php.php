<?php
// Pour tester en local avec le serveur intégré de PHP : php -S 127.0.0.1:8099 -t app tests/router-serveur-php.php
$file = __DIR__ . '/../app' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../app/index.php';
