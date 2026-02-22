<?php

namespace ShopCode\Services;

/**
 * Stáhne XML feed na disk (streaming přes cURL)
 * Nepřidává soubor do RAM — vše teče na disk.
 */
class XmlDownloader
{
    private const CONNECT_TIMEOUT = 30;   // sec
    private const TOTAL_TIMEOUT   = 1800; // 30 min (500MB feed)
    private const CHUNK_SIZE      = 1024 * 1024 * 8; // 8MB buffer

    /**
     * @param string $url       URL XML feedu
     * @param string $destPath  Cesta kam uložit
     * @return array{ok: bool, size: int, error: string|null}
     */
    public static function download(string $url, string $destPath): array
    {
        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            return ['ok' => false, 'size' => 0, 'error' => "Nelze otevřít soubor pro zápis: {$destPath}"];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT,
            CURLOPT_BUFFERSIZE     => self::CHUNK_SIZE,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'ShopCode-XMLImporter/1.0',
            CURLOPT_ENCODING       => '', // accept gzip/deflate
            CURLOPT_LOW_SPEED_LIMIT => 1024,  // min 1KB/s
            CURLOPT_LOW_SPEED_TIME  => 60,    // po 60s bez dat = timeout
        ]);

        $ok    = curl_exec($ch);
        $error = $ok ? null : curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode >= 400) {
            @unlink($destPath);
            return [
                'ok'    => false,
                'size'  => 0,
                'error' => $error ?? "HTTP {$httpCode}",
            ];
        }

        $size = filesize($destPath);
        if ($size === 0) {
            @unlink($destPath);
            return ['ok' => false, 'size' => 0, 'error' => 'Stažený soubor je prázdný'];
        }

        return ['ok' => true, 'size' => $size, 'error' => null];
    }

    /**
     * Zkontroluje dostupnost URL (HEAD request)
     */
    public static function probe(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'ShopCode-XMLImporter/1.0',
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int)curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'ok'           => $code >= 200 && $code < 400,
            'http_code'    => $code,
            'content_size' => $size,
            'error'        => $err ?: null,
        ];
    }
}
