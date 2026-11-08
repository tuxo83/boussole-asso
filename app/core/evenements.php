<?php
// Inscriptions équitables : on dépose un vœu (l'heure ne compte pas) ; à la clôture, priorité à ceux
// qui sont venus le moins souvent sur 12 mois, tirage reproductible pour les ex æquo, liste d'attente.
declare(strict_types=1);

const EVENEMENT_MODES = [
    'ouvert' => 'Ouvert à tous (tout le monde est pris)',
    'auto' => 'Places limitées, attribution automatique',
    'valide' => "Places limitées, liste validée par l'organisateur",
];
const INSCRIPTION_STATUTS = [
    'invite' => 'invité, réponse attendue', 'voeu' => 'vœu déposé', 'retenu' => 'inscrit',
    'attente' => "liste d'attente", 'desiste' => 'désisté', 'decline' => 'invitation déclinée', 'expire' => 'délai dépassé',
];
const EVENEMENT_GROUPES = [
    0 => "Invités de l'organisateur",
    1 => 'Moins souvent venus que la moyenne',
    2 => 'Dans la moyenne',
    3 => 'Plus du double de la moyenne',
];

function evenement_de(array $espace): array
{
    return db_one('SELECT * FROM evenement WHERE espace_id = ?', [$espace['id']])
        ?? ['espace_id' => $espace['id'], 'debut' => null, 'lieu' => '', 'mode' => 'ouvert', 'places' => null, 'cloture' => null, 'etat' => 'ouvert', 'helloasso_formulaire' => null,
            'reglement' => 'aucun', 'lien_paiement' => null, 'delai_jours' => 5, 'dernieres_jours' => 7, 'dernieres_annoncees' => 0];
}

function evenement_limite(array $ev): bool
{
    return $ev['mode'] !== 'ouvert' && (int) $ev['places'] > 0;
}

// Les vœux sont acceptés jusqu'à la clôture (et jamais après le début)
function evenement_accepte_voeux(array $ev): bool
{
    $now = date('Y-m-d H:i:s');
    return $ev['etat'] === 'ouvert' && ($ev['cloture'] === null || $now < $ev['cloture']) && ($ev['debut'] === null || $now < $ev['debut']);
}

function inscription_de(array $ev, array $member): ?array
{
    return db_one('SELECT * FROM inscription WHERE evenement_id = ? AND membre_id = ?', [$ev['espace_id'], $member['id']]);
}

