<?php

namespace ShopCode\Services;

/**
 * Parser Shoptet CSV produktového exportu s konfigurovatelným mapováním sloupců.
 *
 * Základní struktura CSV (solution.shopcode.cz):
 *   code;pairCode;name;defaultCategory;
 *
 * Přes $fieldMap lze namapovat libovolné sloupce CSV na interní názvy polí:
 *   [
 *     'code'      => 'code',           // CSV sloupec 'code' → interní 'code'
 *     'pairCode'  => 'pairCode',       // CSV sloupec 'pairCode' → grupování
 *     'name'      => 'name',
 *     'category'  => 'defaultCategory',
 *     'price'     => 'cena',           // CSV sloupec 'cena' → interní 'price'
 *     'brand'     => 'znacka',
 *     ...
 *   ]
 *
 * Logika grupování variant:
 * - Prázdný pairCode sloupec (nebo není definován) → jednoduchý produkt
 * - Vyplněný pairCode → varianta; stejná hodnota pairCode = jedna skupina
 *   → produkt = name/category/... z prvního řádku skupiny
 *   → variantCodes = pole kódů všech řádků skupiny
 *
 * Kódování (automatická detekce): BOM UTF-8, BOM UTF-16, UTF-8, CP1250, ISO-8859-2
 */
class CsvParser
{
    private const DELIMITER = ';';

    /**
     * @param string        $filePath
     * @param callable      $callback  function(array $product, array $variantCodes): void
     *                                 $product klíče: code, name, category, price, brand,
     *                                          description, availability, pair_code + libovolné extra
     * @param array         $fieldMap  ['interní_pole' => 'název_sloupce_v_CSV']
     *                                 Výchozí hodnoty viz DEFAULT_MAP
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

        // Vyřešíme mapování: fieldMap['price'] = 'cena' → colIdx['price'] = 3
        $colIdx = self::resolveColumns($header, $fieldMap);

        if (!isset($colIdx['code'])) {
            throw new \RuntimeException(
                "CSV nemá sloupec pro 'code'. Nalezené sloupce: " .
                implode(', ', array_map('trim', $header))
            );
        }

        // Seskup řádky podle pairCode
        $grouped = [];
        $singles = [];

        foreach ($lines as $lineNum => $row) {
            if (empty(array_filter($row, fn($c) => trim($c) !== ''))) continue;

            $code     = trim($row[$colIdx['code']] ?? '');
            $pairCode = isset($colIdx['pairCode']) ? trim($row[$colIdx['pairCode']] ?? '') : '';

            if ($code === '') {
                $errors++;
                $errorLog[] = "Řádek " . ($lineNum + 2) . ": prázdný code, přeskočen";
                continue;
            }

            // Extrahuj všechna mapovaná pole z tohoto řádku
            $extracted = self::extractRow($row, $colIdx);

            if ($pairCode !== '') {
                $grouped[$pairCode][] = $extracted;
            } else {
                $singles[] = $extracted;
            }
        }

        // Zpracuj jednoduché produkty
        foreach ($singles as $row) {
            try {
                $callback(array_merge($row, ['pair_code' => null]), []);
                $processed++;
                if ($progress && $processed % 100 === 0) $progress($processed);
            } catch (\Throwable $e) {
                $errors++;
                $errorLog[] = "Produkt {$row['code']}: " . $e->getMessage();
            }
        }

        // Zpracuj skupiny variant
        foreach ($grouped as $pairCode => $rows) {
            try {
                // Produkt = první řádek skupiny, code = null (nemá vlastní)
                $product = array_merge($rows[0], [
                    'code'      => null,
                    'pair_code' => $pairCode,
                ]);
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
     * Rozhodne který index v CSV řádku odpovídá kterému internímu poli.
     * Priorita: fieldMap → automatická detekce podle standardních názvů
     *
     * @return array<string, int>  ['code' => 0, 'name' => 2, ...]
     */
    public static function resolveColumns(array $header, array $fieldMap = []): array
    {
        // Normalizovaná hlavička pro vyhledávání
        $normalHeader = [];
        foreach ($header as $i => $col) {
            $normalHeader[strtolower(trim($col))] = $i;
        }

        // Výchozí mapování (interní_pole => výchozí_název_sloupce_v_CSV)
        $defaults = [
            'code'         => 'code',
            'pairCode'     => 'paircode',
            'name'         => 'name',
            'category'     => 'defaultcategory',
            'price'        => 'price',
            'brand'        => 'brand',
            'description'  => 'description',
            'availability' => 'availability',
            'images'       => 'image',
        ];

        $result = [];
        foreach ($defaults as $internal => $defaultCsvCol) {
            // Preferuj fieldMap pokud zadáno
            $csvCol = strtolower(trim($fieldMap[$internal] ?? $defaultCsvCol));
            if (isset($normalHeader[$csvCol])) {
                $result[$internal] = $normalHeader[$csvCol];
            }
        }

        return $result;
    }

    /**
     * Extrahuje hodnoty z jednoho CSV řádku podle resolved colIdx
     */
    private static function extractRow(array $row, array $colIdx): array
    {
        $data = [];
        foreach ($colIdx as $internal => $idx) {
            if ($internal === 'pairCode') continue; // zpracováváme zvlášť
            $val = trim($row[$idx] ?? '');
            $data[$internal] = $val !== '' ? $val : null;
        }
        return $data;
    }

    /**
     * Načte první řádky CSV feedu a vrátí seznam sloupců + ukázkový řádek.
     * Použitelné pro preview před importem.
     */
    public static function preview(string $filePath): array
    {
        $raw  = file_get_contents($filePath);
        $text = self::decode($raw ?? '');
        if (!$text) return ['columns' => [], 'sample' => []];

        $lines = self::parseCsv($text);
        $header = $lines[0] ?? [];
        $sample = $lines[1] ?? [];

        return [
            'columns' => array_map('trim', $header),
            'sample'  => array_map('trim', $sample),
        ];
    }

    /**
     * Detekuje kódování a vrátí UTF-8 string.
     * BOM UTF-8 → BOM UTF-16 → platné UTF-8 → CP1250 (iconv) → ISO-8859-2
     */
    public static function decode(string $raw): ?string
    {
        if ($raw === '') return '';

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

        // CP1250 / Windows-1250
        $decoded = @iconv('CP1250', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')
            && self::looksCzech($decoded)) {
            return $decoded;
        }

        // ISO-8859-2
        $decoded = @iconv('ISO-8859-2', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }

        // Fallback CP1250
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
