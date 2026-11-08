<?php
// Comptes : session, connexion (mot de passe, lien magique, code), double authentification, limites d'essais
declare(strict_types=1);

const LIEN_DUREE_MINUTES = 15;
const CODE_ESSAIS_MAX = 5;

function current_member(): ?array
{
    static $member = false;
    // Requête authentifiée par clé d'API : le membre est celui de la clé, jamais la session
    if (api_member() !== null) {
        return api_member();
    }
    if ($member === false) {
        $member = null;
        if (!empty($_SESSION['membre_id']) && !empty($_SESSION['connecte'])) {
            // Compte désactivé ou en attente : la session ouverte cesse aussitôt
            $member = db_one('SELECT * FROM membre WHERE id = ? AND actif = 1', [$_SESSION['membre_id']]);
        }
    }
    return $member;
}

// Droits de plateforme : 'admin' (tout), ou une délégation 'cotisations', 'membres', 'publications'
function member_rights(int|string $memberId): array
{
    static $cache = [];
    return $cache[$memberId] ??= array_column(db_all('SELECT droit FROM droit_plateforme WHERE membre_id = ?', [$memberId]), 'droit');
}

function has_right(?array $member, string $right): bool
{
    if ($member === null) {
        return false;
    }
    $rights = member_rights($member['id']);
    return in_array('admin', $rights, true) || in_array($right, $rights, true);
}

function is_admin(?array $member): bool
{
    return $member !== null && in_array('admin', member_rights($member['id']), true);
}

function require_member(): array
{
    $member = current_member();
    if ($member === null) {
        $_SESSION['apres_connexion'] = request_path();
        redirect('/connexion');
    }
    return $member;
}

// Gestion de la plateforme : le droit demandé (l'administrateur les a tous), et la double authentification active
function require_right(string $right = 'admin'): array
{
    $member = require_member();
    if (!has_right($member, $right)) {
        http_response_code(403);
        render('erreur', ['titre' => 'Accès réservé']);
        exit;
    }
    // Gestion : deux facteurs différents dans cette session (API : la clé suffit, elle est créée par un compte à deux facteurs)
    if (api_member() === null && count(facteurs()) < 2) {
        $_SESSION['apres_connexion'] = request_path();
        flash('info', 'Pour la gestion, confirmez votre identité une seconde fois.');
        redirect('/connexion/double-authentification');
    }
    return $member;
}

// Facteurs prouvés dans cette session : 'mdp' (mot de passe), 'email' (lien ou code reçu), 'cle' (FIDO2), 'totp' (application)
function facteurs(): array
{
    return $_SESSION['facteurs'] ?? [];
}

function ajouter_facteur(string $facteur): void
{
    $_SESSION['facteurs'] = array_values(array_unique([...facteurs(), $facteur]));
}

// Second facteur toujours exigé quand la personne en a configuré un (clé ou application)
function member_exige_2fa(array $member): bool
{
    return (int) $member['totp_actif'] === 1 || (int) db_value('SELECT COUNT(*) FROM cle_securite WHERE membre_id = ?', [$member['id']]) > 0;
}

// Première étape réussie (mot de passe, lien ou code) : la session est connectée, ou attend le 2e facteur
function login_first_step(array $member, string $facteur): void
{
    if (!(int) $member['actif']) {
        flash('erreur', (int) ($member['en_attente'] ?? 0) ? 'Votre adhésion est en attente de validation : vous recevrez un e-mail dès qu\'elle sera validée.' : 'Ce compte est désactivé. Contactez l\'association.');
        redirect('/connexion');
    }
    session_regenerate_id(true);
    $_SESSION['membre_id'] = (int) $member['id'];
    $_SESSION['facteurs'] = [$facteur];
    // Choix de chacun : second facteur à chaque connexion, ou seulement en entrant dans la gestion (require_right le demande alors)
    if (member_exige_2fa($member) && !empty($member['mfa_connexion'])) {
        $_SESSION['connecte'] = false;
        redirect('/connexion/double-authentification');
    }
    login_complete($member);
}

