<?php
// Captcha invisible, sans service externe : champ piège, délai minimal de saisie,
// et petite preuve de travail calculée par le navigateur pendant que la personne remplit le formulaire
declare(strict_types=1);

const DEFI_DIFFICULTE = 16;      // bits à zéro en tête de sha256 : environ 65 000 essais, 1 à 3 s sur un téléphone
const DEFI_DELAI_MIN = 2;        // secondes : un robot remplit le formulaire instantanément
const DEFI_DUREE_MAX = 7200;     // secondes : au-delà, le formulaire a expiré
const SANS_JS_MAX = 3;           // sans JavaScript (pas de preuve de travail) : 3 envois par 15 min et par adresse

function humain_champs(): string
{
    $salt = bin2hex(random_bytes(12));
    $time = (string) time();
    $challenge = $salt . '.' . DEFI_DIFFICULTE . '.' . $time;
    $signed = $challenge . '.' . hash_hmac('sha256', $challenge, secret_key());
    return '<input type="hidden" name="_h_defi" value="' . e($signed) . '" data-defi>'
        . '<input type="hidden" name="_h_preuve" value="">'
        . '<div class="piege" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>';
}

// Vrai si la requête vient vraisemblablement d'une personne
function humain_verifier(): bool
{
    if (trim((string) ($_POST['site_web'] ?? '')) !== '') {
        return false;
    }
    $parts = explode('.', (string) ($_POST['_h_defi'] ?? ''));
    if (count($parts) !== 4) {
        return false;
    }
    [$salt, $difficulty, $time, $mac] = $parts;
    $challenge = "$salt.$difficulty.$time";
    if (!hash_equals(hash_hmac('sha256', $challenge, secret_key()), $mac) || (int) $difficulty !== DEFI_DIFFICULTE) {
        return false;
    }
    $age = time() - (int) $time;
    if ($age < DEFI_DELAI_MIN || $age > DEFI_DUREE_MAX) {
        return false;
    }
    // Chaque défi ne sert qu'une fois
    db_query('DELETE FROM defi_utilise WHERE expire_le < NOW()');
    try {
        db_query('INSERT INTO defi_utilise (empreinte, expire_le) VALUES (?, NOW() + INTERVAL ? SECOND)', [hash('sha256', $challenge), DEFI_DUREE_MAX]);
    } catch (PDOException) {
        return false;
    }
    $proof = (string) ($_POST['_h_preuve'] ?? '');
    if ($proof === '') {
        // Navigateur sans JavaScript : accepté, mais rarement
        return !too_many_attempts('sansjs|' . client_ip(), SANS_JS_MAX);
    }
    return ctype_digit($proof) && strlen($proof) <= 12 && leading_zero_bits(hash('sha256', $salt . $proof, true)) >= DEFI_DIFFICULTE;
}

function leading_zero_bits(string $binary): int
{
    $bits = 0;
    foreach (str_split($binary) as $char) {
        $byte = ord($char);
        if ($byte === 0) {
            $bits += 8;
            continue;
        }
        return $bits + (7 - (int) floor(log($byte, 2)));
    }
    return $bits;
}

// Réponse commune quand la vérification échoue : rien n'indique ce qui a manqué
function humain_refus(): never
{
    http_response_code(400);
    render('erreur', ['titre' => "La vérification anti-robot n'a pas abouti : rechargez la page et réessayez."]);
    exit;
}
