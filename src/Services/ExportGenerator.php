<?php

namespace ShopCode\Services;

class ExportGenerator
{
    /**
     * Generuj XML export s recenzemi + produktovými fotkami
     */
    public static function generateXML(array $reviews): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SHOP></SHOP>');
        
        // Seskup podle SKU
        $grouped = [];
        foreach ($reviews as $review) {
            $code = $review['code'] ?? $review['sku'];
            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'code' => $code,
                    'product_images' => $review['product_images'] ?? [],
                    'review_photos' => []
                ];
            }
            
            // Přidej review fotky
            foreach ($review['review_photos'] ?? [] as $photo) {
                $grouped[$code]['review_photos'][] = $photo;
            }
        }
        
        // Generuj XML pro každý produkt
        foreach ($grouped as $data) {
            $item = $xml->addChild('SHOPITEM');
            $item->addChild('CODE', htmlspecialchars($data['code']));
            
            $images = $item->addChild('IMAGES');
            
            // Produktové fotky první
            foreach ($data['product_images'] as $img) {
                $image = $images->addChild('IMAGE', htmlspecialchars($img));
                $image->addAttribute('description', '');
            }
            
            // Review fotky
            foreach ($data['review_photos'] as $photo) {
                $image = $images->addChild('IMAGE', htmlspecialchars($photo));
                $image->addAttribute('description', 'Zákaznická fotka');
            }
            
            // První obrázek jako hlavní
            if (!empty($data['product_images'])) {
                $item->addChild('IMAGE_REF', htmlspecialchars($data['product_images'][0]));
            }
        }
        
        return $xml->asXML();
    }
    
    /**
     * Generuj CSV export
     */
    public static function generateCSV(array $reviews): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Hlavička - PHP 8.4 requires escape parameter
        fputcsv($output, [
            'code',
            'pairCode', 
            'name',
            'author_name',
            'author_email',
            'rating',
            'comment',
            'review_photos',
            'product_images',
            'created_at'
        ], ';', '"', '');
        
        // Data
        foreach ($reviews as $review) {
            fputcsv($output, [
                $review['code'] ?? $review['sku'],
                $review['pair_code'] ?? '',
                $review['product_name'] ?? '',
                $review['author_name'],
                $review['author_email'],
                $review['rating'] ?? '',
                $review['comment'] ?? '',
                implode('|', $review['review_photos'] ?? []),
                implode('|', $review['product_images'] ?? []),
                $review['created_at']
            ], ';', '"', '');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        // Konvertuj na windows-1250 pro Excel pomocí iconv (s fallbackem)
        $converted = iconv('UTF-8', 'windows-1250//TRANSLIT//IGNORE', $csv);
        return $converted !== false ? $converted : $csv;
    }
    
    /**
     * Ulož export do souboru
     */
    public static function saveToFile(string $content, string $filename): string
    {
        $dir = ROOT . '/public/feeds';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $filepath = $dir . '/' . $filename;
        file_put_contents($filepath, $content);
        
        return $filepath;
    }
}