// Participations sur 12 mois : une place obtenue à un événement passé à places limitées.
// Les événements ouverts à tous (assemblée générale…) ne comptent pas : personne n'y a pris la place d'un autre.
function participations(): array
{
    $rows = db_all('SELECT i.membre_id, COUNT(*) AS n FROM inscription i JOIN evenement e ON e.espace_id = i.evenement_id
        WHERE i.statut = "retenu" AND e.mode <> "ouvert" AND e.debut < NOW() AND e.debut >= NOW() - INTERVAL 12 MONTH GROUP BY i.membre_id');
    return array_column($rows, 'n', 'membre_id');
}

// Groupe calculé seul, sans réglage : 1 sous la moyenne (ou jamais venu), 2 dans la moyenne, 3 au-delà du double
function groupe(int $n, float $moyenne): int
{
    return match (true) {
        $n === 0 || $n < $moyenne => 1,
        $n > 2 * $moyenne => 3,
        default => 2,
    };
}

// Classement des vœux : groupe, puis participations, puis tirage reproductible (même résultat à chaque calcul)
function evenement_classer(array $ev): array
{
    $counts = participations();
    $actifs = max(1, (int) db_value('SELECT COUNT(*) FROM membre WHERE actif = 1'));
    $moyenne = array_sum($counts) / $actifs;
    $voeux = db_all('SELECT i.*, m.prenom, m.nom, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "voeu"', [$ev['espace_id']]);
    foreach ($voeux as &$v) {
        $v['participations'] = (int) ($counts[$v['membre_id']] ?? 0);
        $v['groupe'] = $v['invite'] ? 0 : groupe($v['participations'], $moyenne);
        $v['tirage'] = hash('sha256', 'boussole|' . $ev['espace_id'] . '|' . $v['membre_id']);
    }
    unset($v);
    usort($voeux, fn($a, $b) => [$a['groupe'], $a['participations'], $a['tirage']] <=> [$b['groupe'], $b['participations'], $b['tirage']]);
    foreach ($voeux as $i => $v) {
        db_query('UPDATE inscription SET groupe = ?, participations = ?, rang = ? WHERE evenement_id = ? AND membre_id = ?', [$v['groupe'], $v['participations'], $i + 1, $ev['espace_id'], $v['membre_id']]);
        $voeux[$i]['rang'] = $i + 1;
    }
    return $voeux;
}

// Clôture : « auto » attribue et prévient ; « valide » prépare une liste que l'organisateur ajuste
function evenement_cloturer(array $ev): void
{
    $cible = $ev['mode'] === 'valide' ? 'proposee' : 'attribuee';
    // Une seule clôture même si deux requêtes arrivent en même temps
    if (db_query('UPDATE evenement SET etat = ? WHERE espace_id = ? AND etat = "ouvert"', [$cible, $ev['espace_id']])->rowCount() !== 1) {
        return;
    }
    db_query('UPDATE inscription SET statut = "decline" WHERE evenement_id = ? AND statut = "invite"', [$ev['espace_id']]);
    $ev['etat'] = $cible;
    $classement = evenement_classer($ev);
    $places = evenement_limite($ev) ? (int) $ev['places'] : PHP_INT_MAX;
    if ($cible === 'proposee') {
        foreach (array_slice($classement, 0, $places) as $v) {
            db_query('UPDATE inscription SET propose = 1 WHERE evenement_id = ? AND membre_id = ?', [$ev['espace_id'], $v['membre_id']]);
        }
        foreach (evenement_organisateurs($ev) as $orga) {
            mail_send($orga['email'], 'Liste à valider : ' . evenement_titre($ev), "Les inscriptions sont closes. Une liste vous est proposée : ajustez-la puis validez-la.\n\n" . evenement_lien($ev));
        }
        journal($ev['espace_id'], null, 'Inscriptions closes : liste proposée', 'web');
        return;
    }
    evenement_valider($ev, array_column(array_slice($classement, 0, $places), 'membre_id'), null);
}

// Attribution définitive : les retenus sont inscrits, les autres en liste d'attente dans l'ordre du classement
function evenement_valider(array $ev, array $retenus, ?array $par): void
{
    $retenus = array_map('intval', $retenus);
    $rang = 0;
    foreach (db_all('SELECT i.*, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "voeu" ORDER BY i.rang IS NULL, i.rang, i.cree_le', [$ev['espace_id']]) as $v) {
        if (in_array((int) $v['membre_id'], $retenus, true)) {
            evenement_donner_place($ev, $v, evenement_exige($ev) ? 'Place réservée : ' : 'Inscription confirmée : ', 'Les places sont attribuées.');
            continue;
        }
        db_query('UPDATE inscription SET statut = "attente", rang = ? WHERE evenement_id = ? AND membre_id = ?', [++$rang, $ev['espace_id'], $v['membre_id']]);
        mail_send($v['email'], "Liste d'attente : " . evenement_titre($ev), "Il n'y avait pas assez de places. Vous êtes n°$rang sur la liste d'attente : vous serez prévenu si une place se libère.\n\n" . evenement_lien($ev));
    }
    db_query('UPDATE evenement SET etat = "attribuee" WHERE espace_id = ?', [$ev['espace_id']]);
    journal($ev['espace_id'], $par, 'Places attribuées (' . count($retenus) . ' inscrits)', $par === null ? 'web' : null);
}

// Une place libérée profite au premier de la liste d'attente, seulement s'il reste réellement une place
function evenement_promouvoir(array $ev): void
{
    if ($ev['etat'] !== 'attribuee' || !evenement_limite($ev)) {
        return;
    }
    // Dernières places : plus de tour par tour, la liste d'attente est prévenue en une fois
    if (evenement_exige($ev) && evenement_phase($ev) === 'dernieres') {
        evenement_annoncer_places($ev);
        return;
    }
    while ((int) db_value('SELECT COUNT(*) FROM inscription WHERE evenement_id = ? AND statut = "retenu"', [$ev['espace_id']]) < (int) $ev['places']) {
        $suivant = db_one('SELECT i.*, m.email FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "attente" ORDER BY i.rang LIMIT 1', [$ev['espace_id']]);
        if ($suivant === null) {
            return;
        }
        evenement_donner_place($ev, $suivant, 'Une place pour vous : ', "Une place s'est libérée.");
        journal($ev['espace_id'], null, "Place libérée : promotion depuis la liste d'attente", 'web');
    }
}

// Action d'un membre sur son inscription : voeu, retirer, desister, confirmer, decliner
function inscription_agir(array $ev, array $member, string $action): string
{
    $mine = inscription_de($ev, $member);
    $statut = $mine['statut'] ?? null;
    $id = [$ev['espace_id'], $member['id']];
    switch ($action) {
        case 'voeu':
            if (setting('inscription_cotisants', '1') === '1' && !espace_inscription_ouverte($member, db_one('SELECT * FROM espace WHERE id = ?', [$ev['espace_id']]) ?? ['visibilite' => 'equipe'])) {
                return 'Inscription réservée aux membres à jour de cotisation : réglez votre cotisation pour vous inscrire.';
            }
            if ($statut !== null && !in_array($statut, ['desiste', 'decline', 'expire'], true)) {
                return 'Vous êtes déjà sur la liste.';
            }
            if ($ev['debut'] !== null && date('Y-m-d H:i:s') >= $ev['debut']) {
                return "L'événement a commencé.";
            }
            // Ouvert à tous : inscrit tout de suite ; après la clôture : à la suite de la liste d'attente
            if ($ev['mode'] === 'ouvert') {
                $nouveau = 'retenu';
            } elseif (evenement_accepte_voeux($ev) || $ev['etat'] === 'proposee') {
                // Pendant la validation par l'organisateur, un vœu tardif apparaît, non coché, en fin de liste
                $nouveau = 'voeu';
            } elseif ($ev['etat'] === 'ouvert') {
                return 'Les inscriptions sont closes.';
            } else {
                $nouveau = 'attente';
            }
            // Événement jamais réglé par l'organisateur : sa fiche est créée avec les valeurs par défaut
            db_query('INSERT IGNORE INTO evenement (espace_id) VALUES (?)', [$ev['espace_id']]);
            db_query('REPLACE INTO inscription (evenement_id, membre_id, statut, rang) VALUES (?, ?, ?, ?)', [...$id, $nouveau,
                $nouveau === 'attente' ? 1 + (int) db_value('SELECT COALESCE(MAX(rang), 0) FROM inscription WHERE evenement_id = ? AND statut = "attente"', [$ev['espace_id']]) : null]);
            evenement_promouvoir($ev);
            return match ($nouveau) {
                'retenu' => 'Vous êtes inscrit.',
                'voeu' => 'Vœu enregistré. Les places seront attribuées à la clôture : l\'heure de dépôt ne compte pas.',
                default => "Vous êtes sur la liste d'attente.",
            };
        case 'retirer':
        case 'desister':
            if (!in_array($statut, ['voeu', 'retenu', 'attente'], true)) {
                return 'Rien à annuler.';
            }
            if ($statut === 'voeu' && !$mine['invite']) {
                db_query('DELETE FROM inscription WHERE evenement_id = ? AND membre_id = ?', $id);
            } else {
                db_query('UPDATE inscription SET statut = "desiste", rang = NULL WHERE evenement_id = ? AND membre_id = ?', $id);
            }
            if ($statut === 'retenu') {
                journal($ev['espace_id'], $member, 'Désistement : ' . membre_nom($member));
                evenement_promouvoir($ev);
            }
            return 'C\'est noté, merci de nous avoir prévenus.';
        case 'venue':
            if ($statut !== 'retenu' || $ev['reglement'] !== 'confirmation') {
                return 'Rien à confirmer.';
            }
            db_query('UPDATE inscription SET confirme_le = NOW(), echeance = NULL WHERE evenement_id = ? AND membre_id = ?', $id);
            return 'Merci, votre venue est confirmée.';
        case 'prendre':
            if ($statut !== 'attente' || $ev['reglement'] !== 'confirmation' || evenement_phase($ev) !== 'dernieres') {
                return 'Pas de place à prendre pour l\'instant.';
            }
            if (evenement_places_libres($ev) === 0) {
                return 'Désolé, la dernière place vient d\'être prise.';
            }
            db_query('UPDATE inscription SET statut = "retenu", rang = NULL, confirme_le = NOW(), echeance = NULL WHERE evenement_id = ? AND membre_id = ? AND statut = "attente"', $id);
            journal($ev['espace_id'], $member, 'Dernière place prise : ' . membre_nom($member));
            return 'La place est à vous !';
        case 'confirmer':
        case 'decliner':
            if ($statut !== 'invite' || !evenement_accepte_voeux($ev)) {
                return 'Cette invitation n\'attend plus de réponse.';
            }
            db_query('UPDATE inscription SET statut = ? WHERE evenement_id = ? AND membre_id = ?', [$action === 'confirmer' ? ($ev['mode'] === 'ouvert' ? 'retenu' : 'voeu') : 'decline', ...$id]);
            return $action === 'confirmer' ? 'Invitation acceptée : vous serez servi en priorité.' : 'Invitation déclinée.';
    }
    return 'Action inconnue.';
}

// Clôture des événements dont la date de clôture est passée (cron, et à l'affichage pour les hébergeurs sans cron)
function evenements_cloturer_echus(): int
{
    $echus = db_all('SELECT * FROM evenement WHERE etat = "ouvert" AND mode <> "ouvert" AND ((cloture IS NOT NULL AND cloture <= NOW()) OR (debut IS NOT NULL AND debut <= NOW()))');
    foreach ($echus as $ev) {
        evenement_cloturer($ev);
    }
    return count($echus);
}

function evenement_organisateurs(array $ev): array
{
    return db_all('SELECT m.* FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? AND em.niveau IN ("n1", "n2") AND m.actif = 1', [$ev['espace_id']]);
}

function evenement_titre(array $ev): string
{
    return (string) db_value('SELECT titre FROM espace WHERE id = ?', [$ev['espace_id']]);
}

function evenement_lien(array $ev): string
{
    return absolute_url('/espace/' . db_value('SELECT slug FROM espace WHERE id = ?', [$ev['espace_id']]));
}

function evenement_mes_inscriptions(array $member): array
{
    return db_all('SELECT s.titre, s.slug, e.debut, i.statut, i.rang FROM inscription i JOIN evenement e ON e.espace_id = i.evenement_id JOIN espace s ON s.id = e.espace_id
        WHERE i.membre_id = ? AND i.statut IN ("invite", "voeu", "retenu", "attente") AND (e.debut IS NULL OR e.debut >= CURDATE()) ORDER BY e.debut', [$member['id']]);
}

function evenement_date(?string $datetime): string
{
    return $datetime === null ? 'date à préciser' : date('d/m/Y \à H\hi', strtotime($datetime));
}

// Réglages d'un événement (formulaire et API) : renvoie la liste des erreurs, vide si tout est enregistré
function evenement_enregistrer(array $espace, array $member, array $in): array
{
    $ev = evenement_de($espace);
    $mode = (string) ($in['mode'] ?? 'ouvert');
    $places = (int) ($in['places'] ?? 0);
    $debut = evenement_lire_date((string) ($in['debut'] ?? ''));
    $cloture = evenement_lire_date((string) ($in['cloture'] ?? ''));
    $fin = array_key_exists('fin', $in) ? evenement_lire_date((string) $in['fin']) : ($ev['fin'] ?? null);
    $erreurs = array_values(array_filter([
        $fin !== null && $debut !== null && $fin <= $debut ? "L'heure de fin doit suivre le début." : null,
        !isset(EVENEMENT_MODES[$mode]) ? 'Mode inconnu.' : null,
        $mode !== 'ouvert' && $places < 1 ? 'Indiquez le nombre de places.' : null,
        $mode !== 'ouvert' && $cloture === null ? 'Indiquez la date de clôture des vœux.' : null,
        $cloture !== null && $debut !== null && $cloture > $debut ? "La clôture doit précéder l'événement." : null,
        $ev['etat'] !== 'ouvert' && $mode !== $ev['mode'] ? 'Les places sont déjà attribuées : le mode ne change plus.' : null,
    ]));
    if ($erreurs) {
        return $erreurs;
    }
    // Lien de paiement HelloAsso : son dernier segment est le nom du formulaire de billetterie (lecture automatique des paiements)
    $lien = trim((string) ($in['lien_paiement'] ?? ''));
    $lien = preg_match('#^https://[^\s"<>]+$#', $lien) ? mb_substr($lien, 0, 300) : null;
    $billetterie = $lien !== null && str_contains($lien, 'helloasso.com') ? strtolower(preg_replace('#^.*/#', '', rtrim(strtok($lien, '?'), '/'))) : null;
    $reglement = isset(EVENEMENT_REGLEMENTS[$in['reglement'] ?? '']) ? $in['reglement'] : 'aucun';
    db_query('INSERT INTO evenement (espace_id, debut, fin, lieu, mode, places, cloture, helloasso_formulaire, reglement, lien_paiement, delai_jours, dernieres_jours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE debut = VALUES(debut), fin = VALUES(fin), lieu = VALUES(lieu), mode = VALUES(mode), places = VALUES(places), cloture = VALUES(cloture), helloasso_formulaire = VALUES(helloasso_formulaire),
        reglement = VALUES(reglement), lien_paiement = VALUES(lien_paiement), delai_jours = VALUES(delai_jours), dernieres_jours = VALUES(dernieres_jours)',
        [$espace['id'], $debut, $debut !== null ? $fin : null, mb_substr(trim((string) ($in['lieu'] ?? '')), 0, 200), $mode, $places ?: null, $cloture, $billetterie ? mb_substr($billetterie, 0, 150) : null,
            $reglement, $lien, max(1, min(60, (int) ($in['delai_jours'] ?? 5))), max(0, min(90, (int) ($in['dernieres_jours'] ?? 7)))]);
    journal($espace['id'], $member, "Réglages de l'événement modifiés");
    // Plus de places après attribution : la liste d'attente en profite
    evenement_promouvoir(evenement_de($espace));
    return [];
}

function evenement_lire_date(string $value): ?string
{
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value) ?: DateTime::createFromFormat('Y-m-d H:i', $value);
    return $date === false ? null : $date->format('Y-m-d H:i:00');
}

// « Samedi 28 novembre 2026 · 9 h 30 – 12 h » (la fin est facultative ; un autre jour est écrit en entier)
function evenement_horaire(array $ev): string
{
    if (empty($ev['debut'])) {
        return 'Date à préciser';
    }
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $h = fn(int $t) => (int) date('G', $t) . ' h' . (date('i', $t) !== '00' ? ' ' . date('i', $t) : '');
    $jour = fn(int $t) => ucfirst($jours[(int) date('w', $t)]) . ' ' . (int) date('j', $t) . ' ' . $mois[(int) date('n', $t)] . ' ' . date('Y', $t);
    $d = strtotime($ev['debut']);
    $texte = $jour($d) . ' · ' . $h($d);
    if (!empty($ev['fin'])) {
        $f = strtotime($ev['fin']);
        $texte .= date('Y-m-d', $f) === date('Y-m-d', $d) ? ' – ' . $h($f) : ' – ' . lcfirst($jour($f)) . ' · ' . $h($f);
    }
    return $texte;
}

// Fichier d'agenda (.ics) d'un événement : sans aucun participant ; fin par défaut deux heures après le début
function evenement_ics(array $espace, array $ev): string
{
    $tz = new DateTimeZone('Europe/Paris');
    $utc = new DateTimeZone('UTC');
    $fmt = fn(string $d) => (new DateTimeImmutable($d, $tz))->setTimezone($utc)->format('Ymd\THis\Z');
    $fin = !empty($ev['fin']) ? $ev['fin'] : date('Y-m-d H:i:s', strtotime($ev['debut']) + 7200);
    $echap = fn(string $t) => str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $t);
    $hote = parse_url(absolute_url('/'), PHP_URL_HOST) ?: 'boussole';
    $lignes = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Boussole//Agenda//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
        'UID:evenement-' . (int) $espace['id'] . '@' . $hote, 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . $fmt($ev['debut']), 'DTEND:' . $fmt($fin),
        'SUMMARY:' . $echap($espace['titre']), 'URL:' . absolute_url('/espace/' . $espace['slug'])];
    if (trim((string) $ev['lieu']) !== '') {
        $lignes[] = 'LOCATION:' . $echap($ev['lieu']);
    }
    if (trim((string) $espace['description']) !== '') {
        $lignes[] = 'DESCRIPTION:' . $echap(mb_substr($espace['description'], 0, 1000));
    }
    $lignes[] = 'END:VEVENT';
    $lignes[] = 'END:VCALENDAR';
    // Lignes courtes (norme : 75 octets), repliées par un espace, sans couper un caractère accentué
    return implode("\r\n", array_map(fn($l) => implode("\r\n ", mb_str_split($l, 35)), $lignes)) . "\r\n";
}

// Lien « Ajouter à Google Agenda » (Android : le fichier .ics s'y ouvre mal)
function evenement_lien_google(array $espace, array $ev): string
{
    $tz = new DateTimeZone('Europe/Paris');
    $fmt = fn(string $d) => (new DateTimeImmutable($d, $tz))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $fin = !empty($ev['fin']) ? $ev['fin'] : date('Y-m-d H:i:s', strtotime($ev['debut']) + 7200);
    return 'https://calendar.google.com/calendar/render?' . http_build_query(['action' => 'TEMPLATE', 'text' => $espace['titre'],
        'dates' => $fmt($ev['debut']) . '/' . $fmt($fin), 'location' => (string) $ev['lieu'], 'details' => absolute_url('/espace/' . $espace['slug'])]);
}

// Itinéraire vers le lieu : une recherche OpenStreetMap (aucune carte intégrée, donc aucun service tiers chargé)
function evenement_lien_itineraire(string $lieu): string
{
    return 'https://www.openstreetmap.org/search?query=' . rawurlencode($lieu);
}
