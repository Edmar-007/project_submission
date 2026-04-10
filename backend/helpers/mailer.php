<?php
require_once __DIR__ . '/../config/mail.php';

function mail_log_path(): string {
    $dir = APP_ROOT . '/backend/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir . '/mail.log';
}

function mail_write_log(string $message): void {
    file_put_contents(mail_log_path(), '[' . date('Y-m-d H:i:s') . '] ' . $message . "
", FILE_APPEND);
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_expect($socket, array $expectedCodes): string {
    $response = smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP unexpected response: ' . trim($response));
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string {
    fwrite($socket, $command . "
");
    return smtp_expect($socket, $expectedCodes);
}

function send_system_mail_via_smtp(string $to, string $subject, string $body): bool {
    $host = (string) MAIL_HOST;
    $port = (int) MAIL_PORT;
    $secure = strtolower((string) MAIL_SECURE);
    $username = (string) MAIL_USERNAME;
    $password = (string) MAIL_PASSWORD;

    $remote = $secure === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($socket, 20);
    smtp_expect($socket, [220]);
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
    smtp_command($socket, 'EHLO ' . $serverName, [250]);

    if ($secure === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Unable to start TLS encryption for SMTP.');
        }
        smtp_command($socket, 'EHLO ' . $serverName, [250]);
    }

    if ($username !== '' && $password !== '') {
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
    }

    smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $message = implode("
", $headers) . "

" . str_replace(["
", ""], "
", $body);
    $message = preg_replace('/^\./m', '..', $message) . "
.";
    fwrite($socket, $message . "
");
    smtp_expect($socket, [250]);
    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
    return true;
}

function send_system_mail(string $to, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_write_log('MAIL_SKIPPED invalid_address=' . $to . ' subject=' . $subject);
        return false;
    }

    if ((string) MAIL_ENABLED !== '1') {
        mail_write_log('MAIL_DISABLED to=' . $to . ' subject=' . $subject . ' body=' . str_replace(["", "
"], ' ', substr($body, 0, 220)));
        return true;
    }

    try {
        if ((string) MAIL_HOST !== '' && ((string) MAIL_USERNAME !== '' || (string) MAIL_PASSWORD !== '')) {
            $ok = send_system_mail_via_smtp($to, $subject, $body);
            mail_write_log('MAIL_SENT_SMTP to=' . $to . ' subject=' . $subject);
            return $ok;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=UTF-8',
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        ];
        $ok = @mail($to, $subject, $body, implode("
", $headers));
        mail_write_log('MAIL_' . ($ok ? 'SENT_MAIL' : 'FAILED_MAIL') . ' to=' . $to . ' subject=' . $subject);
        return $ok;
    } catch (Throwable $e) {
        mail_write_log('MAIL_FAILED to=' . $to . ' subject=' . $subject . ' error=' . $e->getMessage());
        return false;
    }
}
