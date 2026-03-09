<?php
/**
 * Background scrape jednoho zdroje (pro dlouhé scrapery jako Shoptet)
 * Volání: php scrape_one.php <user_id> <source_id>
 */
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$dotenv = [];
foreach (file(BASE_PATH . '/.env') as $line) {
    $line = trim($line);
    if (!$line || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $dotenv[trim($k)] = trim($v);
}
foreach ($dotenv as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $v;
}

// Bootstrap konstant
foreach (['APP_ENV','APP_DEBUG','APP_URL','APP_KEY','DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','DB_CHARSET'] as $key) {
    if (!defined($key) && isset($dotenv[$key])) define($key, $dotenv[$key]);
}
if (!defined('APP_ENV'))   define('APP_ENV', 'production');
if (!defined('APP_DEBUG')) define('APP_DEBUG', false);
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));

use ShopCode\Core\Database;
use ShopCode\Models\ScrapedReview;
use ShopCode\Services\ReviewScraper;
use ShopCode\Services\DeepLTranslator;

$userId   = (int)($argv[1] ?? 0);
$sourceId = (int)($argv[2] ?? 0);

if (!$userId || !$sourceId) {
    echo "Použití: php scrape_one.php <user_id> <source_id>\n";
    exit(1);
}

$db     = Database::getInstance();
$source = $db->prepare("SELECT * FROM scrape_sources WHERE id = ? AND user_id = ?")->execute([$sourceId, $userId])
    ? null : null;

// Načti zdroj
$stmt = $db->prepare("SELECT * FROM scrape_sources WHERE id = ? AND user_id = ?");
$stmt->execute([$sourceId, $userId]);
$source = $stmt->fetch();
if (!$source) {
    echo "Zdroj $sourceId nenalezen pro uživatele $userId\n";
    exit(1);
}

echo date('Y-m-d H:i:s') . " Scraping: {$source['name']} [{$source['platform']}]\n";
echo "URL: {$source['url']}\n";

$scraped = ReviewScraper::scrape($source['url'], $source['platform']);
echo "Nascrapováno: " . count($scraped) . "\n";

$new = 0;
foreach ($scraped as $r) {
    if (ScrapedReview::insertReview($userId, $sourceId, $r['external_id'], $r['author'], $r['rating'], $r['content'], $r['date'])) {
        $new++;
    }
}
ScrapedReview::updateLastScraped($sourceId);
echo "Nových v DB: $new\n";

// Překlad
$userRow = $db->prepare("SELECT deepl_api_key FROM users WHERE id = ?")->execute([$userId]) ? null : null;
$stmt2   = $db->prepare("SELECT deepl_api_key FROM users WHERE id = ?");
$stmt2->execute([$userId]);
$user = $stmt2->fetch();

if (!empty($user['deepl_api_key']) && $new > 0) {
    $deepl    = new DeepLTranslator($user['deepl_api_key']);
    $langs    = ScrapedReview::getUserLangs($userId);
    $allLangs = array_unique(array_merge(['CS'], $langs));
    $pending  = ScrapedReview::getUntranslated($userId, $allLangs);
    $translated = 0;

    foreach ($pending as $review) {
        if (empty(trim($review['content']))) continue;
        $missingLangs = $review['missing_langs'] ?? $allLangs;

        if (in_array('CS', $missingLangs)) {
            $csText = $deepl->translate($review['content'], 'CS');
            if ($csText) {
                ScrapedReview::saveTranslation($review['id'], 'CS', $csText, true);
                $srcLang = $deepl->detectLang($review['content']);
                if ($srcLang) ScrapedReview::updateSourceLang($review['id'], $srcLang);
                $translated++;
            }
        }
        foreach ($missingLangs as $lang) {
            if (strtoupper($lang) === 'CS') continue;
            $text = $deepl->translate($review['content'], $lang);
            if ($text) { ScrapedReview::saveTranslation($review['id'], $lang, $text, true); $translated++; }
        }
        usleep(200000);
    }
    echo "Přeloženo: $translated\n";
}

echo date('Y-m-d H:i:s') . " Hotovo.\n";
