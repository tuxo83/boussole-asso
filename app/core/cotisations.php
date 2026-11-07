<?php
// Cotisations : à jour ou non, depuis quand, comment. Relances. Effacement des données après la durée de conservation.
declare(strict_types=1);

const COTISATION_MOYENS = ['helloasso' => 'HelloAsso', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'virement' => 'Virement', 'autre' => 'Autre'];

function cotisation_mois_debut(): int
{
    return max(1, min(12, (int) setting('cotisation_mois_debut', '1')));
}

// Période = année de début (une association qui commence en septembre a des périodes « 2026-2027 »)
function cotisation_periode(?string $date = null): int
{
    $time = strtotime($date ?? 'today');
    $year = (int) date('Y', $time);
    return (int) date('n', $time) >= cotisation_mois_debut() ? $year : $year - 1;
}

function cotisation_periode_libelle(int $periode): string
{
    return cotisation_mois_debut() === 1 ? (string) $periode : $periode . '-' . ($periode + 1);
}

function cotisation_debut_periode(int $periode): string
{
    return sprintf('%04d-%02d-01', $periode, cotisation_mois_debut());
}

function cotisation_a_jour(array $member, ?int $periode = null): ?array
{
    return db_one('SELECT * FROM cotisation WHERE membre_id = ? AND periode = ?', [$member['id'], $periode ?? cotisation_periode()]);
}

// Membre « à jour depuis » : première période payée de la suite ininterrompue qui mène à aujourd'hui
function cotisation_depuis(array $member): ?int
{
    $periodes = array_map('intval', array_column(db_all('SELECT periode FROM cotisation WHERE membre_id = ? ORDER BY periode DESC', [$member['id']]), 'periode'));
    $attendue = cotisation_periode();
    $depuis = null;
    foreach ($periodes as $p) {
        if ($p !== $attendue) {
            break;
        }
        $depuis = $p;
        $attendue--;
    }
    return $depuis;
}

function cotisation_marquer(array $member, int $periode, string $payeLe, string $moyen, ?array $par): bool
{
    if (!isset(COTISATION_MOYENS[$moyen]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payeLe) || $payeLe > date('Y-m-d')) {
        return false;
    }
    db_query('INSERT INTO cotisation (membre_id, periode, paye_le, moyen, saisi_par) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE paye_le = VALUES(paye_le), moyen = VALUES(moyen), saisi_par = VALUES(saisi_par)', [$member['id'], $periode, $payeLe, $moyen, $par['id'] ?? null]);
    journal(null, $par, 'Cotisation ' . cotisation_periode_libelle($periode) . ' : ' . membre_nom($member) . ' (' . COTISATION_MOYENS[$moyen] . ')', $par === null ? 'web' : null);
    return true;
}

function cotisation_relancer(array $member, int $periode): void
{
    mail_send($member['email'], 'Cotisation ' . cotisation_periode_libelle($periode) . ' : ' . setting('nom_association', 'Boussole'),
        "Bonjour,\n\nSauf erreur de notre part, votre cotisation " . cotisation_periode_libelle($periode) . " n'est pas encore enregistrée. "
        . "Si vous l'avez déjà réglée, merci d'ignorer ce message.\n\n" . absolute_url('/compte'));
    db_query('REPLACE INTO cotisation_relance (membre_id, periode) VALUES (?, ?)', [$member['id'], $periode]);
}

// Membres actifs pas encore à jour pour la période (hors comptes déjà relancés si demandé)
function cotisation_retardataires(int $periode, bool $sansRelance = false): array
{
    return db_all('SELECT m.* FROM membre m LEFT JOIN cotisation c ON c.membre_id = m.id AND c.periode = ?'
        . ($sansRelance ? ' LEFT JOIN cotisation_relance r ON r.membre_id = m.id AND r.periode = ?' : '')
        . ' WHERE m.actif = 1 AND c.membre_id IS NULL' . ($sansRelance ? ' AND r.membre_id IS NULL' : '') . ' ORDER BY m.nom, m.email',
        $sansRelance ? [$periode, $periode] : [$periode]);
}

// Relance automatique (cron) : N jours après le début de la période, une seule fois ; 0 = désactivée
function cotisations_relances_auto(): int
{
    $jours = (int) setting('cotisation_relance_jours', '30');
    $periode = cotisation_periode();
    if ($jours <= 0 || date('Y-m-d') < date('Y-m-d', strtotime(cotisation_debut_periode($periode) . " +$jours days"))) {
        return 0;
    }
    $liste = cotisation_retardataires($periode, true);
    foreach ($liste as $member) {
        cotisation_relancer($member, $periode);
    }
    return count($liste);
}

