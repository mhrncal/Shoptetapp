#!/usr/bin/env php
<?php
/**
 * Shoptet Photo Import Cron
 * Pro každého uživatele stáhne CSV export fotek ze Shoptetu,
 * aktualizuje shoptet_product_images a spáruje nové CDN URL s review_photos.
 *
 * Doporučené spuštění: jednou denně ráno (Shoptet zpracuje XML z předchozího dne)
 *   0 8 * * * php /srv/app/cron/shoptet-photo-import.php >> /var/log/shopcode-photo-import.log 2>&1
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

use ShopCode\Core\Database;
use ShopCode\Services\ShoptetCsvImporter;

ini_set('memory_limit', '256M');
set_time_limit(600);

$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n");

$lockFile = ROOT . '/tmp/shoptet-photo-import.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    $log('Jiná instance běží, přeskakuji.');
    exit(0);
}

$log('===== Shoptet Photo Import START =====');

try {
    $db = Database::getInstance();

    // Načti všechny uživatele s nastavenou URL importu
    $stmt = $db->prepare('SELECT user_id, csv_url FROM shoptet_photo_imports WHERE csv_url IS NOT NULL AND csv_url != \'\'');
    $stmt->execute();
    $configs = $stmt->fetchAll();

    if (empty($configs)) {
        $log('Žádní uživatelé s nastavenou URL importu.');
        exit(0);
    }

    $log('Nalezeno ' . count($configs) . ' uživatelů.');

    foreach ($configs as $config) {
        $userId = (int)$config['user_id'];
        $url    = $config['csv_url'];

        $log("Uživatel #{$userId}: stahuji CSV...");

        try {
            $snapshot = ShoptetCsvImporter::snapshotUrls($userId);
            $importer = new ShoptetCsvImporter($userId);
            $result   = $importer->importFromUrl($url);

            ShoptetCsvImporter::updateImportStats($userId, $result['rows'], $result['images']);

            $matched = ShoptetCsvImporter::matchNewUrlsToReviews($userId, $snapshot);

            $log("  ✓ {$result['rows']} produktů, {$result['images']} fotek" .
                ($matched > 0 ? ", {$matched} fotek spárováno se Shoptetem" : ''));

        } catch (\Throwable $e) {
            $log("  ✗ Chyba: " . $e->getMessage());
        }
    }

} catch (\Throwable $e) {
    $log('FATÁLNÍ CHYBA: ' . $e->getMessage());
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
    @unlink($lockFile);
}

$log('===== Shoptet Photo Import END =====');
