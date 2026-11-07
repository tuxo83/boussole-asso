<?php
// Site en construction : un bandeau sur chaque page, et un code à 6 chiffres pour voir le site.
// Les membres connectés, la page de connexion, l'API, le MCP et les notifications HelloAsso restent accessibles.
declare(strict_types=1);

const CONSTRUCTION_LIBRES = ['/acces', '/connexion', '/installation', '/theme.css', '/mcp', '/robots.txt', '/sitemap.xml'];

function construction_active(): bool
{
    return installation_done() && setting('construction_active', '') === '1' && preg_match('/^\d{6}$/', (string) setting('construction_code', '')) === 1;
}

// Jeton du cookie : dépend du code, donc changer le code ferme l'accès à tous ceux qui avaient l'ancien
function construction_jeton(): string
{
    return hash_hmac('sha256', 'acces|' . setting('construction_code', ''), (string) (config()['secret'] ?? ''));
}

function construction_autorise(string $path): bool
{
    if (!construction_active() || current_member() !== null) {
        return true;
    }
    foreach (CONSTRUCTION_LIBRES as $libre) {
        if ($path === $libre || str_starts_with($path, $libre . '/')) {
            return true;
        }
    }
    if (str_starts_with($path, '/api/') || str_starts_with($path, '/helloasso/notification/')) {
        return true;
    }
    return hash_equals(construction_jeton(), (string) ($_COOKIE['boussole_acces'] ?? ''));
}

function construction_ouvrir(): void
{
    setcookie('boussole_acces', construction_jeton(), [
        'expires' => time() + 30 * 86400, 'path' => base_path() ?: '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}
