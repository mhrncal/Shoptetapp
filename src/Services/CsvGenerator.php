<?php

namespace ShopCode\Services;

use ShopCode\Models\Review;

/**
 * Generuje CSV soubor ve formátu kompatibilním se Shoptet importem produktových fotek.
 * Každý řádek = jeden produkt (SKU + až 5 URL fotek).
 */
class CsvGenerator
{
    private string $tmpDir;
    private string $appUrl;

    public function __construct()
    {
        $this->tmpDir = ROOT . '/tmp';
        $this->appUrl = defined('APP_URL') ? APP_URL : '';
    }

    /**
     * Vygeneruje CSV soubor z pole recenzí.
     * @param  array  $reviews Výsledek Review::getPendingImport()
     * @return string Cesta k dočasnému souboru
     */
    public function generate(array $reviews): string
    {
        if (empty($reviews)) {
            throw new \RuntimeException('Žádné recenze ke generování.');
        }

        // Seskupíme fotky podle SKU
        $bySku = [];
        foreach ($reviews as $review) {
            $sku = $review['sku'] ?? $review['shoptet_id'] ?? null;
            if (!$sku) continue;

            foreach ($review['photos'] as $photo) {
                $url = $this->appUrl . '/uploads/' . $photo['path'];
                $bySku[$sku][] = $url;
            }
        }

        if (empty($bySku)) {
            throw new \RuntimeException('Žádné fotky pro CSV export.');
        }

        // Vytvoříme CSV soubor
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0755, true);
        }

        $filename = $this->tmpDir . '/shoptet_import_' . date('YmdHis') . '_' . uniqid() . '.csv';
        $handle   = fopen($filename, 'w');

        // BOM pro správné kódování v Excelu / Shoptetu
        fwrite($handle, "\xEF\xBB\xBF");

        // Hlavička — Shoptet formát
        fputcsv($handle, ['Kód', 'Fotka 1', 'Fotka 2', 'Fotka 3', 'Fotka 4', 'Fotka 5'], ';');

        foreach ($bySku as $sku => $urls) {
            $row = [$sku];
            for ($i = 0; $i < 5; $i++) {
                $row[] = $urls[$i] ?? '';
            }
            fputcsv($handle, $row, ';');
        }

        fclose($handle);
        return $filename;
    }

    /**
     * Smaže dočasný CSV soubor
     */
    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Vrátí seznam review ID zahrnutých v exportu
     */
    public function getReviewIds(array $reviews): array
    {
        return array_column(
            array_filter($reviews, fn($r) => !empty($r['sku']) || !empty($r['shoptet_id'])),
            'id'
        );
    }
}
