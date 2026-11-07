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
        // Clés d'API personnelles (IA, MCP) : lecture seule ou écriture, durée limitée, révocables
        ['GET', '/compte/cles', function () {
            $member = require_member();
            render('compte/cles', ['titre' => "Clés d'API", 'cles' => db_all('SELECT * FROM cle_api WHERE membre_id = ? ORDER BY revoquee, cree_le DESC', [$member['id']]),
                'nouvelle' => $_SESSION['cle_nouvelle'] ?? null]);
            unset($_SESSION['cle_nouvelle']);
        }],
        ['POST', '/compte/cles', function () {
            $member = require_member();
            $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 100);
            $jours = (int) ($_POST['duree'] ?? 0);
            if ($libelle === '' || !isset(CLE_DUREES[$jours])) {
                flash('erreur', 'Nom et durée obligatoires.');
                redirect('/compte/cles');
            }
            // Clé d'écriture pour un compte qui a des droits de gestion : double authentification exigée
            if (($_POST['portee'] ?? '') === 'ecriture' && member_rights($member['id']) && !(int) $member['totp_actif']) {
                flash('info', 'Activez la double authentification avant de créer une clé d\'écriture.');
                redirect('/compte/double-authentification');
            }
            $_SESSION['cle_nouvelle'] = api_creer_cle($member, $libelle, (string) ($_POST['portee'] ?? 'lecture'), $jours);
            journal(null, $member, "Clé d'API créée : $libelle");
            redirect('/compte/cles');
        }],
        ['POST', '/compte/cles/{id}/revoquer', function (array $p) {
            $member = require_member();
            $cle = db_one('SELECT * FROM cle_api WHERE id = ?', [(int) $p['id']]);
            // Le propriétaire révoque sa clé ; l'administrateur peut révoquer n'importe quelle clé
            if ($cle !== null && ((int) $cle['membre_id'] === (int) $member['id'] || is_admin($member))) {
                db_query('UPDATE cle_api SET revoquee = 1 WHERE id = ?', [$cle['id']]);
                journal(null, $member, "Clé d'API révoquée : {$cle['libelle']}");
            }
            redirect(is_admin($member) && (int) $cle['membre_id'] !== (int) $member['id'] ? '/admin/cles' : '/compte/cles');
        }],
        ['GET', '/admin/cles', function () {
            require_right('admin');
            render('compte/admin-cles', ['titre' => "Toutes les clés d'API",
                'cles' => db_all('SELECT c.*, m.email, m.prenom, m.nom FROM cle_api c JOIN membre m ON m.id = c.membre_id ORDER BY c.revoquee, c.expire_le')]);
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
