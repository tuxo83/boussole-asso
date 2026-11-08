<?php
// Espaces (comités, commissions, projets, événements) : qui voit, qui contribue, qui gère, et ce qui manque
declare(strict_types=1);

const ESPACE_TYPES = ['comite' => 'Comité', 'commission' => 'Commission', 'projet' => 'Projet', 'evenement' => 'Événement'];
const ESPACE_NIVEAUX = ['n1' => 'N°1 (responsable)', 'n2' => 'N°2 (suppléant)', 'membre' => 'Membre', 'associe' => 'Associé'];
const ESPACE_VISIBILITES = ['equipe' => "L'équipe de l'espace", 'adherents' => 'Tous les membres connectés', 'public' => 'Tout le monde (public)'];
// Une phrase sous chaque choix de visibilité (avis du panel : visible sans clic, mots simples)
const ESPACE_VISIBILITES_AIDE = [
    'public' => 'Visible par tous, même sans compte.',
    'adherents' => 'Visible par les membres une fois connectés.',
    'equipe' => 'Visible seulement par l\'équipe et les administrateurs.',
];
const ESPACE_INACTIVITE_JOURS = 30;

function espace_niveau(?array $member, array $espace): ?string
{
    if ($member === null) {
        return null;
    }
    $row = db_one('SELECT niveau FROM espace_membre WHERE espace_id = ? AND membre_id = ?', [$espace['id'], $member['id']]);
    return $row['niveau'] ?? null;
}

function espace_peut_voir(?array $member, array $espace): bool
{
    return match ($espace['visibilite']) {
        'public' => true,
        'adherents' => $member !== null,
        'cotisants' => $member !== null, // ancienne valeur, traitée comme « membres »
        default => is_admin($member) || espace_niveau($member, $espace) !== null || espace_equipe_parente($member, $espace),
    };
}

// Réunion d'équipe rattachée à un espace : l'équipe de l'espace parent la voit (et peut s'y inscrire)
function espace_equipe_parente(?array $member, array $espace): bool
{
    if ($member === null || empty($espace['parent_id'])) {
        return false;
    }
    return db_one('SELECT 1 AS ok FROM espace_membre WHERE espace_id = ? AND membre_id = ?', [$espace['parent_id'], $member['id']]) !== null;
}

// Contribuer : fichiers, dates clés, météo
function espace_peut_contribuer(?array $member, array $espace): bool
{
    return is_admin($member) || in_array(espace_niveau($member, $espace), ['n1', 'n2', 'membre'], true);
}

// Gérer : l'espace, son équipe ; le n°2 a les mêmes droits que le n°1
function espace_peut_gerer(?array $member, array $espace): bool
{
    return is_admin($member) || in_array(espace_niveau($member, $espace), ['n1', 'n2'], true);
}

function espace_par_slug(string $slug): ?array
{
    return db_one('SELECT * FROM espace WHERE slug = ?', [$slug]);
}

// Ce qui manque, calculé à chaque affichage (les relances par e-mail s'appuient sur la même fonction)
function espace_alertes(array $espace): array
{
    $alertes = [];
    $niveaux = array_column(db_all('SELECT niveau FROM espace_membre WHERE espace_id = ?', [$espace['id']]), 'niveau');
    if (!in_array('n1', $niveaux, true)) {
        $alertes[] = 'Pas de n°1 (responsable)';
    }
    if (!in_array('n2', $niveaux, true)) {
        $alertes[] = 'Pas de n°2 (suppléant)';
    }
    if ($espace['type'] === 'projet' && (int) db_one('SELECT COUNT(*) AS n FROM fichier WHERE espace_id = ?', [$espace['id']])['n'] === 0) {
        $alertes[] = 'Aucun fichier';
    }
    $retard = (int) db_one('SELECT COUNT(*) AS n FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon < CURDATE()', [$espace['id']])['n'];
    if ($retard > 0) {
        $alertes[] = $retard === 1 ? '1 date clé dépassée' : "$retard dates clés dépassées";
    }
    if (strtotime($espace['modifie_le']) < time() - ESPACE_INACTIVITE_JOURS * 86400) {
        $alertes[] = 'Rien de nouveau depuis ' . ESPACE_INACTIVITE_JOURS . ' jours';
    }
    return $alertes;
}

// Journal de l'espace (qui, quand, quoi) ; toute action le met aussi « à jour »
// Historique d'un compte (sécurité) : ce qui arrive au compte $cible, fait par lui-même ou par un responsable
function journal_compte(int $cible, ?array $acteur, string $action): void
{
    journal(null, $acteur, $action, null, $cible);
}

function historique_compte(int $cible, int $limite = 30): array
{
    return db_all('SELECT j.*, m.prenom, m.nom, m.email FROM journal j LEFT JOIN membre m ON m.id = j.membre_id WHERE j.cible_id = ? ORDER BY j.le DESC, j.id DESC LIMIT ' . max(1, $limite), [$cible]);
}

