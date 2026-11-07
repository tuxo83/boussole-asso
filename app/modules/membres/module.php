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
            db_query('UPDATE membre SET prenom = ?, nom = ?, type_membre_id = ?, actif = ? WHERE id = ?', [
                mb_substr(trim((string) ($_POST['prenom'] ?? '')), 0, 100),
                mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 100),
                membre_type_valide($_POST['type_membre_id'] ?? null),
                (int) $cible['id'] === (int) $gestionnaire['id'] ? 1 : (empty($_POST['actif']) ? 0 : 1),
                $cible['id'],
            ]);
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
            }
            journal(null, $gestionnaire, 'Membre modifié : ' . $cible['email']);
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
        ['POST', '/admin/types-membre', function () {
            $gestionnaire = require_right('admin');
            $libelle = mb_substr(trim((string) ($_POST['libelle'] ?? '')), 0, 100);
            if ($libelle !== '') {
                db_query('INSERT IGNORE INTO type_membre (libelle) VALUES (?)', [$libelle]);
                journal(null, $gestionnaire, "Type de membre ajouté : $libelle");
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
