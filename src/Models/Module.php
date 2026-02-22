<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Module
{
    public static function all(): array
    {
        $db = Database::getInstance();
        return $db->query('SELECT * FROM modules ORDER BY label ASC')->fetchAll();
    }

    public static function findByName(string $name): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM modules WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }
}