// Données à effacer : dernière cotisation (ou création du compte, si jamais de cotisation) plus ancienne
// que la durée de conservation. Les comptes qui ont des droits de gestion ne sont jamais proposés.
function cotisation_a_effacer(): array
{
    $ans = max(1, (int) setting('cotisation_conservation_ans', '2'));
    $limite = cotisation_periode() - $ans;
    return db_all('SELECT m.*, MAX(c.periode) AS derniere FROM membre m LEFT JOIN cotisation c ON c.membre_id = m.id
        WHERE NOT EXISTS (SELECT 1 FROM droit_plateforme d WHERE d.membre_id = m.id)
        GROUP BY m.id HAVING (derniere IS NULL AND m.cree_le < CURDATE() - INTERVAL ? YEAR) OR derniere < ?
        ORDER BY derniere, m.nom', [$ans, $limite]);
}

// Météo de la tâche « effacer les données » : à jour s'il n'y a rien à faire
function cotisation_meteo_effacement(array $liste): string
{
    return match (true) {
        !$liste => 'soleil',
        count($liste) < 10 => 'eclaircie',
        default => 'pluie',
    };
}

// Effacement réel : le compte et tout ce qui s'y rattache disparaissent ; son nom est retiré du journal
function membre_effacer(array $member, array $par): void
{
    $nom = membre_nom($member);
    db_query('DELETE FROM membre WHERE id = ?', [$member['id']]);
    foreach (array_unique([$nom, $member['email']]) as $trace) {
        db_query('UPDATE journal SET action = REPLACE(action, ?, "membre effacé") WHERE action LIKE ?', [$trace, '%' . $trace . '%']);
    }
    journal(null, $par, 'Données d\'un membre effacées (fin de la durée de conservation)');
}

// Import HelloAsso (export CSV du site HelloAsso) : on ne garde que l'e-mail et la date de paiement
function cotisation_import_helloasso(string $chemin, int $periode, array $par): array
{
    $contenu = (string) file_get_contents($chemin, false, null, 0, 5 * 1024 * 1024);
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
    if (!mb_check_encoding($contenu, 'UTF-8')) {
        $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }
    $lignes = preg_split('/\R/', trim($contenu));
    $sep = substr_count($lignes[0] ?? '', ';') >= substr_count($lignes[0] ?? '', ',') ? ';' : ',';
    $entetes = array_map(fn($h) => mb_strtolower(trim($h)), str_getcsv((string) array_shift($lignes), $sep, '"', ''));
    $col = function (array $mots) use ($entetes): ?int {
        foreach ($entetes as $i => $h) {
            foreach ($mots as $mot) {
                if (str_contains($h, $mot)) {
                    return $i;
                }
            }
        }
        return null;
    };
    $colMail = $col(['email payeur', 'e-mail payeur', 'email', 'e-mail', 'mail']);
    $colDate = $col(['date du paiement', 'date de paiement', 'date']);
    if ($colMail === null || $colDate === null) {
        return ['erreur' => 'Colonnes « e-mail » et « date » introuvables dans le fichier.'];
    }
    $bilan = ['marques' => 0, 'deja' => 0, 'inconnus' => []];
    foreach ($lignes as $ligne) {
        $cells = str_getcsv($ligne, $sep, '"', '');
        $email = mb_strtolower(trim($cells[$colMail] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }
        $date = cotisation_lire_date(trim($cells[$colDate] ?? ''));
        $member = db_one('SELECT * FROM membre WHERE email = ?', [$email]);
        if ($member === null || $date === null) {
            $bilan['inconnus'][] = $email;
        } elseif (cotisation_a_jour($member, $periode) !== null) {
            $bilan['deja']++;
        } else {
            cotisation_marquer($member, $periode, $date, 'helloasso', $par);
            $bilan['marques']++;
        }
    }
    $bilan['inconnus'] = array_values(array_unique($bilan['inconnus']));
    return $bilan;
}

function cotisation_lire_date(string $value): ?string
{
    foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d'] as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        if ($date !== false) {
            return min($date->format('Y-m-d'), date('Y-m-d'));
        }
    }
    return null;
}
