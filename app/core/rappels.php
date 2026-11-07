<?php
// Rappels par e-mail (cron) : dates clés des espaces, et participants des événements.
// Délais réglables, en jours avant l'échéance (ex. « 14, 3 »). Un seul rappel par délai, jamais en double :
// si le cron a manqué un passage, seul le délai le plus proche est envoyé.
declare(strict_types=1);

const RAPPEL_DELAIS_DEFAUT = [
    'rappel_jalons' => '14, 3',
    'rappel_jalons_evenements' => '60, 14, 3',
    'rappel_participants' => '7, 1',
];

// « 14, 3 » → [14, 3] (0 à 365 jours, du plus grand au plus petit)
function rappel_delais(string $cle): array
{
    $delais = array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) setting($cle, RAPPEL_DELAIS_DEFAUT[$cle]))), fn($d) => $d >= 0 && $d <= 365));
    rsort($delais);
    return $delais;
}

// Délai à envoyer maintenant pour une échéance donnée (null : rien), et marquage des délais dépassés
function rappel_a_envoyer(string $genre, int $objet, string $echeance, array $delais): ?int
{
    $reste = (int) floor((strtotime($echeance) - strtotime('today')) / 86400);
    if ($reste < 0) {
        return null;
    }
    $dus = array_filter($delais, fn($d) => $d >= $reste);
    if (!$dus) {
        return null;
    }
    $proche = min($dus);
    $envoyes = array_map('intval', array_column(db_all('SELECT delai FROM rappel_envoye WHERE genre = ? AND objet_id = ?', [$genre, $objet]), 'delai'));
    foreach ($dus as $d) {
        db_query('INSERT IGNORE INTO rappel_envoye (genre, objet_id, delai) VALUES (?, ?, ?)', [$genre, $objet, $d]);
    }
    return in_array($proche, $envoyes, true) ? null : $proche;
}

function rappel_quand(string $echeance): string
{
    $reste = (int) floor((strtotime($echeance) - strtotime('today')) / 86400);
    return match ($reste) {
        0 => "aujourd'hui",
        1 => 'demain',
        default => "dans $reste jours (le " . date('d/m/Y', strtotime($echeance)) . ')',
    };
}

function rappels_envoyer(): int
{
    $n = 0;
    $jalons = db_all('SELECT j.*, s.titre, s.slug, s.type FROM jalon j JOIN espace s ON s.id = j.espace_id WHERE j.fait = 0 AND s.archive = 0 AND j.date_jalon >= CURDATE()');
    foreach ($jalons as $j) {
        $delai = rappel_a_envoyer('jalon', (int) $j['id'], $j['date_jalon'], rappel_delais($j['type'] === 'evenement' ? 'rappel_jalons_evenements' : 'rappel_jalons'));
        if ($delai === null) {
            continue;
        }
        $niveaux = $j['destinataires'] === 'equipe' ? ['n1', 'n2', 'membre'] : ['n1', 'n2'];
        $equipe = db_all('SELECT m.* FROM espace_membre em JOIN membre m ON m.id = em.membre_id WHERE em.espace_id = ? AND m.actif = 1 AND em.niveau IN ("' . implode('", "', $niveaux) . '")', [$j['espace_id']]);
        foreach ($equipe as $m) {
            mail_send($m['email'], 'Rappel : ' . $j['libelle'] . ' (' . $j['titre'] . ')', "Date clé de « {$j['titre']} » : {$j['libelle']}, " . rappel_quand($j['date_jalon']) . ".\n\n"
                . 'Une fois fait, marquez-la comme faite sur la page de l\'espace.' . "\n\n" . absolute_url('/espace/' . $j['slug']));
            $n++;
        }
    }
    $evenements = db_all('SELECT e.*, s.titre, s.slug FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE s.archive = 0 AND e.debut >= CURDATE()');
    foreach ($evenements as $ev) {
        $delai = rappel_a_envoyer('evenement', (int) $ev['espace_id'], substr($ev['debut'], 0, 10), rappel_delais('rappel_participants'));
        if ($delai === null) {
            continue;
        }
        foreach (db_all('SELECT m.* FROM inscription i JOIN membre m ON m.id = i.membre_id WHERE i.evenement_id = ? AND i.statut = "retenu" AND m.actif = 1', [$ev['espace_id']]) as $m) {
            mail_send($m['email'], 'Rappel : ' . $ev['titre'] . ' ' . rappel_quand(substr($ev['debut'], 0, 10)), "Vous êtes inscrit à « {$ev['titre']} », " . evenement_date($ev['debut'])
                . ($ev['lieu'] !== '' ? ", {$ev['lieu']}" : '') . ".\n\nSi vous ne pouvez plus venir, libérez votre place : elle ira à la personne suivante.\n\n" . absolute_url('/espace/' . $ev['slug']));
            $n++;
        }
    }
    return $n;
}
