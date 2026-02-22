<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ProductTab
{
    public static function forProduct(int $productId, int $userId, bool $activeOnly = false): array
    {
        $db    = Database::getInstance();
        $where = 'product_id = ? AND user_id = ?';
        if ($activeOnly) $where .= ' AND is_active = 1';
        $stmt  = $db->prepare("SELECT * FROM product_tabs WHERE {$where} ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$productId, $userId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM product_tabs WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, int $productId, array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO product_tabs (user_id, product_id, title, content, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId, $productId,
            $data['title'],
            $data['content'],
            (int)($data['sort_order'] ?? 0),
            isset($data['is_active']) ? 1 : 0,
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE product_tabs SET title=?, content=?, sort_order=?, is_active=? WHERE id=? AND user_id=?
        ');
        return $stmt->execute([
            $data['title'], $data['content'],
            (int)($data['sort_order'] ?? 0),
            isset($data['is_active']) ? 1 : 0,
            $id, $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM product_tabs WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
