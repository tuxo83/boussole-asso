<?php
// Démarrage : configuration, base, modules, routage
declare(strict_types=1);

define('BOUSSOLE_ROOT', dirname(__DIR__));
define('BOUSSOLE_VERSION', '0.1.0');
// Code source (lien du pied de page)
const BOUSSOLE_DEPOT = 'https://github.com/tuxo83/boussole-asso';

require __DIR__ . '/db.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/router.php';
require __DIR__ . '/view.php';
require __DIR__ . '/components.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/settings.php';
require __DIR__ . '/secret.php';
require __DIR__ . '/totp.php';
require __DIR__ . '/webauthn.php';
require __DIR__ . '/mail.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/appareils.php';
require __DIR__ . '/humain.php';
require __DIR__ . '/espaces.php';
require __DIR__ . '/contact.php';
require __DIR__ . '/blocs.php';
require __DIR__ . '/images.php';
require __DIR__ . '/evenements.php';
require __DIR__ . '/reglement.php';
require __DIR__ . '/cotisations.php';
require __DIR__ . '/rappels.php';
require __DIR__ . '/helloasso.php';
require __DIR__ . '/taches.php';
require __DIR__ . '/construction.php';
require __DIR__ . '/referencement.php';
require __DIR__ . '/api.php';

// Configuration écrite par install.sh ou par l'assistant d'installation ; null tant qu'elle n'existe pas
function config(): ?array
{
    static $config = false;
    if ($config === false) {
        $file = BOUSSOLE_ROOT . '/config/config.php';
        $config = is_file($file) ? require $file : null;
    }
    return $config;
}

function config_path(): string
{
    return BOUSSOLE_ROOT . '/config/config.php';
}

// Chaque module déclare ses routes et ses entrées de menu dans modules/<nom>/module.php
function modules(): array
{
    static $modules = null;
    if ($modules === null) {
        $modules = [];
        foreach (glob(BOUSSOLE_ROOT . '/modules/*/module.php') as $file) {
            $module = require $file;
            $module['nom'] = basename(dirname($file));
            $modules[$module['nom']] = $module;
        }
        uasort($modules, fn($a, $b) => ($a['ordre'] ?? 50) <=> ($b['ordre'] ?? 50));
    }
    return $modules;
}

function boussole_run(): void
{
    $path = request_path();
    $api = str_starts_with($path, '/api/') || $path === '/mcp';
    if (!$api && session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
        session_start();
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');

    $installed = config() !== null && installation_done();

    // Tant que l'installation n'est pas finie, seul l'assistant répond
    if (!$installed && !str_starts_with($path, '/installation')) {
        redirect('/installation');
    }
    if ($installed) {
        migrate_if_needed();
        // Appareil retenu (« Rester connecté ») : la session se rouvre à un seul facteur
        if (!$api) {
            appareil_restaurer();
        }
        if (construction_active()) {
            header('X-Robots-Tag: noindex, nofollow');
            if (!construction_autorise($path)) {
                redirect('/acces?suite=' . rawurlencode($path));
            }
        }
        // Mot de passe provisoire : rien d'autre tant qu'il n'est pas remplacé (connexion et déconnexion restent possibles)
        if (!$api && !preg_match('#^/(compte/nouveau-mot-de-passe|connexion|deconnexion|acces|theme\.css|assets/)#', $path)) {
            $moi = current_member();
            if ($moi !== null && !empty($moi['mot_de_passe_provisoire'])) {
                redirect('/compte/nouveau-mot-de-passe');
            }
        }
    }

    $router = new Router();
    foreach (modules() as $module) {
        foreach ($module['routes'] ?? [] as $route) {
            $router->add(...$route);
        }
    }
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
}

function is_https(): bool
{
    return ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

// Chemin demandé, avec ou sans réécriture d'adresse (index.php/... fonctionne partout)
function request_path(): string
{
    if (!empty($_SERVER['PATH_INFO'])) {
        $path = $_SERVER['PATH_INFO'];
    } else {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
    }
    $path = '/' . trim($path, '/');
    return $path;
}

// Dossier où est installé Boussole, si ce n'est pas la racine du domaine
function base_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    return $dir === '/' ? '' : $dir;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

// Adresse complète (dans les e-mails) : l'adresse publique réglée dans l'admin, sinon celle de la requête
function absolute_url(string $path): string
{
    $base = rtrim((string) setting('adresse_site', ''), '/');
    if ($base === '') {
        $base = (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
    }
    return $base . '/' . ltrim($path, '/');
}

// Version déployée (écrite par install.sh selon le versioning de la documentation d'infra) : version, commit, date du commit, date du déploiement
function boussole_version(): array
{
    $fichier = BOUSSOLE_ROOT . '/config/VERSION.txt';
    $lignes = is_file($fichier) ? array_map('trim', file($fichier)) : [];
    return ['version' => $lignes[0] ?? 'développement', 'commit' => $lignes[1] ?? '', 'commit_le' => $lignes[2] ?? '', 'deploye_le' => $lignes[3] ?? ''];
}
