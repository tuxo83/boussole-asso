<?php
// Valeurs sensibles en base (mot de passe SMTP…) : chiffrées avec la clé de l'installation (config.php, hors base)
declare(strict_types=1);

function secret_key(): string
{
    return hash('sha256', 'boussole-secret|' . (config()['secret'] ?? ''), true);
}

function secret_encrypt(string $value): string
{
    $iv = random_bytes(12);
    $data = openssl_encrypt($value, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $data);
}

function secret_decrypt(string $stored): ?string
{
    $raw = base64_decode($stored, true);
    if ($raw === false || strlen($raw) < 29) {
        return null;
    }
    $value = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $value === false ? null : $value;
}
