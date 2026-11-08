<?php
// Événements : réglages, vœux, invitations, clôture, validation de la liste, désistements
declare(strict_types=1);

return [
    'ordre' => 35,
    'routes' => [
        // Lien d'une carte « réservé aux membres » vue par un visiteur : connexion, puis retour sur l'événement
        // (le numéro ne dit rien de l'événement, contrairement à son adresse)
        ['GET', '/evenement/{id}', function (array $p) {
            require_member();
            $espace = db_one('SELECT * FROM espace WHERE id = ? AND type = "evenement"', [(int) $p['id']]);
            redirect($espace !== null ? '/espace/' . $espace['slug'] : '/');
        }],
        ['POST', '/espace/{slug}/evenement', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $erreurs = evenement_enregistrer($espace, $member, $_POST);
            if ($erreurs) {
                flash('erreur', implode(' ', $erreurs));
                redirect('/espace/' . $espace['slug'] . '/gerer#reglages');
            }
            flash('succes', 'Réglages enregistrés.');
            redirect('/espace/' . $espace['slug'] . '/gerer#reglages');
        }],
        ['POST', '/espace/{slug}/inscription', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'voir');
            flash('info', inscription_agir(evenement_de($espace), $member, (string) ($_POST['action'] ?? '')));
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/inviter', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            $invite = db_one('SELECT * FROM membre WHERE email = ? AND actif = 1', [trim((string) ($_POST['email'] ?? ''))]);
            if ($invite === null || !evenement_accepte_voeux($ev) || $ev['mode'] === 'ouvert') {
                flash('erreur', $invite === null ? 'Aucun membre actif avec cet e-mail.' : 'Les invitations prioritaires se font avant la clôture, pour un événement à places limitées.');
                redirect('/espace/' . $espace['slug']);
            }
            $deja = inscription_de($ev, $invite);
            // Déjà candidat : il devient prioritaire sans avoir à confirmer
            db_query('REPLACE INTO inscription (evenement_id, membre_id, statut, invite) VALUES (?, ?, ?, 1)', [$espace['id'], $invite['id'], ($deja['statut'] ?? '') === 'voeu' ? 'voeu' : 'invite']);
            mail_send($invite['email'], 'Invitation : ' . $espace['titre'], "Vous êtes invité en priorité. Confirmez ou déclinez sur la page de l'événement avant la clôture.\n\n" . absolute_url('/espace/' . $espace['slug']));
            journal($espace['id'], $member, 'Invitation prioritaire : ' . membre_nom($invite));
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/cloturer', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            if ($ev['mode'] !== 'ouvert' && $ev['etat'] === 'ouvert') {
                db_query('UPDATE evenement SET cloture = NOW() WHERE espace_id = ?', [$espace['id']]);
                journal($espace['id'], $member, 'Clôture anticipée des vœux');
                evenement_cloturer(evenement_de($espace));
            }
            redirect('/espace/' . $espace['slug']);
        }],
        // Paiement reçu autrement (espèces, chèque, ou sans connexion HelloAsso) : l'organisateur le note
        ['POST', '/espace/{slug}/paye', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $inscrit = db_one('SELECT m.* FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.membre_id = ? AND i.paye_le IS NULL', [$espace['id'], (int) ($_POST['membre_id'] ?? 0)]);
            if ($inscrit !== null) {
                db_query('UPDATE inscription SET paye_le = CURDATE() WHERE evenement_id = ? AND membre_id = ?', [$espace['id'], $inscrit['id']]);
                journal($espace['id'], $member, 'Paiement noté : ' . membre_nom($inscrit));
                evenement_regler(evenement_de($espace));
            }
            redirect('/espace/' . $espace['slug']);
        }],
        // Fichier d'agenda (.ics) : mêmes droits que la fiche ; l'événement seul, jamais les participants
        ['GET', '/espace/{slug}/agenda.ics', function (array $p) {
            $espace = espace_par_slug($p['slug']);
            if ($espace === null || $espace['type'] !== 'evenement' || !espace_peut_voir(current_member(), $espace)) {
                http_response_code(404);
                render('erreur', ['titre' => 'Page introuvable']);
                return;
            }
            $ev = evenement_de($espace);
            if (empty($ev['debut'])) {
                redirect('/espace/' . $espace['slug']);
            }
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $espace['slug'] . '.ics"');
            echo evenement_ics($espace, $ev);
        }],
        // Feuille d'émargement imprimable (qui gère l'événement)
        ['GET', '/espace/{slug}/emargement', function (array $p) {
            [$espace] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            $inscrits = db_all('SELECT m.prenom, m.nom, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "retenu" ORDER BY m.nom, m.prenom, m.email', [$espace['id']]);
            render('evenements/emargement', ['titre' => 'Émargement · ' . $espace['titre'], 'espace' => $espace, 'ev' => $ev, 'inscrits' => $inscrits]);
        }],
        // Pointage facultatif : présent, absent, ou rien (sans effet sur les priorités)
        ['POST', '/espace/{slug}/presence', function (array $p) {
            [$espace] = evenement_action($p['slug'], 'gerer');
            $present = match ($_POST['present'] ?? '') { '1' => 1, '0' => 0, default => null };
            db_query('UPDATE inscription SET present = ? WHERE evenement_id = ? AND membre_id = ? AND statut = "retenu"', [$present, $espace['id'], (int) ($_POST['membre_id'] ?? 0)]);
            // Pointage au téléphone : réponse courte, sans recharger la page entre deux pointages
            if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
                header('Content-Type: application/json');
                echo json_encode(['present' => $present, 'presents' => (int) db_value('SELECT COUNT(*) FROM inscription WHERE evenement_id = ? AND statut = "retenu" AND present = 1', [$espace['id']])]);
                return;
            }
            redirect('/espace/' . $espace['slug'] . '#pointage');
        }],
        // L'organisateur annule une inscription : la place revient au suivant de la liste d'attente
        ['POST', '/espace/{slug}/annuler', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            $inscrit = db_one('SELECT m.* FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.membre_id = ? AND i.statut = "retenu"', [$espace['id'], (int) ($_POST['membre_id'] ?? 0)]);
            if ($inscrit !== null) {
                db_query('UPDATE inscription SET statut = "desiste" WHERE evenement_id = ? AND membre_id = ?', [$espace['id'], $inscrit['id']]);
                mail_send($inscrit['email'], 'Inscription annulée : ' . $espace['titre'], "L'organisateur a annulé votre inscription. Pour toute question, répondez-lui directement.\n\n" . absolute_url('/espace/' . $espace['slug']));
                journal($espace['id'], $member, 'Inscription annulée par l\'organisateur : ' . membre_nom($inscrit));
                evenement_promouvoir($ev);
            }
            redirect('/espace/' . $espace['slug']);
        }],
        ['POST', '/espace/{slug}/valider', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            if ($ev['etat'] === 'proposee') {
                evenement_valider($ev, (array) ($_POST['retenus'] ?? []), $member);
                flash('succes', 'Liste validée : les participants sont prévenus.');
            }
            redirect('/espace/' . $espace['slug']);
        }],
    ],
];

