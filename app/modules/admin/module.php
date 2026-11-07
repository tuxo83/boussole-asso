<?php
// Administration : nom et apparence de l'association, adresse du site, envoi des e-mails
declare(strict_types=1);

const LOGO_TAILLE_MAX = 1048576;

return [
    'ordre' => 95,
    'routes' => [
        ['GET', '/admin/parametres', function () {
            require_right('admin');
            render('admin/parametres', ['titre' => 'Réglages', 'v' => settings_all(), 'erreurs' => []]);
        }],
        ['POST', '/admin/parametres', function () {
            $admin = require_right('admin');
            [$values, $erreurs] = admin_settings_validate($_POST);
            $logo = admin_logo_upload($_FILES['logo'] ?? null, $erreurs);
            if ($erreurs) {
                http_response_code(422);
                render('admin/parametres', ['titre' => 'Réglages', 'v' => $values + settings_all(), 'erreurs' => $erreurs]);
                return;
            }
            foreach ($values as $key => $value) {
                setting_set($key, $value);
            }
            if (($_POST['smtp_mot_de_passe'] ?? '') !== '') {
                setting_set('smtp_mot_de_passe', secret_encrypt((string) $_POST['smtp_mot_de_passe']));
            }
            if ($logo !== null) {
                setting_set('logo', $logo);
            }
            flash('succes', 'Réglages enregistrés.');
            redirect('/admin/parametres');
        }],
        ['POST', '/admin/mail-test', function () {
            $admin = require_right('admin');
            $sent = mail_send($admin['email'], 'Test d\'envoi', "Ce message confirme que l'envoi des e-mails fonctionne.\n");
            flash($sent ? 'succes' : 'erreur', $sent ? "E-mail de test envoyé à {$admin['email']}." : "Échec de l'envoi : vérifiez les réglages (journal d'erreurs du serveur).");
            redirect('/admin/parametres');
        }],
    ],
];

function admin_settings_validate(array $input): array
{
    $text = fn(string $key, int $max) => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
    $v = [
        'nom_association' => $text('nom_association', 150),
        'slogan' => $text('slogan', 200),
        'presentation' => $text('presentation', 20000),
        'pied_de_page' => $text('pied_de_page', 500),
        'couleur_principale' => $text('couleur_principale', 7),
        'couleur_accent' => $text('couleur_accent', 7),
        'css_personnalise' => $text('css_personnalise', 50000),
        'adresse_site' => rtrim($text('adresse_site', 300), '/'),
        'mail_expediteur' => $text('mail_expediteur', 190),
        'smtp_hote' => $text('smtp_hote', 190),
        'smtp_port' => $text('smtp_port', 5),
        'smtp_securite' => $text('smtp_securite', 10),
        'smtp_utilisateur' => $text('smtp_utilisateur', 190),
    ];
    $erreurs = [];
    if ($v['nom_association'] === '') {
        $erreurs['nom_association'] = 'Obligatoire.';
    }
    foreach (['couleur_principale', 'couleur_accent'] as $key) {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v[$key])) {
            $erreurs[$key] = 'Couleur au format #rrggbb.';
        }
    }
    if ($v['adresse_site'] !== '' && !preg_match('#^https?://[^\s/]+(/[^\s]*)?$#', $v['adresse_site'])) {
        $erreurs['adresse_site'] = 'Adresse complète, ex. https://exemple.org';
    }
    if ($v['mail_expediteur'] !== '' && !filter_var($v['mail_expediteur'], FILTER_VALIDATE_EMAIL)) {
        $erreurs['mail_expediteur'] = 'Adresse e-mail invalide.';
    }
    if ($v['smtp_port'] !== '' && (!ctype_digit($v['smtp_port']) || (int) $v['smtp_port'] > 65535)) {
        $erreurs['smtp_port'] = 'Port invalide.';
    }
    if (!in_array($v['smtp_securite'], ['starttls', 'ssl', 'aucune'], true)) {
        $erreurs['smtp_securite'] = 'Valeur invalide.';
    }
    // La feuille de style est servie à part (text/css) ; on refuse seulement ce qui pourrait sortir du CSS
    if (stripos($v['css_personnalise'], '</style') !== false || stripos($v['css_personnalise'], '<script') !== false) {
        $erreurs['css_personnalise'] = 'Du CSS uniquement.';
    }
    return [$v, $erreurs];
}

// Logo : PNG, JPEG ou WebP (pas de SVG, qui peut contenir du script), 1 Mo maximum, nom aléatoire
function admin_logo_upload(?array $file, array &$erreurs): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
    $info = $file['error'] === UPLOAD_ERR_OK && $file['size'] <= LOGO_TAILLE_MAX ? @getimagesize($file['tmp_name']) : false;
    if ($info === false || !isset($types[$info[2]])) {
        $erreurs['logo'] = 'Image PNG, JPEG ou WebP de 1 Mo maximum.';
        return null;
    }
    $name = 'uploads/logo-' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], BOUSSOLE_ROOT . '/' . $name)) {
        $erreurs['logo'] = "Le logo n'a pas pu être enregistré (droits du dossier uploads).";
        return null;
    }
    return $name;
}