function journal(int|string|null $espaceId, ?array $member, string $action, ?string $via = null, ?int $cible = null): void
{
    $via ??= api_member() !== null ? 'ia' : 'web';
    db_query('INSERT INTO journal (espace_id, membre_id, action, via, cible_id) VALUES (?, ?, ?, ?, ?)', [$espaceId, $member['id'] ?? null, mb_substr($action, 0, 255), $via, $cible]);
    if ($espaceId !== null) {
        db_query('UPDATE espace SET modifie_le = NOW() WHERE id = ?', [$espaceId]);
    }
}

function slugify(string $text): string
{
    $text = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: ''), '-'));
    return substr($text, 0, 80) ?: 'espace';
}

function slug_unique(string $title): string
{
    $base = slugify($title);
    $slug = $base;
    for ($i = 2; db_one('SELECT id FROM espace WHERE slug = ?', [$slug]) !== null; $i++) {
        $slug = "$base-$i";
    }
    return $slug;
}

// Les messages ne sont gardés qu'un temps (réglable, 12 mois par défaut)
function messages_contact_purger(): int
{
    $mois = max(1, min(60, (int) setting('contact_conservation_mois', '12')));
    return db()->exec('DELETE FROM message_contact WHERE recu_le < NOW() - INTERVAL ' . $mois . ' MONTH') ?: 0;
}

// Le journal des actions n'est gardé qu'un temps (réglable, 3 ans par défaut), comme l'annonce la page de confidentialité
function journal_purger(): int
{
    $ans = max(1, min(10, (int) setting('journal_conservation_ans', '3')));
    return db()->exec('DELETE FROM journal WHERE le < NOW() - INTERVAL ' . $ans . ' YEAR') ?: 0;
}

// Nom à afficher aux autres membres : prénom et nom, sinon le début de l'adresse (jamais l'adresse entière)
function membre_nom_affiche(array $member): string
{
    $name = trim($member['prenom'] . ' ' . $member['nom']);
    return $name !== '' ? $name : (string) strstr($member['email'], '@', true);
}

function membre_nom(array $member): string
{
    $name = trim($member['prenom'] . ' ' . $member['nom']);
    return $name !== '' ? $name : $member['email'];
}

// S'inscrire à un événement réservé aux membres : être à jour de cotisation (l'équipe et l'administrateur, toujours)
function espace_inscription_ouverte(?array $member, array $espace): bool
{
    return $espace['visibilite'] === 'public' || ($member !== null && (cotisation_a_jour($member) !== null || is_admin($member) || espace_niveau($member, $espace) !== null));
}

// Équipe rangée par groupes, d'après le libellé de chacun : « Bureau : Président » (groupe Bureau, rôle Président),
// plusieurs rôles séparés par « ; », sans « : » le groupe est « Membres ». Sert à l'espace et au bloc de page.
function espace_equipe_groupes(array $personnes): array
{
    $groupes = [];
    foreach ($personnes as $p) {
        $roles = array_filter(array_map('trim', explode(';', (string) $p['libelle'])), fn($r) => $r !== '');
        if (!$roles) {
            $roles = [($p['niveau'] === 'associe' ? 'Associés' : 'Membres') . ' : '];
        }
        foreach ($roles as $role) {
            [$groupe, $intitule] = str_contains($role, ':') ? array_map('trim', explode(':', $role, 2)) : ['Membres', $role];
            $groupes[$groupe !== '' ? $groupe : 'Membres'][] = ['personne' => $p, 'role' => $intitule];
        }
    }
    // « Membres » et « Associés » en dernier, les autres groupes dans leur ordre d'apparition
    uksort($groupes, fn($a, $b) => in_array($a, ['Membres', 'Associés'], true) <=> in_array($b, ['Membres', 'Associés'], true));
    return $groupes;
}

// Aide « qui fait quoi dans l'équipe » : un « ? » repliable, le même partout (visibilité, ajout à l'équipe)
function aide_niveaux_equipe(): string
{
    return c_aide('qui voit et qui fait quoi', '<p>Qui voit l\'espace voit tout : description, dates, fichiers, équipe. Les adresses e-mail ne sont jamais montrées.</p>'
        . '<div class="tableau"><table><thead><tr><th>Niveau</th><th>Voir</th><th>Ajouter fichiers, dates, météo</th><th>Gérer l\'équipe, les réglages</th></tr></thead><tbody>'
        . '<tr><td>N°1 et N°2</td><td>oui</td><td>oui</td><td>oui</td></tr>'
        . '<tr><td>Membre</td><td>oui</td><td>oui</td><td>non</td></tr>'
        . '<tr><td>Associé</td><td>oui</td><td>non</td><td>non</td></tr>'
        . '</tbody></table></div><p>Les administrateurs du site peuvent tout faire.</p>');
}

