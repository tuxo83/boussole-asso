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
            if (!humain_verifier()) {
                humain_refus();
            }
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['mot_de_passe'] ?? '');
            if (ip_blocked() || too_many_attempts('ip|' . client_ip(), 30) || too_many_attempts('email|' . $email, 10)) {
                http_response_code(429);
                render('connexion/connexion', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'Trop de tentatives, réessayez dans 15 minutes.']);
                return;
            }
            $member = db_one('SELECT * FROM membre WHERE email = ?', [$email]);
            if ($password !== '') {
                if ($member !== null && $member['mot_de_passe'] !== null && password_verify($password, $member['mot_de_passe'])) {
                    login_first_step($member, 'mdp');
                }
                http_response_code(422);
                render('connexion/connexion', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'E-mail ou mot de passe incorrect.']);
                return;
            }
            // Même réponse que l'adresse existe ou non : on ne révèle pas qui est inscrit (ni qui est désactivé)
            if ($member !== null && (int) $member['actif']) {
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
            if (!humain_verifier()) {
                humain_refus();
            }
            $email = (string) ($_SESSION['connexion_email'] ?? ($_POST['email'] ?? ''));
            $member = too_many_attempts('ip|' . client_ip(), 30) ? null : use_login_code($email, (string) ($_POST['code'] ?? ''));
            if ($member === null) {
                http_response_code(422);
                render('connexion/code', ['titre' => 'Connexion', 'email' => $email, 'erreur' => 'Code incorrect ou expiré.']);
                return;
            }
            unset($_SESSION['connexion_email']);
            login_first_step($member, 'email');
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
            login_first_step($member, 'email');
        }],
        // Second facteur (à la connexion, ou pour confirmer avant une action de gestion) : clé, application,
        // code par e-mail (si le 1er facteur n'était pas l'e-mail), ou mot de passe (si le 1er était l'e-mail)
        ['GET', '/connexion/double-authentification', function () {
            $member = second_facteur_membre();
            render('connexion/totp', ['titre' => 'Double authentification'] + second_facteur_choix($member) + ['erreur' => null, 'code_envoye' => !empty($_SESSION['code_2fa']), 'deja_connecte' => !empty($_SESSION['connecte'])]);
        }],
        ['POST', '/connexion/double-authentification', function () {
            $member = second_facteur_membre();
            $secret = secret_decrypt((string) $member['totp_secret']) ?? '';
            if (!(int) $member['totp_actif'] || too_many_attempts('totp|' . $member['id'], 10) || !totp_verify($secret, (string) ($_POST['code'] ?? ''))) {
                second_facteur_refus($member, 'Code incorrect.');
            }
            second_facteur_ok($member, 'totp');
        }],
        ['POST', '/connexion/double-authentification/envoyer-code', function () {
            $member = second_facteur_membre();
            if (in_array('email', facteurs(), true) || too_many_attempts('code2fa|' . $member['id'], 3)) {
                second_facteur_refus($member, in_array('email', facteurs(), true) ? 'Vous vous êtes déjà connecté par e-mail : utilisez un autre moyen.' : 'Trop de codes demandés : réessayez dans 15 minutes.');
            }
            $code = sprintf('%06d', random_int(0, 999999));
            $_SESSION['code_2fa'] = ['hash' => hash_hmac('sha256', $code, (string) (config()['secret'] ?? '')), 'expire' => time() + 600, 'essais' => 0];
            mail_send($member['email'], 'Code de confirmation : ' . setting('nom_association', 'Boussole'), "Votre code de confirmation : $code\n\nIl est valable 10 minutes. Si vous n'avez rien demandé, changez votre mot de passe.\n");
            redirect('/connexion/double-authentification');
        }],
        ['POST', '/connexion/double-authentification/code', function () {
            $member = second_facteur_membre();
            $attendu = $_SESSION['code_2fa'] ?? null;
            $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? ''));
            if (!is_array($attendu) || $attendu['expire'] < time() || ++$_SESSION['code_2fa']['essais'] > 5
                || !hash_equals($attendu['hash'], hash_hmac('sha256', $code, (string) (config()['secret'] ?? '')))) {
                if (is_array($attendu) && ($_SESSION['code_2fa']['essais'] ?? 0) > 5) {
                    unset($_SESSION['code_2fa']);
                }
                second_facteur_refus($member, 'Code incorrect ou expiré.');
            }
            unset($_SESSION['code_2fa']);
            second_facteur_ok($member, 'email');
        }],
        ['POST', '/connexion/double-authentification/mot-de-passe', function () {
            $member = second_facteur_membre();
            if (too_many_attempts('mdp2fa|' . $member['id'], 10) || $member['mot_de_passe'] === null || !password_verify((string) ($_POST['mot_de_passe'] ?? ''), $member['mot_de_passe'])) {
                second_facteur_refus($member, 'Mot de passe incorrect.');
            }
            second_facteur_ok($member, 'mdp');
        }],
        ['GET', '/connexion/double-authentification/cle', function () {
            $member = second_facteur_membre();
            header('Content-Type: application/json');
            echo json_encode(webauthn_options_connexion($member));
        }],
        ['POST', '/connexion/double-authentification/cle', function () {
            $member = second_facteur_membre();
            try {
                if (too_many_attempts('cle|' . $member['id'], 10)) {
                    throw new WebauthnErreur('Trop d\'essais');
                }
                webauthn_verifier($member, json_decode((string) ($_POST['reponse'] ?? ''), true) ?: []);
            } catch (WebauthnErreur $erreur) {
                second_facteur_refus($member, 'Clé refusée : ' . $erreur->getMessage());
            }
            second_facteur_ok($member, 'cle');
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

// Membre en cours de second facteur : connexion commencée, ou déjà connecté qui confirme (gestion)
function second_facteur_membre(): array
{
    $member = empty($_SESSION['membre_id']) ? null : db_one('SELECT * FROM membre WHERE id = ? AND actif = 1', [$_SESSION['membre_id']]);
    if ($member === null) {
        redirect('/connexion');
    }
    // Session ouverte avant cette version (facteurs non notés) : elle compte pour une seule preuve
    if (!facteurs() && !empty($_SESSION['connecte'])) {
        $_SESSION['facteurs'] = ['session'];
    }
    if (!facteurs()) {
        redirect('/connexion');
    }
    return $member;
}

function second_facteur_choix(array $member): array
{
    $faits = facteurs();
    return [
        'cle' => !in_array('cle', $faits, true) && (int) db_value('SELECT COUNT(*) FROM cle_securite WHERE membre_id = ?', [$member['id']]) > 0,
        'totp' => !in_array('totp', $faits, true) && (int) $member['totp_actif'] === 1,
        'email' => !in_array('email', $faits, true),
        'mdp' => !in_array('mdp', $faits, true) && $member['mot_de_passe'] !== null,
    ];
}

function second_facteur_refus(array $member, string $erreur): never
{
    http_response_code(422);
    render('connexion/totp', ['titre' => 'Double authentification'] + second_facteur_choix($member) + ['erreur' => $erreur, 'code_envoye' => !empty($_SESSION['code_2fa']), 'deja_connecte' => !empty($_SESSION['connecte'])]);
    exit;
}

function second_facteur_ok(array $member, string $facteur): never
{
    session_regenerate_id(true);
    ajouter_facteur($facteur);
    login_complete($member);
}
