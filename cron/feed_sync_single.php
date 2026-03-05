<?php
/**
 * Synchronizace jediného feedu (pro manuální spuštění)
 * Usage: php feed_sync_single.php <feed_id>
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

spl_autoload_register(function ($class) {
    $file = ROOT . '/src/' . str_replace(['ShopCode\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($file)) require $file;
});

use ShopCode\Core\Database;
use ShopCode\Models\ProductFeed;
use ShopCode\Services\{FeedParser, ReviewMatcher, ExportGenerator};

$feedId = (int)($argv[1] ?? 0);
if (!$feedId) { echo "Usage: php feed_sync_single.php <feed_id>\n"; exit(1); }

$startTime = microtime(true);
$db = Database::getInstance();

// Progress soubor — čte ho syncProgress endpoint
$progressFile = ROOT . '/tmp/feed_progress_' . $feedId . '.json';

$writeProgress = function(string $phase, string $message, array $extra = []) use ($progressFile, $startTime) {
    $data = array_merge([
        'phase'   => $phase,
        'message' => $message,
        'elapsed' => round(microtime(true) - $startTime),
        'time'    => date('H:i:s'),
    ], $extra);
    file_put_contents($progressFile, json_encode($data, JSON_UNESCAPED_UNICODE));
};

// Log entry
$logStmt = $db->prepare('INSERT INTO feed_sync_log (feed_id, started_at, status) VALUES (?, NOW(), "running")');
$logStmt->execute([$feedId]);
$logId = (int)$db->lastInsertId();

$writeProgress('start', 'Spouštím synchronizaci...', ['log_id' => $logId]);
echo "[" . date('Y-m-d H:i:s') . "] Syncing feed #{$feedId} (log #{$logId})\n";

try {
    $stmt = $db->prepare('SELECT * FROM product_feeds WHERE id = ?');
    $stmt->execute([$feedId]);
    $feed = $stmt->fetch();
    if (!$feed) throw new \RuntimeException("Feed #{$feedId} not found");

    $parser = new FeedParser();

    // --- STAHOVÁNÍ ---
    $writeProgress('download', 'Stahuji feed...', ['url' => $feed['url']]);
    echo "Downloading...\n";

    // Stáhni s průběžnou velikostí
    $tmpPath = ROOT . '/tmp/feed_' . $feedId . '_' . time() . '.csv';
    $ch = curl_init($feed['url']);
    $fp = fopen($tmpPath, 'wb');
    $downloaded = 0;

    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 1800,
        CURLOPT_USERAGENT      => 'ShopCode-XMLImporter/1.0',
        CURLOPT_ENCODING       => '',
        CURLOPT_NOPROGRESS     => false,
        CURLOPT_PROGRESSFUNCTION => function($ch, $dlTotal, $dlNow) use ($writeProgress, &$downloaded) {
            if ($dlNow > $downloaded + 512 * 1024 || ($dlTotal > 0 && $dlNow === $dlTotal)) {
                $downloaded = $dlNow;
                $totalMb = $dlTotal > 0 ? round($dlTotal / 1048576, 1) : '?';
                $nowMb   = round($dlNow / 1048576, 1);
                $pct     = $dlTotal > 0 ? round($dlNow / $dlTotal * 100) : null;
                $writeProgress('download',
                    "Stahuji: {$nowMb} MB" . ($dlTotal > 0 ? " / {$totalMb} MB" : ""),
                    ['downloaded_mb' => $nowMb, 'total_mb' => $totalMb, 'percent' => $pct]
                );
            }
            return 0;
        },
    ]);

    $ok = curl_exec($ch);
    $err = $ok ? null : curl_error($ch);
    curl_close($ch);
    fclose($fp);

    if (!$ok || !file_exists($tmpPath) || filesize($tmpPath) === 0) {
        throw new \RuntimeException("Download selhal: " . ($err ?? 'prázdný soubor'));
    }

    $sizeMb = round(filesize($tmpPath) / 1048576, 1);
    echo "Downloaded {$sizeMb} MB\n";

    // --- PARSOVÁNÍ ---
    $writeProgress('parse', "Stahuji hotovo ({$sizeMb} MB). Spouštím zpracování...", ['size_mb' => $sizeMb]);
    echo "Parsing...\n";

    // Spočítej počet řádků pro progress
    $lineCount = max(0, intval(shell_exec("wc -l < " . escapeshellarg($tmpPath))) - 1);
    $writeProgress('parse', "Zpracovávám {$lineCount} produktů...", ['total_rows' => $lineCount]);

    // Parsuj s progress callbackem
    $stats = $parser->parseAndStoreWithProgress(
        $feedId, $tmpPath,
        [
            'user_id'   => $feed['user_id'],
            'type'      => $feed['type'],
            'delimiter' => $feed['delimiter'],
            'encoding'  => $feed['encoding'],
        ],
        function(int $done, int $total, array $s) use ($writeProgress, $lineCount) {
            $t = $lineCount > 0 ? $lineCount : $total;
            $pct = $t > 0 ? round($done / $t * 100) : null;
            $writeProgress('parse',
                "Zpracováno {$done}" . ($t > 0 ? " / {$t}" : "") . " řádků" .
                ($pct !== null ? " ({$pct}%)" : ""),
                ['done' => $done, 'total' => $t, 'percent' => $pct,
                 'inserted' => $s['inserted'], 'updated' => $s['updated']]
            );
        }
    );

    @unlink($tmpPath);
    echo "Parse stats: " . json_encode($stats) . "\n";
    ProductFeed::updateFetchStatus($feedId, true);

    // --- PÁROVÁNÍ ---
    $writeProgress('match', "Parsování hotovo. Páruju recenze se produkty...",
        ['inserted' => $stats['inserted'], 'updated' => $stats['updated'], 'total' => $stats['total']]);
    echo "Matching reviews...\n";
    $matchStats = ReviewMatcher::matchReviews($feed['user_id']);
    echo "Match stats: " . json_encode($matchStats) . "\n";

    // --- EXPORT ---
    $writeProgress('export', "Generuji exporty...");
    echo "Generating exports...\n";
    $reviews = ReviewMatcher::getExportableReviews($feed['user_id']);
    if (!empty($reviews)) {
        $xml = ExportGenerator::generateXML($reviews);
        ExportGenerator::saveToFile($xml, "user_{$feed['user_id']}_reviews_with_products.xml");
        $csv = ExportGenerator::generateCSV($reviews);
        ExportGenerator::saveToFile($csv, "user_{$feed['user_id']}_reviews_with_products.csv");
        echo "Exports generated!\n";
    }

    // --- HOTOVO ---
    $duration = round(microtime(true) - $startTime);
    $writeProgress('done', "Hotovo! {$stats['total']} produktů, {$duration}s.", [
        'inserted' => $stats['inserted'], 'updated' => $stats['updated'],
        'total' => $stats['total'], 'duration' => $duration,
        'matched' => $matchStats['matched'] ?? 0,
    ]);

    $db->prepare('UPDATE feed_sync_log SET finished_at=NOW(), status="success",
        products_inserted=?, products_updated=?, products_total=?,
        reviews_matched=?, reviews_total=?, duration_seconds=? WHERE id=?')
       ->execute([$stats['inserted']??0, $stats['updated']??0, $stats['total']??0,
                  $matchStats['matched']??0, $matchStats['total']??0, $duration, $logId]);

    echo "SUCCESS in {$duration}s!\n";

} catch (\Exception $e) {
    $duration = round(microtime(true) - $startTime);
    $writeProgress('error', "Chyba: " . $e->getMessage(), ['duration' => $duration]);
    echo "ERROR: " . $e->getMessage() . "\n";
    $db->prepare('UPDATE feed_sync_log SET finished_at=NOW(), status="error", error_message=?, duration_seconds=? WHERE id=?')
       ->execute([$e->getMessage(), $duration, $logId]);
    ProductFeed::updateFetchStatus($feedId, false, $e->getMessage());
    @unlink($progressFile);
    exit(1);
} finally {
    // Nechej progress soubor 60s aby ho frontend stihl přečíst
    if (file_exists($progressFile)) {
        sleep(2);
        @unlink($progressFile);
    }
}
