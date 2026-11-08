<?php
// Gestion des membres (délégation « membres ») ; les droits de plateforme, eux, sont réservés à l'administrateur
declare(strict_types=1);

const DROITS = ['admin' => 'Administrateur', 'cotisations' => 'Cotisations', 'membres' => 'Membres', 'publications' => 'Publications'];

return [
    'ordre' => 92,
    'routes' => [
        ['GET', '/admin/membres', function () {
            $gestionnaire = require_right('membres');
            render('membres/liste', [
                'titre' => 'Membres',
                'gestionnaire' => $gestionnaire,
                'membres' => db_all('SELECT m.*, t.libelle AS type_libelle FROM membre m LEFT JOIN type_membre t ON t.id = m.type_membre_id WHERE m.en_attente = 0 ORDER BY m.actif DESC, m.nom, m.prenom, m.email'),
                'jamais' => (int) db_value('SELECT COUNT(*) FROM membre WHERE actif = 1 AND derniere_connexion IS NULL AND en_attente = 0'),
                'en_attente' => db_all('SELECT m.*, c.paye_le, c.periode FROM membre m LEFT JOIN cotisation c ON c.membre_id = m.id WHERE m.en_attente = 1 ORDER BY m.cree_le'),
                'types' => db_all('SELECT * FROM type_membre ORDER BY ordre, libelle'),
            ]);
        }],
        ['POST', '/admin/membres', function () {
            $gestionnaire = require_right('membres');
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('erreur', 'Adresse e-mail invalide.');
                redirect('/admin/membres');
            }
            if (db_one('SELECT id FROM membre WHERE email = ?', [$email]) !== null) {
                flash('erreur', 'Ce membre existe déjà.');
                redirect('/admin/membres');
            }
            db_query('INSERT INTO membre (email, prenom, nom, type_membre_id) VALUES (?, ?, ?, ?)', [
                $email,
                mb_substr(trim((string) ($_POST['prenom'] ?? '')), 0, 100),
                mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 100),
                membre_type_valide($_POST['type_membre_id'] ?? null),
            ]);
            journal(null, $gestionnaire, "Membre ajouté : $email");
            flash('succes', "Membre ajouté : il pourra se connecter avec $email.");
            redirect('/admin/membres');
        }],
        ['POST', '/admin/membres/{id}', function (array $p) {
            $gestionnaire = require_right('membres');
            $cible = db_one('SELECT * FROM membre WHERE id = ?', [(int) $p['id']]);
            if ($cible === null) {
                redirect('/admin/membres');
            }
            // Changer l'e-mail (identifiant de connexion) : adresse valide et pas déjà prise
            $email = mb_strtolower(trim((string) ($_POST['email'] ?? $cible['email'])));
            if ($email !== $cible['email']) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('erreur', 'Adresse e-mail invalide : rien n\'a été enregistré.');
                    redirect('/admin/membres');
                }
                if (db_one('SELECT id FROM membre WHERE email = ? AND id <> ?', [$email, $cible['id']]) !== null) {
                    flash('erreur', "L'adresse $email est déjà celle d'un autre membre : rien n'a été enregistré.");
                    redirect('/admin/membres');
                }
                db_query('UPDATE membre SET email = ? WHERE id = ?', [$email, $cible['id']]);
                membre_deconnecter_partout((int) $cible['id'], (int) $cible['id'] === (int) $gestionnaire['id']);
                journal_compte((int) $cible['id'], $gestionnaire, 'E-mail changé : ' . $cible['email'] . ' → ' . $email);
            }
            db_query('UPDATE membre SET prenom = ?, nom = ?, type_membre_id = ?, actif = ? WHERE id = ?', [
                mb_substr(trim((string) ($_POST['prenom'] ?? '')), 0, 100),
                mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 100),
                membre_type_valide($_POST['type_membre_id'] ?? null),
                (int) $cible['id'] === (int) $gestionnaire['id'] ? 1 : (empty($_POST['actif']) ? 0 : 1),
                $cible['id'],
            ]);
            // Compte de démonstration (administrateur) : mot de passe choisi ici, ni provisoire ni modifiable par ses utilisateurs
            if (is_admin($gestionnaire) && (int) $cible['id'] !== (int) $gestionnaire['id']) {
                $demo = empty($_POST['compte_demo']) ? 0 : 1;
                if ($demo !== (int) ($cible['compte_demo'] ?? 0)) {
                    db_query('UPDATE membre SET compte_demo = ? WHERE id = ?', [$demo, $cible['id']]);
                    journal_compte((int) $cible['id'], $gestionnaire, $demo ? 'Marqué compte de démonstration' : 'N\'est plus un compte de démonstration');
                }
                $mdp_demo = (string) ($_POST['mot_de_passe_demo'] ?? '');
                if ($demo && $mdp_demo !== '') {
                    if (mb_strlen($mdp_demo) < 8) {
                        flash('erreur', 'Mot de passe de démonstration trop court (8 caractères au moins) : il n\'a pas été changé.');
                    } else {
                        db_query('UPDATE membre SET mot_de_passe = ?, mot_de_passe_provisoire = 0 WHERE id = ?', [password_hash($mdp_demo, PASSWORD_DEFAULT), $cible['id']]);
                        membre_deconnecter_partout((int) $cible['id']);
                        journal_compte((int) $cible['id'], $gestionnaire, 'Mot de passe de démonstration fixé par un administrateur');
                    }
                }
            }
            // Seul l'administrateur attribue des droits ; il ne peut pas retirer le dernier administrateur
            if (is_admin($gestionnaire)) {
                $droits = array_values(array_intersect(array_keys(DROITS), (array) ($_POST['droits'] ?? [])));
                $admins = (int) db_one("SELECT COUNT(*) AS n FROM droit_plateforme WHERE droit = 'admin'")['n'];
                if (!in_array('admin', $droits, true) && in_array('admin', member_rights($cible['id']), true) && $admins <= 1) {
                    $droits[] = 'admin';
                    flash('info', 'Il doit rester au moins un administrateur.');
                }
                db_query('DELETE FROM droit_plateforme WHERE membre_id = ?', [$cible['id']]);
                foreach ($droits as $droit) {
                    db_query('INSERT INTO droit_plateforme (membre_id, droit) VALUES (?, ?)', [$cible['id'], $droit]);
                }
            }
            if (empty($_POST['actif']) && (int) $cible['id'] !== (int) $gestionnaire['id']) {
                db_query('DELETE FROM droit_plateforme WHERE membre_id = ?', [$cible['id']]);
                membre_deconnecter_partout((int) $cible['id']); // désactivé : ses appareils retenus ne rouvriront rien, même s'il est réactivé
            }
            journal_compte((int) $cible['id'], $gestionnaire, 'Fiche modifiée (nom, type, droits, activation)');
            flash('succes', 'Membre enregistré.');
            redirect('/admin/membres');
        }],
        // Adhésion reçue d'un inconnu : valider (accès ouvert, e-mail de bienvenue) ou refuser (compte supprimé)
        ['POST', '/admin/membres/{id}/validation', function (array $p) {
            $gestionnaire = require_right('membres');
            $member = db_one('SELECT * FROM membre WHERE id = ? AND en_attente = 1', [(int) $p['id']]);
            if ($member === null) {
                redirect('/admin/membres');
            }
            if (($_POST['decision'] ?? '') === 'valider') {
                db_query('UPDATE membre SET actif = 1, en_attente = 0 WHERE id = ?', [$member['id']]);
                $site = (string) setting('nom_association', 'Boussole');
                mail_send($member['email'], "Bienvenue : $site", "Bonjour,\n\nVotre adhésion est validée. Pour accéder à l'espace membres, saisissez votre adresse e-mail sur la page de connexion : vous recevrez un lien.\n\n" . absolute_url('/connexion'));
                journal(null, $gestionnaire, 'Adhésion validée : ' . membre_nom($member));
                flash('succes', membre_nom($member) . ' est maintenant membre.');
            } elseif (($_POST['decision'] ?? '') === 'refuser') {
                db_query('DELETE FROM membre WHERE id = ?', [$member['id']]);
                journal(null, $gestionnaire, 'Adhésion refusée (compte supprimé)');
                flash('info', 'Adhésion refusée : pensez au remboursement éventuel sur HelloAsso.');
            }
            redirect('/admin/membres');
        }],
        // Invitation de bienvenue : un membre, ou tous ceux qui ne se sont jamais connectés
        ['POST', '/admin/membres/inviter', function () {
            $gestionnaire = require_right('membres');
            $id = (int) ($_POST['membre_id'] ?? 0);
            $liste = $id > 0
                ? db_all('SELECT * FROM membre WHERE id = ? AND actif = 1', [$id])
                : db_all('SELECT * FROM membre WHERE actif = 1 AND derniere_connexion IS NULL AND en_attente = 0');
            foreach ($liste as $member) {
                membre_inviter($member, $gestionnaire);
            }
            flash('succes', count($liste) . ' invitation(s) envoyée(s).');
            redirect('/admin/membres');
        }],
        ['POST', '/admin/types-membre', function () {
            $gestionnaire = require_right('admin');
            $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 100);
            if ($libelle !== '') {
                db_query('INSERT IGNORE INTO type_membre (libelle) VALUES (?)', [$libelle]);
                journal(null, $gestionnaire, "Type de membre ajouté : $libelle");
            }
            redirect('/admin/membres');
        }],
        // Effacer un membre (administrateur) : compte, cotisations, inscriptions et clés disparaissent ; son nom est retiré du journal
        ['POST', '/admin/membres/{id}/effacer', function (array $p) {
            $gestionnaire = require_right('admin');
            $cible = db_one('SELECT * FROM membre WHERE id = ?', [(int) $p['id']]);
            if ($cible === null) {
                redirect('/admin/membres');
            }
            if ((int) $cible['id'] === (int) $gestionnaire['id']) {
                flash('erreur', 'Vous ne pouvez pas effacer votre propre compte.');
                redirect('/admin/membres');
            }
            $nom = membre_nom($cible);
            membre_effacer($cible, $gestionnaire, "à la demande de l'administrateur");
            flash('succes', "Membre « $nom » effacé.");
            redirect('/admin/membres');
        }],
        // Mot de passe provisoire (administrateur) : tiré au hasard, affiché une seule fois, à changer à la connexion suivante
        ['POST', '/admin/membres/{id}/mot-de-passe-provisoire', function (array $p) {
            $gestionnaire = require_right('admin');
            $cible = db_one('SELECT * FROM membre WHERE id = ?', [(int) $p['id']]);
            if ($cible === null || (int) $cible['id'] === (int) $gestionnaire['id']) {
                flash('erreur', 'Pour votre propre compte, utilisez « Mon compte ».');
                redirect('/admin/membres');
            }
            if (!empty($cible['compte_demo'])) {
                flash('erreur', 'Compte de démonstration : fixez son mot de passe dans sa fiche.');
                redirect('/admin/membres');
            }
            $provisoire = mot_de_passe_provisoire();
            db_query('UPDATE membre SET mot_de_passe = ?, mot_de_passe_provisoire = 1 WHERE id = ?', [password_hash($provisoire, PASSWORD_DEFAULT), $cible['id']]);
            membre_deconnecter_partout((int) $cible['id']);
            journal_compte((int) $cible['id'], $gestionnaire, 'Mot de passe provisoire donné par un administrateur');
            flash('info', 'Mot de passe provisoire de ' . membre_nom($cible) . ' (' . $cible['email'] . ') : ' . $provisoire
                . ' — notez-le maintenant, il ne sera plus affiché. Il devra en choisir un autre à sa prochaine connexion.');
            redirect('/admin/membres');
        }],
        // Historique d'un compte (administrateur) : connexions, mot de passe, clés, modifications
        ['GET', '/admin/membres/{id}/historique', function (array $p) {
            require_right('admin');
            $cible = db_one('SELECT * FROM membre WHERE id = ?', [(int) $p['id']]);
            if ($cible === null) {
                redirect('/admin/membres');
            }
            render('membres/historique', ['titre' => 'Historique : ' . membre_nom($cible), 'cible' => $cible, 'historique' => historique_compte((int) $cible['id'], 200)]);
        }],
        // Retirer un type : les membres qui l'avaient restent, sans type
        ['POST', '/admin/types-membre/{id}/supprimer', function (array $p) {
            $gestionnaire = require_right('admin');
            $type = db_one('SELECT * FROM type_membre WHERE id = ?', [(int) $p['id']]);
            if ($type !== null) {
                db_query('DELETE FROM type_membre WHERE id = ?', [$type['id']]);
                journal(null, $gestionnaire, 'Type de membre retiré : ' . $type['libelle']);
                flash('succes', 'Type « ' . $type['libelle'] . ' » retiré.');
            }
            redirect('/admin/membres');
        }],
    ],
];

function membre_type_valide(mixed $id): ?int
{
    $id = (int) $id;
    return $id > 0 && db_one('SELECT id FROM type_membre WHERE id = ?', [$id]) !== null ? $id : null;
}

// 3 groupes de 4 caractères sans ambiguïté (pas de 0/O, 1/l/I) : facile à dicter, 59 bits environ
function mot_de_passe_provisoire(): string
{
    $alphabet = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRTUVWXY2345679';
    $groupes = [];
    for ($g = 0; $g < 3; $g++) {
        $groupe = '';
        for ($i = 0; $i < 4; $i++) {
            $groupe .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $groupes[] = $groupe;
    }
    return implode('-', $groupes);
}
