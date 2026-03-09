<?php

namespace ShopCode\Services;

/**
 * Scraper recenzí z Heureka, Trusted Shops, Shoptet
 */
class ReviewScraper
{
    private static int $timeout = 20;

    /**
     * Hlavní metoda — podle platformy zavolá správný scraper
     * Vrací pole ['external_id', 'author', 'rating', 'content', 'date']
     */
    public static function scrape(string $url, string $platform): array
    {
        // Heureka a Shoptet fetchují interně (stránkování / XML)
        if ($platform === 'heureka') {
            $xml = self::fetch($url);
            return $xml ? self::scrapeHeureka($xml, $url) : [];
        }
        if ($platform === 'shoptet') {
            return self::scrapeShoptet($url);
        }

        $html = self::fetch($url);
        if (!$html) return [];

        return match($platform) {
            'trustedshops' => self::scrapeTrustedShops($html, $url),
            default        => [],
        };
    }

    private static function fetch(string $url): ?string
    {
        $parsed  = parse_url($url);
        $referer = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::$timeout,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: cs-CZ,cs;q=0.9,sk;q=0.8,en-US;q=0.7,en;q=0.6',
                'Accept-Encoding: gzip, deflate, br',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1',
                'Referer: ' . $referer,
            ],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEFILE     => '',  // prázdný cookie jar — akceptuje cookies
            CURLOPT_COOKIEJAR      => '',
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($html && $code === 200) ? $html : null;
    }

    // ---------------------------------------------------------------
    // Heureka
    // ---------------------------------------------------------------
    // ---------------------------------------------------------------
    // Trusted Shops
    // ---------------------------------------------------------------
    private static function scrapeTrustedShops(string $html, string $url): array
    {
        // Zjisti celkový počet z JSON-LD AggregateRating nebo HTML
        $allReviews = self::extractJsonLd($html, $url);
        $total      = 0;

        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $scripts);
        foreach ($scripts[1] as $json) {
            $data = @json_decode(trim($json), true);
            if (isset($data['aggregateRating']['reviewCount'])) {
                $total = (int)$data['aggregateRating']['reviewCount'];
                break;
            }
        }

        // HTML je React SSR double-encoded — dekóduj dvakrát
        if (!$total) {
            $decoded = html_entity_decode(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/(\d+)\s*Bewertungen\s*insgesamt/i', $decoded, $m)) {
                $total = (int)str_replace('.', '', $m[1]);
            }
        }

        // Stránkování — každá stránka má 20 recenzí, jedeme dokud přicházejí data
        $perPage = 20;
        $pages   = $total > 0 ? min((int)ceil($total / $perPage), 50) : 50;
        $baseUrl = preg_replace('/[?&]page=\d+/', '', $url);
        $sep     = str_contains($baseUrl, '?') ? '&' : '?';
        for ($page = 2; $page <= $pages; $page++) {
            usleep(300000);
            $pageHtml = self::fetch($baseUrl . $sep . 'page=' . $page);
            if (!$pageHtml) {
    
                break;
            }
            $pageReviews = self::extractJsonLd($pageHtml, $url);

            if (empty($pageReviews)) break;
            $allReviews = array_merge($allReviews, $pageReviews);
        }
        if (!empty($allReviews)) return $allReviews;

        // CSS fallback
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath  = new \DOMXPath($doc);
        $nodes  = $xpath->query('//*[contains(@class,"review") or contains(@class,"Review")]');
        $result = [];
        foreach ($nodes as $i => $node) {
            $content = self::xpathText($xpath, './/*[contains(@class,"comment") or contains(@class,"text") or contains(@class,"body")]', $node);
            $author  = self::xpathText($xpath, './/*[contains(@class,"author") or contains(@class,"name") or contains(@class,"buyer")]', $node) ?: 'Anonymní';
            $dateRaw = self::xpathText($xpath, './/*[contains(@class,"date") or @itemprop="datePublished"]', $node);
            if (!$content) continue;
            $result[] = [
                'external_id' => md5($url . $i . $content),
                'author'      => trim($author),
                'rating'      => null,
                'content'     => trim($content),
                'date'        => self::parseDate($dateRaw),
            ];
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // Shoptet
    // ---------------------------------------------------------------

    // ---------------------------------------------------------------
    // Google Places API
    // $url = Place ID (např. ChIJN1t_tDeuEmsRUsoyG83frY4)
    // ---------------------------------------------------------------
    public static function scrapeGooglePlaces(string $placeId, string $apiKey): array
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json?'
            . http_build_query([
                'place_id' => $placeId,
                'fields'   => 'reviews',
                'language' => 'cs',
                'key'      => $apiKey,
            ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return [];
        $data = @json_decode($response, true);
        if (($data['status'] ?? '') !== 'OK') return [];

        $result = [];
        foreach ($data['result']['reviews'] ?? [] as $r) {
            if (empty($r['text'])) continue;
            $result[] = [
                'external_id' => md5($placeId . $r['author_name'] . $r['time']),
                'author'      => $r['author_name'] ?? 'Anonymní',
                'rating'      => isset($r['rating']) ? (int)$r['rating'] : null,
                'content'     => $r['text'],
                'date'        => isset($r['time']) ? date('Y-m-d', $r['time']) : null,
            ];
        }
        return $result;
    }


    // ---------------------------------------------------------------
    // JSON-LD fallback (funguje na všech platformách)
    // ---------------------------------------------------------------
    private static function extractJsonLd(string $html, string $url): array
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $matches);
        $result = [];

        foreach ($matches[1] as $json) {
            $data = @json_decode(trim($json), true);
            if (!$data) continue;

            // Může být pole nebo objekt
            $items = isset($data[0]) ? $data : [$data];
            foreach ($items as $item) {
                $type    = $item['@type'] ?? '';
                $reviews = [];

                if ($type === 'Review') {
                    $reviews = [$item];
                } elseif (isset($item['review'])) {
                    $reviews = is_array($item['review'][0] ?? null) ? $item['review'] : [$item['review']];
                } elseif (isset($item['reviews'])) {
                    $reviews = $item['reviews'];
                }

                foreach ($reviews as $i => $r) {
                    $content = $r['reviewBody'] ?? $r['description'] ?? '';
                    $author  = $r['author']['name'] ?? $r['author'] ?? 'Anonymní';
                    $rating  = isset($r['reviewRating']['ratingValue']) ? (int)$r['reviewRating']['ratingValue'] : null;
                    $date    = $r['datePublished'] ?? null;

                    $result[] = [
                        'external_id' => md5($author . $content . ($date ?? '')),
                        'author'      => is_string($author) ? $author : 'Anonymní',
                        'rating'      => $rating,
                        'content'     => trim($content),
                        'date'        => $date ? date('Y-m-d', strtotime($date)) : null,
                    ];
                }
            }
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    private static function xpathText(\DOMXPath $xpath, string $query, \DOMNode $ctx): string
    {
        $nodes = $xpath->query($query, $ctx);
        return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) : '';
    }

    private static function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        // Zkus různé formáty
        foreach (['Y-m-d', 'd.m.Y', 'd. m. Y', 'm/d/Y', 'Y-m-d\TH:i:sP'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim($raw));
            if ($d) return $d->format('Y-m-d');
        }
        $ts = @strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private static function scrapeHeureka(string $xml, string $url = ''): array
    {
        if (!$xml) return [];

        // Suppress XML errors
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if (!$doc) return [];

        $reviews = [];
        foreach ($doc->review as $r) {
            $ratingId = (string)$r->rating_id;
            $ts       = (int)$r->unix_timestamp;
            $date     = $ts ? date('Y-m-d', $ts) : null;
            $rating   = (int)round((float)$r->total_rating);

            // Obsah: pros + summary spojeny
            $parts = [];
            if (!empty((string)$r->pros))    $parts[] = trim((string)$r->pros);
            if (!empty((string)$r->summary)) $parts[] = trim((string)$r->summary);
            $content = implode(' ', $parts);

            $reviews[] = [
                'external_id' => $ratingId ?: md5($content . $date),
                'author'      => 'Zákazník Heureka',
                'rating'      => min(5, max(1, $rating)),
                'content'     => $content,
                'date'        => $date,
            ];
        }
        return $reviews;
    }

    public static function scrapeShoptet(string $url): array
    {
        $reviews = [];
        $page    = 1;
        $maxPages = 500; // safety limit

        while ($page <= $maxPages) {
            $pageUrl = rtrim($url, '/') . '/strana-' . $page . '/';
            $html    = self::fetchHtml($pageUrl);
            if (!$html) break;

            $parsed = self::parseShoptetPage($html);
            if (empty($parsed)) break;

            $reviews = array_merge($reviews, $parsed);

            // Zjisti počet stránek z první stránky
            if ($page === 1) {
                if (preg_match('/strana-(\d+)\/[^"]*"[^>]*>\s*\d+\s*<\/a>\s*<\/li>\s*<\/ul>/i', $html, $m)) {
                    $maxPages = (int)$m[1];
                } else {
                    $maxPages = 1;
                }
            }

            $page++;
            usleep(300000);
        }

        return $reviews;
    }

    private static function parseShoptetPage(string $html): array
    {
        $reviews = [];
        // Extrahuj všechny vote-wrap bloky
        preg_match_all('/<div class="vote-wrap"[^>]*>(.*?)<\/div>\s*<\/div>\s*(?=<div class="vote-wrap"|<\/div>\s*<\/div>\s*<\/div>\s*(?:<!--|\/\/|$|<h2|<div id="paging))/si', $html, $matches);

        // Fallback: rozděl na vote-wrap sekce manuálně
        $chunks = preg_split('/<div class="vote-wrap"[^>]*>/', $html);
        array_shift($chunks); // první je před prvním výsledkem

        foreach ($chunks as $chunk) {
            // Autor
            $author = '';
            if (preg_match('/<span[^>]*data-testid="textRatingAuthor"[^>]*>\s*<span>([^<]+)<\/span>/i', $chunk, $m)) {
                $author = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }

            // Rating (počet star-on)
            preg_match_all('/<span class="star star-on[^"]*"><\/span>/i', $chunk, $stars);
            $rating = count($stars[0]);
            if ($rating === 0) continue; // přeskoč pokud není rating

            // Datum
            $date = null;
            if (preg_match('/<span[^>]*data-testid="latestContributionDate"[^>]*>\s*([\d.]+)\s*<\/span>/i', $chunk, $m)) {
                $parts = explode('.', trim($m[1]));
                if (count($parts) === 3) {
                    $date = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                }
            }

            // Obsah
            $content = '';
            if (preg_match('/<div[^>]*data-testid="textRating"[^>]*>\s*(.*?)\s*<\/div>/si', $chunk, $m)) {
                $content = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }

            if (!$author && !$content) continue;

            $externalId = md5($author . '|' . $content . '|' . $date);
            $reviews[]  = [
                'external_id' => $externalId,
                'author'      => $author ?: 'Zákazník',
                'rating'      => min(5, max(1, $rating)),
                'content'     => $content,
                'date'        => $date,
            ];
        }

        return $reviews;
    }
}
namespace ShopCode\Services;

