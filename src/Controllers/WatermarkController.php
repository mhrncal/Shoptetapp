<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Database, Session};
use ShopCode\Models\WatermarkSettings;

class WatermarkController extends BaseController
{
    public function settings(): void
    {
        $userId = $this->user['id'] ?? null;
        if (!$userId) {
            $this->redirect('/login');
        }
        
        $settings = WatermarkSettings::getForUser($userId);
        if (!$settings) {
            WatermarkSettings::createDefault($userId);
            $settings = WatermarkSettings::getForUser($userId);
        }
        
        $this->view('watermark/settings', [
            'settings' => $settings,
            'positions' => WatermarkSettings::POSITIONS,
            'fonts' => WatermarkSettings::FONTS,
            'sizes' => WatermarkSettings::SIZES,
        ]);
    }
    
    public function update(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'] ?? null;
        if (!$userId) {
            $this->redirect('/login');
        }
        
        $data = [
            'text' => $this->request->post('text', 'Zákaznická fotka'),
            'font' => $this->request->post('font', 'Arial'),
            'position' => $this->request->post('position', 'BR'),
            'color' => $this->request->post('color', '#FFFFFF'),
            'size' => $this->request->post('size', 'medium'),
            'opacity' => (int)$this->request->post('opacity', 80),
            'padding' => (int)$this->request->post('padding', 20),
            'shadow_enabled' => ($this->request->post('shadow_enabled') !== null),
            'enabled' => ($this->request->post('enabled') !== null),
        ];
        
        if (WatermarkSettings::update($userId, $data)) {
            Session::flash('success', 'Nastavení watermarku uloženo');
        } else {
            Session::flash('error', 'Chyba při ukládání nastavení');
        }
        
        $this->redirect('/watermark/settings');
    }

    /**
     * Přegeneruj watermark na všech fotkách
     */
    public function regenerate(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'] ?? null;
        if (!$userId) {
            $this->redirect('/login');
        }
        
        $db = Database::getInstance();
        
        // Načti všechny fotky tohoto uživatele
        $stmt = $db->prepare('
            SELECT rp.*, r.user_id 
            FROM review_photos rp
            JOIN reviews r ON r.id = rp.review_id
            WHERE r.user_id = ?
        ');
        $stmt->execute([$userId]);
        $photos = $stmt->fetchAll();
        
        if (empty($photos)) {
            Session::flash('error', 'Žádné fotky k přegenerování');
            $this->redirect('/watermark/settings');
        }
        
        $uploadDir = ROOT . '/public/uploads';
        $handler = new \ShopCode\Services\ImageHandler($uploadDir);
        $success = 0;
        $failed = 0;
        
        foreach ($photos as $photo) {
            try {
                // Cesta k originálnímu souboru
                $originalPath = ROOT . '/public/uploads/' . str_replace(
                    ['.jpg', '.png', '.webp'],
                    ['_original.jpg', '_original.png', '_original.webp'],
                    $photo['path']
                );
                
                // Pokud originál neexistuje, přeskoč
                if (!file_exists($originalPath)) {
                    $failed++;
                    continue;
                }
                
                // Načti originální obrázek
                $img = match($photo['mime_type']) {
                    'image/jpeg' => @imagecreatefromjpeg($originalPath),
                    'image/png'  => @imagecreatefrompng($originalPath),
                    'image/webp' => @imagecreatefromwebp($originalPath),
                    default => false
                };
                
                if (!$img) {
                    $failed++;
                    continue;
                }
                
                // Aplikuj nový watermark
                $watermarked = $handler->applyWatermark($img, $userId);
                
                // Ulož display verzi (přepiš starou)
                $displayPath = ROOT . '/public/uploads/' . $photo['path'];
                match($photo['mime_type']) {
                    'image/jpeg' => imagejpeg($watermarked, $displayPath, 90),
                    'image/png'  => imagepng($watermarked, $displayPath, 6),
                    'image/webp' => imagewebp($watermarked, $displayPath, 90),
                };
                
                // Vytvoř nový thumbnail
                $thumb = $handler->createThumbnail($watermarked);
                $thumbPath = ROOT . '/public/uploads/' . $photo['thumb'];
                match($photo['mime_type']) {
                    'image/jpeg' => imagejpeg($thumb, $thumbPath, 90),
                    'image/png'  => imagepng($thumb, $thumbPath, 6),
                    'image/webp' => imagewebp($thumb, $thumbPath, 90),
                };
                
                imagedestroy($img);
                imagedestroy($watermarked);
                imagedestroy($thumb);
                
                $success++;
                
            } catch (\Exception $e) {
                $failed++;
            }
        }
        
        $total = count($photos);
        Session::flash('success', "Přegenerováno {$success} z {$total} fotek" . ($failed > 0 ? " ({$failed} selhalo)" : ""));
        $this->redirect('/watermark/settings');
    }
}
