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
        $html = self::fetch($url);
        if (!$html) return [];

        return match($platform) {
            'heureka'      => self::scrapeHeureka($html, $url),
            'trustedshops' => self::scrapeTrustedShops($html, $url),
            'shoptet'      => self::scrapeShoptet($html, $url),
            'google'       => self::scrapeGoogle($html, $url),
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
    private static function scrapeHeureka(string $html, string $url): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);
        $reviews = [];

        // Heureka: recenze jsou v .c-review nebo [data-review]
        $nodes = $xpath->query('//*[contains(@class,"c-review") or contains(@class,"review-item")]');

        foreach ($nodes as $i => $node) {
            $content = self::xpathText($xpath, './/*[contains(@class,"c-review__text") or contains(@class,"review-body") or contains(@class,"review__text")]', $node)
                    ?: self::xpathText($xpath, './/p', $node);

            $author  = self::xpathText($xpath, './/*[contains(@class,"c-review__author") or contains(@class,"review-author") or contains(@class,"review__author")]', $node)
                    ?: 'Anonymní';

            $ratingEl = $xpath->query('.//*[@class and (contains(@class,"c-stars") or contains(@class,"rating"))]/@*[name()="data-rating" or name()="aria-label" or name()="title"]', $node);
            $rating   = null;
            if ($ratingEl->length > 0) {
                preg_match('/(\d+)/', $ratingEl->item(0)->nodeValue, $m);
                $rating = isset($m[1]) ? min(5, (int)$m[1]) : null;
            }

            $dateEl = self::xpathText($xpath, './/*[contains(@class,"c-review__date") or contains(@class,"review-date") or @itemprop="datePublished"]/@content', $node)
                   ?: self::xpathText($xpath, './/*[contains(@class,"date")]', $node);

            if (!$content) continue;

            $reviews[] = [
                'external_id' => md5($url . $i . $content),
                'author'      => trim($author),
                'rating'      => $rating,
                'content'     => trim($content),
                'date'        => self::parseDate($dateEl),
            ];
        }

        // Fallback: JSON-LD
        if (empty($reviews)) {
            $reviews = array_merge($reviews, self::extractJsonLd($html, $url));
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
        $seen    = md5(implode('', array_column($allReviews, 'external_id')));

        for ($page = 2; $page <= $pages; $page++) {
            usleep(300000);
            $pageHtml = self::fetch($baseUrl . $sep . 'page=' . $page);
            if (!$pageHtml) break;
            $pageReviews = self::extractJsonLd($pageHtml, $url);
            if (empty($pageReviews)) break;
            // Detekuj duplikáty — stejná stránka = konec
            $newSeen = md5(implode('', array_column($pageReviews, 'external_id')));
            if ($newSeen === $seen) break;
            $seen = $newSeen;
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
    private static function scrapeShoptet(string $html, string $url): array
    {
        $reviews = self::extractJsonLd($html, $url);
        if (!empty($reviews)) return $reviews;

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new \DOMXPath($doc);

        $nodes = $xpath->query('//*[contains(@class,"rating-list__item") or contains(@class,"review-item") or contains(@class,"productReview")]');
        $result = [];

        foreach ($nodes as $i => $node) {
            $content = self::xpathText($xpath, './/*[contains(@class,"review-text") or contains(@class,"perex") or contains(@class,"comment")]', $node)
                    ?: self::xpathText($xpath, './/p', $node);
            $author  = self::xpathText($xpath, './/*[contains(@class,"author") or contains(@class,"user") or contains(@class,"name")]', $node) ?: 'Anonymní';
            $dateRaw = self::xpathText($xpath, './/*[contains(@class,"date") or @itemprop="datePublished"]', $node);
            $ratingEl = $xpath->query('.//*[@itemprop="ratingValue"]/@content', $node);
            $rating   = $ratingEl->length > 0 ? (int)$ratingEl->item(0)->nodeValue : null;

            if (!$content) continue;
            $result[] = [
                'external_id' => md5($url . $i . $content),
                'author'      => trim($author),
                'rating'      => $rating,
                'content'     => trim($content),
                'date'        => self::parseDate($dateRaw),
            ];
        }
        return $result;
    }

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
                    $content = $r['reviewBody'] ?? $r['description'] ?? null;
                    if (!$content) continue;
                    $author  = $r['author']['name'] ?? $r['author'] ?? 'Anonymní';
                    $rating  = isset($r['reviewRating']['ratingValue']) ? (int)$r['reviewRating']['ratingValue'] : null;
                    $date    = $r['datePublished'] ?? null;

                    $result[] = [
                        'external_id' => md5($url . $i . $content),
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
}
