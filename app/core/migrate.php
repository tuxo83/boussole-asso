<?php
// Migrations : fichiers migrations/NNN_nom.sql, appliqués une seule fois, dans l'ordre
declare(strict_types=1);

function migration_files(): array
{
    $files = glob(BOUSSOLE_ROOT . '/migrations/[0-9][0-9][0-9]_*.sql');
    sort($files);
    return $files;
}

function migrate(PDO $pdo): array
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migration (
        nom VARCHAR(190) PRIMARY KEY,
        applique_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    // Un seul processus migre à la fois (plusieurs visiteurs peuvent arriver en même temps)
    $pdo->query("SELECT GET_LOCK('boussole_migration', 30)");
    try {
        $done = $pdo->query('SELECT nom FROM schema_migration')->fetchAll(PDO::FETCH_COLUMN);
        $applied = [];
        foreach (migration_files() as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            foreach (sql_statements((string) file_get_contents($file)) as $statement) {
                $pdo->exec($statement);
            }
            $pdo->prepare('INSERT INTO schema_migration (nom) VALUES (?)')->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('boussole_migration')");
    }
}

// Découpe un fichier SQL en requêtes (séparées par « ; » en fin de ligne, commentaires « -- » ignorés)
function sql_statements(string $sql): array
{
    $lines = array_filter(explode("\n", $sql), fn($line) => !str_starts_with(ltrim($line), '--'));
    $statements = preg_split('/;\s*(\n|$)/', implode("\n", $lines));
    return array_values(array_filter(array_map('trim', $statements), fn($s) => $s !== ''));
}

// À chaque requête web : migre si une nouvelle version a apporté des migrations (hébergeurs sans ligne de commande)
function migrate_if_needed(): void
{
    $files = migration_files();
    $latest = $files ? basename(end($files)) : '';
    if (setting('schema_version') !== $latest) {
        migrate(db());
        setting_set('schema_version', $latest);
    }
}
