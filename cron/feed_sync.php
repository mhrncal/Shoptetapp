<?php
/**
 * CRON: Synchronizace product feedů a párování s recenzemi
 * 
 * Spustí se denně (např. 3:00 ráno)
 * Přidej do crontab:
 * 
 * 5 3 * * * /usr/bin/php /srv/app/cron/feed_sync.php >> /srv/app/tmp/logs/feed_sync.log 2>&1
 */

define('ROOT', dirname(__DIR__));

// Načti config
require ROOT . '/config/config.php';

// Autoload tříd
spl_autoload_register(function ($class) {
    $prefix = 'ShopCode\\';
    $base_dir = ROOT . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use ShopCode\Core\Database;
use ShopCode\Models\ProductFeed;
use ShopCode\Services\{FeedParser, ReviewMatcher, ExportGenerator};

echo "[" . date('Y-m-d H:i:s') . "] Feed sync started\n";

try {
    $db = Database::getInstance();
    
    // Najdi všechny aktivní feedy
    $stmt = $db->query('SELECT * FROM product_feeds WHERE enabled = 1');
    $feeds = $stmt->fetchAll();
    
    echo "Found " . count($feeds) . " active feeds\n";
    
    $parser = new FeedParser();
    
    foreach ($feeds as $feed) {
        echo "\n--- Processing feed #{$feed['id']}: {$feed['name']} ---\n";
        
        try {
            // 1. Stáhni CSV
            echo "Downloading from: {$feed['url']}\n";
            $filepath = $parser->downloadFeed($feed['id'], $feed['url']);
            
            if (!$filepath) {
                throw new \RuntimeException("Download failed");
            }
            
            echo "Downloaded to: $filepath\n";
            
            // 2. Parsuj a ulož do DB
            $config = [
                'user_id' => $feed['user_id'],
                'type' => $feed['type'],
                'delimiter' => $feed['delimiter'],
                'encoding' => $feed['encoding']
            ];
            
            $stats = $parser->parseAndStore($feed['id'], $filepath, $config);
            
            echo "Parse stats: " . json_encode($stats) . "\n";
            
            // 3. Aktualizuj status
            ProductFeed::updateFetchStatus($feed['id'], true);
            
            // 4. Spáruj recenze
            echo "Matching reviews...\n";
            $matchStats = ReviewMatcher::matchReviews($feed['user_id']);
            echo "Match stats: " . json_encode($matchStats) . "\n";
            
            // 5. Vygeneruj export
            echo "Generating exports...\n";
            $reviews = ReviewMatcher::getExportableReviews($feed['user_id']);
            
            if (!empty($reviews)) {
                // XML
                $xml = ExportGenerator::generateXML($reviews);
                $xmlFile = "user_{$feed['user_id']}_reviews_with_products.xml";
                ExportGenerator::saveToFile($xml, $xmlFile);
                echo "XML saved: $xmlFile\n";
                
                // CSV
                $csv = ExportGenerator::generateCSV($reviews);
                $csvFile = "user_{$feed['user_id']}_reviews_with_products.csv";
                ExportGenerator::saveToFile($csv, $csvFile);
                echo "CSV saved: $csvFile\n";
            } else {
                echo "No exportable reviews found\n";
            }
            
            echo "Feed #{$feed['id']} processed successfully!\n";
            
        } catch (\Exception $e) {
            echo "ERROR processing feed #{$feed['id']}: " . $e->getMessage() . "\n";
            ProductFeed::updateFetchStatus($feed['id'], false, $e->getMessage());
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Feed sync completed\n";
    
} catch (\Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
