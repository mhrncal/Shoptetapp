<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class XmlImport
{
    public static function addToQueue(int $userId, string $feedUrl, int $priority = 5): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO xml_processing_queue
                (user_id, xml_feed_url, status, priority, max_retries)
            VALUES (?, ?, "pending", ?, 3)
        ');
        $stmt->execute([$userId, $feedUrl, $priority]);
        return (int)$db->lastInsertId();
    }

    public static function getQueueForUser(int $userId, int $limit = 10): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM xml_processing_queue
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getActiveQueueItem(int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM xml_processing_queue
            WHERE user_id = ? AND status IN ('pending','processing')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getHistoryForUser(int $userId, int $limit = 20): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM xml_imports
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getQueueItem(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM xml_processing_queue WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function cancelQueueItem(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE xml_processing_queue
            SET status = 'failed', error_message = 'Zrušeno uživatelem'
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function hasActiveImport(int $userId): bool
    {
        return self::getActiveQueueItem($userId) !== null;
    }
}
