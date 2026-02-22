<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Review
{
    public const STATUSES = [
        'pending'  => ['label' => 'Čeká',     'color' => 'warning'],
        'approved' => ['label' => 'Schválena', 'color' => 'success'],
        'rejected' => ['label' => 'Zamítnuta', 'color' => 'danger'],
    ];

    public static function allForUser(int $userId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db     = Database::getInstance();
        $where  = ['r.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['status'])) {
            $where[]  = 'r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(r.author_name LIKE ? OR r.sku LIKE ? OR r.author_email LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }
        if (!empty($filters['product_id'])) {
            $where[]  = 'r.product_id = ?';
            $params[] = $filters['product_id'];
        }
        if (isset($filters['imported'])) {
            $where[]  = 'r.imported = ?';
            $params[] = (int)$filters['imported'];
        }

        $offset = ($page - 1) * $perPage;
        $stmt   = $db->prepare('
            SELECT r.*, p.name AS product_name, p.shoptet_id AS product_shoptet_id
            FROM reviews r
            LEFT JOIN products p ON p.id = r.product_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ');
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['photos'] = $r['photos'] ? json_decode($r['photos'], true) : [];
        }
        return $rows;
    }

    public static function count(int $userId, array $filters = []): int
    {
        $db     = Database::getInstance();
        $where  = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['status'])) { $where[] = 'status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['search'])) {
            $where[]  = '(author_name LIKE ? OR sku LIKE ? OR author_email LIKE ?)';
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s; $params[] = $s;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT r.*, p.name AS product_name
            FROM reviews r
            LEFT JOIN products p ON p.id = r.product_id
            WHERE r.id = ? AND r.user_id = ? LIMIT 1
        ');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['photos'] = $row['photos'] ? json_decode($row['photos'], true) : [];
        return $row;
    }

    /**
     * Vytvoří recenzi z public API endpointu (bez user_id v requestu — matchujeme podle shoptet_id/sku)
     */
    public static function create(int $userId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO reviews
                (user_id, product_id, shoptet_id, sku, author_name, author_email,
                 rating, comment, photos, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())
        ');
        $stmt->execute([
            $userId,
            $data['product_id'] ?? null,
            $data['shoptet_id'] ?? null,
            $data['sku']        ?? null,
            $data['author_name'],
            $data['author_email'],
            $data['rating']     ?? null,
            $data['comment']    ?? null,
            json_encode($data['photos'] ?? []),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function setStatus(int $id, int $userId, string $status, ?string $note = null): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE reviews
            SET status = ?, admin_note = ?, reviewed_at = NOW()
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([$status, $note, $id, $userId]);
    }

    public static function bulkSetStatus(array $ids, int $userId, string $status): int
    {
        if (empty($ids)) return 0;
        $db          = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt        = $db->prepare("
            UPDATE reviews SET status = ?, reviewed_at = NOW()
            WHERE id IN ({$placeholders}) AND user_id = ?
        ");
        $params = array_merge([$status], array_map('intval', $ids), [$userId]);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function markImported(array $ids, int $userId): int
    {
        if (empty($ids)) return 0;
        $db           = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt         = $db->prepare("
            UPDATE reviews SET imported = 1, imported_at = NOW()
            WHERE id IN ({$placeholders}) AND user_id = ?
        ");
        $params = array_merge(array_map('intval', $ids), [$userId]);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function delete(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        // Načteme nejdřív pro smazání fotek
        $row  = self::findById($id, $userId);
        if (!$row) return null;
        $db->prepare('DELETE FROM reviews WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
        return $row;
    }

    /**
     * Vrátí schválené, neimportované recenze pro CSV export
     */
    public static function getPendingImport(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM reviews
            WHERE user_id = ? AND status = 'approved' AND imported = 0
            ORDER BY created_at ASC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['photos'] = $r['photos'] ? json_decode($r['photos'], true) : [];
        }
        return $rows;
    }

    public static function countByStatus(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT status, COUNT(*) AS cnt FROM reviews WHERE user_id = ? GROUP BY status
        ');
        $stmt->execute([$userId]);
        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    /**
     * Rate limiting — max N requestů z jedné IP za okno (sekund)
     */
    public static function checkRateLimit(string $ip, string $endpoint, int $max, int $window): bool
    {
        $db      = Database::getInstance();
        $ipHash  = hash('sha256', $ip);
        $now     = date('Y-m-d H:i:s');
        $cutoff  = date('Y-m-d H:i:s', time() - $window);

        // Vyčisti staré záznamy
        $db->prepare("DELETE FROM rate_limits WHERE window_start < ?")->execute([$cutoff]);

        $stmt = $db->prepare("SELECT id, hits FROM rate_limits WHERE ip_hash = ? AND endpoint = ? LIMIT 1");
        $stmt->execute([$ipHash, $endpoint]);
        $row = $stmt->fetch();

        if (!$row) {
            $db->prepare("INSERT INTO rate_limits (ip_hash, endpoint, hits, window_start) VALUES (?,?,1,?)")
               ->execute([$ipHash, $endpoint, $now]);
            return true;
        }

        if ($row['hits'] >= $max) return false;

        $db->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE id = ?")->execute([$row['id']]);
        return true;
    }
}
