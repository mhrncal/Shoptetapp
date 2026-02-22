<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Branch
{
    public const DAYS = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];

    public static function allForUser(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM branches WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM branches WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO branches
                (user_id, name, description, street_address, city, postal_code,
                 image_url, branch_url, google_maps_url, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $data['name'],
            $data['description'] ?: null,
            $data['street_address'] ?: null,
            $data['city'] ?: null,
            $data['postal_code'] ?: null,
            $data['image_url'] ?: null,
            $data['branch_url'] ?: null,
            $data['google_maps_url'] ?: null,
            $data['latitude'] ?: null,
            $data['longitude'] ?: null,
        ]);
        $id = (int)$db->lastInsertId();

        // Inicializuj otevírací doby (7 dní)
        self::initHours($id);

        return $id;
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE branches
            SET name = ?, description = ?, street_address = ?, city = ?,
                postal_code = ?, image_url = ?, branch_url = ?,
                google_maps_url = ?, latitude = ?, longitude = ?
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([
            $data['name'],
            $data['description'] ?: null,
            $data['street_address'] ?: null,
            $data['city'] ?: null,
            $data['postal_code'] ?: null,
            $data['image_url'] ?: null,
            $data['branch_url'] ?: null,
            $data['google_maps_url'] ?: null,
            $data['latitude'] ?: null,
            $data['longitude'] ?: null,
            $id,
            $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM branches WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    // ---- Otevírací doby ----

    public static function getHours(int $branchId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM branch_hours WHERE branch_id = ? ORDER BY day_of_week ASC');
        $stmt->execute([$branchId]);
        $rows = $stmt->fetchAll();

        // Indexovat po dni
        $hours = [];
        foreach ($rows as $row) {
            $hours[$row['day_of_week']] = $row;
        }
        return $hours;
    }

    public static function saveHours(int $branchId, array $hours): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO branch_hours (branch_id, day_of_week, is_closed, open_from, open_to, note)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_closed = VALUES(is_closed),
                open_from = VALUES(open_from),
                open_to   = VALUES(open_to),
                note      = VALUES(note)
        ');

        for ($day = 0; $day <= 6; $day++) {
            $h         = $hours[$day] ?? [];
            $isClosed  = !empty($h['is_closed']) ? 1 : 0;
            $openFrom  = !$isClosed && !empty($h['open_from'])  ? $h['open_from']  : null;
            $openTo    = !$isClosed && !empty($h['open_to'])    ? $h['open_to']    : null;
            $note      = !empty($h['note']) ? trim($h['note']) : null;

            $stmt->execute([$branchId, $day, $isClosed, $openFrom, $openTo, $note]);
        }
    }

    private static function initHours(int $branchId): void
    {
        // Výchozí: Po–Pá 9:00–17:00, So–Ne zavřeno
        $defaults = [];
        for ($d = 0; $d <= 6; $d++) {
            $defaults[$d] = [
                'is_closed' => $d >= 5 ? 1 : 0,
                'open_from' => $d < 5  ? '09:00' : null,
                'open_to'   => $d < 5  ? '17:00' : null,
            ];
        }
        self::saveHours($branchId, $defaults);
    }

    public static function count(int $userId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM branches WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
