<?php
// WebAuthn (FIDO2, passkeys) sans bibliothèque : décodage CBOR minimal, clés COSE ES256 et RS256,
// vérification de l'enregistrement (attestation « none ») et de la signature à la connexion.
// Le domaine (rpId) et l'origine viennent de l'adresse du site fixée à l'installation, jamais de la requête.
declare(strict_types=1);

final class WebauthnErreur extends RuntimeException
{
}

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    $raw = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    if ($raw === false) {
        throw new WebauthnErreur('Encodage invalide');
    }
    return $raw;
}

// ---------- CBOR (RFC 8949), longueurs définies uniquement, profondeur limitée

function cbor_decode(string $data, int &$offset = 0, int $depth = 0): mixed
{
    if ($depth > 8 || $offset >= strlen($data)) {
        throw new WebauthnErreur('CBOR invalide');
    }
    $initial = ord($data[$offset++]);
    $major = $initial >> 5;
    $info = $initial & 0x1f;
    if ($major === 7) {
        return match ($info) {
            20 => false, 21 => true, 22 => null,
            default => throw new WebauthnErreur('CBOR : valeur simple non prise en charge'),
        };
    }
    $value = match (true) {
        $info < 24 => $info,
        $info === 24 => cbor_uint($data, $offset, 1),
        $info === 25 => cbor_uint($data, $offset, 2),
        $info === 26 => cbor_uint($data, $offset, 4),
        $info === 27 => cbor_uint($data, $offset, 8),
        default => throw new WebauthnErreur('CBOR : longueur indéfinie non prise en charge'),
    };
    switch ($major) {
        case 0:
            return $value;
        case 1:
            return -1 - $value;
        case 2:
        case 3:
            if ($value > strlen($data) - $offset) {
                throw new WebauthnErreur('CBOR tronqué');
            }
            $bytes = substr($data, $offset, $value);
            $offset += $value;
            return $bytes;
        case 4:
            $list = [];
            for ($i = 0; $i < $value; $i++) {
                $list[] = cbor_decode($data, $offset, $depth + 1);
            }
            return $list;
        case 5:
            $map = [];
            for ($i = 0; $i < $value; $i++) {
                $key = cbor_decode($data, $offset, $depth + 1);
                if (!is_int($key) && !is_string($key)) {
                    throw new WebauthnErreur('CBOR : clé invalide');
                }
                $map[$key] = cbor_decode($data, $offset, $depth + 1);
            }
            return $map;
    }
    throw new WebauthnErreur('CBOR : type non pris en charge');
}

function cbor_uint(string $data, int &$offset, int $size): int
{
    if ($offset + $size > strlen($data)) {
        throw new WebauthnErreur('CBOR tronqué');
    }
    $value = 0;
    for ($i = 0; $i < $size; $i++) {
        $value = ($value << 8) | ord($data[$offset + $i]);
    }
    $offset += $size;
    if ($value < 0) {
        throw new WebauthnErreur('CBOR : entier trop grand');
    }
    return $value;
}

// ---------- Clés COSE → PEM (ES256 : P-256 ; RS256)

