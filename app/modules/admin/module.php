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
        // Tester les réglages d'envoi tels que saisis, sans les enregistrer ; le formulaire reste rempli
        ['POST', '/admin/mail-test', function () {
            $admin = require_right('admin');
            [$values] = admin_settings_validate($_POST);
            $saisis = array_intersect_key($values, array_flip(['mail_expediteur', 'smtp_hote', 'smtp_port', 'smtp_securite', 'smtp_utilisateur']));
            if (($_POST['smtp_mot_de_passe'] ?? '') !== '') {
                $saisis['smtp_mot_de_passe'] = (string) $_POST['smtp_mot_de_passe'];
            }
            $reglages = mail_reglages($saisis);
            $dest = trim((string) ($_POST['test_destinataire'] ?? '')) ?: $admin['email'];
            $voie = $reglages['smtp_hote'] !== '' ? "le serveur {$reglages['smtp_hote']}:{$reglages['smtp_port']}" : "la fonction d'envoi de l'hébergeur (aucun serveur SMTP réglé : le message risque de ne jamais arriver)";
            $ok = mail_send($dest, "Test d'envoi", "Ce message confirme que l'envoi des e-mails de " . setting('nom_association', 'Boussole') . " fonctionne.\n", $reglages);
            $test = $ok
                ? ['succes', "Accepté par $voie pour $dest. Vérifiez la boîte de réception (et les indésirables). Pensez à enregistrer les réglages s'ils sont nouveaux."]
                : ['erreur', "Échec de l'envoi par $voie : " . (mail_erreur() ?? 'raison inconnue') . '.'];
            journal(null, $admin, 'Test d\'envoi d\'e-mail : ' . ($ok ? 'réussi' : 'échec'));
            render('admin/parametres', ['titre' => 'Réglages', 'v' => $values + settings_all(), 'erreurs' => [], 'test_mail' => $test, 'test_destinataire' => $dest]);
        }],
    ],
];

function admin_settings_validate(array $input): array
{
    $text = fn(string $key, int $max) => mb_substr(trim((string) ($input[$key] ?? '')), 0, $max);
    $v = [
        'nom_association' => $text('nom_association', 150),
        'slogan' => $text('slogan', 200),
        'bandeau_legende' => $text('bandeau_legende', 300),
        'presentation' => $text('presentation', 20000),
        'pied_de_page' => $text('pied_de_page', 500),
        'couleur_principale' => $text('couleur_principale', 7),
        'couleur_accent' => $text('couleur_accent', 7),
        'couleur_membres' => $text('couleur_membres', 7),
        'css_personnalise' => $text('css_personnalise', 50000),
        'adresse_site' => rtrim($text('adresse_site', 300), '/'),
        'mail_expediteur' => $text('mail_expediteur', 190),
        'smtp_hote' => $text('smtp_hote', 190),
        'smtp_port' => $text('smtp_port', 5),
        'smtp_securite' => $text('smtp_securite', 10),
        'smtp_utilisateur' => $text('smtp_utilisateur', 190),
        'rappel_jalons' => $text('rappel_jalons', 50),
        'rappel_jalons_evenements' => $text('rappel_jalons_evenements', 50),
        'rappel_participants' => $text('rappel_participants', 50),
        'construction_active' => ($input['construction_active'] ?? '') === '1' ? '1' : '',
        'construction_code' => preg_replace('/\D/', '', $text('construction_code', 20)),
        'contact_destinataire' => filter_var($text('contact_destinataire', 190), FILTER_VALIDATE_EMAIL) ? $text('contact_destinataire', 190) : '',
        'contact_conservation_mois' => (string) max(1, min(60, (int) ($input['contact_conservation_mois'] ?? 12))),
        'journal_conservation_ans' => (string) max(1, min(10, (int) ($input['journal_conservation_ans'] ?? 3))),
        'meteo_auto_retard_jours' => (string) max(1, min(365, (int) ($input['meteo_auto_retard_jours'] ?? 15))),
        'meteo_auto_dates_bloque' => (string) max(2, min(20, (int) ($input['meteo_auto_dates_bloque'] ?? 3))),
        'meteo_auto_horizon_jours' => (string) max(1, min(365, (int) ($input['meteo_auto_horizon_jours'] ?? 30))),
        'application_active' => ($input['application_active'] ?? '') === '1' ? '1' : '0',
        'application_proposer' => ($input['application_proposer'] ?? '') === '1' ? '1' : '0',
        'application_nom_court' => $text('application_nom_court', 30),
        'appareil_duree_jours' => (string) max(0, min(400, (int) ($input['appareil_duree_jours'] ?? 90))),
        'mfa_validite_heures' => (string) max(1, min(720, (int) ($input['mfa_validite_heures'] ?? 12))),
        'fichiers_apercu' => ($input['fichiers_apercu'] ?? '') === '1' ? '1' : '0',
        'arrivee_cotisant' => connexion_arrivee_valide($text('arrivee_cotisant', 120)) ? $text('arrivee_cotisant', 120) : '',
        'arrivee_non_cotisant' => connexion_arrivee_valide($text('arrivee_non_cotisant', 120)) ? $text('arrivee_non_cotisant', 120) : '',
    ];
    // Code d'accès choisi au hasard si on active le mode sans en donner un
    if ($v['construction_active'] === '1' && $v['construction_code'] === '') {
        $v['construction_code'] = sprintf('%06d', random_int(0, 999999));
    }
    $erreurs = [];
    if ($v['nom_association'] === '') {
        $erreurs['nom_association'] = 'Obligatoire.';
    }
    foreach (['couleur_principale', 'couleur_accent', 'couleur_membres'] as $key) {
        // Couleurs des niveaux d'accès : facultatives (vide = couleur par défaut)
        if ($key === 'couleur_membres' && $v[$key] === '') {
            continue;
        }
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
    if ($v['construction_code'] !== '' && !preg_match('/^\d{6}$/', $v['construction_code'])) {
        $erreurs['construction_code'] = 'Six chiffres.';
    }
    foreach (['rappel_jalons', 'rappel_jalons_evenements', 'rappel_participants'] as $key) {
        if (!preg_match('/^(\s*\d{1,3}\s*(,\s*\d{1,3}\s*)*)?$/', $v[$key])) {
            $erreurs[$key] = 'Des nombres de jours séparés par des virgules, ex. « 14, 3 ».';
        }
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
    if ($info === false || !isset($types[$info[2]]) || $info[0] > LOGO_COTE_MAX || $info[1] > LOGO_COTE_MAX) {
        $erreurs['logo'] = 'Image PNG, JPEG ou WebP de 1 Mo et ' . LOGO_COTE_MAX . ' pixels de côté au plus.';
        return null;
    }
    $name = 'uploads/logo-' . bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], BOUSSOLE_ROOT . '/' . $name)) {
        $erreurs['logo'] = "Le logo n'a pas pu être enregistré (droits du dossier uploads).";
        return null;
    }
    return $name;
}
