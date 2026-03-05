<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ProductVideo
{
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
        $stmt = $db->prepare('INSERT INTO product_videos (user_id, product_id, title, url, sort_order) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId, $productId, $data['title'] ?: null, $data['url'], (int)($data['sort_order'] ?? 0)]);
        return (int)$db->lastInsertId();
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM product_videos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Extrahuje embed URL z YouTube/Vimeo odkazu
     */
    public static function embedUrl(string $url): ?string
    {
        // YouTube: youtu.be/ID nebo youtube.com/watch?v=ID nebo youtube.com/embed/ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Vimeo: vimeo.com/ID nebo player.vimeo.com/video/ID
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
