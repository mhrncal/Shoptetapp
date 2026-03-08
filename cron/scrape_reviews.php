<?php
/**
 * Cron: denní scraping recenzí + překlad přes DeepL
 * 0 2 * * * /usr/local/bin/php /srv/app/cron/scrape_reviews.php >> /srv/app/public/logs/cron-scrape.log 2>&1
 */

define('ROOT', dirname(__DIR__));

// Config
foreach (file(ROOT . '/.env') as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

// Konstanty
require_once ROOT . '/config/config.php';

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'ShopCode\\';
    $base   = ROOT . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

use ShopCode\Core\Database;
use ShopCode\Models\ScrapedReview;
use ShopCode\Services\ReviewScraper;
use ShopCode\Services\DeepLTranslator;

$log = fn(string $msg) => print('[' . date('H:i:s') . '] ' . $msg . "\n");
$log('=== Scrape recenzí start ===');

// 1. Scrape
$sources = ScrapedReview::getAllSourcesForCron();
$log('Zdrojů: ' . count($sources));

foreach ($sources as $source) {
    $log("Scraping: {$source['name']} ({$source['platform']})");
    try {
        $reviews = ReviewScraper::scrape($source['url'], $source['platform']);
        $new = 0;
        foreach ($reviews as $r) {
            $ok = ScrapedReview::insertReview(
                $source['user_id'], $source['id'],
                $r['external_id'], $r['author'],
                $r['rating'], $r['content'], $r['date']
            );
            if ($ok) $new++;
        }
        ScrapedReview::updateLastScraped($source['id']);
        $log("  → $new nových z " . count($reviews));
    } catch (\Throwable $e) {
        $log("  ERROR: " . $e->getMessage());
    }
}

// 2. Překlad
$apiKey = defined('DEEPL_API_KEY') ? DEEPL_API_KEY : ($_ENV['DEEPL_API_KEY'] ?? null);
if (!$apiKey) {
    $log('DEEPL_API_KEY není nastaven — překlad přeskočen.');
} else {
    $deepl = new DeepLTranslator($apiKey);
    $db    = Database::getInstance();
    $stmt  = $db->query("SELECT DISTINCT user_id FROM scrape_sources WHERE is_active = 1");
    $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($userIds as $userId) {
        $langs   = ScrapedReview::getUserLangs((int)$userId);
        if (empty($langs)) continue;
        $pending = ScrapedReview::getUntranslated((int)$userId, $langs);
        $log("User $userId: " . count($pending) . " k překladu → " . implode(', ', $langs));

        foreach ($pending as $review) {
            foreach ($langs as $lang) {
                $translated = $deepl->translate($review['content'], $lang);
                if ($translated) ScrapedReview::saveTranslation($review['id'], $lang, $translated);
                usleep(200000);
            }
        }
    }
}

$log('=== Hotovo ===');
