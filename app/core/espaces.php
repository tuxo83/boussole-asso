<?php
// Espaces (comités, commissions, projets, événements) : qui voit, qui contribue, qui gère, et ce qui manque
declare(strict_types=1);

const ESPACE_TYPES = ['comite' => 'Comité', 'commission' => 'Commission', 'projet' => 'Projet', 'evenement' => 'Événement'];
const ESPACE_NIVEAUX = ['n1' => 'N°1 (responsable)', 'n2' => 'N°2 (suppléant)', 'membre' => 'Membre', 'associe' => 'Associé'];
const ESPACE_VISIBILITES = ['equipe' => "L'équipe de l'espace", 'adherents' => 'Tous les membres connectés', 'public' => 'Tout le monde (public)'];
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
        default => is_admin($member) || espace_niveau($member, $espace) !== null,
    };
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
