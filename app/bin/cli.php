<?php
// Commandes en ligne : php bin/cli.php migrate
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/core/app.php';

$command = $argv[1] ?? '';
switch ($command) {
    case 'migrate':
        if (config() === null) {
            fwrite(STDERR, "Pas de configuration : rien à migrer\n");
            exit(1);
        }
        $applied = migrate(db());
        echo $applied ? 'Migrations appliquées : ' . implode(', ', $applied) . "\n" : "Base à jour\n";
        break;
    case 'config-sql':
        // Écrit config/config.php à partir de fichiers d'identifiants (simple-web-server : ../sql/db, login, pwd)
        [$name, $user, $password] = array_map(fn($f) => trim((string) @file_get_contents($f)), array_slice($argv, 2, 3));
        if ($name === '' || $user === '') {
            fwrite(STDERR, "Usage : php bin/cli.php config-sql <fichier db> <fichier login> <fichier pwd>\n");
            exit(1);
        }
        $old = config() ?? [];
        $config = [
            'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => $name, 'user' => $user, 'password' => $password],
            'secret' => $old['secret'] ?? bin2hex(random_bytes(32)),
        ];
        // Code demandé par l'assistant d'installation : seul celui qui a lu le journal du déploiement peut finir l'installation
        $config['code_installation'] = $old['code_installation'] ?? substr(bin2hex(random_bytes(6)), 0, 10);
        if ($config !== $old) {
            file_put_contents(config_path(), "<?php\n// Écrit au déploiement\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
            chmod(config_path(), 0640);
            echo "Configuration écrite\n";
        }
        if (!installation_done()) {
            echo "Installation à terminer sur le site, code d'installation : {$config['code_installation']}\n";
        }
        break;
    case 'cron':
        // Tâches planifiées (rappels, relances…) : ajoutées par les étapes suivantes
        echo "cron : rien à faire pour l'instant\n";
        break;
    default:
        fwrite(STDERR, "Usage : php bin/cli.php migrate|config-sql|cron\n");
        exit(1);
}
