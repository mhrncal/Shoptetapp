<?php

namespace ShopCode\Services;

use ShopCode\Core\Database;
use ShopCode\Models\WatermarkSettings;

/**
 * Vylepšený ImageHandler s:
 * - Resize logikou (1024-1600px)
 * - Automatickým watermarkem
 * - EXIF cleanup
 */
class ImageHandler
{
    private string $uploadDir;
    private int $maxFileSize = 10485760; // 10MB
    private array $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    
    // Resize konstanty
    private const MIN_SIZE = 1024;
    private const MAX_SIZE = 1600;
    private const THUMB_SIZE = 300;

    public function __construct(string $uploadDir)
    {
        $this->uploadDir = rtrim($uploadDir, '/');
    }

    /**
     * Zpracuje upload fotky s resize a watermarkem
     */
    public function process(array $file, int $userId): array
    {
        // Validace
        $this->validate($file);
        
        // Načti obrázek
        $img = $this->loadImage($file['tmp_name'], $file['type']);
        
        // EXIF cleanup (bezpečnost)
        $img = $this->removeExif($img);
        
        // Resize podle pravidel
        $img = $this->smartResize($img);
        
        // Generuj názvy
        $uniqueId = bin2hex(random_bytes(8));
        $ext = $this->getExtension($file['type']);
        
        $paths = [
            'original' => "{$uniqueId}_original.{$ext}",
            'display'  => "{$uniqueId}.{$ext}",
            'thumb'    => "{$uniqueId}_thumb.{$ext}",
        ];
        
        // Složka pro usera
        $userDir = "{$this->uploadDir}/{$userId}/{$uniqueId}";
        if (!is_dir($userDir)) {
            mkdir($userDir, 0755, true);
        }
        
        // Ulož originál (bez watermarku)
        $this->save($img, "{$userDir}/{$paths['original']}", $file['type']);
        
        // Aplikuj watermark
        $watermarked = $this->applyWatermark($img, $userId);
        
        // Ulož display verzi (s watermarkem)
        $this->save($watermarked, "{$userDir}/{$paths['display']}", $file['type']);
        
        // Vytvoř thumbnail (z watermarked)
        $thumb = $this->createThumbnail($watermarked);
        $this->save($thumb, "{$userDir}/{$paths['thumb']}", $file['type']);
        
        // Cleanup
        imagedestroy($img);
        imagedestroy($watermarked);
        imagedestroy($thumb);
        
        return [
            'path'  => "{$userId}/{$uniqueId}/{$paths['display']}",
            'thumb' => "{$userId}/{$uniqueId}/{$paths['thumb']}",
            'mime'  => $file['type'],
        ];
    }

    /**
     * Smart resize podle pravidel:
     * < 1024px → vycentrovat do 1024x1024
     * 1024-1600px → ponechat
     * > 1600px → zmenšit (delší strana = 1600px)
     */
    private function smartResize(\GdImage $img): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $max = max($w, $h);
        
        // Případ 1: Menší než 1024px → vycentrovat do 1024x1024
        if ($max < self::MIN_SIZE) {
            return $this->centerInCanvas($img, self::MIN_SIZE, self::MIN_SIZE);
        }
        
        // Případ 2: Mezi 1024-1600px → ponechat
        if ($max <= self::MAX_SIZE) {
            return $img;
        }
        
        // Případ 3: Větší než 1600px → zmenšit
        $ratio = self::MAX_SIZE / $max;
        $newW = (int)round($w * $ratio);
        $newH = (int)round($h * $ratio);
        
