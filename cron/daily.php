#!/usr/bin/env php
<?php
/**
 * Denní CRON — foto expiry, upozornění, mazání
 * Spouštět každý den v 6:00:
 *   0 6 * * * /usr/local/bin/php /srv/app/cron/daily.php >> /srv/app/logs/cron-daily.log 2>&1
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/src/Core/Database.php';
require ROOT . '/src/Core/Mailer.php';

// Autoload
spl_autoload_register(function ($class) {
    $file = ROOT . '/src/' . str_replace(['ShopCode\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

$db  = \ShopCode\Core\Database::getInstance();
$now = new DateTime();

echo "[" . $now->format('Y-m-d H:i:s') . "] Denní CRON spuštěn\n";

// ── 1. Upozornění — fotky staré 23+ dní (7 dní před smazáním) ──────────────
echo "=== Upozornění na expiraci fotek ===\n";

$warnStmt = $db->prepare("
    SELECT u.id, u.email, u.photo_warning_sent_at,
           COUNT(rp.id) as photo_count,
           MIN(rp.created_at) as oldest_photo
    FROM users u
    JOIN reviews r ON r.user_id = u.id
    JOIN review_photos rp ON rp.review_id = r.id
    WHERE rp.path IS NOT NULL
      AND rp.created_at < DATE_SUB(NOW(), INTERVAL 23 DAY)
      AND (u.photo_warning_sent_at IS NULL OR u.photo_warning_sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
    GROUP BY u.id
");
$warnStmt->execute();
$toWarn = $warnStmt->fetchAll();

foreach ($toWarn as $user) {
    $exportUrl = APP_URL . '/reviews/export-photos';
    $deleteDate = (new DateTime($user['oldest_photo']))->modify('+30 days')->format('d.m.Y');

    $html = "
    <p>Dobrý den,</p>
    <p>vaše fotky z fotorecenzí <strong>expirují dne {$deleteDate}</strong>.</p>
    <p>Pro zachování přístupu k fotorecenzím si prosím stáhněte zálohu fotek před tímto datem.</p>
    <p><a href='{$exportUrl}' style='background:#0d6efd;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Stáhnout zálohu fotek ({$user['photo_count']} fotek)</a></p>
    <p>Po stažení zálohy se 30denní lhůta automaticky resetuje.</p>
    <p>S pozdravem,<br>ShopCode</p>
    ";

    $text = "Vaše fotky expirují dne {$deleteDate}. Stáhněte zálohu na: {$exportUrl}";

    if (\ShopCode\Core\Mailer::send($user['email'], "⚠️ Fotky expirují za 7 dní — stáhněte zálohu", $html, $text)) {
        $db->prepare("UPDATE users SET photo_warning_sent_at = NOW() WHERE id = ?")->execute([$user['id']]);
        echo "  Email odeslan: {$user['email']} ({$user['photo_count']} fotek, expiry {$deleteDate})\n";
    } else {
        echo "  CHYBA odeslání: {$user['email']}\n";
    }
}

if (empty($toWarn)) echo "  Žádní uživatelé k upozornění\n";

// ── 2. Zablokování + smazání souborů — fotky staré 30+ dní ─────────────────
echo "=== Mazání expirovaných fotek ===\n";

// Najdi uživatele jejichž poslední export je starší 30 dní (nebo nikdy)
$expireStmt = $db->prepare("
    SELECT u.id, u.email,
           MAX(pel.exported_at) as last_export,
           COUNT(rp.id) as photo_count
    FROM users u
    JOIN reviews r ON r.user_id = u.id
    JOIN review_photos rp ON rp.review_id = r.id
    LEFT JOIN photo_export_log pel ON pel.user_id = u.id
    WHERE rp.path IS NOT NULL
      AND rp.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.id
    HAVING last_export IS NULL OR last_export < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$expireStmt->execute();
$toDelete = $expireStmt->fetchAll();

foreach ($toDelete as $user) {
    echo "  Mažu fotky uživatele {$user['email']} ({$user['photo_count']} fotek)\n";

    // Načti cesty k souborům
    $photosStmt = $db->prepare("
        SELECT rp.id, rp.path, rp.thumb
        FROM review_photos rp
        JOIN reviews r ON r.id = rp.review_id
        WHERE r.user_id = ? AND rp.path IS NOT NULL
          AND rp.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $photosStmt->execute([$user['id']]);
    $photos = $photosStmt->fetchAll();

    $deleted = 0;
    foreach ($photos as $photo) {
        // Smaž fyzické soubory
        foreach ([$photo['path'], $photo['thumb']] as $rel) {
            if (!$rel) continue;
            $abs = ROOT . '/public/uploads/' . ltrim($rel, '/');
            if (file_exists($abs)) {
                unlink($abs);
                $deleted++;
            }
        }
        // Nastav path = NULL v DB (zachovej záznam)
        $db->prepare("UPDATE review_photos SET path = NULL, thumb = NULL WHERE id = ?")->execute([$photo['id']]);
    }

    echo "    Smazáno {$deleted} souborů, DB záznamy zachovány\n";
}

if (empty($toDelete)) echo "  Žádné fotky k smazání\n";

echo "[" . date('Y-m-d H:i:s') . "] Hotovo\n\n";
