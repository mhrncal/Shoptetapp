<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Product
{
    public static function all(int $userId, array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $db     = Database::getInstance();
        $where  = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['search'])) {
            $where[]  = '(name LIKE ? OR shoptet_id LIKE ? OR brand LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s);
        }
        if (!empty($filters['category'])) {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['brand'])) {
            $where[]  = 'brand = ?';
            $params[] = $filters['brand'];
        }

        $offset   = ($page - 1) * $perPage;
        $orderBy  = match($filters['sort'] ?? '') {
            'price_asc'  => 'price ASC',
            'price_desc' => 'price DESC',
            'name_asc'   => 'name ASC',
            default      => 'updated_at DESC',
        };

        $sql    = 'SELECT * FROM products WHERE ' . implode(' AND ', $where)
                . " ORDER BY {$orderBy} LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(int $userId, array $filters = []): int
    {
        $db     = Database::getInstance();
        $where  = ['user_id = ?'];
        $params = [$userId];

        if (!empty($filters['search'])) {
            $where[]  = '(name LIKE ? OR shoptet_id LIKE ? OR brand LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s);
        }
        if (!empty($filters['category'])) {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM products WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getVariants(int $productId, int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM product_variants WHERE product_id = ? AND user_id = ? ORDER BY name');
        $stmt->execute([$productId, $userId]);
        return $stmt->fetchAll();
    }

    public static function getCategories(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT DISTINCT category FROM products WHERE user_id = ? AND category IS NOT NULL ORDER BY category');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function getBrands(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT DISTINCT brand FROM products WHERE user_id = ? AND brand IS NOT NULL ORDER BY brand');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
