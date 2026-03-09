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
        if ($platform === 'heureka') {
            $xml = self::fetch($url);
            return $xml ? self::scrapeHeureka($xml) : [];
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
    private static function scrapeHeureka(string $xml): array
    {
        if (!$xml) return [];
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
            $parts    = [];
            if (!empty((string)$r->pros))    $parts[] = trim((string)$r->pros);
            if (!empty((string)$r->summary)) $parts[] = trim((string)$r->summary);
            $content  = implode(' ', $parts);
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
    public static function scrapeShoptet(string $url): array
    {
        $reviews  = [];
        $page     = 1;
        $maxPages = 500;

        while ($page <= $maxPages) {
            $pageUrl = rtrim($url, '/') . '/strana-' . $page . '/';
            $html    = self::fetch($pageUrl);
            if (!$html) break;
            $parsed = self::parseShoptetPage($html);
            if (empty($parsed)) break;
            $reviews = array_merge($reviews, $parsed);
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
        $chunks  = preg_split('/<div class="vote-wrap"[^>]*>/', $html);
        array_shift($chunks);
        foreach ($chunks as $chunk) {
            $author = '';
            if (preg_match('/<span[^>]*data-testid="textRatingAuthor"[^>]*>\s*<span>([^<]+)<\/span>/i', $chunk, $m)) {
                $author = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }
            preg_match_all('/<span class="star star-on[^"]*"><\/span>/i', $chunk, $stars);
            $rating = count($stars[0]);
            if ($rating === 0) continue;
            $date = null;
            if (preg_match('/<span[^>]*data-testid="latestContributionDate"[^>]*>\s*([\d.]+)\s*<\/span>/i', $chunk, $m)) {
                $parts = explode('.', trim($m[1]));
                if (count($parts) === 3) $date = sprintf('%04d-%02d-%02d', $parts[2], $parts[1], $parts[0]);
            }
            $content = '';
            if (preg_match('/<div[^>]*data-testid="textRating"[^>]*>\s*(.*?)\s*<\/div>/si', $chunk, $m)) {
                $content = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            }
            if (!$author && !$content) continue;
            $reviews[] = [
                'external_id' => md5($author . '|' . $content . '|' . $date),
                'author'      => $author ?: 'Zákazník',
                'rating'      => min(5, max(1, $rating)),
                'content'     => $content,
                'date'        => $date,
            ];
        }
        return $reviews;
    }

    // ---------------------------------------------------------------
    // Google Places API
    // $url = Place ID (např. ChIJN1t_tDeuEmsRUsoyG83frY4)
    // ---------------------------------------------------------------
    public static function scrapeGooglePlaces(string $placeId, string $apiKey): array
    {
        // Google Places API vrací max 5 recenzí per jazyk
        // Dotážeme se na více jazyků abychom získali více unikátních recenzí
        $languages = ['cs', 'de', 'en', 'pl', 'sk'];
        $seen   = [];
        $result = [];

        foreach ($languages as $lang) {
            $endpoint = 'https://maps.googleapis.com/maps/api/place/details/json?'
                . http_build_query([
                    'place_id'    => $placeId,
                    'fields'      => 'reviews',
                    'language'    => $lang,
                    'reviews_sort'=> 'newest',
                    'key'         => $apiKey,
                ]);

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) continue;
            $data = @json_decode($response, true);
            if (($data['status'] ?? '') !== 'OK') continue;

            foreach ($data['result']['reviews'] ?? [] as $r) {
                $dedupKey = $placeId . '|' . ($r['author_name'] ?? '') . '|' . ($r['time'] ?? '');
                if (isset($seen[$dedupKey])) continue;
                $seen[$dedupKey] = true;

                $result[] = [
                    'external_id' => md5($dedupKey),
                    'author'      => $r['author_name'] ?? 'Anonymní',
                    'rating'      => isset($r['rating']) ? (int)$r['rating'] : null,
                    'content'     => $r['text'] ?? '',
                    'date'        => isset($r['time']) ? date('Y-m-d', (int)$r['time']) : null,
                ];
            }
            usleep(200000);
        }
        return $result;
    }

    private static function scrapeGoogle(string $html, string $url): array
    {
        // Google Maps vyžaduje Places API — tato metoda není použita
        return [];
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

    /**
     * Google recenze přes Outscraper API
     * URL/place_id: Google Maps URL nebo Place ID (ChIJ...)
     * apiKey: Outscraper API klíč
     */
    public static function scrapeOutscraper(string $placeId, string $apiKey, int $limit = 100): array
    {
        // Outscraper akceptuje Google Maps URL i Place ID
        $endpoint = 'https://api.app.outscraper.com/maps/reviews-v3?' . http_build_query([
            'query'       => $placeId,
            'reviewsLimit' => $limit,
            'language'    => 'en',
            'async'       => 'false',
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'X-API-KEY: ' . $apiKey,
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$body) return [];

        $data = @json_decode($body, true);
        if (!$data || empty($data['data'][0])) return [];

        $reviews = [];
        foreach ($data['data'][0] as $place) {
            if (empty($place['reviews_data'])) continue;
            foreach ($place['reviews_data'] as $r) {
                $text = trim($r['review_text'] ?? '');
                $date = null;
                if (!empty($r['review_datetime_utc'])) {
                    $date = date('Y-m-d', strtotime($r['review_datetime_utc']));
                } elseif (!empty($r['review_timestamp'])) {
                    $date = date('Y-m-d', (int)$r['review_timestamp']);
                }
                $rating = (int)round((float)($r['review_rating'] ?? 0));
                $author = trim($r['author_title'] ?? 'Google zákazník');
                $extId  = $r['review_id'] ?? md5($author . $text . $date);

                $reviews[] = [
                    'external_id' => $extId,
                    'author'      => $author,
                    'rating'      => min(5, max(1, $rating)),
                    'content'     => $text,
                    'date'        => $date,
                ];
            }
        }
        return $reviews;
    }
}