/**
 * Scraper recenzí z Heureka, Trusted Shops, Shoptet
 */
class ReviewScraper
{
    private static int $timeout = 20;

    /**
     * Hlavní metoda — podle platformy zavolá správný scraper
     * Vrací pole ['external_id', 'author', 'rating', 'content', 'date']
     */
    public static function scrape(string $url, string $platform): array
    {
        // Heureka a Shoptet fetchují interně (stránkování / XML)
        if ($platform === 'heureka') {
            $xml = self::fetch($url);
            return $xml ? self::scrapeHeureka($xml, $url) : [];
        }
        if ($platform === 'shoptet') {
            return self::scrapeShoptet($url);
        }

        $html = self::fetch($url);
        if (!$html) return [];

        return match($platform) {
            'trustedshops' => self::scrapeTrustedShops($html, $url),
            default        => [],
        };
    }

    private static function fetch(string $url): ?string
    {
        $parsed  = parse_url($url);
        $referer = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::$timeout,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: cs-CZ,cs;q=0.9,sk;q=0.8,en-US;q=0.7,en;q=0.6',
                'Accept-Encoding: gzip, deflate, br',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1',
                'Referer: ' . $referer,
            ],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEFILE     => '',  // prázdný cookie jar — akceptuje cookies
            CURLOPT_COOKIEJAR      => '',
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($html && $code === 200) ? $html : null;
    }

    // ---------------------------------------------------------------
    // Heureka
    // ---------------------------------------------------------------
    // ---------------------------------------------------------------
    // Trusted Shops
    // ---------------------------------------------------------------
    private static function scrapeTrustedShops(string $html, string $url): array
    {
        // Zjisti celkový počet z JSON-LD AggregateRating nebo HTML
        $allReviews = self::extractJsonLd($html, $url);
        $total      = 0;

        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $scripts);
        foreach ($scripts[1] as $json) {
            $data = @json_decode(trim($json), true);
            if (isset($data['aggregateRating']['reviewCount'])) {
                $total = (int)$data['aggregateRating']['reviewCount'];
                break;
            }
        }

        // HTML je React SSR double-encoded — dekóduj dvakrát
        if (!$total) {
            $decoded = html_entity_decode(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/(\d+)\s*Bewertungen\s*insgesamt/i', $decoded, $m)) {
                $total = (int)str_replace('.', '', $m[1]);
            }
        }

        // Stránkování — každá stránka má 20 recenzí, jedeme dokud přicházejí data
        $perPage = 20;
        $pages   = $total > 0 ? min((int)ceil($total / $perPage), 50) : 50;
        $baseUrl = preg_replace('/[?&]page=\d+/', '', $url);
        $sep     = str_contains($baseUrl, '?') ? '&' : '?';
        for ($page = 2; $page <= $pages; $page++) {
            usleep(300000);
            $pageHtml = self::fetch($baseUrl . $sep . 'page=' . $page);
            if (!$pageHtml) {
    
                break;
            }
            $pageReviews = self::extractJsonLd($pageHtml, $url);

            if (empty($pageReviews)) break;
            $allReviews = array_merge($allReviews, $pageReviews);
        }
        if (!empty($allReviews)) return $allReviews;

        // CSS fallback
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath  = new \DOMXPath($doc);
        $nodes  = $xpath->query('//*[contains(@class,"review") or contains(@class,"Review")]');
        $result = [];
        foreach ($nodes as $i => $node) {
            $content = self::xpathText($xpath, './/*[contains(@class,"comment") or contains(@class,"text") or contains(@class,"body")]', $node);
            $author  = self::xpathText($xpath, './/*[contains(@class,"author") or contains(@class,"name") or contains(@class,"buyer")]', $node) ?: 'Anonymní';
            $dateRaw = self::xpathText($xpath, './/*[contains(@class,"date") or @itemprop="datePublished"]', $node);
            if (!$content) continue;
            $result[] = [
                'external_id' => md5($url . $i . $content),
                'author'      => trim($author),
                'rating'      => null,
                'content'     => trim($content),
                'date'        => self::parseDate($dateRaw),
            ];
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // Shoptet
    // ---------------------------------------------------------------

    // ---------------------------------------------------------------
    // Google Places API
    // $url = Place ID (např. ChIJN1t_tDeuEmsRUsoyG83frY4)
    // ---------------------------------------------------------------
    public static function scrapeGooglePlaces(string $placeId, string $apiKey): array
    {
        $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json?'
            . http_build_query([
                'place_id' => $placeId,
                'fields'   => 'reviews',
                'language' => 'cs',
                'key'      => $apiKey,
            ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return [];
        $data = @json_decode($response, true);
        if (($data['status'] ?? '') !== 'OK') return [];

        $result = [];
        foreach ($data['result']['reviews'] ?? [] as $r) {
            if (empty($r['text'])) continue;
            $result[] = [
                'external_id' => md5($placeId . $r['author_name'] . $r['time']),
                'author'      => $r['author_name'] ?? 'Anonymní',
                'rating'      => isset($r['rating']) ? (int)$r['rating'] : null,
                'content'     => $r['text'],
                'date'        => isset($r['time']) ? date('Y-m-d', $r['time']) : null,
            ];
        }
        return $result;
    }


    // ---------------------------------------------------------------
    // JSON-LD fallback (funguje na všech platformách)
    // ---------------------------------------------------------------
    private static function extractJsonLd(string $html, string $url): array
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $matches);
        $result = [];

        foreach ($matches[1] as $json) {
            $data = @json_decode(trim($json), true);
            if (!$data) continue;

            // Může být pole nebo objekt
            $items = isset($data[0]) ? $data : [$data];
            foreach ($items as $item) {
                $type    = $item['@type'] ?? '';
                $reviews = [];

                if ($type === 'Review') {
                    $reviews = [$item];
                } elseif (isset($item['review'])) {
                    $reviews = is_array($item['review'][0] ?? null) ? $item['review'] : [$item['review']];
                } elseif (isset($item['reviews'])) {
                    $reviews = $item['reviews'];
                }

                foreach ($reviews as $i => $r) {
                    $content = $r['reviewBody'] ?? $r['description'] ?? '';
                    $author  = $r['author']['name'] ?? $r['author'] ?? 'Anonymní';
                    $rating  = isset($r['reviewRating']['ratingValue']) ? (int)$r['reviewRating']['ratingValue'] : null;
                    $date    = $r['datePublished'] ?? null;

                    $result[] = [
                        'external_id' => md5($author . $content . ($date ?? '')),
                        'author'      => is_string($author) ? $author : 'Anonymní',
                        'rating'      => $rating,
                        'content'     => trim($content),
                        'date'        => $date ? date('Y-m-d', strtotime($date)) : null,
                    ];
                }
            }
        }
        return $result;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    private static function xpathText(\DOMXPath $xpath, string $query, \DOMNode $ctx): string
    {
        $nodes = $xpath->query($query, $ctx);
        return $nodes && $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) : '';
    }

    private static function parseDate(?string $raw): ?string
    {
        if (!$raw) return null;
        // Zkus různé formáty
        foreach (['Y-m-d', 'd.m.Y', 'd. m. Y', 'm/d/Y', 'Y-m-d\TH:i:sP'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim($raw));
            if ($d) return $d->format('Y-m-d');
        }
        $ts = @strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private static function scrapeHeureka(string $xml, string $url = ''): array
    {
        if (!$xml) return [];

        // Suppress XML errors
        $prev = libxml_use_internal_errors(true);
        $doc  = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);
        if (!$doc) return [];

        $reviews = [];
        foreach ($doc->review as $r) {
            $ratingId = (string)$r->rating_id;
            $ts       = (int)$r->unix_timestamp;
            $date     = $ts ? date('Y-m-d', $ts) : null;
            $rating   = (int)round((float)$r->total_rating);

            // Obsah: pros + summary spojeny
            $parts = [];
            if (!empty((string)$r->pros))    $parts[] = trim((string)$r->pros);
            if (!empty((string)$r->summary)) $parts[] = trim((string)$r->summary);
            $content = implode(' ', $parts);

            $reviews[] = [
                'external_id' => $ratingId ?: md5($content . $date),
                'author'      => 'Zákazník Heureka',
                'rating'      => min(5, max(1, $rating)),
                'content'     => $content,
                'date'        => $date,
            ];
        }
        return $reviews;
    }

    public static function scrapeShoptet(string $url): array
    {
        $reviews = [];
        $page    = 1;
        $maxPages = 500; // safety limit

        while ($page <= $maxPages) {
            $pageUrl = rtrim($url, '/') . '/strana-' . $page . '/';
            $html    = self::fetchHtml($pageUrl);
            if (!$html) break;

            $parsed = self::parseShoptetPage($html);
            if (empty($parsed)) break;

            $reviews = array_merge($reviews, $parsed);

            // Zjisti počet stránek z první stránky
            if ($page === 1) {
                if (preg_match('/strana-(\d+)\/[^"]*"[^>]*>\s*\d+\s*<\/a>\s*<\/li>\s*<\/ul>/i', $html, $m)) {
                    $maxPages = (int)$m[1];
                } else {
                    $maxPages = 1;
                }
            }

            $page++;
            usleep(300000);
        }

        return $reviews;
    }

    private static function parseShoptetPage(string $html): array
    {
        $reviews = [];
        // Extrahuj všechny vote-wrap bloky
        preg_match_all('/<div class="vote-wrap"[^>]*>(.*?)<\/div>\s*<\/div>\s*(?=<div class="vote-wrap"|<\/div>\s*<\/div>\s*<\/div>\s*(?:<!--|\/\/|$|<h2|<div id="paging))/si', $html, $matches);

        // Fallback: rozděl na vote-wrap sekce manuálně
        $chunks = preg_split('/<div class="vote-wrap"[^>]*>/', $html);
        array_shift($chunks); // první je před prvním výsledkem

        foreach ($chunks as $chunk) {
            // Autor
            $author = '';
            if (preg_match('/<span[^>]*data-testid="textRatingAuthor"[^>]*>\s*<span>([^<]+)<\/span>/i', $chunk, $m)) {
                $author = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }

            // Rating (počet star-on)
            preg_match_all('/<span class="star star-on[^"]*"><\/span>/i', $chunk, $stars);
            $rating = count($stars[0]);
            if ($rating === 0) continue; // přeskoč pokud není rating

            // Datum
            $date = null;
            if (preg_match('/<span[^>]*data-testid="latestContributionDate"[^>]*>\s*([\d.]+)\s*<\/span>/i', $chunk, $m)) {
                $parts = explode('.', trim($m[1]));
                if (count($parts) === 3) {
                    $date = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
                }
            }

            // Obsah
            $content = '';
            if (preg_match('/<div[^>]*data-testid="textRating"[^>]*>\s*(.*?)\s*<\/div>/si', $chunk, $m)) {
                $content = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }

            if (!$author && !$content) continue;

            $externalId = md5($author . '|' . $content . '|' . $date);
            $reviews[]  = [
                'external_id' => $externalId,
                'author'      => $author ?: 'Zákazník',
                'rating'      => min(5, max(1, $rating)),
                'content'     => $content,
                'date'        => $date,
            ];
        }

        return $reviews;
    }
}
