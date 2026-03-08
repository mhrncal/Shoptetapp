<?php
if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

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

use ShopCode\Services\ReviewScraper;

echo "=== Scrape diagnostika ===\n\n";

$url      = $_GET['url'] ?? '';
$platform = $_GET['platform'] ?? 'heureka';

if (!$url) {
    echo "Použití: ?key=shopcode_diag&url=https://...&platform=heureka|trustedshops|shoptet|google\n\n";
    echo "Příklady:\n";
    echo "  Heureka:       ?key=shopcode_diag&url=https://obchod.heureka.cz/recenze/&platform=heureka\n";
    echo "  Trusted Shops: ?key=shopcode_diag&url=https://www.trustedshops.cz/hodnoceni/...&platform=trustedshops\n";
    echo "  Shoptet:       ?key=shopcode_diag&url=https://vas-eshop.cz/hodnoceni/&platform=shoptet\n";
    exit;
}

echo "URL: $url\n";
echo "Platform: $platform\n\n";

// Stáhni HTML
echo "--- cURL fetch ---\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: cs-CZ,cs;q=0.9'],
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
]);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

echo "HTTP status: $code\n";
echo "HTML délka: " . strlen($html) . " bytů\n";
if ($err) echo "cURL error: $err\n";

if (!$html || $code !== 200) {
    echo "\nNelze stáhnout stránku.\n";
    exit;
}

// JSON-LD check
echo "\n--- JSON-LD ---\n";
preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $m);
echo "JSON-LD bloků: " . count($m[1]) . "\n";
foreach ($m[1] as $i => $json) {
    $data = @json_decode(trim($json), true);
    $type = $data['@type'] ?? ($data[0]['@type'] ?? 'N/A');
    echo "  [$i] @type=$type\n";
    if (isset($data['review']) || isset($data['reviews'])) {
        $cnt = count($data['review'] ?? $data['reviews'] ?? []);
        echo "       → recenzí: $cnt\n";
    }
}

// CSS třídy přítomné v HTML
echo "\n--- CSS třídy (recenze) ---\n";
$classes = ['c-review', 'review-item', 'review-body', 'review__text', 'rating-list__item',
            'productReview', 'review-full-text', 'MyEned', 'jftiEf', 'wiI7pd'];
foreach ($classes as $cls) {
    $cnt = substr_count($html, $cls);
    if ($cnt > 0) echo "  .$cls: $cnt výskytů\n";
}

// Výsledek scraperu
echo "\n--- Scraper výsledek ---\n";
try {
    $reviews = ReviewScraper::scrape($url, $platform);
    echo "Nalezeno recenzí: " . count($reviews) . "\n";
    foreach (array_slice($reviews, 0, 3) as $i => $r) {
        echo "\n  [$i] author={$r['author']} rating={$r['rating']} date={$r['date']}\n";
        echo "      content: " . mb_substr($r['content'], 0, 100) . "...\n";
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Ukázka HTML struktury
echo "\n--- HTML ukázka (prvních 3000 znaků body) ---\n";
preg_match('/<body[^>]*>(.*)/si', $html, $bm);
$body = strip_tags($bm[1] ?? $html);
$body = preg_replace('/\s+/', ' ', $body);
echo mb_substr(trim($body), 0, 3000) . "\n";
