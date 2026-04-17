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
            'text'           => $this->request->post('text', 'Zákaznická fotka'),
            'font'           => $this->request->post('font', 'Arial'),
            'position'       => $this->request->post('position', 'BR'),
            'color'          => $this->request->post('color', '#FFFFFF'),
            'size'           => $this->request->post('size', 'medium'),
            'opacity'        => (int)$this->request->post('opacity', 80),
            'padding'        => (int)$this->request->post('padding', 20),
            'shadow_enabled' => ($this->request->post('shadow_enabled') !== null),
            'enabled'        => ($this->request->post('enabled') !== null),
            'watermark_type' => $this->request->post('watermark_type', 'text'),
        ];

        // Zpracování uploadu loga
        $currentSettings = WatermarkSettings::getForUser($userId);
        $data['logo_path'] = $currentSettings['logo_path'] ?? null;

        if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($_FILES['logo']['tmp_name']);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

            if (in_array($mime, $allowedMimes)) {
                $dir = ROOT . '/public/uploads/watermarks/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                // Smaž starý logo
                if (!empty($currentSettings['logo_path'])) {
                    @unlink(ROOT . '/public/' . ltrim($currentSettings['logo_path'], '/'));
                }

                $filename = 'logo_' . $userId . '_' . time();
                $saved    = false;

                if ($mime === 'image/svg+xml') {
                    // SVG → PNG přes ImageMagick convert
                    $pngFile = $dir . $filename . '.png';
                    $cmd     = sprintf(
                        '/usr/bin/convert -background none %s %s 2>&1',
                        escapeshellarg($_FILES['logo']['tmp_name']),
                        escapeshellarg($pngFile)
                    );
                    $output = shell_exec($cmd);
                    if (file_exists($pngFile) && filesize($pngFile) > 0) {
                        $data['logo_path'] = 'uploads/watermarks/' . $filename . '.png';
                        $saved = true;
                    } else {
                        Session::flash('error', 'SVG se nepodařilo převést na PNG: ' . ($output ?: 'neznámá chyba'));
                    }
                } else {
                    // PNG/JPG/WEBP/GIF – ulož přímo jako PNG pro konzistenci
                    $ext = match($mime) {
                        'image/jpeg' => 'jpg', 'image/png' => 'png',
                        'image/webp' => 'webp', 'image/gif' => 'gif', default => 'png'
                    };
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dir . $filename . '.' . $ext)) {
                        $data['logo_path'] = 'uploads/watermarks/' . $filename . '.' . $ext;
                        $saved = true;
                    }
                }
            }
        }
        
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
        $failed  = 0;
        set_time_limit(300);
        
        foreach ($photos as $photo) {
            try {
                $ext         = pathinfo($photo['path'], PATHINFO_EXTENSION);
                $displayPath = ROOT . '/public/uploads/' . ltrim($photo['path'], '/');
                $origPath    = substr($displayPath, 0, -strlen('.' . $ext)) . '_original.' . $ext;

                // Použij originál pokud existuje, jinak display
                $srcPath = file_exists($origPath) ? $origPath : $displayPath;
                if (!file_exists($srcPath)) {
                    error_log("[regen] SKIP (no file): {$srcPath}");
                    $failed++;
                    continue;
                }

                $mime = $photo['mime_type'] ?: mime_content_type($srcPath);

                $img = match(true) {
                    str_contains($mime, 'jpeg') => @imagecreatefromjpeg($srcPath),
                    str_contains($mime, 'png')  => @imagecreatefrompng($srcPath),
                    str_contains($mime, 'webp') => @imagecreatefromwebp($srcPath),
                    default => false
                };

                if (!$img) {
                    error_log("[regen] FAIL load: {$srcPath} mime={$mime}");
                    $failed++;
                    continue;
                }

                $watermarked = $handler->applyWatermark($img, $userId);
                $thumb       = $handler->createThumbnail($watermarked);
                $thumbPath   = ROOT . '/public/uploads/' . ltrim($photo['thumb'] ?? '', '/');

                $saveOk = match(true) {
                    str_contains($mime, 'jpeg') => imagejpeg($watermarked, $displayPath, 90) && imagejpeg($thumb, $thumbPath, 90),
                    str_contains($mime, 'png')  => imagepng($watermarked, $displayPath, 6)   && imagepng($thumb, $thumbPath, 6),
                    str_contains($mime, 'webp') => imagewebp($watermarked, $displayPath, 90) && imagewebp($thumb, $thumbPath, 90),
                    default => false
                };

                imagedestroy($img);
                imagedestroy($watermarked);
                imagedestroy($thumb);

                if ($saveOk) {
                    $success++;
                    error_log("[regen] OK: {$displayPath}");
                } else {
                    $failed++;
                    error_log("[regen] FAIL save: {$displayPath}");
                }
                
            } catch (\Throwable $e) {
                $failed++;
                error_log("[regen] EXCEPTION photo_id={$photo['id']}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            }
        }
        
        $total = count($photos);
        Session::flash('success', "Přegenerováno {$success} z {$total} fotek" . ($failed > 0 ? " ({$failed} selhalo)" : ""));
        $this->redirect('/watermark/settings');
    }
}
