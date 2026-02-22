<?php

namespace ShopCode\Services;

/**
 * Odesílá notifikační emaily superadminovi.
 * Tři typy:
 *   1. XML import selhal
 *   2. Webhook selhal (všechny retry vyčerpány)
 *   3. Uživatel zamknut (brute-force)
 */
class AdminNotifier
{
    // ----------------------------------------------------------------
    // 1. XML import selhal
    // ----------------------------------------------------------------

    public static function xmlImportFailed(
        int    $userId,
        string $userEmail,
        string $feedUrl,
        string $errorMessage,
        int    $retryCount,
        int    $maxRetries
    ): bool {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $appUrl  = defined('APP_URL')  ? APP_URL  : '';

        $subject = "[{$appName}] ❌ XML import selhal — {$userEmail}";

        $html = self::layout($subject, "
            <h2 style='color:#ef4444;margin-top:0;'>❌ XML import selhal</h2>
            <p>Import XML feedu selhal po {$maxRetries} pokusech a byl označen jako <strong>failed</strong>.</p>

            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;width:140px;'>Uživatel</td>
                    <td style='padding:10px 0;'><strong>{$userEmail}</strong> (ID: {$userId})</td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Feed URL</td>
                    <td style='padding:10px 0;word-break:break-all;'><a href='" . htmlspecialchars($feedUrl) . "' style='color:#60a5fa;'>" . htmlspecialchars($feedUrl) . "</a></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Počet pokusů</td>
                    <td style='padding:10px 0;'>{$retryCount} / {$maxRetries}</td>
                </tr>
                <tr>
                    <td style='padding:10px 0;color:#9ca3af;vertical-align:top;'>Chyba</td>
                    <td style='padding:10px 0;'>
                        <code style='background:#1f2937;padding:8px 12px;border-radius:4px;display:block;color:#f87171;font-size:13px;white-space:pre-wrap;'>" . htmlspecialchars($errorMessage) . "</code>
                    </td>
                </tr>
            </table>

            <a href='{$appUrl}/admin/xml-queue'
               style='display:inline-block;background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;'>
               Zobrazit XML frontu
            </a>
        ");

        return Mailer::notifySuperadmin($subject, $html);
    }

    // ----------------------------------------------------------------
    // 2. Webhook selhal — všechny retry vyčerpány
    // ----------------------------------------------------------------

    public static function webhookFailed(
        int    $userId,
        string $userEmail,
        string $webhookName,
        string $webhookUrl,
        string $eventType,
        int    $attempts,
        ?int   $lastStatusCode,
        ?string $lastError
    ): bool {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $appUrl  = defined('APP_URL')  ? APP_URL  : '';

        $subject = "[{$appName}] ⚠️ Webhook selhal — {$webhookName}";

        $statusInfo = $lastStatusCode
            ? "HTTP {$lastStatusCode}"
            : ($lastError ?? 'Neznámá chyba');

        $html = self::layout($subject, "
            <h2 style='color:#f59e0b;margin-top:0;'>⚠️ Webhook selhal</h2>
            <p>Všechny pokusy o doručení webhooky byly vyčerpány. Payload nebyl doručen.</p>

            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;width:140px;'>Uživatel</td>
                    <td style='padding:10px 0;'><strong>{$userEmail}</strong> (ID: {$userId})</td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Webhook</td>
                    <td style='padding:10px 0;'><strong>" . htmlspecialchars($webhookName) . "</strong></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>URL</td>
                    <td style='padding:10px 0;word-break:break-all;'><code style='color:#9ca3af;'>" . htmlspecialchars($webhookUrl) . "</code></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Event</td>
                    <td style='padding:10px 0;'><code style='background:#1f2937;padding:3px 8px;border-radius:4px;color:#60a5fa;'>" . htmlspecialchars($eventType) . "</code></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Pokusů</td>
                    <td style='padding:10px 0;'>{$attempts}</td>
                </tr>
                <tr>
                    <td style='padding:10px 0;color:#9ca3af;'>Poslední odpověď</td>
                    <td style='padding:10px 0;'>
                        <code style='background:#1f2937;padding:8px 12px;border-radius:4px;display:block;color:#f87171;font-size:13px;'>" . htmlspecialchars($statusInfo) . "</code>
                    </td>
                </tr>
            </table>

            <a href='{$appUrl}/admin/users/{$userId}'
               style='display:inline-block;background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;'>
               Zobrazit uživatele
            </a>
        ");

        return Mailer::notifySuperadmin($subject, $html);
    }

    // ----------------------------------------------------------------
    // 3. Uživatel zamknut (brute-force)
    // ----------------------------------------------------------------

    public static function userLocked(
        int    $userId,
        string $email,
        string $ipAddress,
        int    $attempts,
        int    $lockMinutes
    ): bool {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $appUrl  = defined('APP_URL')  ? APP_URL  : '';
        $time    = date('d.m.Y H:i:s');

        $subject = "[{$appName}] 🔒 Účet zamknut — {$email}";

        $html = self::layout($subject, "
            <h2 style='color:#ef4444;margin-top:0;'>🔒 Účet byl zamknut</h2>
            <p>Uživatelský účet byl automaticky zamknut z důvodu opakovaných neúspěšných pokusů o přihlášení.</p>

            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;width:140px;'>E-mail</td>
                    <td style='padding:10px 0;'><strong>" . htmlspecialchars($email) . "</strong></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>User ID</td>
                    <td style='padding:10px 0;'>{$userId}</td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>IP adresa</td>
                    <td style='padding:10px 0;'>
                        <code style='background:#1f2937;padding:3px 8px;border-radius:4px;color:#f87171;'>{$ipAddress}</code>
                    </td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Pokusů</td>
                    <td style='padding:10px 0;'>{$attempts}</td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>Zamknuto na</td>
                    <td style='padding:10px 0;'>{$lockMinutes} minut</td>
                </tr>
                <tr>
                    <td style='padding:10px 0;color:#9ca3af;'>Čas</td>
                    <td style='padding:10px 0;'>{$time}</td>
                </tr>
            </table>

            <p style='color:#9ca3af;font-size:14px;'>
                Pokud se nejedná o podezřelou aktivitu, nemusíte nic dělat — účet se automaticky odemkne za {$lockMinutes} minut.
                Pokud jde o útok, zvažte zablokování IP adresy.
            </p>

            <a href='{$appUrl}/admin/users/{$userId}'
               style='display:inline-block;background:#ef4444;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;margin-right:10px;'>
               Zobrazit účet
            </a>
            <a href='{$appUrl}/admin/audit-log'
               style='display:inline-block;background:#374151;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;'>
               Audit log
            </a>
        ");

        return Mailer::notifySuperadmin($subject, $html);
    }

    // ----------------------------------------------------------------
    // HTML layout šablona
    // ----------------------------------------------------------------

    private static function layout(string $title, string $content): string
    {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $year    = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#0f1117;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#e5e7eb;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f1117;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

                    <!-- Header -->
                    <tr>
                        <td style="background:#1a1d27;border-radius:12px 12px 0 0;padding:24px 32px;border-bottom:1px solid #374151;">
                            <span style="font-size:20px;font-weight:700;color:#fff;">{$appName}</span>
                            <span style="font-size:13px;color:#6b7280;margin-left:8px;">Admin notifikace</span>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="background:#1a1d27;padding:32px;border-radius:0 0 12px 12px;">
                            {$content}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 0 0;text-align:center;color:#4b5563;font-size:12px;">
                            © {$year} {$appName} · Tato zpráva byla vygenerována automaticky.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
}

    // ----------------------------------------------------------------
    // 4. Nová registrace čeká na schválení
    // ----------------------------------------------------------------

    public static function newRegistration(
        int    $userId,
        string $email,
        string $firstName,
        string $lastName,
        string $shopName
    ): bool {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $appUrl  = defined('APP_URL')  ? APP_URL  : '';

        $subject = "[{$appName}] 🆕 Nová registrace — {$email}";

        $html = self::layout($subject, "
            <h2 style='color:#fff;margin-top:0;'>🆕 Nová registrace</h2>
            <p>Nový uživatel se zaregistroval a čeká na schválení.</p>

            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;width:120px;'>Jméno</td>
                    <td style='padding:10px 0;'><strong>" . htmlspecialchars("{$firstName} {$lastName}") . "</strong></td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>E-mail</td>
                    <td style='padding:10px 0;'>" . htmlspecialchars($email) . "</td>
                </tr>
                <tr style='border-bottom:1px solid #374151;'>
                    <td style='padding:10px 0;color:#9ca3af;'>E-shop</td>
                    <td style='padding:10px 0;'>" . htmlspecialchars($shopName ?: '—') . "</td>
                </tr>
                <tr>
                    <td style='padding:10px 0;color:#9ca3af;'>User ID</td>
                    <td style='padding:10px 0;'>{$userId}</td>
                </tr>
            </table>

            <a href='{$appUrl}/admin/users/{$userId}'
               style='display:inline-block;background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;'>
               Schválit / zamítnout
            </a>
        ");

        return Mailer::notifySuperadmin($subject, $html);
    }

    // ----------------------------------------------------------------
    // 5. Uvítací email novému uživateli
    // ----------------------------------------------------------------

    public static function welcomeUser(string $toEmail, string $firstName): bool
    {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        $subject = "Vítejte v {$appName}";

        $html = self::layout($subject, "
            <h2 style='color:#fff;margin-top:0;'>Vítejte, " . htmlspecialchars($firstName) . "!</h2>
            <p>Váš účet byl úspěšně vytvořen.</p>
            <p>Než budete moci systém plně používat, musí váš účet schválit administrátor. Jakmile bude váš účet schválen, dostanete notifikaci.</p>
            <p style='color:#9ca3af;margin-top:24px;font-size:14px;'>Děkujeme za registraci.</p>
        ");

    // ----------------------------------------------------------------
    // 6. Schválení účtu — email uživateli (vrací HTML string, neodesílá)
    // ----------------------------------------------------------------

    public static function approvalEmail(string $firstName, string $appUrl): string
    {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';

        return self::layout("Váš účet byl schválen", "
            <h2 style='color:#22c55e;margin-top:0;'>✅ Váš účet byl schválen</h2>
            <p>Ahoj " . htmlspecialchars($firstName) . ",</p>
            <p>Váš účet v systému <strong>{$appName}</strong> byl schválen administrátorem. Nyní máte plný přístup k aplikaci.</p>
            <p style='margin:32px 0;'>
                <a href='{$appUrl}/dashboard'
                   style='display:inline-block;background:#22c55e;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;'>
                   Přejít do aplikace
                </a>
            </p>
        ");
    }
}
