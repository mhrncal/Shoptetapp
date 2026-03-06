<?php

namespace ShopCode\Services;

/**
 * Wrapper — deleguje na Core\Mailer
 */
class Mailer
{
    public static function notifySuperadmin(string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $to = defined('SUPERADMIN_EMAIL') ? SUPERADMIN_EMAIL : null;
        if (!$to) return false;
        return \ShopCode\Core\Mailer::send($to, $subject, $htmlBody, $textBody);
    }

    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        return \ShopCode\Core\Mailer::send($to, $subject, $htmlBody, $textBody);
    }
}
