<?php
// « Rester connecté sur cet appareil » (site et application installée).
// Le cookie porte un sélecteur et un secret ; la base ne garde que l'empreinte du secret.
// Il rouvre seulement la première étape (mot de passe ou e-mail) : la gestion redemande le second facteur,
// et qui a choisi le second facteur à chaque connexion n'en profite pas.
declare(strict_types=1);

const APPAREIL_COOKIE = 'boussole_appareil';

// Durée en jours (réglage) ; 0 : la fonction est coupée
function appareil_duree_jours(): int
{
    return max(0, min(400, (int) setting('appareil_duree_jours', '90')));
}

function appareil_autorise(array $member): bool
{
    // Compte de démonstration (partagé) : jamais retenu, chacun verrait et couperait les appareils des autres
    return appareil_duree_jours() > 0 && !(member_exige_2fa($member) && !empty($member['mfa_connexion'])) && empty($member['compte_demo']);
}

function appareil_cookie(string $valeur, int $expire): void
{
    setcookie(APPAREIL_COOKIE, $valeur, ['expires' => $expire, 'path' => base_path() ?: '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

// Nom lisible de l'appareil, pour la liste « Mes appareils » (rien d'autre n'est gardé du navigateur)
function appareil_libelle(string $agent, bool $appli): string
{
    $systeme = match (true) {
        str_contains($agent, 'iPhone') => 'iPhone',
        str_contains($agent, 'iPad') => 'iPad',
        str_contains($agent, 'Android') => 'Android',
        str_contains($agent, 'Windows') => 'Windows',
        str_contains($agent, 'Mac OS') => 'Mac',
        str_contains($agent, 'Linux') => 'Linux',
        default => 'Appareil',
    };
    $navigateur = match (true) {
        str_contains($agent, 'Edg/') => 'Edge',
        str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
        str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
        str_contains($agent, 'Safari/') => 'Safari',
        default => 'navigateur',
    };
    return $appli ? "Application · $systeme" : "$systeme · $navigateur";
}

// Après une connexion réussie, si la personne l'a demandé
function appareil_retenir(array $member, string $facteur, bool $appli): void
{
    if (!appareil_autorise($member)) {
        return;
    }
    $selecteur = bin2hex(random_bytes(12));
    $secret = bin2hex(random_bytes(32));
    $jours = appareil_duree_jours();
    db_query('INSERT INTO connexion_appareil (membre_id, selecteur, empreinte, facteur, libelle, expire_le, derniere_utilisation) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
        $member['id'], $selecteur, hash('sha256', $secret), in_array($facteur, ['mdp', 'email'], true) ? $facteur : 'mdp',
        appareil_libelle((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $appli), date('Y-m-d H:i:s', time() + $jours * 86400),
    ]);
    $_SESSION['appareil_id'] = (int) db_value('SELECT id FROM connexion_appareil WHERE selecteur = ?', [$selecteur]);
    $_SESSION['csrf'] = appareil_csrf($selecteur);
    appareil_cookie("$selecteur.$secret", time() + $jours * 86400);
    journal_compte((int) $member['id'], $member, 'Appareil retenu : ' . appareil_libelle((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $appli));
}

// Au début de chaque visite sans session connectée : rouvre la session à un seul facteur.
// L'échéance glisse (au plus une fois par jour) : un appareil utilisé reste connecté.
function appareil_restaurer(): void
{
    $valeur = (string) ($_COOKIE[APPAREIL_COOKIE] ?? '');
    if ($valeur === '' || !empty($_SESSION['connecte']) || !empty($_SESSION['membre_id'])) {
        return;
    }
    if (!preg_match('/^([0-9a-f]{24})\.([0-9a-f]{64})$/', $valeur, $m)) {
        appareil_cookie('', time() - 3600);
        return;
    }
    $ligne = db_one('SELECT * FROM connexion_appareil WHERE selecteur = ?', [$m[1]]);
    $member = $ligne ? db_one('SELECT * FROM membre WHERE id = ? AND actif = 1', [$ligne['membre_id']]) : null;
    $valide = $ligne !== null && hash_equals($ligne['empreinte'], hash('sha256', $m[2])) && strtotime($ligne['expire_le']) > time()
        && $member !== null && appareil_autorise($member) && empty($member['mot_de_passe_provisoire']);
    if (!$valide) {
        if ($ligne !== null) {
            db_query('DELETE FROM connexion_appareil WHERE id = ?', [$ligne['id']]);
        }
        appareil_cookie('', time() - 3600);
        return;
    }
    session_regenerate_id(true);
    $_SESSION['membre_id'] = (int) $member['id'];
    $_SESSION['facteurs'] = []; // aucune preuve donnée dans cette session : voir session_par_appareil()
    $_SESSION['connecte'] = true;
    $_SESSION['appareil_id'] = (int) $ligne['id'];
    $_SESSION['par_appareil'] = true;
    $_SESSION['session_version'] = (int) $member['session_version'];
    // Jeton CSRF propre à l'appareil : un formulaire resté ouvert dans l'application passe encore après la fin de la session PHP
    $_SESSION['csrf'] = appareil_csrf($ligne['selecteur']);
    // Au plus une fois par jour : l'échéance glisse, et la réouverture apparaît dans « Activité du compte »
    if ($ligne['derniere_utilisation'] === null || strtotime($ligne['derniere_utilisation']) < time() - 86400) {
        $expire = time() + appareil_duree_jours() * 86400;
        db_query('UPDATE connexion_appareil SET derniere_utilisation = NOW(), expire_le = ? WHERE id = ?', [date('Y-m-d H:i:s', $expire), $ligne['id']]);
        appareil_cookie($valeur, $expire);
        journal_compte((int) $member['id'], $member, 'Connexion rouverte : ' . $ligne['libelle']);
    }
}

// Déconnexion : cet appareil n'est plus retenu
function appareil_oublier_courant(): void
{
    $valeur = (string) ($_COOKIE[APPAREIL_COOKIE] ?? '');
    if (preg_match('/^([0-9a-f]{24})\./', $valeur, $m)) {
        db_query('DELETE FROM connexion_appareil WHERE selecteur = ?', [$m[1]]);
    }
    if ($valeur !== '') {
        appareil_cookie('', time() - 3600);
    }
}

// Mot de passe changé, mot de passe provisoire, « déconnecter les autres » : plus aucun autre appareil retenu
// ($sauf : l'appareil de la session en cours, qui reste connecté)
function appareils_oublier_tous(int $memberId, ?int $sauf = null): void
{
    db_query('DELETE FROM connexion_appareil WHERE membre_id = ? AND id <> ?', [$memberId, $sauf ?? 0]);
}

function appareils_du_membre(int $memberId): array
{
    return db_all('SELECT id, libelle, cree_le, derniere_utilisation FROM connexion_appareil WHERE membre_id = ? AND expire_le > NOW() ORDER BY COALESCE(derniere_utilisation, cree_le) DESC', [$memberId]);
}

function appareils_nettoyer(): int
{
    return db_query('DELETE FROM connexion_appareil WHERE expire_le < NOW()')->rowCount();
}

function appareil_csrf(string $selecteur): string
{
    return hash_hmac('sha256', 'csrf|' . $selecteur, secret_key());
}

// Case « Rester connecté » des formulaires de connexion (cochée d'office dans l'application installée, par le script ;
// sur la page du lien e-mail, cochée si elle l'avait été au début de la connexion)
function appareil_case(): string
{
    if (appareil_duree_jours() === 0) {
        return '';
    }
    $deja = (string) ($_SESSION['rester_connecte'] ?? '');
    $attributs = $deja !== '' ? ' value="' . e($deja) . '" checked' : ' value="1"';
    return '<label class="case case-bloc"><input type="checkbox" name="rester"' . $attributs . ' data-rester-connecte><span>Rester connecté sur cet appareil'
        . '<small class="discret">' . appareil_duree_jours() . ' jours, pas sur un ordinateur partagé</small></span></label>';
}

// Valeur retenue en session jusqu'à la fin de la connexion : '' (non), '1' (navigateur), 'appli' (application installée)
function appareil_choix_post(): string
{
    return match ((string) ($_POST['rester'] ?? '')) {
        '1' => '1',
        'appli' => 'appli',
        default => '',
    };
}
