<?php
// Événement payant (ou à confirmer), en phases nommées (à ne pas confondre avec les groupes numérotés) :
//   Vœux → Paiement (ou Confirmation) : chaque personne retenue a N jours ; sinon la place passe au suivant
//   → Dernières places : à J-X, les places libres vont au premier qui paie (ou confirme) → Événement
declare(strict_types=1);

const EVENEMENT_REGLEMENTS = [
    'aucun' => 'Rien à faire : la place est acquise dès l\'attribution',
    'paiement' => 'Paiement (HelloAsso) dans un délai, sinon la place passe au suivant',
    'confirmation' => 'Confirmation de venue dans un délai, sinon la place passe au suivant',
];

function evenement_exige(array $ev): bool
{
    return evenement_limite($ev) && ($ev['reglement'] ?? 'aucun') !== 'aucun';
}

function inscription_reglee(array $i, array $ev): bool
{
    return ($ev['reglement'] ?? 'aucun') === 'paiement' ? $i['paye_le'] !== null : (($ev['reglement'] ?? 'aucun') === 'aucun' || $i['confirme_le'] !== null);
}

function evenement_debut_dernieres(array $ev): ?string
{
    return $ev['debut'] === null ? null : date('Y-m-d H:i:s', strtotime($ev['debut']) - (int) $ev['dernieres_jours'] * 86400);
}

// Phase en cours : voeux, validation, reglement, dernieres, attribuee (sans règlement), termine
function evenement_phase(array $ev): string
{
    $now = date('Y-m-d H:i:s');
    if ($ev['debut'] !== null && $now >= $ev['debut']) {
        return 'termine';
    }
    if ($ev['etat'] === 'ouvert') {
        return 'voeux';
    }
    if ($ev['etat'] === 'proposee') {
        return 'validation';
    }
    if (!evenement_exige($ev)) {
        return 'attribuee';
    }
    $dernieres = evenement_debut_dernieres($ev);
    return $dernieres !== null && $now >= $dernieres ? 'dernieres' : 'reglement';
}

function evenement_places_libres(array $ev): int
{
    return max(0, (int) $ev['places'] - (int) db_value('SELECT COUNT(*) FROM inscription WHERE evenement_id = ? AND statut = "retenu"', [$ev['espace_id']]));
}

// Donne une place : avec règlement, une échéance (N jours, jamais après le début) et le lien pour payer ou confirmer
function evenement_donner_place(array $ev, array $i, string $sujet, string $intro): void
{
    $exige = evenement_exige($ev) && !inscription_reglee($i, $ev);
    $echeance = null;
    if ($exige) {
        $echeance = date('Y-m-d H:i:00', min(time() + (int) $ev['delai_jours'] * 86400, $ev['debut'] !== null ? strtotime($ev['debut']) : PHP_INT_MAX));
    }
    db_query('UPDATE inscription SET statut = "retenu", rang = NULL, echeance = ?, rappel_echeance = 0 WHERE evenement_id = ? AND membre_id = ?', [$echeance, $ev['espace_id'], $i['membre_id']]);
    $quand = $echeance !== null ? evenement_date($echeance) : '';
    $texte = match (true) {
        !$exige => "$intro Votre place est confirmée. Si vous ne pouvez plus venir, libérez-la sur la page de l'événement.",
        $ev['reglement'] === 'paiement' => "$intro Une place vous est réservée. Pour la garder, payez avant le $quand"
            . ($ev['lien_paiement'] ? ' : ' . $ev['lien_paiement'] : ' (lien sur la page de l\'événement)') . ".\nSans paiement à cette date, elle passera à la personne suivante. Un rappel automatique vous sera envoyé la veille.",
        default => "$intro Une place vous est réservée. Pour la garder, confirmez votre venue sur la page de l'événement avant le $quand.\nSans confirmation à cette date, elle passera à la personne suivante. Un rappel automatique vous sera envoyé la veille.",
    };
    mail_send($i['email'], $sujet . evenement_titre($ev), $texte . "\n\n" . evenement_lien($ev));
}

// Dernières places : prévenir toute la liste d'attente qu'il reste des places (premier qui paie ou confirme)
function evenement_annoncer_places(array $ev): void
{
    $libres = evenement_places_libres($ev);
    if ($libres === 0) {
        return;
    }
    $action = $ev['reglement'] === 'paiement' ? 'payez' . ($ev['lien_paiement'] ? ' (' . $ev['lien_paiement'] . ')' : '') : 'cliquez sur « Je prends la place » sur la page de l\'événement';
    foreach (db_all('SELECT m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "attente" AND m.actif = 1', [$ev['espace_id']]) as $m) {
        mail_send($m['email'], "Dernières places : " . evenement_titre($ev), "Il reste $libres place(s). Elles vont maintenant aux premières personnes de la liste d'attente qui se manifestent : $action.\n\n" . evenement_lien($ev));
    }
    db_query('UPDATE evenement SET dernieres_annoncees = 1 WHERE espace_id = ?', [$ev['espace_id']]);
    journal($ev['espace_id'], null, "Dernières places annoncées à la liste d'attente ($libres)", 'web');
}

