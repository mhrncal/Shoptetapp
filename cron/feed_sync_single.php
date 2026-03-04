<?php
/**
 * Synchronizace jediného feedu (pro manuální spuštění)
 * Usage: php feed_sync_single.php <feed_id>
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';
require ROOT . '/config/config.php';

use ShopCode\Core\Database;
use ShopCode\Models\ProductFeed;
use ShopCode\Services\{FeedParser, ReviewMatcher, ExportGenerator};

$feedId = (int)($argv[1] ?? 0);

if (!$feedId) {
    echo "Usage: php feed_sync_single.php <feed_id>\n";
    exit(1);
}

$startTime = microtime(true);
$db = Database::getInstance();

// Vytvoř záznam v logu
$logStmt = $db->prepare('
    INSERT INTO feed_sync_log (feed_id, started_at, status)
    VALUES (?, NOW(), "running")
');
$logStmt->execute([$feedId]);
$logId = (int)$db->lastInsertId();

echo "[" . date('Y-m-d H:i:s') . "] Syncing feed #{$feedId} (log #{$logId})\n";

try {
    $stmt = $db->prepare('SELECT * FROM product_feeds WHERE id = ?');
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    
    if (!$feed) {
        throw new \RuntimeException("Feed #{$feedId} not found");
    }
    
    $parser = new FeedParser();
    
    // Stáhni
    echo "Downloading...\n";
    $filepath = $parser->downloadFeed($feedId, $feed['url']);
    
    if (!$filepath) {
        throw new \RuntimeException("Download failed");
    }
    
    // Parsuj
    echo "Parsing...\n";
    $stats = $parser->parseAndStore($feedId, $filepath, [
        'user_id' => $feed['user_id'],
        'type' => $feed['type'],
        'delimiter' => $feed['delimiter'],
        'encoding' => $feed['encoding']
    ]);
    
    echo "Parse stats: " . json_encode($stats) . "\n";
    
    ProductFeed::updateFetchStatus($feedId, true);
    
    // Páruj
    echo "Matching reviews...\n";
    $matchStats = ReviewMatcher::matchReviews($feed['user_id']);
    echo "Match stats: " . json_encode($matchStats) . "\n";
    
    // Export
    echo "Generating exports...\n";
    $reviews = ReviewMatcher::getExportableReviews($feed['user_id']);
    
    if (!empty($reviews)) {
        $xml = ExportGenerator::generateXML($reviews);
        ExportGenerator::saveToFile($xml, "user_{$feed['user_id']}_reviews_with_products.xml");
        
        $csv = ExportGenerator::generateCSV($reviews);
        ExportGenerator::saveToFile($csv, "user_{$feed['user_id']}_reviews_with_products.csv");
        
        echo "Exports generated!\n";
    }
    
    // Aktualizuj log - SUCCESS
    $duration = round(microtime(true) - $startTime);
    $updateStmt = $db->prepare('
        UPDATE feed_sync_log 
        SET finished_at = NOW(),
            status = "success",
            products_inserted = ?,
            products_updated = ?,
            products_total = ?,
            reviews_matched = ?,
            reviews_total = ?,
            duration_seconds = ?
        WHERE id = ?
    ');
    
    $updateStmt->execute([
        $stats['inserted'] ?? 0,
        $stats['updated'] ?? 0,
        $stats['total'] ?? 0,
        $matchStats['matched'] ?? 0,
        $matchStats['total'] ?? 0,
        $duration,
        $logId
    ]);
    
    echo "SUCCESS in {$duration}s!\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Aktualizuj log - ERROR
    $duration = round(microtime(true) - $startTime);
    $updateStmt = $db->prepare('
        UPDATE feed_sync_log 
        SET finished_at = NOW(),
            status = "error",
            error_message = ?,
            duration_seconds = ?
        WHERE id = ?
    ');
    $updateStmt->execute([$e->getMessage(), $duration, $logId]);
    
    ProductFeed::updateFetchStatus($feedId, false, $e->getMessage());
    exit(1);
}
