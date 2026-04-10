<?php
require_once __DIR__ . '/../config/mail.php';

function mail_log_path(): string {
    return APP_ROOT . '/backend/logs/mail.log';
}

function send_system_mail(string $to, string $subject, string $body): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if ((string) MAIL_ENABLED !== '1') {
        $line = sprintf("[%s] MAIL_DISABLED to=%s subject=%s\n%s\n\n", date('Y-m-d H:i:s'), $to, $subject, $body);
        file_put_contents(mail_log_path(), $line, FILE_APPEND);
        return true;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/plain; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
    ];

    $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
    $line = sprintf("[%s] MAIL_%s to=%s subject=%s\n", date('Y-m-d H:i:s'), $ok ? 'SENT' : 'FAILED', $to, $subject);
    file_put_contents(mail_log_path(), $line, FILE_APPEND);
    return $ok;
}
