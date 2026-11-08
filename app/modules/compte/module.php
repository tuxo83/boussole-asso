<?php
// Mon compte : informations, mot de passe facultatif, double authentification
declare(strict_types=1);

const MOT_DE_PASSE_MIN = 12;

return [
    'ordre' => 85,
    'routes' => [
        ['GET', '/compte', function () {
            $member = require_member();
            render('compte/compte', ['titre' => 'Mon compte', 'membre' => $member, 'erreur' => null]);
        }],
        ['POST', '/compte/mot-de-passe', function () {
            $member = require_member();
            compte_demo_refuser($member);
            // Changer ou retirer un mot de passe existant d'un compte qui gère : deux preuves (le premier mot de passe peut être choisi après un lien e-mail)
            if ($member['mot_de_passe'] !== null && (member_rights($member['id']) || member_exige_2fa($member)) && count(facteurs()) < 2) {
                $_SESSION['apres_connexion'] = '/compte/parametres';
                redirect('/connexion/double-authentification');
            }
            $password = (string) ($_POST['mot_de_passe'] ?? '');
            if ($password === '') {
                db_query('UPDATE membre SET mot_de_passe = NULL WHERE id = ?', [$member['id']]);
                journal_compte((int) $member['id'], $member, 'Mot de passe retiré');
                flash('succes', 'Mot de passe retiré : connexion par lien envoyé par e-mail.');
                redirect('/compte/parametres');
            }
            if (mb_strlen($password) < MOT_DE_PASSE_MIN || $password !== (string) ($_POST['confirmation'] ?? '')) {
                http_response_code(422);
                render('compte/parametres', ['titre' => 'Paramètres', 'membre' => $member, 'historique' => historique_compte((int) $member['id'], 15), 'tuiles' => administration_tuiles($member),
                    'erreur' => 'Le mot de passe doit faire au moins ' . MOT_DE_PASSE_MIN . ' caractères, et la confirmation être identique.']);
                return;
            }
            db_query('UPDATE membre SET mot_de_passe = ?, mot_de_passe_provisoire = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $member['id']]);
            journal_compte((int) $member['id'], $member, $member['mot_de_passe'] === null ? 'Mot de passe choisi' : 'Mot de passe changé');
            flash('succes', 'Mot de passe enregistré.');
            redirect('/compte/parametres');
        }],
        // Administration : les raccourcis de gestion selon les droits (depuis le menu de compte)
        // Paramètres : la sécurité de mon compte, et l'administration du site pour qui a des droits
        ['GET', '/compte/parametres', function () {
            $member = require_member();
            render('compte/parametres', ['titre' => 'Paramètres', 'membre' => $member, 'erreur' => null,
                'historique' => historique_compte((int) $member['id'], 15), 'tuiles' => administration_tuiles($member)]);
        }],
        // Aide « Qui peut faire quoi » : pour qui a un droit de gestion (lien depuis Administration du site)
        ['GET', '/compte/aide-droits', function () {
            $member = require_member();
            if (!member_rights($member['id'])) {
                redirect('/compte/parametres');
            }
            render('compte/aide-droits', ['titre' => 'Qui peut faire quoi']);
        }],
        ['GET', '/administration', function () {
            require_member();
            redirect('/compte/parametres#administration');
        }],
        // Rappel de cotisation masqué jusqu'à la prochaine connexion
        ['POST', '/compte/rappel-cotisation/masquer', function () {
            require_member();
            $_SESSION['rappel_cotisation_masque'] = true;
            $retour = (string) ($_POST['retour'] ?? '/');
            redirect(str_starts_with($retour, '/') && !str_starts_with($retour, '//') ? $retour : '/');
        }],
        // Après un mot de passe provisoire donné par l'administrateur : en choisir un à soi, différent
        ['GET', '/compte/nouveau-mot-de-passe', function () {
            $member = require_member();
            if (empty($member['mot_de_passe_provisoire'])) {
                redirect('/compte');
            }
            render('compte/nouveau-mot-de-passe', ['titre' => 'Choisissez votre mot de passe', 'erreur' => null]);
        }],
        ['POST', '/compte/nouveau-mot-de-passe', function () {
            $member = require_member();
            if (empty($member['mot_de_passe_provisoire'])) {
                redirect('/compte');
            }
            $password = (string) ($_POST['mot_de_passe'] ?? '');
            $erreur = match (true) {
                mb_strlen($password) < MOT_DE_PASSE_MIN || $password !== (string) ($_POST['confirmation'] ?? '') => 'Au moins ' . MOT_DE_PASSE_MIN . ' caractères, et la confirmation identique.',
                $member['mot_de_passe'] !== null && password_verify($password, $member['mot_de_passe']) => 'Choisissez un mot de passe différent du mot de passe provisoire.',
                default => null,
            };
            if ($erreur !== null) {
                http_response_code(422);
                render('compte/nouveau-mot-de-passe', ['titre' => 'Choisissez votre mot de passe', 'erreur' => $erreur]);
                return;
            }
            db_query('UPDATE membre SET mot_de_passe = ?, mot_de_passe_provisoire = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $member['id']]);
            journal_compte((int) $member['id'], $member, 'Mot de passe provisoire remplacé');
            flash('succes', 'Mot de passe enregistré. Bienvenue !');
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
            compte_demo_refuser($member);
            $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 100);
            $jours = (int) ($_POST['duree'] ?? 0);
            if ($libelle === '' || !isset(CLE_DUREES[$jours])) {
                flash('erreur', 'Nom et durée obligatoires.');
                redirect('/compte/cles');
            }
            // Clé d'écriture pour un compte qui a des droits de gestion : double authentification exigée
            if (in_array($_POST['portee'] ?? '', ['ecriture', 'admin'], true) && member_rights($member['id']) && count(facteurs()) < 2) {
                $_SESSION['apres_connexion'] = '/compte/cles';
                flash('info', 'Confirmez votre identité une seconde fois avant de créer une clé d\'écriture.');
                redirect('/connexion/double-authentification');
            }
            $_SESSION['cle_nouvelle'] = api_creer_cle($member, $libelle, (string) ($_POST['portee'] ?? 'lecture'), $jours);
            journal_compte((int) $member['id'], $member, "Clé d'API créée : $libelle");
            redirect('/compte/cles');
        }],
        ['POST', '/compte/cles/{id}/revoquer', function (array $p) {
            $member = require_member();
            $cle = db_one('SELECT * FROM cle_api WHERE id = ?', [(int) $p['id']]);
            // Le propriétaire révoque sa clé ; l'administrateur peut révoquer n'importe quelle clé
            if ($cle !== null && ((int) $cle['membre_id'] === (int) $member['id'] || is_admin($member))) {
                db_query('UPDATE cle_api SET revoquee = 1 WHERE id = ?', [$cle['id']]);
                journal_compte((int) $cle['membre_id'], $member, "Clé d'API révoquée : {$cle['libelle']}");
            }
            redirect(is_admin($member) && (int) $cle['membre_id'] !== (int) $member['id'] ? '/admin/cles' : '/compte/cles');
        }],
        ['GET', '/admin/cles', function () {
            require_right('admin');
            render('compte/admin-cles', ['titre' => "Toutes les clés d'API",
                'cles' => db_all('SELECT c.*, m.email, m.prenom, m.nom FROM cle_api c JOIN membre m ON m.id = c.membre_id ORDER BY c.revoquee, c.expire_le')]);
        }],
        // Sécurité du compte : clés FIDO2, application ; modifiable seulement après deux preuves si le compte gère quelque chose
        ['GET', '/compte/double-authentification', function () {
            $member = securite_membre();
            $_SESSION['totp_en_attente'] ??= totp_new_secret();
            securite_page($member, null);
        }],
        ['POST', '/compte/double-authentification', function () {
            $member = securite_membre();
            compte_demo_refuser($member);
            $secret = (string) ($_SESSION['totp_en_attente'] ?? '');
            if ($secret === '' || !totp_verify($secret, (string) ($_POST['code'] ?? ''))) {
                http_response_code(422);
                securite_page($member, 'Code incorrect : vérifiez l\'heure de votre téléphone.');
                return;
            }
            db_query('UPDATE membre SET totp_secret = ?, totp_actif = 1 WHERE id = ?', [secret_encrypt($secret), $member['id']]);
            unset($_SESSION['totp_en_attente']);
            ajouter_facteur('totp');
            journal_compte((int) $member['id'], $member, 'Application d\'authentification activée');
            mail_send($member['email'], 'Application d\'authentification activée', "Une application d'authentification vient d'être activée sur votre compte. Si ce n'est pas vous, prévenez l'administrateur.\n");
            flash('succes', 'Application d\'authentification activée.');
            redirect('/compte/parametres');
        }],
        // Quand demander la double authentification : à chaque connexion, ou seulement pour la gestion
        ['POST', '/compte/double-authentification/moment', function () {
            $member = require_member();
            compte_demo_refuser($member);
            $chaque = ($_POST['mfa_connexion'] ?? '') === '1' ? 1 : 0;
            db_query('UPDATE membre SET mfa_connexion = ? WHERE id = ?', [$chaque, $member['id']]);
            journal_compte((int) $member['id'], $member, $chaque ? 'Double authentification : à chaque connexion' : 'Double authentification : seulement pour la gestion');
            flash('succes', 'Réglage enregistré.');
            redirect('/compte/double-authentification');
        }],
        ['GET', '/compte/cles-securite/options', function () {
            $member = securite_membre();
            header('Content-Type: application/json');
            echo json_encode(webauthn_options_creation($member));
        }],
        ['POST', '/compte/cles-securite', function () {
            $member = securite_membre();
            compte_demo_refuser($member);
            try {
                webauthn_enregistrer($member, json_decode((string) ($_POST['reponse'] ?? ''), true) ?: [], (string) ($_POST['libelle'] ?? ''));
            } catch (WebauthnErreur $erreur) {
                http_response_code(422);
                securite_page($member, 'Clé non enregistrée : ' . $erreur->getMessage());
                return;
            }
            ajouter_facteur('cle');
            journal_compte((int) $member['id'], $member, 'Clé de sécurité ajoutée');
            mail_send($member['email'], 'Nouvelle clé de sécurité', "Une clé de sécurité vient d'être ajoutée à votre compte. Si ce n'est pas vous, prévenez l'administrateur.\n");
            flash('succes', 'Clé enregistrée : elle vous sera demandée à la connexion.');
            redirect('/compte/double-authentification');
        }],
        ['POST', '/compte/cles-securite/{id}/supprimer', function (array $p) {
            $member = securite_membre();
            compte_demo_refuser($member);
            if (db_query('DELETE FROM cle_securite WHERE id = ? AND membre_id = ?', [(int) $p['id'], $member['id']])->rowCount() === 1) {
                journal_compte((int) $member['id'], $member, 'Clé de sécurité retirée');
            }
            redirect('/compte/double-authentification');
        }],
    ],
];

// Modifier la sécurité du compte : deux preuves dès qu'un second facteur existe (sinon, quelqu'un qui n'a que
// le mot de passe pourrait remplacer la clé). Le tout premier (clé ou application) s'ajoute après une seule preuve :
// c'est ce qui permet de démarrer quand les e-mails ne partent pas encore. Un e-mail et le journal le signalent.
function securite_membre(): array
{
    $member = require_member();
    if (member_exige_2fa($member) && count(facteurs()) < 2) {
        $_SESSION['apres_connexion'] = request_path() === '/compte/double-authentification' ? '/compte/double-authentification' : '/compte/parametres';
        redirect('/connexion/double-authentification');
    }
    return $member;
}

function securite_page(array $member, ?string $erreur): void
{
    render('compte/totp', ['titre' => 'Sécurité du compte', 'membre' => $member, 'secret' => (string) ($_SESSION['totp_en_attente'] ?? totp_new_secret()),
        'cles' => db_all('SELECT * FROM cle_securite WHERE membre_id = ? ORDER BY cree_le', [$member['id']]), 'erreur' => $erreur]);
}

// Compte de démonstration : ses utilisateurs ne changent ni son mot de passe ni sa sécurité (seul un administrateur le peut)
function compte_demo_refuser(array $member): void
{
    if (!empty($member['compte_demo'])) {
        flash('erreur', 'Compte de démonstration : son mot de passe et sa sécurité ne se changent pas ici.');
        redirect('/compte/parametres');
    }
}

// Raccourcis d'administration selon les droits : [icône, titre, aide, lien, pastille (ce qui attend)]
function administration_tuiles(array $member): array
{
    $tuiles = [];
    if (has_right($member, 'publications')) {
        $tuiles[] = ['crayon', 'Pages du site', 'Créer et modifier les pages', '/admin/pages', ''];
    }
    if (has_right($member, 'cotisations')) {
        $effacer = count(cotisation_a_effacer());
        $tuiles[] = ['medaille', 'Cotisations', 'À jour, chèques, relances', '/cotisations', $effacer ? $effacer . ' à effacer' : ''];
    }
    if (has_right($member, 'membres')) {
        $tuiles[] = ['personnes', 'Membres', 'Ajouter, modifier, droits', '/admin/membres', ''];
    }
    $tuiles[] = ['bouclier', 'Qui peut faire quoi', 'Les droits, espace par espace', '/compte/aide-droits', ''];
    if (is_admin($member)) {
        $nouveaux = (int) db_value('SELECT COUNT(*) FROM message_contact WHERE lu = 0');
        $tuiles[] = ['enveloppe', 'Messages reçus', 'Formulaire de contact', '/admin/messages', $nouveaux ? $nouveaux . ($nouveaux > 1 ? ' nouveaux' : ' nouveau') : ''];
        $tuiles[] = ['reglages', 'Réglages du site', 'Nom, apparence, e-mails', '/admin/parametres', ''];
        $tuiles[] = ['cle', 'Toutes les clés', "Clés d'API des membres", '/admin/cles', ''];
    }
    return $tuiles;
}

// Nombre de choses qui attendent dans l'administration (pastille du menu de compte)
function administration_en_attente(array $member): int
{
    $n = 0;
    if (has_right($member, 'cotisations')) {
        $n += count(cotisation_a_effacer());
    }
    if (is_admin($member)) {
        $n += (int) db_value('SELECT COUNT(*) FROM message_contact WHERE lu = 0');
    }
    return $n;
}
