<?php
// Affichage : un gabarit commun (views/layout.php) autour de la vue de chaque page
declare(strict_types=1);

// Échappement HTML : toute donnée affichée passe par là
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Cherche la vue dans le module (« module/vue ») ou dans views/
function view_file(string $view): string
{
    if (str_contains($view, '/')) {
        [$module, $name] = explode('/', $view, 2);
        $file = BOUSSOLE_ROOT . "/modules/$module/views/$name.php";
    } else {
        $file = BOUSSOLE_ROOT . "/views/$view.php";
    }
    if (!is_file($file)) {
        throw new RuntimeException("Vue introuvable : $view");
    }
    return $file;
}

function partial(string $view, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require view_file($view);
    return (string) ob_get_clean();
}

function render(string $view, array $data = [], string $layout = 'layout'): void
{
    $content = partial($view, $data);
    echo partial($layout, $data + ['content' => $content]);
}

function flash(?string $type = null, ?string $message = null): array
{
    if ($type !== null) {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        return [];
    }
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function t(string $key, array $vars = []): string
{
    static $strings = null;
    $strings ??= require BOUSSOLE_ROOT . '/core/lang/fr.php';
    $text = $strings[$key] ?? $key;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

// Menu : chaque module peut ajouter des entrées (fonction « menu » de son module.php)
function menu_items(): array
{
    $items = [];
    foreach (modules() as $module) {
        if (isset($module['menu'])) {
            $items = array_merge($items, ($module['menu'])());
        }
    }
    usort($items, fn($a, $b) => ($a['ordre'] ?? 50) <=> ($b['ordre'] ?? 50));
    return $items;
}
