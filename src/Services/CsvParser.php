<?php

namespace ShopCode\Services;

/**
 * Parser Shoptet CSV produktového exportu.
 *
 * Struktura CSV: code;pairCode;name;defaultCategory; (nebo libovolná via fieldMap)
 *
 * Logika grupování:
 *   - Prázdný pairCode → jednoduchý produkt
 *   - Vyplněný pairCode → variantní produkt; stejný pairCode = jedna skupina
 *     → product = name/category/price/... z prvního řádku skupiny
 *     → variantCodes = kódy všech řádků skupiny
 *
 * fieldMap: [ 'code' => 'sloupec_v_csv', 'name' => 'jiny_sloupec', ... ]
 * Výchozí hodnoty pokud fieldMap chybí:
 *   code     → 'code'
 *   pairCode → 'pairCode'
 *   name     → 'name'
 *   category → 'defaultCategory'
 *
 * Kódování (automatická detekce): UTF-8 BOM, UTF-8, UTF-16 LE/BE, CP1250, ISO-8859-2
 */
class CsvParser
{
    private const DELIMITER = ';';

    // Výchozí mapování interní pole → název CSV sloupce
    private const DEFAULT_MAP = [
        'code'         => 'code',
        'pairCode'     => 'pairCode',
        'name'         => 'name',
        'category'     => 'defaultCategory',
        'price'        => '',          // prázdné = neimportovat pokud není nastaveno
        'brand'        => '',
        'description'  => '',
        'availability' => '',
        'images'       => '',
        'ean'          => '',
        'stock'        => '',
    ];

    /**
     * @param string        $filePath
     * @param callable      $callback  function(array $product, array $variantCodes): void
     *                                 $product obsahuje všechna namapovaná pole jako string|null
     *                                 $variantCodes = [] | ['KOD1', 'KOD2', ...]
     * @param array         $fieldMap  [ 'code' => 'col_name', 'price' => 'cena_sdph', ... ]
     *                                 Přepisuje DEFAULT_MAP. Prázdný string = ignoruj pole.
     * @param callable|null $progress  function(int $processed): void
     * @return array{processed: int, errors: int, error_log: string[]}
     */
    public static function stream(
        string    $filePath,
        callable  $callback,
        array     $fieldMap  = [],
        ?callable $progress  = null
    ): array {
        $processed = 0;
        $errors    = 0;
        $errorLog  = [];

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            throw new \RuntimeException("Nelze otevřít CSV: {$filePath}");
        }

        $text = self::decode($raw);
        if ($text === null) {
            throw new \RuntimeException("Nepodařilo se dekódovat CSV soubor.");
        }

        $lines  = self::parseCsv($text);
        $header = array_shift($lines);
        if (!$header) {
            throw new \RuntimeException("CSV nemá hlavičku.");
        }

        // Slouč výchozí mapování s uživatelským
        $map = array_merge(self::DEFAULT_MAP, array_filter($fieldMap, fn($v) => $v !== ''));

        // Sestavení indexu sloupců: colName → index v CSV
        $headerIndex = [];
        foreach ($header as $i => $col) {
            $headerIndex[trim($col)] = $i;
        }

        // Ověř povinný sloupec 'code'
        $codeCol = $map['code'] ?? 'code';
        if (!isset($headerIndex[$codeCol])) {
            throw new \RuntimeException(
                "CSV nemá sloupec '{$codeCol}' (interní pole 'code'). " .
                "Dostupné sloupce: " . implode(', ', array_keys($headerIndex))
            );
        }

        // Sestavení resolveru: interní_pole → index CSV sloupce (nebo null)
        $resolver = [];
        foreach ($map as $internal => $csvCol) {
            $resolver[$internal] = ($csvCol !== '' && isset($headerIndex[$csvCol]))
                ? $headerIndex[$csvCol]
                : null;
        }

        // Parsování řádků
        $grouped = [];   // pairCode → [rows]
        $singles = [];

        $pairColIdx = $resolver['pairCode'] ?? null;
        $codeColIdx = $resolver['code'];

        foreach ($lines as $lineNum => $row) {
            if (empty(array_filter($row, fn($c) => trim($c) !== ''))) continue;

            $code     = trim($row[$codeColIdx] ?? '');
            $pairCode = $pairColIdx !== null ? trim($row[$pairColIdx] ?? '') : '';

            if ($code === '') {
                $errors++;
                $errorLog[] = "Řádek " . ($lineNum + 2) . ": prázdný code, přeskočen";
                continue;
            }

            // Extrahuj všechna dostupná pole
            $fields = self::extractFields($row, $resolver);

            if ($pairCode !== '') {
                $grouped[$pairCode][] = $fields;
            } else {
                $singles[] = $fields;
            }
        }

        // Jednoduché produkty
        foreach ($singles as $fields) {
            try {
                $callback(array_merge($fields, ['pair_code' => null]), []);
                $processed++;
                if ($progress && $processed % 100 === 0) $progress($processed);
            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = "Produkt {$fields['code']}: " . $e->getMessage();
            }
        }

        // Skupiny variant
        foreach ($grouped as $pairCode => $rows) {
            try {
                // Produkt = data z prvního řádku skupiny
                $product = array_merge($rows[0], ['pair_code' => $pairCode, 'code' => null]);
                // Kódy variant = code ze všech řádků skupiny
                $variantCodes = array_column($rows, 'code');

                $callback($product, $variantCodes);
                $processed++;
                if ($progress && $processed % 100 === 0) $progress($processed);
            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = "Skupina pairCode={$pairCode}: " . $e->getMessage();
            }
        }

        return compact('processed', 'errors') + ['error_log' => $errorLog];
    }

    /**
     * Extrahuje všechna interní pole z řádku CSV podle resolveru.
     * Vrátí: ['code' => 'PC858', 'name' => 'Produkt', 'price' => '299', ...]
     */
    private static function extractFields(array $row, array $resolver): array
    {
        $fields = [];
        foreach ($resolver as $internal => $colIdx) {
            $fields[$internal] = ($colIdx !== null && isset($row[$colIdx]))
                ? (trim($row[$colIdx]) ?: null)
                : null;
        }
        return $fields;
    }

    /**
     * Detekuje kódování a vrátí UTF-8 string.
     * Pořadí: BOM UTF-8 → BOM UTF-16 → platné UTF-8 → CP1250 (iconv) → ISO-8859-2
     */
    public static function decode(string $raw): ?string
    {
        // BOM UTF-8 (EF BB BF)
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        // BOM UTF-16 LE (FF FE)
        if (str_starts_with($raw, "\xFF\xFE")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
        }

        // BOM UTF-16 BE (FE FF)
        if (str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }

        // Platné UTF-8 bez BOM
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        // CP1250 / Windows-1250 přes iconv
        $decoded = @iconv('CP1250', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8') && self::looksCzech($decoded)) {
            return $decoded;
        }

        // ISO-8859-2
        $decoded = @iconv('ISO-8859-2', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        // Poslední záchrana — CP1250 bez kontroly
        $decoded = @iconv('CP1250', 'UTF-8//IGNORE', $raw);
        return $decoded !== false ? $decoded : null;
    }

    private static function looksCzech(string $text): bool
    {
        return (bool)preg_match('/[áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/u', $text);
    }

    private static function parseCsv(string $text): array
    {
        $text   = str_replace(["\r\n", "\r"], "\n", $text);
        $result = [];
        foreach (explode("\n", $text) as $line) {
            if (trim($line) === '') continue;
            $result[] = str_getcsv($line, self::DELIMITER, '"');
        }
        return $result;
    }
}
