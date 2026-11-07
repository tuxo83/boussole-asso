<?php
// Connexion : mot de passe (facultatif), sinon lien magique par e-mail avec code à 6 chiffres de secours,
// puis 2e facteur si la double authentification est active
declare(strict_types=1);

return [
    'ordre' => 90,
    'routes' => [
        ['GET', '/connexion', function () {
            if (current_member() !== null) {
                redirect('/compte');
            }
            render('connexion/connexion', ['titre' => 'Connexion', 'email' => '', 'erreur' => null]);
        }],
        ['POST', '/connexion', function () {
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['mot_de_passe'] ?? '');
            if (too_many_attempts('ip|' . client_ip(), 30) || too_many_attempts('email|' . $email, 10)) {
                http_response_code(429);
                render('connexion/connexion', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'Trop de tentatives, réessayez dans 15 minutes.']);
                return;
            }
            $member = db_one('SELECT * FROM membre WHERE email = ?', [$email]);
            if ($password !== '') {
                if ($member !== null && $member['mot_de_passe'] !== null && password_verify($password, $member['mot_de_passe'])) {
                    login_first_step($member);
                }
                http_response_code(422);
                render('connexion/connexion', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'E-mail ou mot de passe incorrect.']);
                return;
            }
            // Même réponse que l'adresse existe ou non : on ne révèle pas qui est inscrit
            if ($member !== null) {
                [$link, $code] = create_login_token($member);
                $site = (string) setting('nom_association', 'Boussole');
                mail_send($member['email'], "Connexion à $site",
                    "Bonjour,\n\nPour vous connecter, ouvrez ce lien (valable " . LIEN_DUREE_MINUTES . " minutes, une seule fois) :\n"
                    . absolute_url('/connexion/lien/' . $link) . "\n\nOu saisissez ce code sur la page de connexion : $code\n\n"
                    . "Si vous n'avez rien demandé, ignorez ce message.\n");
            }
            $_SESSION['connexion_email'] = $email;
            redirect('/connexion/code');
        }],
        ['GET', '/connexion/code', function () {
            render('connexion/code', ['titre' => 'Connexion', 'email' => (string) ($_SESSION['connexion_email'] ?? ''), 'erreur' => null]);
        }],
        ['POST', '/connexion/code', function () {
            $email = (string) ($_SESSION['connexion_email'] ?? ($_POST['email'] ?? ''));
            $member = too_many_attempts('ip|' . client_ip(), 30) ? null : use_login_code($email, (string) ($_POST['code'] ?? ''));
            if ($member === null) {
                http_response_code(422);
                render('connexion/code', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'Code incorrect ou expiré.']);
                return;
            }
            unset($_SESSION['connexion_email']);
            login_first_step($member);
        }],
        // Le lien ouvre une page de confirmation : un logiciel qui « prévisualise » le lien ne consomme pas la connexion
        ['GET', '/connexion/lien/{jeton}', function (array $p) {
            render('connexion/lien', ['titre' => 'Connexion', 'jeton' => $p['jeton']]);
        }],
        ['POST', '/connexion/lien/{jeton}', function (array $p) {
            $member = use_login_link($p['jeton']);
            if ($member === null) {
                flash('erreur', 'Ce lien a expiré ou a déjà servi. Demandez-en un nouveau.');
                redirect('/connexion');
            }
            login_first_step($member);
        }],
        ['GET', '/connexion/double-authentification', function () {
            if (empty($_SESSION['membre_id'])) {
                redirect('/connexion');
            }
            render('connexion/totp', ['titre' => 'Double authentification', 'erreur' => null]);
        }],
        ['POST', '/connexion/double-authentification', function () {
            $member = empty($_SESSION['membre_id']) ? null : db_one('SELECT * FROM membre WHERE id = ?', [$_SESSION['membre_id']]);
            if ($member === null) {
                redirect('/connexion');
            }
            $secret = secret_decrypt((string) $member['totp_secret']) ?? '';
            if (too_many_attempts('totp|' . $member['id'], 10) || !totp_verify($secret, (string) ($_POST['code'] ?? ''))) {
                http_response_code(422);
                render('connexion/totp', ['titre' => 'Double authentification', 'erreur' => 'Code incorrect.']);
                return;
            }
            login_complete($member);
        }],
        ['POST', '/deconnexion', function () {
            logout();
            redirect('/');
        }],
    ],
    'menu' => fn() => current_member() !== null
        ? [['libelle' => 'Mon compte', 'lien' => '/compte', 'ordre' => 95]]
        : [['libelle' => 'Connexion', 'lien' => '/connexion', 'ordre' => 95]],
];
