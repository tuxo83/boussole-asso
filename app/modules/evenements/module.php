<?php
// Événements : réglages, vœux, invitations, clôture, validation de la liste, désistements
declare(strict_types=1);

return [
    'ordre' => 35,
    'routes' => [
        ['POST', '/espace/{slug}/evenement', function (array $p) {
            [$espace, $member] = evenement_action($p['slug'], 'gerer');
            $ev = evenement_de($espace);
            $mode = (string) ($_POST['mode'] ?? 'ouvert');
            $places = (int) ($_POST['places'] ?? 0);
            $debut = evenement_lire_date((string) ($_POST['debut'] ?? ''));
            $cloture = evenement_lire_date((string) ($_POST['cloture'] ?? ''));
            $erreurs = array_filter([
                !isset(EVENEMENT_MODES[$mode]) ? 'Mode inconnu.' : null,
                $mode !== 'ouvert' && $places < 1 ? 'Indiquez le nombre de places.' : null,
                $mode !== 'ouvert' && $cloture === null ? 'Indiquez la date de clôture des vœux.' : null,
                $cloture !== null && $debut !== null && $cloture > $debut ? "La clôture doit précéder l'événement." : null,
                $ev['etat'] !== 'ouvert' && $mode !== $ev['mode'] ? "Les places sont déjà attribuées : le mode ne change plus." : null,
            ]);
            if ($erreurs) {
                flash('erreur', implode(' ', $erreurs));
                redirect('/espace/' . $espace['slug']);
            }
            db_query('INSERT INTO evenement (espace_id, debut, lieu, mode, places, cloture) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE debut = VALUES(debut), lieu = VALUES(lieu), mode = VALUES(mode), places = VALUES(places), cloture = VALUES(cloture)',
                [$espace['id'], $debut, mb_substr(trim((string) ($_POST['lieu'] ?? '')), 0, 200), $mode, $places ?: null, $cloture]);
            journal($espace['id'], $member, "Réglages de l'événement modifiés");
            // Plus de places après attribution : la liste d'attente en profite
            evenement_promouvoir(evenement_de($espace));
            flash('succes', 'Réglages enregistrés.');
            redirect('/espace/' . $espace['slug']);
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

function evenement_lire_date(string $value): ?string
{
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date === false ? null : $date->format('Y-m-d H:i:00');
}

// Données de la partie « inscriptions » d'une fiche d'événement
function evenement_vue(array $espace, ?array $member): array
{
    evenements_cloturer_echus();
    $ev = evenement_de($espace);
    $gere = espace_peut_gerer($member, $espace);
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
