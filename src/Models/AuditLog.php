<?php

namespace ShopCode\Models;

use ShopCode\Core\{Database, Session};

class AuditLog
{
    public static function log(
        string  $action,
        string  $resourceType,
        ?string $resourceId  = null,
        ?array  $oldValues   = null,
        ?array  $newValues   = null,
        ?int    $userId      = null
    ): void {
        $db      = Database::getInstance();
        $user    = Session::get('user');
        $actorId = $userId ?? ($user['id'] ?? null);

        $stmt = $db->prepare('
            INSERT INTO audit_logs
                (user_id, action, resource_type, resource_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $actorId,
            $action,
            $resourceType,
            $resourceId,
            $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public static function all(int $page = 1, int $perPage = 50, array $filters = []): array
    {
        $db     = Database::getInstance();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[]  = 'al.action = ?';
            $params[] = $filters['action'];
        }

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare('
            SELECT al.*, u.email, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        $db     = Database::getInstance();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM audit_logs WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
