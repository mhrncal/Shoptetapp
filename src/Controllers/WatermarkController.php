<?php

namespace ShopCode\Controllers;

use ShopCode\Models\WatermarkSettings;

class WatermarkController extends BaseController
{
    public function settings(): void
    {
        $userId = $this->user['id'] ?? $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . APP_URL . '/login');
            exit;
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
        $userId = $this->user['id'] ?? $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
        
        $data = [
            'text' => $_POST['text'] ?? 'Zákaznická fotka',
            'font' => $_POST['font'] ?? 'Arial',
            'position' => $_POST['position'] ?? 'BR',
            'color' => $_POST['color'] ?? '#FFFFFF',
            'size' => $_POST['size'] ?? 'medium',
            'opacity' => (int)($_POST['opacity'] ?? 80),
            'padding' => (int)($_POST['padding'] ?? 20),
            'shadow_enabled' => isset($_POST['shadow_enabled']),
            'enabled' => isset($_POST['enabled']),
        ];
        
        if (WatermarkSettings::update($userId, $data)) {
            $_SESSION['flash'] = ['success' => 'Nastavení watermarku uloženo'];
        } else {
            $_SESSION['flash'] = ['error' => 'Chyba při ukládání nastavení'];
        }
        
        header('Location: ' . APP_URL . '/watermark/settings');
        exit;
    }
}

    /**
     * Přegeneruj watermark na všech fotkách
     */
    public function regenerate(): void
    {
        $userId = $this->user['id'] ?? $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . APP_URL . '/login');
            exit;
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
            $_SESSION['flash'] = ['error' => 'Žádné fotky k přegenerování'];
            header('Location: ' . APP_URL . '/watermark/settings');
            exit;
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
        $_SESSION['flash'] = ['success' => "Přegenerováno {$success} z {$total} fotek" . ($failed > 0 ? " ({$failed} selhalo)" : "")];
        header('Location: ' . APP_URL . '/watermark/settings');
        exit;
    }
