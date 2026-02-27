#!/usr/bin/env php
<?php
/**
 * XML Feed Generator — Cron script
 * Generuje XML feedy pro všechny uživatele se schválenými recenzemi
 * 
 * Spouštět denně v 18:00:
 *   0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1
 * 
 * Bezpečnostní mechanismy:
 * - Mutex lock (nepustí druhou instanci)
 * - Timeout (max 10 minut běhu)
 * - Hung process detection (uvolní starší lock)
 * - Per-user timeout (max 2 minuty na feed)
 * - Error handling (chyba u jednoho nepřeruší ostatní)
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

use ShopCode\Core\Database;
use ShopCode\Models\{Review, User};
use ShopCode\Services\{XmlFeedGenerator, AdminNotifier};

// ── Konstanty ──────────────────────────────────────────────
const MAX_EXECUTION_TIME = 600;    // 10 minut celkově
const PER_USER_TIMEOUT   = 120;    // 2 minuty na uživatele
const LOCK_MAX_AGE       = 1800;   // 30 minut = hung process

// ── PHP limity ─────────────────────────────────────────────
ini_set('memory_limit', '256M');
set_time_limit(MAX_EXECUTION_TIME);
ini_set('max_execution_time', MAX_EXECUTION_TIME);

// ── Mutex lock s hung process detection ────────────────────
$lockFile = ROOT . '/tmp/xml-feeds.lock';
$lock     = fopen($lockFile, 'c');

// Pokusíme se získat lock (non-blocking)
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Lock existuje - zkontrolujeme jestli není zaseknutý
    $lockStat = fstat($lock);
    $lockAge  = time() - $lockStat['mtime'];
    
    if ($lockAge > LOCK_MAX_AGE) {
        echo "[" . date('Y-m-d H:i:s') . "] ⚠️  Starý lock (stáří: " . round($lockAge/60) . " min) - uvolňuji a pokračuji...\n";
        // Vynutíme uvolnění
        flock($lock, LOCK_UN);
        fclose($lock);
        $lock = fopen($lockFile, 'c');
        flock($lock, LOCK_EX);
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Jiná instance běží (běží " . round($lockAge/60, 1) . " min), přeskakuji.\n";
        exit(0);
    }
}

// Zapíšeme PID a timestamp
ftruncate($lock, 0);
fwrite($lock, json_encode([
    'pid'   => getmypid(),
    'start' => time(),
    'date'  => date('Y-m-d H:i:s')
]));
fflush($lock);

// ── Inicializace ───────────────────────────────────────────
$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] {$msg}\n");

$startTime = microtime(true);
$generated = 0;
$errors    = 0;

try {
    $db = Database::getInstance();
    
    // Načteme autoloader
    spl_autoload_register(function (string $class) {
        $path = ROOT . '/src/' . str_replace(['ShopCode\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($path)) require $path;
    });

    $log("===== XML Feed Generator START (PID: " . getmypid() . ") =====");

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

    foreach ($users as $user) {
        // Zkontroluj celkový timeout
        $elapsed = microtime(true) - $startTime;
        if ($elapsed > MAX_EXECUTION_TIME - 10) {
            $log("⚠️  Blížím se k časovému limitu (" . round($elapsed) . "s), končím předčasně.");
            break;
        }
        
        $userId = (int)$user['id'];
        $shopName = $user['shop_name'] ?: $user['email'];
        
        // Per-user timeout
        $userStart = microtime(true);
        
        try {
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

            // Generujeme permanentní XML feed s timeoutem
            $feedUrl = $xmlGen->generatePermanentFeed($userId, $reviews);
            
            $userElapsed = microtime(true) - $userStart;
            
            // Varování pokud generování trvalo příliš dlouho
            if ($userElapsed > PER_USER_TIMEOUT * 0.8) {
                $log("  ⚠️  Generování feedu trvalo dlouho: " . round($userElapsed, 2) . "s");
            }
            
            $log("  ✅ XML feed vygenerován: {$feedUrl} (" . round($userElapsed, 2) . "s)");
            $generated++;
            
        } catch (\Throwable $e) {
            $errors++;
            $userElapsed = microtime(true) - $userStart;
            $log("  ❌ Chyba při generování feedu pro #{$userId}: " . $e->getMessage());
            $log("     Stack trace: " . substr($e->getTraceAsString(), 0, 200));
            
            // Email při opakovaných chybách
            if ($errors >= 3) {
                try {
                    AdminNotifier::notifySuperadmin(
                        subject: "[ShopCode] ⚠️  XML Feed Generator - Opakované chyby",
                        htmlBody: "
                            <h2>XML Feed Generator - Opakované chyby</h2>
                            <p><strong>Počet chyb:</strong> {$errors}</p>
                            <p><strong>Poslední chyba u uživatele:</strong> #{$userId} ({$shopName})</p>
                            <p><strong>Chyba:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                            <p><strong>Čas:</strong> " . date('Y-m-d H:i:s') . "</p>
                            <p>Zkontroluj logy: /var/log/shopcode-xml-feeds.log</p>
                        "
                    );
                } catch (\Throwable $ignored) {}
            }
            
            // Pokračujeme s dalším uživatelem
            continue;
        }
    }

    $totalTime = microtime(true) - $startTime;
    $log("===== XML Feed Generator END | Vygenerováno: {$generated} | Chyb: {$errors} | Čas: " . round($totalTime, 2) . "s =====");

} catch (\Throwable $e) {
    $log("FATÁLNÍ CHYBA: " . $e->getMessage());
    $log("Stack trace: " . $e->getTraceAsString());
    
    // Email při fatální chybě
    try {
        AdminNotifier::notifySuperadmin(
            subject: "[ShopCode] ❌ XML Feed Generator - Fatální chyba",
            htmlBody: "
                <h2>XML Feed Generator - Fatální chyba</h2>
                <p><strong>Chyba:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                <p><strong>Čas:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Stack trace:</strong></p>
                <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
            "
        );
    } catch (\Throwable $ignored) {}
    
    exit(1);

} finally {
    // Vždy uvolníme lock
    if (isset($lock) && is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    
    // Smažeme lock soubor pokud všechno proběhlo OK
    if (isset($errors) && $errors === 0 && file_exists($lockFile)) {
        @unlink($lockFile);
    }
}
