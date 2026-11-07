<?php
// Mon compte : informations, mot de passe facultatif, double authentification
declare(strict_types=1);

const MOT_DE_PASSE_MIN = 12;

return [
    'ordre' => 85,
    'routes' => [
        ['GET', '/compte', function () {
            render('compte/compte', ['titre' => 'Mon compte', 'membre' => require_member(), 'erreur' => null]);
        }],
        ['POST', '/compte/mot-de-passe', function () {
            $member = require_member();
            $password = (string) ($_POST['mot_de_passe'] ?? '');
            if ($password === '') {
                db_query('UPDATE membre SET mot_de_passe = NULL WHERE id = ?', [$member['id']]);
                flash('succes', 'Mot de passe retiré : connexion par lien envoyé par e-mail.');
                redirect('/compte');
            }
            if (mb_strlen($password) < MOT_DE_PASSE_MIN || $password !== (string) ($_POST['confirmation'] ?? '')) {
                http_response_code(422);
                render('compte/compte', ['titre' => 'Mon compte', 'membre' => $member,
                    'erreur' => 'Le mot de passe doit faire au moins ' . MOT_DE_PASSE_MIN . ' caractères, et la confirmation être identique.']);
                return;
            }
            db_query('UPDATE membre SET mot_de_passe = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $member['id']]);
            flash('succes', 'Mot de passe enregistré.');
            redirect('/compte');
        }],
        ['GET', '/compte/double-authentification', function () {
            $member = require_member();
            $_SESSION['totp_en_attente'] ??= totp_new_secret();
            render('compte/totp', ['titre' => 'Double authentification', 'membre' => $member,
                'secret' => $_SESSION['totp_en_attente'], 'erreur' => null]);
        }],
        ['POST', '/compte/double-authentification', function () {
            $member = require_member();
            $secret = (string) ($_SESSION['totp_en_attente'] ?? '');
            if ($secret === '' || !totp_verify($secret, (string) ($_POST['code'] ?? ''))) {
                http_response_code(422);
                render('compte/totp', ['titre' => 'Double authentification', 'membre' => $member,
                    'secret' => $secret ?: totp_new_secret(), 'erreur' => 'Code incorrect : vérifiez l\'heure de votre téléphone.']);
                return;
            }
            db_query('UPDATE membre SET totp_secret = ?, totp_actif = 1 WHERE id = ?', [secret_encrypt($secret), $member['id']]);
            unset($_SESSION['totp_en_attente']);
            flash('succes', 'Double authentification activée.');
            redirect('/compte');
        }],
    ],
];
