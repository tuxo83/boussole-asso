<?php
// Paramètres de l'installation (nom, couleurs, textes…) : en base, jamais en dur dans le code
declare(strict_types=1);

function setting(string $key, ?string $default = null): ?string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function settings_all(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) {
        $cache = [];
        try {
            foreach (db_all('SELECT cle, valeur FROM parametre') as $row) {
                $cache[$row['cle']] = $row['valeur'];
            }
        } catch (PDOException) {
            // Table pas encore créée (avant la première migration)
        }
    }
    return $cache;
}

function setting_set(string $key, string $value): void
{
    db_query('INSERT INTO parametre (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)', [$key, $value]);
    settings_all(true);
}

function installation_done(): bool
{
    try {
        return setting('installation_terminee') === '1';
    } catch (Throwable) {
        return false;
    }
}

// Couleur au format #rrggbb, sinon la valeur par défaut (jamais de texte libre dans le CSS)
function color_setting(string $key, string $default): string
{
    $value = (string) setting($key, $default);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}
