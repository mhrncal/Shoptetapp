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
            $code = $review['code'] ?? $review['sku'] ?? '';
            if (!$code) continue;
            if (!isset($grouped[$code])) {
                $grouped[$code] = [
                    'code'           => $code,
                    'product_images' => $review['product_images'] ?? [],
                    'review_photos'  => [],
                ];
            }
            foreach ($review['review_photos'] ?? [] as $photo) {
                $grouped[$code]['review_photos'][] = $photo;
            }
        }

        foreach ($grouped as $data) {
            $item   = $xml->addChild('SHOPITEM');
            $item->addChild('CODE', htmlspecialchars((string)$data['code']));
            $images = $item->addChild('IMAGES');
            foreach ($data['product_images'] as $img) {
                $image = $images->addChild('IMAGE', htmlspecialchars((string)$img));
                $image->addAttribute('description', '');
            }
            foreach ($data['review_photos'] as $photo) {
                $image = $images->addChild('IMAGE', htmlspecialchars((string)$photo));
                $image->addAttribute('description', 'Zákaznická fotka');
            }
            if (!empty($data['product_images'])) {
                $item->addChild('IMAGE_REF', htmlspecialchars((string)$data['product_images'][0]));
            }
        }

        return (string)$xml->asXML();
    }

    /**
     * Generuj CSV export (UTF-8 s BOM pro Excel)
     */
    public static function generateCSV(array $reviews): string
    {
        $rows   = [];
        $rows[] = ['code', 'pairCode', 'name', 'author_name', 'author_email',
                   'rating', 'comment', 'review_photos', 'product_images', 'created_at'];

        foreach ($reviews as $r) {
            $rows[] = [
                $r['code'] ?? $r['sku'] ?? '',
                $r['pair_code'] ?? '',
                $r['product_name'] ?? '',
                $r['author_name'] ?? '',
                $r['author_email'] ?? '',
                $r['rating'] ?? '',
                $r['comment'] ?? '',
                implode('|', $r['review_photos'] ?? []),
                implode('|', $r['product_images'] ?? []),
                $r['created_at'] ?? '',
            ];
        }

        // Ruční sestavení CSV — bez iconv, bez fputcsv problémů
        $lines = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $cell) {
                $cell    = str_replace('"'  , '""'  , (string)$cell);
                $cells[] = '"' . $cell . '"';
            }
            $lines[] = implode(';', $cells);
        }

        // UTF-8 BOM aby Excel správně dekódoval
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Ulož export do souboru
     */
    public static function saveToFile(string $content, string $filename): string
    {
        $dir = ROOT . '/public/feeds';
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Nelze vytvořit složku: {$dir}");
            }
        }

        $filepath = $dir . '/' . $filename;
        $bytes    = file_put_contents($filepath, $content);

        if ($bytes === false) {
            throw new \RuntimeException("Nelze zapsat soubor: {$filepath}");
        }

        return $filepath;
    }
}
