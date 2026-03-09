<?php
/**
 * Cron: denní scraping recenzí všech uživatelů + překlad přes DeepL
 * 0 2 * * * /opt/techs/php-8.4.12/bin/php /srv/app/cron/scrape_reviews.php >> /srv/app/public/logs/cron-scrape.log 2>&1
 */

define('ROOT', dirname(__DIR__));

foreach (file(ROOT . '/.env') as $line) {
    $line = trim($line);
    if (!$line || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

require_once ROOT . '/config/config.php';

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

// Lock
$lockFile = ROOT . '/public/logs/cron-scrape.lock';
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    echo '[' . date('H:i:s') . '] SKIP: jiz bezi (lock)' . PHP_EOL;
    exit(0);
}

$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

set_time_limit(0);

$log('════════════════════════════════════════');
$log('Scrape recenzi START');
$log('════════════════════════════════════════');

$db = Database::getInstance();

// 1. SCRAPING
$sources = ScrapedReview::getAllSourcesForCron();
$log('Aktivnich zdroju: ' . count($sources));
$scraped = 0;
$errors  = 0;

foreach ($sources as $source) {
    $log("-- [{$source['id']}] {$source['name']} ({$source['platform']}, user {$source['user_id']})");
    try {
        switch ($source['platform']) {
            case 'heureka':
            case 'trustedshops':
            case 'shoptet':
                $reviews = ReviewScraper::scrape($source['url'], $source['platform']);
                break;
            case 'outscraper':
                $log('   SKIP: Outscraper se importuje rucne pres XLSX');
                continue 2;
            case 'google':
                $stmt = $db->prepare('SELECT google_places_api_key FROM users WHERE id = ?');
                $stmt->execute([$source['user_id']]);
                $googleKey = $stmt->fetchColumn();
                if (!$googleKey) { $log('   SKIP: Google klic neni nastaven'); continue 2; }
                $reviews = ReviewScraper::scrapeGooglePlaces($source['url'], $googleKey);
                break;
            default:
                $log("   SKIP: neznama platforma");
                continue 2;
        }
        $new = ScrapedReview::insertReviews($source['user_id'], $source['id'], $reviews);
        ScrapedReview::updateLastScraped($source['id']);
        $log("   -> stazeno: " . count($reviews) . ", novych: $new");
        $scraped += $new;
    } catch (\Throwable $e) {
        $log("   ERROR: " . $e->getMessage());
        $errors++;
    }
    sleep(2); // pauza mezi zdroji
}

$log("Scraping hotov. Novych: $scraped, chyb: $errors");
$log('');

// 2. PREKLAD
$log('-- Preklad neprelozenych recenzi');

$stmt = $db->query("
    SELECT DISTINCT u.id, u.deepl_api_key
    FROM users u
    JOIN scrape_sources ss ON ss.user_id = u.id AND ss.is_active = 1
    WHERE u.deepl_api_key IS NOT NULL AND u.deepl_api_key != ''
");
$users = $stmt->fetchAll();
$log('Uzivatelu s DeepL klicem: ' . count($users));

foreach ($users as $user) {
    $userId = (int)$user['id'];
    $deepl  = new DeepLTranslator($user['deepl_api_key']);
    $langs  = ScrapedReview::getUserLangs($userId);
    $allLangs = array_unique(array_merge(['CS'], $langs));
    $pending  = ScrapedReview::getUntranslated($userId, $allLangs);

    if (empty($pending)) { $log("User $userId: vse prelozeno"); continue; }
    $log("User $userId: " . count($pending) . " recenzi -> " . implode(', ', $allLangs));
    $translated = 0;

    foreach ($pending as $review) {
        if (empty(trim($review['content']))) continue;
        $missingLangs = $review['missing_langs'] ?? $allLangs;

        $srcLang = $review['source_lang'] ?? null;
        if (!$srcLang) {
            $srcLang = $deepl->detectLang($review['content']);
            if ($srcLang) ScrapedReview::updateSourceLang($review['id'], $srcLang);
            usleep(100000);
        }

        foreach ($missingLangs as $lang) {
            $targetBase = strtoupper(explode('-', $lang)[0]);
            $sourceBase = $srcLang ? strtoupper(explode('-', $srcLang)[0]) : null;
            if ($sourceBase && $sourceBase === $targetBase) {
                ScrapedReview::saveTranslation($review['id'], $lang, $review['content'], false);
                $translated++;
                continue;
            }
            $text = $deepl->translate($review['content'], $lang, $srcLang);
            if (!$srcLang && $deepl->lastDetectedLang) {
                $srcLang = $deepl->lastDetectedLang;
                ScrapedReview::updateSourceLang($review['id'], $srcLang);
            }
            if ($text) {
                ScrapedReview::saveTranslation($review['id'], $lang, $text, true);
                $translated++;
            }
            usleep(200000);
        }
        usleep(100000);
    }

    $log("User $userId: prelozeno $translated textu");
    sleep(1);
}

flock($fp, LOCK_UN);
fclose($fp);

$log('');
$log('Scrape recenzi HOTOVO');
