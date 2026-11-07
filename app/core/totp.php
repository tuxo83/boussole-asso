<?php
// Double authentification par application (TOTP, RFC 6238) : codes à 6 chiffres renouvelés toutes les 30 s
declare(strict_types=1);

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

function base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function base32_decode(string $text): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $text))) as $char) {
        $bits .= str_pad(decbin(strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr(bindec($byte));
        }
    }
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $hash = hash_hmac('sha1', pack('J', $step), base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff) % 1000000;
    return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}

// Accepte le code de la période courante et des voisines (décalage d'horloge)
function totp_verify(string $secret, string $code, ?int $now = null): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    $step = intdiv($now ?? time(), 30);
    foreach ([-1, 0, 1] as $delta) {
        if (hash_equals(totp_code($secret, $step + $delta), $code)) {
            return true;
        }
    }
    return false;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = (string) setting('nom_association', 'Boussole');
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
}
