<?php

namespace ShopCode\Services;

class DeepLTranslator
{
    // Podporované cílové jazyky DeepL
    public const LANGUAGES = [
        'BG' => 'Bulharština',
        'CS' => 'Čeština',
        'SK' => 'Slovenština',
        'EN-GB' => 'Angličtina',
        'DE' => 'Němčina',
        'ES' => 'Španělština',
        'FR' => 'Francouzština',
        'HU' => 'Maďarština',
        'NL' => 'Nizozemština',
        'TR' => 'Turečtina',
        'RU' => 'Ruština',
        'UK' => 'Ukrajinština',
        'HR' => 'Chorvatština',
        'SL' => 'Slovinština',
        'IT' => 'Italština',
        'PL' => 'Polština',
    ];

    private string $apiKey;
    public ?string $lastDetectedLang = null;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Přeloží text do zadaného jazyka.
     * Vrací přeložený text nebo null při chybě.
     */
    public function translate(string $text, string $targetLang, ?string $sourceLang = null): ?string
    {
        if (empty(trim($text))) return null;

        // DeepL free API endpoint (klíče končí :fx)
        $endpoint = str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $params = [
            'text'        => $text,
            'target_lang' => strtoupper($targetLang),
        ];
        if ($sourceLang) {
            $params['source_lang'] = strtoupper($sourceLang);
        } else {
            // Bez source_lang DeepL může chybně detekovat jazyk — nejdřív detekujeme
            $detected = $this->detectLang($text);
            if ($detected) {
                $params['source_lang'] = strtoupper($detected);
                $this->lastDetectedLang = $detected;
            }
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => [
                'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) return null;

        $data = @json_decode($response, true);
        return $data['translations'][0]['text'] ?? null;
    }

    /**
     * Přeloží text a vrátí ['text' => ..., 'detected_lang' => ...]
     */
    public function translateWithLang(string $text, string $targetLang): ?array
    {
        if (empty(trim($text))) return null;

        $endpoint = str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'text'        => $text,
                'target_lang' => strtoupper($targetLang),
            ]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) return null;
        $data = @json_decode($response, true);
        $t    = $data['translations'][0] ?? null;
        if (!$t) return null;

        return [
            'text'          => $t['text'],
            'detected_lang' => strtoupper($t['detected_source_language'] ?? ''),
        ];
    }

    /**
     * Detekuje jazyk textu přes DeepL /v2/translate (přeloží do EN jen pro detekci — výsledek zahoď)
     */
    public function detectLang(string $text): ?string
    {
        $endpoint = str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'text'        => mb_substr($text, 0, 100),
                'target_lang' => 'EN-GB',
            ]),
            CURLOPT_HTTPHEADER => [
                'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = @json_decode($response, true);
        return strtoupper($data['translations'][0]['detected_source_language'] ?? '') ?: null;
    }

    /**
     * Přeloží pole textů najednou (batch)
     */
    public function translateBatch(array $texts, string $targetLang): array
    {
        if (empty($texts)) return [];

        $endpoint = str_ends_with($this->apiKey, ':fx')
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $params = ['target_lang' => strtoupper($targetLang)];
        foreach ($texts as $text) {
            $params['text[]'] = $text; // cURL nepodporuje pole přímo
        }

        // Sestavíme query string ručně pro pole
        $body = 'target_lang=' . urlencode(strtoupper($targetLang));
        foreach ($texts as $text) {
            $body .= '&text=' . urlencode($text);
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$response) return array_fill(0, count($texts), null);

        $data = @json_decode($response, true);
        return array_column($data['translations'] ?? [], 'text');
    }
}
