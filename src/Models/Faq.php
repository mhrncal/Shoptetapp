<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;
use PDO;

class Faq
{
    public static function allForUser(int $userId, array $filters = []): array
    {
        $db    = Database::getInstance();
        $where = ['f.user_id = ?'];
        $params = [$userId];

        if (isset($filters['product_id'])) {
            $where[]  = 'f.product_id = ?';
            $params[] = $filters['product_id'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(f.question LIKE ? OR f.answer LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }
        if (isset($filters['general_only']) && $filters['general_only']) {
            $where[] = 'f.product_id IS NULL';
        }

        $stmt = $db->prepare('
            SELECT f.*, p.name AS product_name, p.shoptet_id AS product_shoptet_id
            FROM faqs f
            LEFT JOIN products p ON p.id = f.product_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY f.product_id IS NULL DESC, f.sort_order ASC, f.created_at DESC
        ');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM faqs WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO faqs (user_id, product_id, question, answer, is_public, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $data['product_id'] ?: null,
            $data['question'],
            $data['answer'],
            isset($data['is_public']) ? 1 : 0,
            (int)($data['sort_order'] ?? 0),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE faqs
            SET product_id = ?, question = ?, answer = ?, is_public = ?, sort_order = ?
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([
            $data['product_id'] ?: null,
            $data['question'],
            $data['answer'],
            isset($data['is_public']) ? 1 : 0,
            (int)($data['sort_order'] ?? 0),
            $id,
            $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM faqs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function reorder(int $userId, array $ids): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('UPDATE faqs SET sort_order = ? WHERE id = ? AND user_id = ?');
        foreach ($ids as $order => $id) {
            $stmt->execute([$order, (int)$id, $userId]);
        }
    }

    public static function count(int $userId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM faqs WHERE user_id = ?');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
