<?php

namespace ShopCode\Services;

use ShopCode\Core\Database;

/**
 * Streamové stahování a parsování Shoptet exportu fotek produktů.
 * Zpracovává CSV soubory s desetitisíci řádků bez načtení celého souboru do paměti.
 *
 * Formát CSV: code;pairCode;name;defaultImage;image;image2;...image28
 * Kódování: Windows-1250, oddělovač: ;
 */
class ShoptetCsvImporter
{
    private const CHUNK_SIZE = 8192; // 8 KB na chunk
    private const BATCH_SIZE = 500;  // řádků před DB flush
    private const DELIMITER  = ';';
    // Kódování se detekuje automaticky z BOM nebo prvního chunku

    private \PDO $db;
    private int      $userId;

    public function __construct(int $userId)
    {
        $this->db     = Database::getInstance();
        $this->userId = $userId;
    }

    /**
     * Stáhne CSV z URL streamově a importuje do DB.
     * @return array{rows: int, images: int, errors: string[]}
     */
    public function importFromUrl(string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL není dostupné.');
        }

        // Stáhneme do temp souboru streamově
        $tmpFile = tempnam(sys_get_temp_dir(), 'shopcode_csv_');
        $fp      = fopen($tmpFile, 'wb');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopCode/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '', // accept gzip/deflate
            CURLOPT_HTTPHEADER     => ['Accept: text/csv,text/plain,*/*'],
        ]);

        $ok      = curl_exec($ch);
        $errMsg  = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode < 200 || $httpCode >= 300) {
            @unlink($tmpFile);
            throw new \RuntimeException(
                "Nelze stáhnout CSV (HTTP {$httpCode})" . ($errMsg ? ": {$errMsg}" : '') . " z URL: {$url}"
            );
        }

        try {
            $stream = fopen($tmpFile, 'r');
            return $this->processStream($stream);
        } finally {
            if (isset($stream) && is_resource($stream)) fclose($stream);
            @unlink($tmpFile);
        }
    }

    /**
     * Zpracuje již otevřený stream (pro testování nebo lokální soubory).
     */
    public function processStream($stream): array
    {
        $rowCount   = 0;
        $imgCount   = 0;
        $errors     = [];
        $headerMap  = null;
        $buffer     = '';
        $batch      = [];
        $encoding   = null; // detekuje se z prvního chunku

        // Vymažeme staré záznamy uživatele před importem
        $this->db->prepare('DELETE FROM shoptet_product_images WHERE user_id = ?')
                 ->execute([$this->userId]);

        while (!feof($stream)) {
            $chunk  = fread($stream, self::CHUNK_SIZE);
            if ($chunk === false) break;

            // Detekce kódování z prvního chunku (BOM nebo heuristika)
            if ($encoding === null) {
                $encoding = $this->detectEncoding($chunk);
            }

            // Konverze kódování pouze pokud není UTF-8
            if ($encoding !== 'UTF-8') {
                $converted = @iconv($encoding, 'UTF-8//IGNORE', $chunk);
                $chunk = ($converted !== false) ? $converted : $chunk;
            }
            // Odstraň UTF-8 BOM pokud přítomen
            if ($buffer === '') {
                $chunk = ltrim($chunk, "\xEF\xBB\xBF");
            }
            $buffer .= $chunk;

            // Zpracuj kompletní řádky z bufferu
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = rtrim(substr($buffer, 0, $pos), "\r\n");
                $buffer = substr($buffer, $pos + 1);

                if (trim($line) === '') continue;

                $cols = str_getcsv($line, self::DELIMITER, '"', '\\');

                // První řádek = hlavička
                if ($headerMap === null) {
                    $headerMap = $this->buildHeaderMap($cols);
                    continue;
                }

                $result = $this->processRow($cols, $headerMap);
                if ($result === null) continue;

                $rowCount++;
                $imgCount += $result['img_count'];
                $batch[]   = $result;

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->flushBatch($batch);
                    $batch = [];
                }
            }
        }

        // Zpracuj zbytek bufferu (poslední řádek bez \n)
        if (trim($buffer) !== '') {
            $cols = str_getcsv(rtrim($buffer, "\r\n"), self::DELIMITER, '"', '\\');
            if ($headerMap !== null) {
                $result = $this->processRow($cols, $headerMap);
                if ($result !== null) {
                    $rowCount++;
                    $imgCount += $result['img_count'];
                    $batch[]   = $result;
                }
            }
        }

        if (!empty($batch)) {
            $this->flushBatch($batch);
        }

        return [
            'rows'   => $rowCount,
            'images' => $imgCount,
            'errors' => $errors,
        ];
    }

    /**
     * Detekuje kódování z prvního chunku dat.
     * Kontroluje UTF-8 BOM, pak zkouší validitu UTF-8, jinak předpokládá Windows-1250.
     */
    private function detectEncoding(string $chunk): string
    {
        // UTF-8 BOM
        if (str_starts_with($chunk, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }
        // Validní UTF-8?
        if (mb_check_encoding($chunk, 'UTF-8')) {
            return 'UTF-8';
        }
        // Fallback na windows-1250 (typické pro Shoptet CZ exporty)
        return 'windows-1250';
    }

    /**
     */
    private function buildHeaderMap(array $cols): array
    {
        $map = [];
        foreach ($cols as $i => $col) {
            $map[trim($col)] = $i;
        }
        return $map;
    }

    /**
     * Zpracuje jeden datový řádek a vrátí připravená data pro DB.
     */
    private function processRow(array $cols, array $headerMap): ?array
    {
        $skuIdx = $headerMap['code'] ?? null;
        if ($skuIdx === null) return null;

        $sku = trim($cols[$skuIdx] ?? '');
        if ($sku === '') return null;

        // Sbírej všechny URL fotek: defaultImage, image, image2..image28
        $imageColumns = ['defaultImage', 'image'];
        for ($i = 2; $i <= 28; $i++) {
            $imageColumns[] = 'image' . $i;
        }

        $urls = [];
        foreach ($imageColumns as $colName) {
            $idx = $headerMap[$colName] ?? null;
            if ($idx === null) continue;
            $url = trim($cols[$idx] ?? '');
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                // Odstraň query string (cache buster ?68d25dc5)
                $clean = strtok($url, '?');
                if ($clean && !in_array($clean, $urls, true)) {
                    $urls[] = $clean;
                }
            }
        }

        return [
            'sku'       => $sku,
            'urls'      => $urls,
            'img_count' => count($urls),
        ];
    }

    /**
     * Zapíše dávku řádků do DB pomocí INSERT ... ON DUPLICATE KEY UPDATE.
     */
    private function flushBatch(array $batch): void
    {
        if (empty($batch)) return;

        $placeholders = implode(', ', array_fill(0, count($batch), '(?, ?, ?, NOW())'));
        $sql = "INSERT INTO shoptet_product_images (user_id, sku, image_urls, updated_at)
                VALUES {$placeholders}
                ON DUPLICATE KEY UPDATE image_urls = VALUES(image_urls), updated_at = NOW()";

        $params = [];
        foreach ($batch as $row) {
            $params[] = $this->userId;
            $params[] = $row['sku'];
            $params[] = json_encode($row['urls'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->db->prepare($sql)->execute($params);
    }

    /**
     * Vrátí URL fotek pro dané SKU (pro použití při generování XML).
     * @return string[]
     */
    public static function getUrlsForSku(int $userId, string $sku): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT image_urls FROM shoptet_product_images WHERE user_id = ? AND sku = ?');
        $stmt->execute([$userId, $sku]);
        $row  = $stmt->fetch();
        if (!$row) return [];
        return json_decode($row['image_urls'], true) ?? [];
    }

    /**
     * Uloží/aktualizuje URL exportu pro uživatele.
     */
    public static function saveImportUrl(int $userId, string $url): void
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO shoptet_photo_imports (user_id, csv_url) VALUES (?, ?)
                      ON DUPLICATE KEY UPDATE csv_url = VALUES(csv_url)")
           ->execute([$userId, $url]);
    }

    /**
     * Vrátí uloženou konfiguraci importu pro uživatele.
     */
    public static function getImportConfig(int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM shoptet_photo_imports WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Aktualizuje statistiky po úspěšném importu.
     */
    public static function updateImportStats(int $userId, int $rows, int $images): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE shoptet_photo_imports
                      SET last_imported_at = NOW(), last_row_count = ?, last_image_count = ?
                      WHERE user_id = ?")
           ->execute([$rows, $images, $userId]);
    }
}
