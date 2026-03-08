<?php

namespace ShopCode\Services;

class DeepLTranslator
{
    // Podporované cílové jazyky DeepL
    public const LANGUAGES = [
        'CS' => 'Čeština',
        'SK' => 'Slovenština',
        'EN-GB' => 'Angličtina',
        'DE' => 'Němčina',
        'PL' => 'Polština',
        'HU' => 'Maďarština',
        'RO' => 'Rumunština',
        'HR' => 'Chorvatština',
        'SL' => 'Slovinština',
        'FR' => 'Francouzština',
        'IT' => 'Italština',
        'ES' => 'Španělština',
        'NL' => 'Nizozemština',
    ];

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Přeloží text do zadaného jazyka.
     * Vrací přeložený text nebo null při chybě.
     */
    public function translate(string $text, string $targetLang): ?string
    {
        if (empty(trim($text))) return null;

        // DeepL free API endpoint (klíče končí :fx)
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
        return $data['translations'][0]['text'] ?? null;
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