function der_length(int $n): string
{
    if ($n < 128) {
        return chr($n);
    }
    $bytes = ltrim(pack('N', $n), "\0");
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function der_integer(string $bytes): string
{
    $bytes = ltrim($bytes, "\0");
    if ($bytes === '' || ord($bytes[0]) & 0x80) {
        $bytes = "\0" . $bytes;
    }
    return "\x02" . der_length(strlen($bytes)) . $bytes;
}

function cose_vers_pem(array $cose): string
{
    $kty = $cose[1] ?? null;
    $alg = $cose[3] ?? null;
    if ($kty === 2 && $alg === -7 && ($cose[-1] ?? null) === 1) {
        $x = $cose[-2] ?? '';
        $y = $cose[-3] ?? '';
        if (!is_string($x) || !is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
            throw new WebauthnErreur('Clé EC invalide');
        }
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
    } elseif ($kty === 3 && $alg === -257) {
        $n = $cose[-1] ?? '';
        $e = $cose[-2] ?? '';
        if (!is_string($n) || !is_string($e) || strlen($n) < 256) {
            throw new WebauthnErreur('Clé RSA invalide');
        }
        $rsa = der_integer($n) . der_integer($e);
        $rsa = "\x30" . der_length(strlen($rsa)) . $rsa;
        $bits = "\x03" . der_length(strlen($rsa) + 1) . "\x00" . $rsa;
        $algo = hex2bin('300d06092a864886f70d0101010500');
        $der = "\x30" . der_length(strlen($algo . $bits)) . $algo . $bits;
    } else {
        throw new WebauthnErreur('Type de clé non pris en charge (ES256 ou RS256)');
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    if (openssl_pkey_get_public($pem) === false) {
        throw new WebauthnErreur('Clé publique illisible');
    }
    return $pem;
}

// ---------- Contexte : domaine et origine du site

function webauthn_origine(): string
{
    $url = parse_url((string) setting('adresse_site', absolute_url('/')));
    return ($url['scheme'] ?? 'https') . '://' . ($url['host'] ?? 'localhost') . (isset($url['port']) ? ':' . $url['port'] : '');
}

function webauthn_rp_id(): string
{
    return (string) (parse_url(webauthn_origine(), PHP_URL_HOST) ?: 'localhost');
}

function webauthn_defi(string $usage): string
{
    $defi = random_bytes(32);
    $_SESSION['webauthn'] = ['defi' => b64url_encode($defi), 'usage' => $usage, 'expire' => time() + 300];
    return b64url_encode($defi);
}

// Défi à usage unique : consommé à la première vérification, réussie ou non
function webauthn_defi_attendu(string $usage): string
{
    $attendu = $_SESSION['webauthn'] ?? null;
    unset($_SESSION['webauthn']);
    if (!is_array($attendu) || $attendu['usage'] !== $usage || $attendu['expire'] < time()) {
        throw new WebauthnErreur('Défi expiré : recommencez');
    }
    return $attendu['defi'];
}

function webauthn_client_data(string $json64, string $type, string $usage): string
{
    $raw = b64url_decode($json64);
    $client = json_decode($raw, true);
    if (!is_array($client) || ($client['type'] ?? '') !== $type) {
        throw new WebauthnErreur('Réponse de la clé invalide');
    }
    if (!hash_equals(webauthn_defi_attendu($usage), (string) ($client['challenge'] ?? ''))) {
        throw new WebauthnErreur('Défi incorrect');
    }
    if (!hash_equals(webauthn_origine(), (string) ($client['origin'] ?? ''))) {
        throw new WebauthnErreur('Origine incorrecte (adresse du site différente de celle des réglages)');
    }
    return $raw;
}

// Données de l'authentificateur : domaine, présence de l'utilisateur, compteur, et (enregistrement) la clé
function webauthn_auth_data(string $data, bool $avecCle): array
{
    if (strlen($data) < 37 || !hash_equals(hash('sha256', webauthn_rp_id(), true), substr($data, 0, 32))) {
        throw new WebauthnErreur('Domaine incorrect');
    }
    $flags = ord($data[32]);
    if (!($flags & 0x01)) {
        throw new WebauthnErreur('Présence de l\'utilisateur non confirmée');
    }
    $out = ['compteur' => unpack('N', substr($data, 33, 4))[1]];
    if ($avecCle) {
        if (!($flags & 0x40) || strlen($data) < 55) {
            throw new WebauthnErreur('Clé absente de la réponse');
        }
        $len = unpack('n', substr($data, 53, 2))[1];
        if ($len < 16 || $len > 1023 || strlen($data) < 55 + $len) {
            throw new WebauthnErreur('Identifiant de clé invalide');
        }
        $out['identifiant'] = substr($data, 55, $len);
        $offset = 55 + $len;
        $cose = cbor_decode($data, $offset);
        if (!is_array($cose)) {
            throw new WebauthnErreur('Clé invalide');
        }
        $out['pem'] = cose_vers_pem($cose);
    }
    return $out;
}

// ---------- Enregistrement d'une clé

function webauthn_options_creation(array $member): array
{
    return [
        'challenge' => webauthn_defi('creation'),
        'rp' => ['name' => (string) setting('nom_association', 'Boussole'), 'id' => webauthn_rp_id()],
        'user' => ['id' => b64url_encode(hash('sha256', 'membre|' . $member['id'] . '|' . (config()['secret'] ?? ''), true)), 'name' => $member['email'], 'displayName' => membre_nom($member)],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
        'timeout' => 120000,
        'attestation' => 'none',
        'authenticatorSelection' => ['userVerification' => 'preferred', 'residentKey' => 'discouraged'],
        'excludeCredentials' => webauthn_cles_autorisees($member),
    ];
}

function webauthn_enregistrer(array $member, array $reponse, string $libelle): void
{
    webauthn_client_data((string) ($reponse['clientDataJSON'] ?? ''), 'webauthn.create', 'creation');
    $attestation = cbor_decode(b64url_decode((string) ($reponse['attestationObject'] ?? '')));
    if (!is_array($attestation) || !is_string($attestation['authData'] ?? null)) {
        throw new WebauthnErreur('Attestation invalide');
    }
    $auth = webauthn_auth_data($attestation['authData'], true);
    $id = b64url_encode($auth['identifiant']);
    if (db_one('SELECT id FROM cle_securite WHERE identifiant = ?', [$id]) !== null) {
        throw new WebauthnErreur('Cette clé est déjà enregistrée');
    }
    db_query('INSERT INTO cle_securite (membre_id, libelle, identifiant, cle_publique, compteur) VALUES (?, ?, ?, ?, ?)',
        [$member['id'], mb_substr(trim($libelle), 0, 100) ?: 'Clé de sécurité', $id, $auth['pem'], $auth['compteur']]);
}

// ---------- Connexion avec une clé

function webauthn_cles_autorisees(array $member): array
{
    return array_map(fn($c) => ['type' => 'public-key', 'id' => $c['identifiant']], db_all('SELECT identifiant FROM cle_securite WHERE membre_id = ?', [$member['id']]));
}

function webauthn_options_connexion(array $member): array
{
    return ['challenge' => webauthn_defi('connexion'), 'rpId' => webauthn_rp_id(), 'timeout' => 120000,
        'userVerification' => 'preferred', 'allowCredentials' => webauthn_cles_autorisees($member)];
}

function webauthn_verifier(array $member, array $reponse): void
{
    $client = webauthn_client_data((string) ($reponse['clientDataJSON'] ?? ''), 'webauthn.get', 'connexion');
    $cle = db_one('SELECT * FROM cle_securite WHERE identifiant = ? AND membre_id = ?', [(string) ($reponse['id'] ?? ''), $member['id']]);
    if ($cle === null) {
        throw new WebauthnErreur('Clé inconnue pour ce compte');
    }
    $authData = b64url_decode((string) ($reponse['authenticatorData'] ?? ''));
    $auth = webauthn_auth_data($authData, false);
    $signature = b64url_decode((string) ($reponse['signature'] ?? ''));
    if (openssl_verify($authData . hash('sha256', $client, true), $signature, $cle['cle_publique'], OPENSSL_ALGO_SHA256) !== 1) {
        throw new WebauthnErreur('Signature incorrecte');
    }
    // Compteur : s'il est utilisé et qu'il recule, la clé a peut-être été copiée
    if (($auth['compteur'] !== 0 || (int) $cle['compteur'] !== 0) && $auth['compteur'] <= (int) $cle['compteur']) {
        throw new WebauthnErreur('Compteur de la clé incohérent : clé peut-être copiée');
    }
    db_query('UPDATE cle_securite SET compteur = ?, derniere_utilisation = NOW() WHERE id = ?', [$auth['compteur'], $cle['id']]);
}