// Règles appliquées régulièrement (tâches planifiées, affichage, après une lecture HelloAsso)
function evenement_regler(array $ev): void
{
    if (!evenement_exige($ev) || $ev['etat'] !== 'attribuee') {
        return;
    }
    // Échéances dépassées : la place est perdue
    foreach (db_all('SELECT i.*, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "retenu" AND i.echeance IS NOT NULL AND i.echeance < NOW()', [$ev['espace_id']]) as $i) {
        if (inscription_reglee($i, $ev)) {
            db_query('UPDATE inscription SET echeance = NULL WHERE evenement_id = ? AND membre_id = ?', [$ev['espace_id'], $i['membre_id']]);
            continue;
        }
        db_query('UPDATE inscription SET statut = "expire", echeance = NULL WHERE evenement_id = ? AND membre_id = ?', [$ev['espace_id'], $i['membre_id']]);
        mail_send($i['email'], 'Place libérée : ' . evenement_titre($ev), "Le délai pour " . ($ev['reglement'] === 'paiement' ? 'payer' : 'confirmer') . " est passé : votre place est allée à la personne suivante.\n\n" . evenement_lien($ev));
        journal($ev['espace_id'], null, 'Délai dépassé, place libérée : ' . membre_nom(db_one('SELECT * FROM membre WHERE id = ?', [$i['membre_id']]) ?? []), 'web');
    }
    // Paiement ou confirmation reçus : plus d'échéance
    foreach (db_all('SELECT * FROM inscription WHERE evenement_id = ? AND statut = "retenu" AND echeance IS NOT NULL', [$ev['espace_id']]) as $i) {
        if (inscription_reglee($i, $ev)) {
            db_query('UPDATE inscription SET echeance = NULL WHERE evenement_id = ? AND membre_id = ?', [$ev['espace_id'], $i['membre_id']]);
        }
    }
    if (evenement_phase($ev) === 'dernieres') {
        // Premier qui paie (ou confirme), premier servi, parmi la liste d'attente
        $regles = db_all('SELECT i.*, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "attente" AND '
            . ($ev['reglement'] === 'paiement' ? 'i.paye_le IS NOT NULL ORDER BY i.paye_le, i.rang' : 'i.confirme_le IS NOT NULL ORDER BY i.confirme_le, i.rang'), [$ev['espace_id']]);
        foreach ($regles as $i) {
            if (evenement_places_libres($ev) === 0) {
                break;
            }
            evenement_donner_place($ev, $i, 'Votre place : ', 'Vous avez été parmi les premiers :');
        }
        if (!(int) $ev['dernieres_annoncees']) {
            evenement_annoncer_places($ev);
        }
        return;
    }
    evenement_promouvoir($ev);
}

function evenements_regler_tous(): int
{
    $liste = db_all('SELECT * FROM evenement WHERE etat = "attribuee" AND reglement <> "aucun" AND (debut IS NULL OR debut > NOW())');
    foreach ($liste as $ev) {
        evenement_regler($ev);
    }
    return count($liste);
}

// Rappel la veille de l'échéance (une seule fois)
function evenements_rappels_echeance(): int
{
    $n = 0;
    foreach (db_all('SELECT i.*, m.email, e.reglement, e.lien_paiement, e.espace_id AS ev_id FROM inscription i JOIN membre m ON m.id = i.membre_id JOIN evenement e ON e.espace_id = i.evenement_id
        WHERE i.statut = "retenu" AND i.echeance IS NOT NULL AND i.rappel_echeance = 0 AND i.echeance > NOW() AND i.echeance <= NOW() + INTERVAL 1 DAY') as $i) {
        $ev = db_one('SELECT * FROM evenement WHERE espace_id = ?', [$i['ev_id']]);
        if (inscription_reglee($i, $ev)) {
            continue;
        }
        mail_send($i['email'], 'Rappel : ' . ($ev['reglement'] === 'paiement' ? 'paiement' : 'confirmation') . ' avant le ' . evenement_date($i['echeance']) . ' (' . evenement_titre($ev) . ')',
            'Votre place vous attend jusqu\'au ' . evenement_date($i['echeance']) . ' : ' . ($ev['reglement'] === 'paiement' ? 'payez' . ($ev['lien_paiement'] ? ' sur ' . $ev['lien_paiement'] : '') : 'confirmez votre venue sur la page de l\'événement')
            . ". Passé ce délai, elle ira à la personne suivante.\n\n" . evenement_lien($ev));
        db_query('UPDATE inscription SET rappel_echeance = 1 WHERE evenement_id = ? AND membre_id = ?', [$i['evenement_id'], $i['membre_id']]);
        $n++;
    }
    return $n;
}

// « encore 3 jours », « encore 5 heures », « encore 12 minutes »
function duree_restante(string $jusqua): string
{
    $s = strtotime($jusqua) - time();
    return match (true) {
        $s <= 0 => 'terminé',
        $s >= 2 * 86400 => 'encore ' . intdiv($s, 86400) . ' jours',
        $s >= 7200 => 'encore ' . intdiv($s, 3600) . ' heures',
        default => 'encore ' . max(1, intdiv($s, 60)) . ' minutes',
    };
}
