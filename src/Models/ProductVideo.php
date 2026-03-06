<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ProductVideo
{
    const MAX_SIZE = 5 * 1024 * 1024; // 5 MB
    const ALLOWED_TYPES = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];

    public static function forProduct(int $productId, int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM product_videos WHERE product_id = ? AND user_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$productId, $userId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM product_videos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, int $productId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO product_videos (user_id, product_id, title, url, file_path, sort_order, autoplay) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $userId,
            $productId,
            $data['title'] ?: null,
            $data['url'] ?: null,
            $data['file_path'] ?: null,
            (int)($data['sort_order'] ?? 0),
            (int)!empty($data['autoplay']),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('UPDATE product_videos SET title=?, autoplay=? WHERE id=? AND user_id=?');
        $stmt->execute([$data['title'] ?: null, (int)!empty($data['autoplay']), $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        // Smaž soubor pokud existuje
        $video = self::findById($id, $userId);
        if ($video && $video['file_path']) {
            $path = UPLOAD_DIR . 'videos/' . $video['file_path'];
            if (file_exists($path)) @unlink($path);
        }
        $stmt = $db->prepare('DELETE FROM product_videos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Nahraje soubor na server, vrátí file_path nebo chybu
     */
    public static function handleUpload(array $file, int $userId): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Chyba při nahrávání souboru.'];
        }
        if ($file['size'] > self::MAX_SIZE) {
            return ['error' => 'Video je příliš velké. Maximum je 5 MB.'];
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES)) {
            return ['error' => 'Nepodporovaný formát. Použijte MP4, WebM nebo MOV.'];
        }

        $dir = UPLOAD_DIR . 'videos/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp4';
        $filename = 'v_' . $userId . '_' . uniqid() . '.' . strtolower($ext);
        $dest     = $dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['error' => 'Nepodařilo se uložit soubor.'];
        }

        return ['file_path' => $filename];
    }

    /**
     * URL pro přehrání lokálního videa
     */
    public static function localUrl(string $filePath): string
    {
        return APP_URL . '/public/uploads/videos/' . $filePath;
    }

    /**
     * Extrahuje embed URL z YouTube/Vimeo odkazu
     */
    public static function embedUrl(string $url): ?string
    {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    /**
     * Thumbnail pro YouTube
     */
    public static function thumbnail(string $url): ?string
    {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg';
        }
        return null;
    }
}
