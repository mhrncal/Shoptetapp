#!/usr/bin/env php
<?php
/**
 * Watermark regeneration diagnostic
 * Usage: php /srv/app/cron/regen-watermark.php [user_id]
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'ShopCode\\';
    $base   = ROOT . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

use ShopCode\Core\Database;
use ShopCode\Models\WatermarkSettings;
use ShopCode\Services\ImageHandler;

ini_set('memory_limit', '512M');
set_time_limit(300);

$log = fn(string $msg) => print('[' . date('H:i:s') . '] ' . $msg . "\n");

$userId = isset($argv[1]) ? (int)$argv[1] : null;

$db = Database::getInstance();

// Načti uživatele
if ($userId) {
    $users = [['id' => $userId]];
} else {
    $stmt = $db->query("SELECT DISTINCT r.user_id as id FROM reviews r JOIN review_photos rp ON rp.review_id = r.id");
    $users = $stmt->fetchAll();
}

$log("Uživatelé ke zpracování: " . count($users));

foreach ($users as $user) {
    $uid = (int)$user['id'];

    // Watermark settings
    $settings = WatermarkSettings::getForUser($uid);
    if (!$settings) {
        $log("User #{$uid}: Chybí watermark settings – přeskakuji");
        continue;
    }
    if (!$settings['enabled']) {
        $log("User #{$uid}: Watermark je vypnutý – přeskakuji");
        continue;
    }

    $type = $settings['watermark_type'] ?? 'text';
    $log("User #{$uid}: type={$type} text='{$settings['text']}' logo=" . ($settings['logo_path'] ?? 'NULL'));

    if ($type === 'logo' && !empty($settings['logo_path'])) {
        $logoAbs = ROOT . '/public/' . ltrim($settings['logo_path'], '/');
        $log("  Logo: {$logoAbs} → " . (file_exists($logoAbs) ? 'EXISTS (' . filesize($logoAbs) . 'b)' : 'CHYBÍ'));
    }

    // Načti fotky
    $stmt = $db->prepare('SELECT rp.* FROM review_photos rp JOIN reviews r ON r.id = rp.review_id WHERE r.user_id = ? AND rp.path IS NOT NULL');
    $stmt->execute([$uid]);
    $photos = $stmt->fetchAll();
    $log("  Fotek: " . count($photos));

    $handler = new ImageHandler(ROOT . '/public/uploads');
    $ok = 0; $fail = 0;

    foreach ($photos as $photo) {
        $ext      = pathinfo($photo['path'], PATHINFO_EXTENSION);
        $display  = ROOT . '/public/uploads/' . ltrim($photo['path'], '/');
        $original = substr($display, 0, -strlen('.' . $ext)) . '_original.' . $ext;
        $src      = file_exists($original) ? $original : $display;

        if (!file_exists($src)) {
            $log("  SKIP (no file): {$src}");
            $fail++;
            continue;
        }

        $mime = $photo['mime_type'] ?: @mime_content_type($src);
        $img  = match(true) {
            str_contains((string)$mime, 'jpeg') => @imagecreatefromjpeg($src),
            str_contains((string)$mime, 'png')  => @imagecreatefrompng($src),
            str_contains((string)$mime, 'webp') => @imagecreatefromwebp($src),
            default => false
        };

        if (!$img) {
            $log("  FAIL load: {$src} (mime={$mime})");
            $fail++;
            continue;
        }

        try {
            $wm    = $handler->applyWatermark($img, $uid);
            $thumb = $handler->createThumbnail($wm);

            $thumbPath = ROOT . '/public/uploads/' . ltrim($photo['thumb'] ?? '', '/');

            $saved = match(true) {
                str_contains((string)$mime, 'jpeg') => imagejpeg($wm, $display, 90) && (@imagejpeg($thumb, $thumbPath, 90) || true),
                str_contains((string)$mime, 'png')  => imagepng($wm, $display, 6)   && (@imagepng($thumb, $thumbPath, 6) || true),
                str_contains((string)$mime, 'webp') => imagewebp($wm, $display, 90) && (@imagewebp($thumb, $thumbPath, 90) || true),
                default => false
            };

            imagedestroy($img);
            imagedestroy($wm);
            imagedestroy($thumb);

            if ($saved) {
                $ok++;
                $log("  OK: " . basename($display) . " (" . round(filesize($display)/1024) . "KB)");
            } else {
                $fail++;
                $log("  FAIL save: {$display}");
            }
        } catch (\Throwable $e) {
            imagedestroy($img);
            $fail++;
            $log("  EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }

    $log("  Výsledek: {$ok} OK, {$fail} selhalo");
}

$log("Hotovo.");
