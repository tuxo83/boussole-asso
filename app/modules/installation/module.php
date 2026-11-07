<?php
// Assistant d'installation : base de données (si elle n'est pas déjà fournie), nom de l'association, premier administrateur
declare(strict_types=1);

return [
    'ordre' => 99,
    'routes' => [
        ['GET', '/installation', function () {
            if (installation_done()) {
                redirect('/');
            }
            render('installation/formulaire', ['titre' => t('installation.titre'), 'valeurs' => [], 'erreurs' => []]);
        }],
        ['POST', '/installation', function () {
            if (installation_done()) {
                redirect('/');
            }
            [$valeurs, $erreurs] = installation_validate($_POST);
            if ($erreurs) {
                http_response_code(422);
                render('installation/formulaire', ['titre' => t('installation.titre'), 'valeurs' => $valeurs, 'erreurs' => $erreurs]);
                return;
            }
            installation_apply($valeurs);
            flash('succes', 'Installation terminée.');
            redirect('/');
        }],
    ],
];

function installation_validate(array $input): array
{
    $champ = fn(string $nom, int $max = 190) => mb_substr(trim((string) ($input[$nom] ?? '')), 0, $max);
    $v = [
        'nom_association' => $champ('nom_association', 150),
        'email_admin' => $champ('email_admin'),
        'code_installation' => $champ('code_installation', 64),
    ];
    $erreurs = [];
    if ($v['nom_association'] === '') {
        $erreurs['nom_association'] = "Le nom de l'association est obligatoire.";
    }
    if (!filter_var($v['email_admin'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['email_admin'] = 'Adresse e-mail invalide.';
    }
    // Le premier administrateur se connecte par mot de passe : l'envoi d'e-mails n'est pas encore réglé
    $v['mot_de_passe'] = (string) ($input['mot_de_passe'] ?? '');
    if (mb_strlen($v['mot_de_passe']) < 12 || $v['mot_de_passe'] !== (string) ($input['confirmation'] ?? '')) {
        $erreurs['mot_de_passe'] = 'Au moins 12 caractères, et la confirmation identique.';
    }

    $config = config();
    if ($config === null) {
        // Hébergeur sans configuration préparée : la base est saisie ici
        foreach (['db_host', 'db_name', 'db_user', 'db_password'] as $nom) {
            $v[$nom] = $champ($nom);
        }
        $v['db_port'] = (int) ($input['db_port'] ?? 3306) ?: 3306;
        if ($v['db_host'] === '' || $v['db_name'] === '' || $v['db_user'] === '') {
            $erreurs['db'] = 'Serveur, base et utilisateur sont obligatoires.';
        } elseif (!$erreurs) {
            try {
                db_connect(installation_db_config($v));
            } catch (PDOException) {
                $erreurs['db'] = 'Connexion à la base impossible avec ces informations.';
            }
        }
        if (!is_writable(dirname(config_path()))) {
            $erreurs['db'] = "Le dossier config/ n'est pas accessible en écriture.";
        }
    } elseif (!empty($config['code_installation']) && !hash_equals((string) $config['code_installation'], $v['code_installation'])) {
        // Configuration préparée au déploiement : seul celui qui a le code peut finir l'installation
        $erreurs['code_installation'] = "Code d'installation incorrect.";
    }
    return [$v, $erreurs];
}

function installation_db_config(array $v): array
{
    return ['host' => $v['db_host'], 'port' => $v['db_port'], 'name' => $v['db_name'], 'user' => $v['db_user'], 'password' => $v['db_password']];
}

function installation_apply(array $v): void
{
    if (config() === null) {
        $config = ['db' => installation_db_config($v), 'secret' => bin2hex(random_bytes(32))];
        file_put_contents(config_path(), "<?php\n// Écrit par l'assistant d'installation\nreturn " . var_export($config, true) . ";\n", LOCK_EX);
        chmod(config_path(), 0640);
        $pdo = db_connect($config['db']);
    } else {
        $pdo = db();
    }
    migrate($pdo);
    $set = $pdo->prepare('INSERT INTO parametre (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)');
    $set->execute(['nom_association', $v['nom_association']]);
    $pdo->prepare('INSERT INTO membre (email, est_admin, mot_de_passe) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE est_admin = 1, mot_de_passe = VALUES(mot_de_passe)')
        ->execute([mb_strtolower($v['email_admin']), password_hash($v['mot_de_passe'], PASSWORD_DEFAULT)]);
    // Adresse du site figée à l'installation : les liens des e-mails ne dépendent pas de l'en-tête Host d'une requête
    $set->execute(['adresse_site', (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path()]);
    $set->execute(['installation_terminee', '1']);
}
