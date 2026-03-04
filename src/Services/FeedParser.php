<?php

namespace ShopCode\Services;

use ShopCode\Core\Database;
use ShopCode\Models\ProductFeed;

class FeedParser
{
    private string $cacheDir;
    
    public function __construct()
    {
        $this->cacheDir = ROOT . '/tmp/feeds';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Stáhni CSV feed a ulož na disk
     */
    public function downloadFeed(int $feedId, string $url): ?string
    {
        try {
            $filename = "feed_{$feedId}_" . date('Y-m-d_H-i-s') . '.csv';
            $filepath = $this->cacheDir . '/' . $filename;
            
            // Použij cURL (robustnější než fopen)
            $ch = curl_init($url);
            
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 300, // 5 minut
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ShopCode/1.0 (Feed Downloader)',
                CURLOPT_ENCODING => '', // Accept all encodings
            ]);
            
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($content === false) {
                throw new \RuntimeException("cURL error: $error");
            }
            
            if ($httpCode !== 200) {
                throw new \RuntimeException("HTTP error $httpCode");
            }
            
            if (empty($content)) {
                throw new \RuntimeException("Downloaded file is empty");
            }
            
            // Ulož na disk
            $bytes = file_put_contents($filepath, $content);
            if ($bytes === false) {
                throw new \RuntimeException("Cannot write to file: $filepath");
            }
            
            // Smaž staré soubory (starší než 7 dní)
            $this->cleanOldFiles(7);
            
            return $filepath;
            
        } catch (\Exception $e) {
            error_log("FeedParser download error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parsuj CSV a ulož do DB
     */
    public function parseAndStore(int $feedId, string $filepath, array $config): array
    {
        $userId = $config['user_id'];
        $type = $config['type'];
        $delimiter = $config['delimiter'] ?? ';';
        $encoding = $config['encoding'] ?? 'windows-1250';
        
        $db = Database::getInstance();
        $stats = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
        
        // Otevři soubor
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Nelze otevřít soubor: $filepath");
        }
        
        // Čti hlavičku
        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if (!$header) {
            fclose($handle);
            throw new \RuntimeException("CSV nemá hlavičku");
        }
        
        // Konvertuj encoding hlavičky
        if ($encoding !== 'UTF-8') {
            $header = array_map(function($h) use ($encoding) {
                return iconv($encoding, 'UTF-8//TRANSLIT', $h);
            }, $header);
        }
        
        // Najdi indexy sloupců
        $codeIdx = array_search('code', $header);
        $pairCodeIdx = array_search('pairCode', $header);
        $nameIdx = array_search('name', $header);
        
        if ($codeIdx === false || $nameIdx === false) {
            fclose($handle);
            throw new \RuntimeException("CSV nemá povinné sloupce: code, name");
        }
        
        // Najdi image sloupce (defaultImage, image, image2, image3, ...)
        $imageColumns = [];
        if ($type === 'csv_with_images') {
            foreach ($header as $idx => $col) {
                if ($col === 'defaultImage' || preg_match('/^image\d*$/', $col)) {
                    $imageColumns[] = $idx;
                }
            }
        }
        
        // Batch insert pro rychlost
        $batchSize = 500;
        $batch = [];
        
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $stats['total']++;
            
            // Konvertuj encoding
            if ($encoding !== 'UTF-8') {
                $row = array_map(function($r) use ($encoding) {
                    return iconv($encoding, 'UTF-8//TRANSLIT', $r);
                }, $row);
            }
            
            $code = $row[$codeIdx] ?? null;
            $pairCode = isset($pairCodeIdx) ? ($row[$pairCodeIdx] ?? null) : null;
            $name = $row[$nameIdx] ?? null;
            
            if (empty($code) || empty($name)) {
                $stats['errors']++;
                continue;
            }
            
            // Sbírej obrázky (jen URL, ne stahuj!)
            $images = [];
            foreach ($imageColumns as $imgIdx) {
                $imgUrl = $row[$imgIdx] ?? null;
                if (!empty($imgUrl) && filter_var($imgUrl, FILTER_VALIDATE_URL)) {
                    $images[] = $imgUrl;
                }
            }
            
            $batch[] = [
                'code' => $code,
                'pair_code' => $pairCode,
                'name' => $name,
                'images' => $images
            ];
            
            // Flush batch
            if (count($batch) >= $batchSize) {
                $this->insertBatch($userId, $batch, $stats);
                $batch = [];
            }
        }
        
        // Flush zbývající
        if (!empty($batch)) {
            $this->insertBatch($userId, $batch, $stats);
        }
        
        fclose($handle);
        
        return $stats;
    }
    
    /**
     * Insert/Update batch produktů
     */
    private function insertBatch(int $userId, array $batch, array &$stats): void
    {
        $db = Database::getInstance();
        
        foreach ($batch as $item) {
            try {
                // Zkontroluj jestli produkt existuje
                $stmt = $db->prepare('
                    SELECT id FROM products 
                    WHERE user_id = ? AND code = ?
                ');
                $stmt->execute([$userId, $item['code']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // UPDATE
                    $stmt = $db->prepare('
                        UPDATE products 
                        SET pair_code = ?,
                            name = ?,
                            images = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ');
                    
                    $stmt->execute([
                        $item['pair_code'],
                        $item['name'],
                        json_encode($item['images']),
                        $existing['id']
                    ]);
                    
                    $stats['updated']++;
                } else {
                    // INSERT
                    $stmt = $db->prepare('
                        INSERT INTO products 
                        (user_id, code, pair_code, name, shoptet_id, images, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ');
                    
                    $stmt->execute([
                        $userId,
                        $item['code'],
                        $item['pair_code'],
                        $item['name'],
                        $item['code'], // shoptet_id = code jako fallback
                        json_encode($item['images'])
                    ]);
                    
                    $stats['inserted']++;
                }
                
            } catch (\Exception $e) {
                error_log("FeedParser insert error: " . $e->getMessage());
                $stats['errors']++;
            }
        }
    }
    
    /**
     * Smaž staré cache soubory
     */
    private function cleanOldFiles(int $daysOld): void
    {
        $files = glob($this->cacheDir . '/feed_*.csv');
        $threshold = time() - ($daysOld * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }
}
