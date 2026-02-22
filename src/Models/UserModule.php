<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class UserModule
{
    public static function isActive(int $userId, string $moduleName): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT 1 FROM user_modules um
            INNER JOIN modules m ON m.id = um.module_id
            WHERE um.user_id = ? AND m.name = ? AND um.status = "active"
            LIMIT 1
        ');
        $stmt->execute([$userId, $moduleName]);
        return (bool)$stmt->fetchColumn();
    }

    public static function getForUser(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT m.*, um.status, um.activated_at
            FROM modules m
            LEFT JOIN user_modules um ON um.module_id = m.id AND um.user_id = ?
            ORDER BY m.label ASC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function getActiveNamesForUser(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT m.name FROM user_modules um
            INNER JOIN modules m ON m.id = um.module_id
            WHERE um.user_id = ? AND um.status = "active"
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function setStatus(int $userId, int $moduleId, string $status): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO user_modules (user_id, module_id, status, activated_at)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status       = VALUES(status),
                activated_at = VALUES(activated_at),
                updated_at   = NOW()
        ');
        $activatedAt = $status === 'active' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$userId, $moduleId, $status, $activatedAt]);
    }

    public static function assignAllToUser(int $userId): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO user_modules (user_id, module_id, status)
            SELECT ?, id, "inactive" FROM modules
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ');
        $stmt->execute([$userId]);
    }
}