// « Qui peut voir cet espace ? » : trois boutons radio, une phrase sous chacun, et le « ? » des niveaux (création et modification sur place)
function c_visibilite_choix(string $valeur, array $libelles, ?string $parent = null, string $prefixe = 'visibilite'): string
{
    $html = '<fieldset class="choix-visibilite"><legend>' . ($parent !== null ? 'Qui peut voir cet événement ?' : 'Qui peut voir cet espace ?') . ' ' . aide_niveaux_equipe() . '</legend>';
    foreach ($libelles as $cle => $libelle) {
        $id = $prefixe . '-' . $cle;
        $aide = ESPACE_VISIBILITES_AIDE[$cle] ?? '';
        if ($cle === 'equipe' && $parent !== null) {
            $aide = 'Visible seulement par l\'équipe, l\'équipe de « ' . $parent . ' » et les administrateurs. Dans leur agenda seulement.';
        } elseif ($parent !== null && $cle !== 'equipe') {
            $aide .= ' Dans l\'agenda de tous.';
        }
        $html .= '<label class="choix" for="' . e($id) . '"><input type="radio" name="visibilite" id="' . e($id) . '" value="' . e($cle) . '"' . ($valeur === $cle ? ' checked' : '')
            . ' aria-describedby="' . e($id) . '-aide"><span><strong>' . e($libelle) . '</strong><small id="' . e($id) . '-aide">' . e($aide) . '</small></span></label>';
    }
    return $html . '</fieldset>';
}

// Météo automatique (case « Météo automatique ») : calculée d'après le responsable et les dates clés ; durées réglables.
// Bloqué : pas de n°1, une date dépassée depuis longtemps, ou trop de dates dépassées. En retard : une date dépassée.
// En cours : une date proche ou de l'activité récente. À jour : rien en retard et un responsable.
function espace_meteo_calculee(array $espace): array
{
    $longtemps = max(1, (int) setting('meteo_auto_retard_jours', '15'));
    $trop = max(2, (int) setting('meteo_auto_dates_bloque', '3'));
    $horizon = max(1, (int) setting('meteo_auto_horizon_jours', '30'));
    $n1 = db_value('SELECT COUNT(*) FROM espace_membre WHERE espace_id = ? AND niveau = "n1"', [$espace['id']]) > 0;
    $depassees = db_all('SELECT date_jalon FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon < CURDATE() ORDER BY date_jalon', [$espace['id']]);
    $pluriel = fn(int $n) => $n === 1 ? '1 date clé dépassée' : "$n dates clés dépassées";
    if (!$n1) {
        return ['orage', 'pas de responsable (n°1)'];
    }
    if ($depassees) {
        $plus_vieille = (int) floor((time() - strtotime($depassees[0]['date_jalon'])) / 86400);
        if (count($depassees) >= $trop) {
            return ['orage', $pluriel(count($depassees))];
        }
        if ($plus_vieille > $longtemps) {
            return ['orage', 'date clé dépassée depuis ' . $plus_vieille . ' jours'];
        }
        return ['pluie', $pluriel(count($depassees))];
    }
    $proche = db_one('SELECT date_jalon, libelle FROM jalon WHERE espace_id = ? AND fait = 0 AND date_jalon >= CURDATE() AND date_jalon <= CURDATE() + INTERVAL ? DAY ORDER BY date_jalon LIMIT 1', [$espace['id'], $horizon]);
    if ($proche) {
        return ['eclaircie', 'prochaine date le ' . date('d/m', strtotime($proche['date_jalon']))];
    }
    if (strtotime((string) $espace['modifie_le']) > time() - $horizon * 86400) {
        return ['eclaircie', 'activité récente'];
    }
    return ['soleil', 'rien en retard'];
}

// Recalcule et enregistre la météo d'un espace en mode automatique (sans toucher à la date de modification) ; renvoie l'espace à jour
function espace_meteo_actualiser(array $espace): array
{
    if (empty($espace['meteo_auto'])) {
        return $espace;
    }
    [$meteo, $raison] = espace_meteo_calculee($espace);
    if ($meteo !== $espace['meteo'] || $raison !== $espace['meteo_raison']) {
        db_query('UPDATE espace SET meteo = ?, meteo_raison = ?, modifie_le = modifie_le WHERE id = ?', [$meteo, $raison, $espace['id']]);
        $espace['meteo'] = $meteo;
        $espace['meteo_raison'] = $raison;
    }
    return $espace;
}

// Tâche planifiée : toutes les météos automatiques (les dates passent même quand personne ne visite l'espace)
function espaces_meteo_auto_actualiser(): int
{
    $n = 0;
    foreach (db_all('SELECT * FROM espace WHERE meteo_auto = 1 AND archive = 0') as $espace) {
        $avant = $espace['meteo'];
        $n += espace_meteo_actualiser($espace)['meteo'] !== $avant ? 1 : 0;
    }
    return $n;
}

// Libellé d'une personne dans l'équipe : soit « libelle » tel quel (API, ancien formulaire), soit « groupe » + « role » du formulaire
function espace_libelle_compose(array $in): string
{
    if (array_key_exists('role', $in) || array_key_exists('groupe', $in)) {
        $groupe = trim(str_replace([':', ';'], ' ', (string) ($in['groupe'] ?? '')));
        $role = trim(str_replace(';', ',', (string) ($in['role'] ?? '')));
        return $groupe !== '' ? $groupe . ' : ' . $role : $role;
    }
    return trim((string) ($in['libelle'] ?? ''));
}
