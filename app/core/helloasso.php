<?php
// Connexion HelloAsso (facultative) : lit les paiements des formulaires d'adhésion et de billetterie.
// Seuls l'e-mail du payeur et la date sont gardés. Une notification HelloAsso ne fait que déclencher
// une lecture de l'API : son contenu n'est jamais cru (HelloAsso ne signe pas ses notifications).
declare(strict_types=1);

function helloasso_actif(): bool
{
    return setting('helloasso_client_id', '') !== '' && setting('helloasso_secret', '') !== '' && setting('helloasso_organisation', '') !== '';
}

function helloasso_api(): string
{
    return rtrim((string) (config()['helloasso_api'] ?? 'https://api.helloasso.com'), '/');
}

function helloasso_http(string $method, string $url, array $headers, ?string $body = null): array
{
    $context = stream_context_create(['http' => [
        'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '',
        'timeout' => 15, 'ignore_errors' => true, 'user_agent' => 'Boussole/' . BOUSSOLE_VERSION,
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $response = @file_get_contents($url, false, $context);
    $status = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    return [$status, $response === false ? null : json_decode($response, true)];
}

// Jeton d'accès gardé chiffré 25 minutes (valable 30) : peu d'appels au service d'authentification, limité par HelloAsso
function helloasso_jeton(): ?string
{
    $cache = secret_decrypt((string) setting('helloasso_jeton', ''));
    if ($cache !== null && (int) setting('helloasso_jeton_expire', '0') > time()) {
        return $cache;
    }
    $secret = secret_decrypt((string) setting('helloasso_secret', ''));
    [$status, $data] = helloasso_http('POST', helloasso_api() . '/oauth2/token', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['grant_type' => 'client_credentials', 'client_id' => (string) setting('helloasso_client_id', ''), 'client_secret' => (string) $secret]));
    if ($status !== 200 || !is_string($data['access_token'] ?? null)) {
        setting_set('helloasso_erreur', 'Connexion refusée par HelloAsso (identifiant ou secret à vérifier), code ' . $status);
        return null;
    }
    setting_set('helloasso_jeton', secret_encrypt($data['access_token']));
    setting_set('helloasso_jeton_expire', (string) (time() + 25 * 60));
    return $data['access_token'];
}

// Paiements autorisés d'un formulaire depuis une date : [[email, date], …]
function helloasso_paiements(string $jeton, string $type, string $formulaire, string $depuis): ?array
{
    $base = helloasso_api() . '/v5/organizations/' . rawurlencode((string) setting('helloasso_organisation', '')) . '/forms/' . $type . '/' . rawurlencode($formulaire) . '/payments';
    $query = ['from' => $depuis, 'states' => 'Authorized', 'pageSize' => 100, 'sortOrder' => 'Asc', 'sortField' => 'Date'];
    $paiements = [];
    for ($page = 0; $page < 50; $page++) {
        [$status, $data] = helloasso_http('GET', $base . '?' . http_build_query($query), ['Authorization: Bearer ' . $jeton, 'Accept: application/json']);
        if ($status !== 200 || !is_array($data)) {
            setting_set('helloasso_erreur', "Formulaire « $formulaire » illisible (code $status) : nom du formulaire ou droits de la clé à vérifier");
            return null;
        }
        foreach ((array) ($data['data'] ?? []) as $p) {
            $email = mb_strtolower(trim((string) ($p['payer']['email'] ?? '')));
            $date = cotisation_lire_date(substr((string) ($p['date'] ?? ''), 0, 10));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) && $date !== null && ($p['state'] ?? 'Authorized') === 'Authorized') {
                $paiements[] = [$email, $date, (string) ($p['payer']['firstName'] ?? ''), (string) ($p['payer']['lastName'] ?? '')];
            }
        }
        $suite = $data['pagination']['continuationToken'] ?? null;
        if (count((array) ($data['data'] ?? [])) < 100 || !is_string($suite) || $suite === '') {
            break;
        }
        $query['continuationToken'] = $suite;
    }
    return $paiements;
}

// Synchronisation : adhésions → cotisations ; billetteries → « payé » sur les inscriptions. Renvoie le nombre de mises à jour.
function helloasso_synchroniser(bool $force = false): int
{
    if (!helloasso_actif()) {
        return 0;
    }
    $intervalle = max(5, (int) setting('helloasso_intervalle', '10')) * 60;
    if (!$force && (int) setting('helloasso_derniere', '0') + $intervalle - 30 > time()) {
        return 0;
    }
    setting_set('helloasso_derniere', (string) time());
    $jeton = helloasso_jeton();
    if ($jeton === null) {
        return 0;
    }
    // On relit les 3 derniers jours à chaque passage : un paiement tardif ou une coupure ne fait rien perdre
    $depuis = date('Y-m-d\T00:00:00', strtotime('-3 days'));
    $n = 0;
    $formulaire = (string) setting('helloasso_adhesion', '');
    if ($formulaire !== '') {
        $paiements = helloasso_paiements($jeton, 'Membership', $formulaire, (string) setting('helloasso_premiere', '') === '' ? cotisation_debut_periode(cotisation_periode()) . 'T00:00:00' : $depuis);
        if ($paiements === null) {
            return $n;
        }
        setting_set('helloasso_premiere', '1');
        foreach ($paiements as [$email, $date, $prenom, $nom]) {
            $n += cotisation_paiement_recu($email, $prenom, $nom, $date, 'helloasso', null) === 'deja' ? 0 : 1;
        }
    }
    foreach (db_all('SELECT e.* FROM evenement e JOIN espace s ON s.id = e.espace_id WHERE e.helloasso_formulaire IS NOT NULL AND e.helloasso_formulaire <> "" AND s.archive = 0 AND (e.debut IS NULL OR e.debut >= CURDATE() - INTERVAL 7 DAY)') as $ev) {
        $paiements = helloasso_paiements($jeton, 'Event', $ev['helloasso_formulaire'], date('Y-m-d\T00:00:00', strtotime('-1 year')));
        if ($paiements === null) {
            return $n;
        }
        foreach ($paiements as [$email, $date]) {
            $n += db_query('UPDATE inscription i JOIN membre m ON m.id = i.membre_id SET i.paye_le = ? WHERE i.evenement_id = ? AND m.email = ? AND i.paye_le IS NULL', [$date, $ev['espace_id'], $email])->rowCount();
        }
    }
    // Paiements de billetterie : les règles des événements s'appliquent aussitôt (place gardée, dernières places)
    evenements_regler_tous();
    setting_set('helloasso_erreur', '');
    setting_set('helloasso_reussie', date('Y-m-d H:i:s'));
    return $n;
}
