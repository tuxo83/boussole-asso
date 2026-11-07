<?php
// Envoi d'e-mails : SMTP si réglé dans l'admin, sinon la fonction mail() de l'hébergeur
declare(strict_types=1);

function mail_send(string $to, string $subject, string $body): bool
{
    $from = (string) setting('mail_expediteur', '');
    $name = (string) setting('nom_association', 'Boussole');
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('Boussole : e-mail non envoyé (expéditeur ou destinataire invalide)');
        return false;
    }
    $headers = [
        'From' => mail_encode($name) . " <$from>",
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=utf-8',
        'Content-Transfer-Encoding' => 'base64',
    ];
    $body64 = chunk_split(base64_encode($body));

    // Développement : les e-mails sont écrits dans un fichier au lieu d'être envoyés
    if (!empty(config()['mail_fichier'])) {
        file_put_contents(config()['mail_fichier'], "To: $to\nSubject: $subject\n\n$body\n----\n", FILE_APPEND | LOCK_EX);
        return true;
    }
    if (setting('smtp_hote', '') !== '') {
        return smtp_send($from, $to, mail_encode($subject), $headers, $body64);
    }
    $lines = '';
    foreach ($headers as $key => $value) {
        $lines .= "$key: $value\r\n";
    }
    return mail($to, mail_encode($subject), $body64, $lines);
}

function mail_encode(string $text): string
{
    return preg_match('/[^\x20-\x7e]/', $text) ? '=?UTF-8?B?' . base64_encode($text) . '?=' : $text;
}

function smtp_send(string $from, string $to, string $subject, array $headers, string $body64): bool
{
    $host = (string) setting('smtp_hote');
    $port = (int) setting('smtp_port', '587');
    $security = (string) setting('smtp_securite', 'starttls');
    $user = (string) setting('smtp_utilisateur', '');
    $password = secret_decrypt((string) setting('smtp_mot_de_passe', '')) ?? '';

    $socket = @stream_socket_client(($security === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15);
    if ($socket === false) {
        error_log("Boussole SMTP : connexion impossible ($errstr)");
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
            smtp_command($socket, base64_encode($password), 235);
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
        error_log('Boussole SMTP : ' . $exception->getMessage());
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
