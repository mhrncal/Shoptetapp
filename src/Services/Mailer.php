<?php

namespace ShopCode\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * SMTP mailer wrapper.
 * Konfiguruje se přes config/config.php konstanty.
 *
 * Vyžadované konstanty v config.php:
 *   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD,
 *   MAIL_FROM, MAIL_FROM_NAME, MAIL_ENCRYPTION (tls|ssl|'')
 *   SUPERADMIN_EMAIL
 */
class Mailer
{
    /**
     * Odešle email superadminovi.
     */
    public static function notifySuperadmin(string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $to = defined('SUPERADMIN_EMAIL') ? SUPERADMIN_EMAIL : null;
        if (!$to) return false;

        return self::send($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Obecná metoda pro odeslání emailu.
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        // Načteme PHPMailer ze složky lib/
        $libPath = ROOT . '/lib/PHPMailer/';
        require_once $libPath . 'Exception.php';
        require_once $libPath . 'PHPMailer.php';
        require_once $libPath . 'SMTP.php';

        $mail = new PHPMailer(true);

        try {
            // SMTP konfigurace
            $mail->isSMTP();
            $mail->Host        = defined('MAIL_HOST')     ? MAIL_HOST     : 'localhost';
            $mail->SMTPAuth    = defined('MAIL_USERNAME')  && MAIL_USERNAME !== '';
            $mail->Username    = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
            $mail->Password    = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
            $mail->Port        = defined('MAIL_PORT')     ? (int)MAIL_PORT : 587;

            $encryption = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls';
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure  = false;
            }

            // Časové limity
            $mail->Timeout    = 10;
            $mail->SMTPDebug  = 0; // 0 = bez výstupu, 2 = verbose (pro ladění)

            // Odesílatel
            $fromEmail = defined('MAIL_FROM')      ? MAIL_FROM      : (defined('SUPERADMIN_EMAIL') ? SUPERADMIN_EMAIL : 'noreply@shopcode.cz');
            $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'ShopCode');
            $mail->setFrom($fromEmail, $fromName);

            // Příjemce
            $mail->addAddress($to);

            // Obsah
            $mail->CharSet  = 'UTF-8';
            $mail->Subject  = $subject;
            $mail->isHTML(true);
            $mail->Body     = $htmlBody;
            $mail->AltBody  = $textBody ?: strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody));

            $mail->send();
            return true;

        } catch (MailerException $e) {
            // Logujeme chybu do souboru — nechceme způsobit výjimku v calleru
            self::logError("Mailer error to {$to}: " . $mail->ErrorInfo);
            return false;
        } catch (\Throwable $e) {
            self::logError("Mailer fatal error: " . $e->getMessage());
            return false;
        }
    }

    private static function logError(string $message): void
    {
        $logFile = ROOT . '/tmp/mail-errors.log';
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }
}