        return $this->resize($img, $newW, $newH);
    }

    /**
     * Vycentruje obrázek do většího canvasu s bílým pozadím
     */
    private function centerInCanvas(\GdImage $img, int $canvasW, int $canvasH): \GdImage
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        
        $canvas = imagecreatetruecolor($canvasW, $canvasH);
        
        // Bílé pozadí
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $canvasW, $canvasH, $white);
        imagealphablending($canvas, true);
        
        // Vycentrovat
        $x = (int)(($canvasW - $srcW) / 2);
        $y = (int)(($canvasH - $srcH) / 2);
        
        imagecopy($canvas, $img, $x, $y, 0, 0, $srcW, $srcH);
        imagedestroy($img);
        
        return $canvas;
    }

    /**
     * Resize obrázku
     */
    private function resize(\GdImage $img, int $newW, int $newH): \GdImage
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        
        $canvas = imagecreatetruecolor($newW, $newH);
        
        // Zachování průhlednosti
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $newW, $newH, $white);
        imagealphablending($canvas, true);
        
        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($img);
        
        return $canvas;
    }

    /**
     * Vytvoř čtvercový thumbnail
     */
    public function createThumbnail(\GdImage $img): \GdImage
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $min = min($srcW, $srcH);
        $x = (int)(($srcW - $min) / 2);
        $y = (int)(($srcH - $min) / 2);

        $canvas = imagecreatetruecolor(self::THUMB_SIZE, self::THUMB_SIZE);
        
        // Bílé pozadí
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, self::THUMB_SIZE, self::THUMB_SIZE, $white);
        imagealphablending($canvas, true);
        
        imagecopyresampled($canvas, $img, 0, 0, $x, $y, self::THUMB_SIZE, self::THUMB_SIZE, $min, $min);
        
        return $canvas;
    }

    /**
     * Aplikuj watermark podle user nastavení
     */
    public function applyWatermark(\GdImage $img, int $userId): \GdImage
    {
        $settings = WatermarkSettings::getForUser($userId);
        
        // Pokud není enabled, vrať originál
        if (!$settings || !$settings['enabled']) {
            return $this->cloneImage($img);
        }
        
        $canvas = $this->cloneImage($img);
        $w = imagesx($canvas);
        $h = imagesy($canvas);
        
        // Velikost fontu
        $sizeMap = ['small' => 16, 'medium' => 24, 'large' => 36];
        $fontSize = $sizeMap[$settings['size']] ?? 24;
        
        // Barva
        list($r, $g, $b) = $this->hexToRgb($settings['color']);
        $alpha = (int)((100 - $settings['opacity']) * 1.27);
        $color = imagecolorallocatealpha($canvas, $r, $g, $b, $alpha);
        
        // Stín (pokud enabled)
        if ($settings['shadow_enabled']) {
            $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, $alpha);
        }
        
        // Vypočítej pozici
        $text = $settings['text'];
        $padding = $settings['padding'];
        // GD font 5: každý znak je ~9px široký, 15px vysoký
        $textW = strlen($text) * 9;
        $textH = 15;
        
        $coords = $this->calculatePosition($settings['position'], $w, $h, $textW, $textH, $padding);
        
        // Vykreslí stín
        if ($settings['shadow_enabled']) {
            imagestring($canvas, 5, $coords['x'] + 2, $coords['y'] - 10 + 2, $text, $shadow);
        }
        
        // Vykresli text (GD font 5 = largest built-in)
        imagestring($canvas, 5, $coords['x'], $coords['y'] - 10, $text, $color);
        
        return $canvas;
    }

    /**
     * Vypočítá XY souřadnice pro danou pozici
     */
    private function calculatePosition(string $pos, int $w, int $h, int $textW, int $textH, int $pad): array
    {
        $positions = [
            'TL' => ['x' => $pad, 'y' => $pad + $textH],
            'TC' => ['x' => ($w - $textW) / 2, 'y' => $pad + $textH],
            'TR' => ['x' => $w - $textW - $pad, 'y' => $pad + $textH],
            'ML' => ['x' => $pad, 'y' => ($h + $textH) / 2],
            'MC' => ['x' => ($w - $textW) / 2, 'y' => ($h + $textH) / 2],
            'MR' => ['x' => $w - $textW - $pad, 'y' => ($h + $textH) / 2],
            'BL' => ['x' => $pad, 'y' => $h - $pad],
            'BC' => ['x' => ($w - $textW) / 2, 'y' => $h - $pad],
            'BR' => ['x' => $w - $textW - $pad, 'y' => $h - $pad],
        ];
        
        return $positions[$pos] ?? $positions['BR'];
    }

    /**
     * Klonuje GD obrázek
     */
    private function cloneImage(\GdImage $img): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $clone = imagecreatetruecolor($w, $h);
        imagealphablending($clone, false);
        imagesavealpha($clone, true);
        imagecopy($clone, $img, 0, 0, 0, 0, $w, $h);
        return $clone;
    }

    /**
     * Odstraní EXIF data (GPS, atd.)
     */
    private function removeExif(\GdImage $img): \GdImage
    {
        // GD automaticky odstraní EXIF při rekreaci
        return $this->cloneImage($img);
    }

    /**
     * Hex to RGB
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Vrátí cestu k TTF fontu
     */
    private function getFont(string $fontName): string
    {
        // Fallback na system font (GD built-in)
        // Pro produkci můžeš přidat TTF fonty do /lib/fonts/
        $fontPath = ROOT . "/lib/fonts/{$fontName}.ttf";
        if (file_exists($fontPath)) {
            return $fontPath;
        }
        
        // Fallback - použij GD vestavěný font (číslo 5)
        // Pro TTF musíme mít cestu, takže použijeme Liberation Sans
        return ROOT . '/lib/fonts/LiberationSans-Regular.ttf';
    }

    private function validate(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Chyba při uploadu souboru');
        }
        if ($file['size'] > $this->maxFileSize) {
            throw new \Exception('Soubor je příliš velký (max 10MB)');
        }
        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new \Exception('Nepodporovaný formát obrázku');
        }
    }

    private function loadImage(string $path, string $mime): \GdImage
    {
        return match($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => throw new \Exception('Nepodporovaný formát'),
        };
    }

    private function save(\GdImage $img, string $path, string $mime): void
    {
        match($mime) {
            'image/jpeg' => imagejpeg($img, $path, 90),
            'image/png'  => imagepng($img, $path, 6),
            'image/webp' => imagewebp($img, $path, 90),
        };
    }

    private function getExtension(string $mime): string
    {
        return match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