function evenement_action(string $slug, string $droit): array
{
    $member = require_member();
    $espace = espace_par_slug($slug);
    $ok = $espace !== null && $espace['type'] === 'evenement'
        && ($droit === 'gerer' ? espace_peut_gerer($member, $espace) : espace_peut_voir($member, $espace));
    if (!$ok) {
        http_response_code(403);
        render('erreur', ['titre' => 'Action non autorisée']);
        exit;
    }
    return [$espace, $member];
}


// Données de la partie « inscriptions » d'une fiche d'événement
function evenement_vue(array $espace, ?array $member, bool $vue_orga = true): array
{
    evenements_cloturer_echus();
    evenement_regler(evenement_de($espace));
    $ev = evenement_de($espace);
    $gere = $vue_orga && espace_peut_gerer($member, $espace);
    return [
        'espace' => $espace,
        'ev' => $ev,
        'membre' => $member,
        'mien' => $member !== null ? inscription_de($ev, $member) : null,
        'gere' => $gere,
        'inscrits' => (int) db_value('SELECT COUNT(*) FROM inscription WHERE evenement_id = ? AND statut = "retenu"', [$espace['id']]),
        'liste' => $gere ? db_all('SELECT i.*, m.prenom, m.nom, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ?
            ORDER BY FIELD(i.statut, "retenu", "voeu", "invite", "attente", "desiste", "decline"), i.rang IS NULL, i.rang, m.nom', [$espace['id']]) : [],
    ];
}
