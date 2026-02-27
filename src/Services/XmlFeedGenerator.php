<?php

namespace ShopCode\Services;

use ShopCode\Models\Review;

/**
 * Generuje XML feed ve formátu kompatibilním se Shoptet importem produktových fotek.
 * Každý produkt má element s SKU a URL fotkami.
 */
class XmlFeedGenerator
{
    private string $tmpDir;
    private string $feedsDir;
    private string $appUrl;

    public function __construct()
    {
        $this->tmpDir = ROOT . '/tmp';
        $this->feedsDir = ROOT . '/public/feeds';
        $this->appUrl = defined('APP_URL') ? APP_URL : '';
        
        // Vytvoř feeds adresář pokud neexistuje
        if (!is_dir($this->feedsDir)) {
            mkdir($this->feedsDir, 0755, true);
        }
    }

    /**
     * Vygeneruje XML feed z pole recenzí.
     * 
     * @param  int    $userId  ID uživatele (pro uložení do feeds/)
     * @param  array  $reviews Výsledek Review::getPendingImport()
     * @return string Cesta k souboru
     */
    public function generate(int $userId, array $reviews): string
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
            throw new \RuntimeException('Žádné fotky pro XML export.');
        }

        // Vytvoříme XML soubor
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');

        foreach ($bySku as $sku => $urls) {
            $product = $xml->addChild('product');
            $product->addChild('code', htmlspecialchars($sku, ENT_XML1, 'UTF-8'));
            
            $images = $product->addChild('images');
            foreach ($urls as $url) {
                $images->addChild('image', htmlspecialchars($url, ENT_XML1, 'UTF-8'));
            }
        }

        // Uložíme do tmp/ pro dočasný export
        $tmpFilename = $this->tmpDir . '/shoptet_export_' . date('YmdHis') . '_' . uniqid() . '.xml';
        
        // Naformátovaný XML s odsazením
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($tmpFilename);

        return $tmpFilename;
    }

    /**
     * Vygeneruje permanentní XML feed pro uživatele (používá CRON)
     * Uloží do /public/feeds/user_{userId}_reviews.xml
     * 
     * @param  int   $userId  ID uživatele
     * @param  array $reviews Schválené recenze
     * @return string URL k feedu
     */
    public function generatePermanentFeed(int $userId, array $reviews): string
    {
        if (empty($reviews)) {
            // Prázdný feed
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');
        } else {
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

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><products></products>');

            foreach ($bySku as $sku => $urls) {
                $product = $xml->addChild('product');
                $product->addChild('code', htmlspecialchars($sku, ENT_XML1, 'UTF-8'));
                
                $images = $product->addChild('images');
                foreach ($urls as $url) {
                    $images->addChild('image', htmlspecialchars($url, ENT_XML1, 'UTF-8'));
                }
            }
        }

        // Uložíme do public/feeds/
        $filename = "user_{$userId}_reviews.xml";
        $filepath = $this->feedsDir . '/' . $filename;
        
        // Naformátovaný XML
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($filepath);

        // Vrátíme veřejnou URL
        return $this->appUrl . '/feeds/' . $filename;
    }

    /**
     * Smaže dočasný soubor
     */
    public function cleanup(string $path): void
    {
        if (file_exists($path) && str_contains($path, '/tmp/')) {
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
