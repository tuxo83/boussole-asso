<?php
// Tests unitaires de la vérification WebAuthn : CBOR malformé, clés COSE, signatures
declare(strict_types=1);
require __DIR__ . '/../app/core/webauthn.php';
$ok = $ko = 0;
function check(string $label, bool $test): void
{
    global $ok, $ko;
    $test ? $ok++ : $ko++;
    echo ($test ? 'OK  ' : 'KO  ') . "$label\n";
}
function refuse(callable $f): bool
{
    try {
        $f();
        return false;
    } catch (WebauthnErreur) {
        return true;
    }
}

check('CBOR : entier, texte, tableau, dictionnaire', cbor_decode("\xa2\x01\x02\x63abc\x82\x01\x20") === [1 => 2, 'abc' => [1, -1]]);
check('CBOR tronqué refusé', refuse(fn() => cbor_decode("\x58\x20abc")));
check('CBOR : longueur indéfinie refusée', refuse(fn() => cbor_decode("\x5f\x41a\xff")));
check('CBOR : imbrication trop profonde refusée', refuse(fn() => cbor_decode(str_repeat("\x81", 20) . "\x01")));
check('CBOR : vide refusé', refuse(fn() => cbor_decode('')));
check('base64url : aller-retour', b64url_decode(b64url_encode("\xff\xfe\x00abc")) === "\xff\xfe\x00abc");
check('base64url invalide refusé', refuse(fn() => b64url_decode('!!!')));

// Clé EC P-256 réelle → COSE → PEM → vérification d'une signature
$key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$d = openssl_pkey_get_details($key)['ec'];
$pem = cose_vers_pem([1 => 2, 3 => -7, -1 => 1, -2 => str_pad($d['x'], 32, "\0", STR_PAD_LEFT), -3 => str_pad($d['y'], 32, "\0", STR_PAD_LEFT)]);
openssl_sign('données', $sig, $key, OPENSSL_ALGO_SHA256);
check('ES256 : signature valide acceptée', openssl_verify('données', $sig, $pem, OPENSSL_ALGO_SHA256) === 1);
check('ES256 : données modifiées refusées', openssl_verify('donnéeS', $sig, $pem, OPENSSL_ALGO_SHA256) !== 1);
check('EC : point de mauvaise taille refusé', refuse(fn() => cose_vers_pem([1 => 2, 3 => -7, -1 => 1, -2 => 'x', -3 => 'y'])));
check('EC : point hors courbe refusé', refuse(fn() => cose_vers_pem([1 => 2, 3 => -7, -1 => 1, -2 => str_repeat("\x01", 32), -3 => str_repeat("\x02", 32)])));
check('algorithme non pris en charge refusé', refuse(fn() => cose_vers_pem([1 => 1, 3 => -8, -1 => 6, -2 => str_repeat('a', 32)])));

// Clé RSA réelle (RS256)
$rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$r = openssl_pkey_get_details($rsa)['rsa'];
$pem = cose_vers_pem([1 => 3, 3 => -257, -1 => $r['n'], -2 => $r['e']]);
openssl_sign('données', $sig, $rsa, OPENSSL_ALGO_SHA256);
check('RS256 : signature valide acceptée', openssl_verify('données', $sig, $pem, OPENSSL_ALGO_SHA256) === 1);
check('RSA : clé trop courte refusée', refuse(fn() => cose_vers_pem([1 => 3, 3 => -257, -1 => str_repeat("\xff", 64), -2 => "\x01\x00\x01"])));

echo "Bilan : $ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
