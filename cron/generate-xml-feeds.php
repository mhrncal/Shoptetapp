#!/usr/bin/env php
<?php
/**
 * XML Feed Generator — Cron script
 * Generuje XML feedy pro všechny uživatele se schválenými recenzemi
 * 
 * Spouštět denně v 18:00:
 *   0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

use ShopCode\Core\Database;
use ShopCode\Models\{Review, User};
use ShopCode\Services\{XmlFeedGenerator, AdminNotifier};

// ── Mutex lock ─────────────────────────────────────────────
$lockFile = ROOT . '/tmp/xml-feeds.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Jiná instance běží, přeskakuji.\n";
    exit(0);
}
fwrite($lock, getmypid());

// ── Inicializace ───────────────────────────────────────────
$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] {$msg}\n");

try {
    $db = Database::getInstance();
    
    // Načteme autoloader
    spl_autoload_register(function (string $class) {
        $path = ROOT . '/src/' . str_replace(['ShopCode\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) require $path;
    });

    $log("===== XML Feed Generator START =====");

    // ── Načteme všechny uživatele se schválenými recenzemi ──
    $stmt = $db->prepare("
        SELECT DISTINCT u.id, u.email, u.shop_name
        FROM reviews r
        JOIN users u ON u.id = r.user_id
        WHERE r.status = 'approved'
          AND u.status = 'approved'
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();

    if (empty($users)) {
        $log("Žádní uživatelé se schválenými recenzemi.");
        exit(0);
    }

    $log("Nalezeno " . count($users) . " uživatelů se schválenými recenzemi.");

    $xmlGen = new XmlFeedGenerator();
    $generated = 0;

    foreach ($users as $user) {
        $userId = (int)$user['id'];
        $shopName = $user['shop_name'] ?: $user['email'];
        
        // Načteme všechny schválené recenze (včetně již importovaných)
        $stmt = $db->prepare("
            SELECT * FROM reviews
            WHERE user_id = ? AND status = 'approved'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId]);
        $reviews = $stmt->fetchAll();
        
        // Dekódujeme photos JSON
        foreach ($reviews as &$r) {
            $r['photos'] = $r['photos'] ? json_decode($r['photos'], true) : [];
        }

        $reviewCount = count($reviews);
        $log("Uživatel #{$userId} ({$shopName}): {$reviewCount} schválených recenzí.");

        try {
            // Generujeme permanentní XML feed
            $feedUrl = $xmlGen->generatePermanentFeed($userId, $reviews);
            $log("  ✅ XML feed vygenerován: {$feedUrl}");
            $generated++;
            
        } catch (\Throwable $e) {
            $log("  ❌ Chyba při generování feedu: " . $e->getMessage());
        }
    }

    $log("===== XML Feed Generator END | Vygenerováno: {$generated} feedů =====");

} catch (\Throwable $e) {
    $log("FATÁLNÍ CHYBA: " . $e->getMessage());
    exit(1);

} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
