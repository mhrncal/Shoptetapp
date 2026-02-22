<?php

namespace ShopCode\Services;

/**
 * Zpracování nahraných fotek recenzí.
 * Resize na max 2000×2000, thumbnail 300×300.
 * Ukládá do /public/uploads/{user_id}/{review_uuid}/
 */
class ImageHandler
{
    private const MAX_SIDE   = 2000;
    private const THUMB_SIZE = 300;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_BYTES    = 10 * 1024 * 1024; // 10 MB

    private string $baseDir;
    private string $baseUrl;

    public function __construct()
    {
        $this->baseDir = ROOT . '/public/uploads';
        $this->baseUrl = (defined('APP_URL') ? APP_URL : '') . '/uploads';
    }

    /**
     * Zpracuje jedno nahraté foto.
     * @param  array  $file   $_FILES['photos'][n] struktura
     * @param  int    $userId
     * @param  string $uuid   UUID recenze
     * @return array  ['path' => 'relativní cesta', 'thumb' => '...', 'url' => '...', 'thumb_url' => '...']
     * @throws \RuntimeException
     */
    public function process(array $file, int $userId, string $uuid): array
    {
        // ── Validace ──────────────────────────────────────────
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Chyba při nahrávání souboru: kód ' . $file['error']);
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Soubor je příliš velký (max 10 MB).');
        }

        // Validace skutečného MIME (ne jen přípona)
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \RuntimeException("Nepodporovaný formát souboru: {$mime}");
        }

        // ── Příprava adresáře ─────────────────────────────────
        $dir = "{$this->baseDir}/{$userId}/{$uuid}";
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Nelze vytvořit adresář pro fotky.');
        }

        // Bezpečný náhodný název souboru
        $ext      = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        };
        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $thumbName = 'thumb_' . $filename;

        $destPath  = "{$dir}/{$filename}";
        $thumbPath = "{$dir}/{$thumbName}";

        // ── Resize a uložení ──────────────────────────────────
        $image = $this->loadImage($file['tmp_name'], $mime);
        $image = $this->resize($image, self::MAX_SIDE, self::MAX_SIDE);
        $this->save($image, $destPath, $mime);
        imagedestroy($image);

        // Thumbnail
        $thumb = $this->loadImage($destPath, $mime);
        $thumb = $this->cropSquare($thumb, self::THUMB_SIZE);
        $this->save($thumb, $thumbPath, $mime);
        imagedestroy($thumb);

        $relPath   = "{$userId}/{$uuid}/{$filename}";
        $relThumb  = "{$userId}/{$uuid}/{$thumbName}";

        return [
            'path'      => $relPath,
            'thumb'     => $relThumb,
            'url'       => "{$this->baseUrl}/{$relPath}",
            'thumb_url' => "{$this->baseUrl}/{$relThumb}",
        ];
    }

    /**
     * Smaže celou složku recenze
     */
    public function deleteFolder(int $userId, string $uuid): void
    {
        $dir = "{$this->baseDir}/{$userId}/{$uuid}";
        if (!is_dir($dir)) return;
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }

    // ── Private helpers ───────────────────────────────────────

    private function loadImage(string $path, string $mime): \GdImage
    {
        return match($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => throw new \RuntimeException("Nepodporovaný MIME: {$mime}"),
        };
    }

    private function resize(\GdImage $img, int $maxW, int $maxH): \GdImage
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);

        if ($srcW <= $maxW && $srcH <= $maxH) return $img;

        $ratio  = min($maxW / $srcW, $maxH / $srcH);
        $newW   = (int)round($srcW * $ratio);
        $newH   = (int)round($srcH * $ratio);
        $canvas = imagecreatetruecolor($newW, $newH);

        // Průhlednost pro PNG
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $newW, $newH, $transparent);

        imagecopyresampled($canvas, $img, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($img);
        return $canvas;
    }

    private function cropSquare(\GdImage $img, int $size): \GdImage
    {
        $srcW = imagesx($img);
        $srcH = imagesy($img);
        $min  = min($srcW, $srcH);
        $x    = (int)(($srcW - $min) / 2);
        $y    = (int)(($srcH - $min) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        imagecopyresampled($canvas, $img, 0, 0, $x, $y, $size, $size, $min, $min);
        imagedestroy($img);
        return $canvas;
    }

    private function save(\GdImage $img, string $path, string $mime): void
    {
        match($mime) {
            'image/jpeg' => imagejpeg($img, $path, 88),
            'image/png'  => imagepng($img, $path, 6),
            'image/webp' => imagewebp($img, $path, 85),
        };
    }
}
