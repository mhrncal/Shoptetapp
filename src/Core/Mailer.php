<?php

namespace ShopCode\Core;

/**
 * Jednoduchý SMTP mailer bez závislostí
 */
class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $host     = MAIL_HOST;
        $port     = MAIL_PORT;
        $user     = MAIL_USER;
        $pass     = MAIL_PASS;
        $from     = MAIL_FROM;
        $fromName = MAIL_FROM_NAME;

        if (empty($host) || empty($from)) {
            error_log("Mailer: SMTP není nakonfigurován");
            return false;
        }

        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }

        $boundary = 'boundary_' . md5(uniqid());
        $msgId    = '<' . uniqid() . '@shopcode.cz>';

        // Sestavení multipart emailu
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($textBody) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($htmlBody) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        try {
            $ssl     = ($port === 465) ? 'ssl' : 'tcp';
            $timeout = 15;
            $conn    = fsockopen("{$ssl}://{$host}", $port, $errno, $errstr, $timeout);
            if (!$conn) throw new \RuntimeException("Nelze se připojit: {$errstr}");

            self::expect($conn, 220);
            self::cmd($conn, "EHLO shopcode.cz");
            self::expect($conn, 250);

            if ($port !== 465) {
                self::cmd($conn, "STARTTLS");
                self::expect($conn, 220);
                stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                self::cmd($conn, "EHLO shopcode.cz");
                self::expect($conn, 250);
            }

            self::cmd($conn, "AUTH LOGIN");
            self::expect($conn, 334);
            self::cmd($conn, base64_encode($user));
            self::expect($conn, 334);
            self::cmd($conn, base64_encode($pass));
            self::expect($conn, 235);

            self::cmd($conn, "MAIL FROM:<{$from}>");
            self::expect($conn, 250);
            self::cmd($conn, "RCPT TO:<{$to}>");
            self::expect($conn, 250);
            self::cmd($conn, "DATA");
            self::expect($conn, 354);

            $encodedFrom = "=?UTF-8?B?" . base64_encode($fromName) . "?=";
            $encodedSubj = "=?UTF-8?B?" . base64_encode($subject)  . "?=";

            $headers  = "From: {$encodedFrom} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$encodedSubj}\r\n";
            $headers .= "Message-ID: {$msgId}\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

            fwrite($conn, $headers . "\r\n" . $body . "\r\n.\r\n");
            self::expect($conn, 250);
            self::cmd($conn, "QUIT");
            fclose($conn);
            return true;

        } catch (\Exception $e) {
            error_log("Mailer error: " . $e->getMessage());
            return false;
        }
    }

    private static function cmd($conn, string $cmd): void
    {
        fwrite($conn, $cmd . "\r\n");
    }

    private static function expect($conn, int $code): string
    {
        $response = '';
        while ($line = fgets($conn, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $actual = (int)substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException("SMTP očekáváno {$code}, dostáno {$actual}: {$response}");
        }
        return $response;
    }
}
