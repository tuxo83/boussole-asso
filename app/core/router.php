<?php
// Routage : méthode + chemin, paramètres {nom} dans le chemin
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[a-z0-9-]+)', $pattern) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            $allowed = true;
            if ($routeMethod !== strtoupper($method)) {
                continue;
            }
            // L'API et le MCP s'authentifient par clé (en-tête Authorization), jamais par cookie : pas de CSRF possible
            // La notification HelloAsso vient d'un serveur et n'est jamais lue : elle déclenche seulement une vérification
            if ($routeMethod === 'POST' && !str_starts_with($path, '/api/') && $path !== '/mcp' && !str_starts_with($path, '/helloasso/notification/')) {
                csrf_check();
            }
            $handler(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            return;
        }
        http_response_code($allowed ? 405 : 404);
        render('erreur', ['titre' => $allowed ? 'Méthode non autorisée' : 'Page introuvable']);
    }
}
