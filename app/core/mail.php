<?php
// Envoi d'e-mails : SMTP si réglé dans l'admin, sinon la fonction mail() de l'hébergeur
declare(strict_types=1);

// Dernière erreur d'envoi, lisible (sans mot de passe) : affichée par le test des réglages
function mail_erreur(?string $erreur = null, bool $effacer = false): ?string
{
    static $derniere = null;
    if ($effacer) {
        $derniere = null;
    } elseif ($erreur !== null) {
        $derniere = $erreur;
        error_log('Boussole e-mail : ' . $erreur);
    }
    return $derniere;
}

// Réglages SMTP : ceux enregistrés, ou ceux d'un formulaire pas encore enregistré (test)
function mail_reglages(array $autres = []): array
{
    return $autres + [
        'mail_expediteur' => (string) setting('mail_expediteur', ''),
        'smtp_hote' => (string) setting('smtp_hote', ''),
        'smtp_port' => (string) setting('smtp_port', '587'),
        'smtp_securite' => (string) setting('smtp_securite', 'starttls'),
        'smtp_utilisateur' => (string) setting('smtp_utilisateur', ''),
        'smtp_mot_de_passe' => secret_decrypt((string) setting('smtp_mot_de_passe', '')) ?? '',
    ];
}

// $reglages fourni : test réel, même en développement (pas de fichier à la place de l'envoi)
// $pieces : fichiers joints, chacun ['nom' => …, 'type' => …, 'contenu' => …]
function mail_send(string $to, string $subject, string $body, ?array $reglages = null, ?string $repondre_a = null, array $pieces = []): bool
{
    $test = $reglages !== null;
    $reglages ??= mail_reglages();
    $from = $reglages['mail_expediteur'];
    $name = (string) setting('nom_association', 'Boussole');
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_erreur($from === '' ? "aucune adresse d'expédition réglée" : 'adresse d\'expédition ou de destination invalide');
        return false;
    }
    $headers = [
        'From' => mail_encode($name) . " <$from>",
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=utf-8',
        'Content-Transfer-Encoding' => 'base64',
    ];
    if ($repondre_a !== null && filter_var($repondre_a, FILTER_VALIDATE_EMAIL)) {
        $headers['Reply-To'] = $repondre_a;
    }
    $body64 = chunk_split(base64_encode($body));
    if ($pieces) {
        // Texte puis fichiers, chaque partie en base64 : aucune ligne ne commence par « . »
        $frontiere = 'bsl-' . bin2hex(random_bytes(12));
        $headers['Content-Type'] = "multipart/mixed; boundary=\"$frontiere\"";
        unset($headers['Content-Transfer-Encoding']);
        $body64 = "--$frontiere\nContent-Type: text/plain; charset=utf-8\nContent-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode($body), 76, "\n");
        foreach ($pieces as $piece) {
            $nom = mail_encode(str_replace(['"', '\\', "\r", "\n"], '_', (string) $piece['nom']));
            $body64 .= "--$frontiere\nContent-Type: {$piece['type']}; name=\"$nom\"\nContent-Disposition: attachment; filename=\"$nom\"\n"
                . "Content-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode((string) $piece['contenu']), 76, "\n");
        }
        $body64 .= "--$frontiere--\n";
    }

    // Développement : les e-mails sont écrits dans un fichier au lieu d'être envoyés
    if (!$test && !empty(config()['mail_fichier'])) {
        $joints = implode('', array_map(fn($p) => "[Pièce jointe] {$p['nom']} ({$p['type']}, " . strlen((string) $p['contenu']) . " octets)\n", $pieces));
        file_put_contents(config()['mail_fichier'], "To: $to\nSubject: $subject\n\n$body\n$joints----\n", FILE_APPEND | LOCK_EX);
        return true;
    }
    if ($reglages['smtp_hote'] !== '') {
        return smtp_send($from, $to, mail_encode($subject), $headers, $body64, $reglages);
    }
    $lines = '';
    foreach ($headers as $key => $value) {
        $lines .= "$key: $value\r\n";
    }
    $ok = mail($to, mail_encode($subject), $body64, $lines);
    if (!$ok) {
        mail_erreur("la fonction d'envoi de l'hébergeur a refusé le message (aucun serveur SMTP réglé)");
    }
    return $ok;
}

function mail_encode(string $text): string
{
    return preg_match('/[^\x20-\x7e]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

function smtp_send(string $from, string $to, string $subject, array $headers, string $body64, ?array $reglages = null): bool
{
    $reglages ??= mail_reglages();
    $host = $reglages['smtp_hote'];
    $port = (int) ($reglages['smtp_port'] ?: 587);
    $security = $reglages['smtp_securite'];
    $user = $reglages['smtp_utilisateur'];
    $password = $reglages['smtp_mot_de_passe'];

    $socket = @stream_socket_client(($security === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15);
    if ($socket === false) {
        mail_erreur("connexion impossible à $host:$port ($errstr)");
        return false;
    }
    stream_set_timeout($socket, 15);
    try {
        smtp_expect($socket, 220);
        $domain = substr(strrchr($from, '@'), 1);
        smtp_command($socket, "EHLO $domain", 250);
        if ($security === 'starttls') {
            smtp_command($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS refusé');
            }
            smtp_command($socket, "EHLO $domain", 250);
        }
        if ($user !== '') {
            smtp_command($socket, 'AUTH LOGIN', 334);
            smtp_command($socket, base64_encode($user), 334);
            try {
                smtp_command($socket, base64_encode($password), 235);
            } catch (RuntimeException $exception) {
                throw new RuntimeException('identifiant ou mot de passe refusé : ' . $exception->getMessage());
            }
        }
        smtp_command($socket, "MAIL FROM:<$from>", 250);
        smtp_command($socket, "RCPT TO:<$to>", 250);
        smtp_command($socket, 'DATA', 354);
        $message = "To: <$to>\r\nSubject: $subject\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . "@$domain>\r\n";
        foreach ($headers as $key => $value) {
            $message .= "$key: $value\r\n";
        }
        // Le corps est en base64 : aucune ligne ne commence par « . »
        $message .= "\r\n" . str_replace("\n", "\r\n", rtrim($body64)) . "\r\n.";
        smtp_command($socket, $message, 250);
        smtp_command($socket, 'QUIT', 221);
        return true;
    } catch (RuntimeException $exception) {
        mail_erreur('SMTP ' . $exception->getMessage());
        return false;
    } finally {
        fclose($socket);
    }
}

function smtp_command($socket, string $command, int $expected): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $expected);
}

function smtp_expect($socket, int $expected): void
{
    $response = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    if ((int) substr($response, 0, 3) !== $expected) {
        throw new RuntimeException("réponse inattendue : " . trim($response));
    }
}
