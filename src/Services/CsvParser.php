<?php

namespace ShopCode\Services;

/**
 * Parser Shoptet CSV produktového exportu.
 *
 * Struktura CSV (solution.shopcode.cz):
 *   code;pairCode;name;defaultCategory;
 *
 * Logika grupování:
 * - Prázdný pairCode → jednoduchý produkt
 * - Vyplněný pairCode → varianta; stejný pairCode = jedna skupina
 *   → produkt = name + category z prvního řádku skupiny
 *   → variantCodes = pole kódů všech řádků skupiny
 *
 * Kódování (detekce automatická):
 * - UTF-8 s BOM (EF BB BF)
 * - UTF-8 bez BOM
 * - Windows-1250 / CP1250 (přes iconv)
 * - ISO-8859-2 (přes iconv)
 */
class CsvParser
{
    private const DELIMITER = ';';

    /**
     * @param string        $filePath
     * @param callable      $callback  function(array $product, array $variantCodes): void
     * @param callable|null $progress  function(int $processed): void
     * @return array{processed: int, errors: int, error_log: string[]}
     */
    public static function stream(
        string    $filePath,
        callable  $callback,
        ?callable $progress = null
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

        // Namapuj sloupce (case-insensitive, ignoruj trailing whitespace)
        $colMap = [];
        foreach ($header as $i => $col) {
            $colMap[strtolower(trim($col))] = $i;
        }

        $codeCol     = $colMap['code']           ?? null;
        $pairCol     = $colMap['paircode']        ?? null;
        $nameCol     = $colMap['name']            ?? null;
        $categoryCol = $colMap['defaultcategory'] ?? null;

        if ($codeCol === null) {
            throw new \RuntimeException("CSV nemá sloupec 'code'. Nalezené sloupce: " . implode(', ', array_keys($colMap)));
        }

        // Seskup řádky
        $grouped = [];   // pairCode (string) → array of rows
        $singles = [];   // jednoduché produkty

        foreach ($lines as $lineNum => $row) {
            if (empty(array_filter($row, fn($c) => trim($c) !== ''))) continue;

            $code     = trim($row[$codeCol] ?? '');
            $pairCode = $pairCol !== null ? trim($row[$pairCol] ?? '') : '';
            $name     = $nameCol !== null ? trim($row[$nameCol] ?? '') : '';
            $category = $categoryCol !== null ? trim($row[$categoryCol] ?? '') : '';

            if ($code === '') {
                $errors++;
                $errorLog[] = "Řádek " . ($lineNum + 2) . ": prázdný code, přeskočen";
                continue;
            }

            if ($pairCode !== '') {
                $grouped[$pairCode][] = compact('code', 'name', 'category');
            } else {
                $singles[] = compact('code', 'name', 'category');
            }
        }

        // Zpracuj jednoduché produkty
        foreach ($singles as $row) {
            try {
                $callback([
                    'name'      => $row['name'] ?: null,
                    'code'      => $row['code'],
                    'pair_code' => null,
                    'category'  => $row['category'] ?: null,
                ], []);
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
                $first = $rows[0];
                $callback([
                    'name'      => $first['name'] ?: null,
                    'code'      => null,          // variantní produkt nemá vlastní code
                    'pair_code' => $pairCode,
                    'category'  => $first['category'] ?: null,
                ], array_column($rows, 'code'));
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
     * Detekuje kódování a vrátí UTF-8 string.
     * Pořadí detekce: BOM UTF-8 → BOM UTF-16 → platné UTF-8 → CP1250 (iconv) → ISO-8859-2
     */
    public static function decode(string $raw): ?string
    {
        // BOM UTF-8 (EF BB BF)
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);  // už je UTF-8, jen odstranit BOM
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

        // Poslední záchrana — CP1250 bez kontroly češtiny
        $decoded = @iconv('CP1250', 'UTF-8//IGNORE', $raw);
        return $decoded !== false ? $decoded : null;
    }

    /**
     * Obsahuje text česká písmena (validní UTF-8)?
     */
    private static function looksCzech(string $text): bool
    {
        return (bool)preg_match('/[áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/u', $text);
    }

    /**
     * Parsuje CSV text do pole řádků.
     */
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