function login_complete(array $member): never
{
    $deja = !empty($_SESSION['connecte']);
    $_SESSION['connecte'] = true;
    unset($_SESSION['rappel_cotisation_masque']); // le rappel de cotisation masqué revient à chaque connexion
    $preuves = ['mdp' => 'mot de passe', 'email' => 'e-mail', 'code' => 'code e-mail', 'totp' => 'application', 'cle' => 'clé de sécurité'];
    journal_compte((int) $member['id'], $member, ($deja ? 'Seconde vérification' : 'Connexion') . ' (' . implode(' + ', array_map(fn($f) => $preuves[$f] ?? $f, facteurs())) . ')');
    db_query('UPDATE membre SET derniere_connexion = NOW() WHERE id = ?', [$member['id']]);
    // Page demandée avant la connexion, sinon l'arrivée réglée selon la cotisation (par défaut « Mon compte »)
    $next = $_SESSION['apres_connexion'] ?? connexion_arrivee($member);
    unset($_SESSION['apres_connexion']);
    redirect(str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/compte');
}

function logout(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// Limite d'essais : $max essais par fenêtre de $minutes pour une clé (e-mail, adresse…)
function too_many_attempts(string $key, int $max = 10, int $minutes = 15): bool
{
    $hash = hash('sha256', $key);
    db_query('DELETE FROM tentative_connexion WHERE le < NOW() - INTERVAL 1 DAY');
    $count = (int) db_one('SELECT COUNT(*) AS n FROM tentative_connexion WHERE cle = ? AND le > NOW() - INTERVAL ? MINUTE', [$hash, $minutes])['n'];
    db_query('INSERT INTO tentative_connexion (cle) VALUES (?)', [$hash]);
    return $count >= $max;
}

// Adresse IP trop insistante : bloquée 1 h au-delà de 100 envois en 1 h, en plus des limites par 15 min
function ip_blocked(): bool
{
    $hash = hash('sha256', 'ip|' . client_ip());
    return (int) db_one('SELECT COUNT(*) AS n FROM tentative_connexion WHERE cle = ? AND le > NOW() - INTERVAL 1 HOUR', [$hash])['n'] >= 100;
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

// Lien magique + code à 6 chiffres de secours, à usage unique, valables LIEN_DUREE_MINUTES
function create_login_token(array $member): array
{
    $link = bin2hex(random_bytes(32));
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db_query('DELETE FROM connexion_jeton WHERE membre_id = ? OR expire_le < NOW()', [$member['id']]);
    db_query(
        'INSERT INTO connexion_jeton (membre_id, lien_hash, code_hash, expire_le) VALUES (?, ?, ?, NOW() + INTERVAL ? MINUTE)',
        [$member['id'], hash('sha256', $link), token_code_hash($member['id'], $code), LIEN_DUREE_MINUTES]
    );
    return [$link, $code];
}

function token_code_hash(int|string $memberId, string $code): string
{
    return hash_hmac('sha256', $memberId . '|' . $code, secret_key());
}

function use_login_link(string $link): ?array
{
    $token = db_one('SELECT * FROM connexion_jeton WHERE lien_hash = ? AND utilise_le IS NULL AND expire_le > NOW()', [hash('sha256', $link)]);
    if ($token === null) {
        return null;
    }
    db_query('UPDATE connexion_jeton SET utilise_le = NOW() WHERE id = ?', [$token['id']]);
    return db_one('SELECT * FROM membre WHERE id = ?', [$token['membre_id']]);
}

function use_login_code(string $email, string $code): ?array
{
    $member = db_one('SELECT * FROM membre WHERE email = ?', [mb_strtolower(trim($email))]);
    if ($member === null) {
        return null;
    }
    $token = db_one('SELECT * FROM connexion_jeton WHERE membre_id = ? AND utilise_le IS NULL AND expire_le > NOW()', [$member['id']]);
    if ($token === null || (int) $token['essais'] >= CODE_ESSAIS_MAX) {
        return null;
    }
    if (!hash_equals($token['code_hash'], token_code_hash($member['id'], preg_replace('/\D/', '', $code)))) {
        db_query('UPDATE connexion_jeton SET essais = essais + 1 WHERE id = ?', [$token['id']]);
        return null;
    }
    db_query('UPDATE connexion_jeton SET utilise_le = NOW() WHERE id = ?', [$token['id']]);
    return $member;
}

// Invitation de bienvenue : comment se connecter (aucun mot de passe n'est envoyé ; le lien se demande sur le site)
function membre_inviter(array $member, ?array $par): void
{
    $site = (string) setting('nom_association', 'Boussole');
    mail_send($member['email'], "Bienvenue sur le site de $site", "Bonjour " . trim((string) $member['prenom']) . ",\n\n"
        . "Votre compte membre est prêt sur le site de $site : agenda et inscriptions aux événements, votre cotisation, les espaces de travail.\n\n"
        . "Pour vous connecter, ouvrez cette adresse et saisissez votre e-mail (" . $member['email'] . ") : vous recevrez aussitôt un lien de connexion, sans mot de passe à retenir.\n"
        . absolute_url('/connexion?email=' . rawurlencode($member['email'])) . "\n\nÀ bientôt,\n$site\n");
    db_query('UPDATE membre SET invite_le = NOW() WHERE id = ?', [$member['id']]);
    journal(null, $par, 'Invitation envoyée : ' . membre_nom($member), $par === null ? 'web' : null);
}

// Où arrive un membre qui se connecte sans page demandée : réglable pour les membres à jour et les autres
function connexion_arrivee(array $member): string
{
    $cle = cotisation_a_jour($member) !== null || is_admin($member) ? 'arrivee_cotisant' : 'arrivee_non_cotisant';
    $chemin = (string) setting($cle, '');
    return connexion_arrivee_valide($chemin) ? $chemin : '/compte';
}

function connexion_arrivee_valide(string $chemin): bool
{
    return $chemin !== '' && (bool) preg_match('#^/(compte|espaces|page/[a-z0-9-]+)?$#', $chemin);
}

// Choix proposés dans les réglages : Mon compte, les espaces, et les pages publiées
function connexion_arrivees_possibles(): array
{
    $choix = ['' => 'Mon compte', '/espaces' => 'Espaces', '/' => 'Accueil'];
    foreach (db_all('SELECT slug, titre FROM page WHERE publiee = 1 AND est_accueil = 0 ORDER BY ordre, titre') as $p) {
        $choix['/page/' . $p['slug']] = 'Page « ' . $p['titre'] . ' »';
    }
    return $choix;
}

