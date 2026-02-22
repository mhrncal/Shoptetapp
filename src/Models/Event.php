<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Event
{
    public static function allForUser(int $userId, array $filters = []): array
    {
        $db    = Database::getInstance();
        $where = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['upcoming'])) {
            $where[]  = 'end_date >= NOW()';
        }
        if (!empty($filters['past'])) {
            $where[]  = 'end_date < NOW()';
        }
        if (isset($filters['is_active'])) {
            $where[]  = 'is_active = ?';
            $params[] = (int)$filters['is_active'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(title LIKE ? OR address LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $order = !empty($filters['past']) ? 'start_date DESC' : 'start_date ASC';

        $stmt = $db->prepare('
            SELECT * FROM events
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $order
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM events WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO events
                (user_id, title, description, start_date, end_date,
                 event_url, image_url, address, google_maps_url, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $data['title'],
            $data['description'] ?: null,
            $data['start_date'],
            $data['end_date'],
            $data['event_url'] ?: null,
            $data['image_url'] ?: null,
            $data['address'] ?: null,
            $data['google_maps_url'] ?: null,
            isset($data['is_active']) ? 1 : 0,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE events
            SET title = ?, description = ?, start_date = ?, end_date = ?,
                event_url = ?, image_url = ?, address = ?, google_maps_url = ?, is_active = ?
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            $data['start_date'],
            $data['end_date'],
            $data['event_url'] ?: null,
            $data['image_url'] ?: null,
            $data['address'] ?: null,
            $data['google_maps_url'] ?: null,
            isset($data['is_active']) ? 1 : 0,
            $id,
            $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM events WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function countUpcoming(int $userId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE user_id = ? AND is_active = 1 AND end_date >= NOW()");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
