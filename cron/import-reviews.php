#!/usr/bin/env php
<?php
/**
 * Shoptet fotorecenze — Selenium import cron
 * Spouštět každých 30 minut:
 *   30 * * * * php /var/www/shopcode/cron/import-reviews.php >> /var/log/shopcode-reviews.log 2>&1
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php'; // pro facebook/webdriver

use ShopCode\Core\Database;
use ShopCode\Models\Review;
use ShopCode\Services\{CsvGenerator, ShoptetBot, AdminNotifier};

// ── Mutex lock ─────────────────────────────────────────────
$lockFile = ROOT . '/tmp/import-reviews.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Jiná instance běží, přeskakuji.\n";
    exit(0);
}
fwrite($lock, getmypid());

// ── Inicializace ───────────────────────────────────────────
const MAX_RETRIES    = 3;
const RETRY_LOG_FILE = ROOT . '/tmp/import-reviews-retries.json';

$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] {$msg}\n");

try {
    $db = Database::getInstance();

    // Kontrola opakovaných selhání — po 3x se pozastavíme
    $retries = 0;
    if (file_exists(RETRY_LOG_FILE)) {
        $retryData = json_decode(file_get_contents(RETRY_LOG_FILE), true);
        $retries   = (int)($retryData['count'] ?? 0);
        $lastFail  = $retryData['last_fail'] ?? 0;

        if ($retries >= MAX_RETRIES) {
            $log("Import pozastaven po {$retries} selháních — čeká na ruční zásah.");
            $log("Smažte soubor " . RETRY_LOG_FILE . " pro obnovení.");
            exit(1);
        }
    }

    // ── Načteme všechny uživatele se schválenými neimportovanými recenzemi ──
    $stmt = $db->prepare("
        SELECT DISTINCT user_id FROM reviews
        WHERE status = 'approved' AND imported = 0
    ");
    $stmt->execute();
    $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    if (empty($userIds)) {
        $log("Žádné recenze ke zpracování.");
        // Reset retry counter při úspěchu
        @unlink(RETRY_LOG_FILE);
        exit(0);
    }

    $log("Nalezeno " . count($userIds) . " uživatelů se schválenými recenzemi.");

    $csvGen = new CsvGenerator();
    $bot    = new ShoptetBot();
    $totalImported = 0;

    foreach ($userIds as $userId) {
        $reviews = Review::getPendingImport((int)$userId);
        if (empty($reviews)) continue;

        $log("Uživatel #{$userId}: " . count($reviews) . " recenzí ke zpracování.");

        try {
            // Generujeme CSV
            $csvPath   = $csvGen->generate($reviews);
            $reviewIds = $csvGen->getReviewIds($reviews);

            $log("CSV vygenerován: " . basename($csvPath) . " (" . count($reviewIds) . " recenzí)");

            // Spustíme Selenium import
            $result = $bot->importCsv($csvPath);

            // Logujeme výstup Selenia
            foreach ($result['log'] as $line) {
                $log("  [Selenium] {$line}");
            }

            if ($result['success']) {
                // Označíme jako importované
                $count = Review::markImported($reviewIds, (int)$userId);
                $log("✅ Import úspěšný — označeno {$count} recenzí.");
                $totalImported += $count;
            } else {
                throw new \RuntimeException($result['message']);
            }

            $csvGen->cleanup($csvPath);

        } catch (\Throwable $e) {
            $log("❌ Chyba pro uživatele #{$userId}: " . $e->getMessage());
            @$csvGen->cleanup($csvPath ?? '');

            // Zaznamenáme selhání
            $retries++;
            file_put_contents(RETRY_LOG_FILE, json_encode([
                'count'     => $retries,
                'last_fail' => time(),
                'error'     => $e->getMessage(),
            ]));

            // Email admina
            try {
                AdminNotifier::notifySuperadmin(
                    subject: "[ShopCode] ❌ Selenium import recenzí selhal",
                    htmlBody: "
                        <h2>Selenium import recenzí selhal</h2>
                        <p><strong>Uživatel:</strong> #{$userId}</p>
                        <p><strong>Chyba:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                        <p><strong>Pokus:</strong> {$retries} / " . MAX_RETRIES . "</p>
                        <p>Po " . MAX_RETRIES . " selháních se import automaticky pozastaví.</p>
                    "
                );
            } catch (\Throwable $ignored) {}
        }
    }

    // Reset retry counter při úspěchu
    if ($totalImported > 0) {
        @unlink(RETRY_LOG_FILE);
    }

    $log("Hotovo. Celkem importováno: {$totalImported} recenzí.");

} catch (\Throwable $e) {
    $log("FATÁLNÍ CHYBA: " . $e->getMessage());
    exit(1);

} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
