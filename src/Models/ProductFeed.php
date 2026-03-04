<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ProductFeed
{
    /**
     * Najdi všechny feedy pro usera
     */
    public static function allForUser(int $userId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM product_feeds 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Vytvoř nový feed
     */
    public static function create(int $userId, array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO product_feeds 
            (user_id, name, url, type, delimiter, encoding, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $userId,
            $data['name'],
            $data['url'],
            $data['type'] ?? 'csv_simple',
            $data['delimiter'] ?? ';',
            $data['encoding'] ?? 'windows-1250',
            $data['enabled'] ?? true
        ]);
        
        return (int)$db->lastInsertId();
    }
    
    /**
     * Najdi feed podle ID
     */
    public static function findById(int $id, int $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM product_feeds 
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Smaž feed
     */
    public static function delete(int $id, int $userId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            DELETE FROM product_feeds 
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([$id, $userId]);
    }
    
    /**
     * Aktualizuj status po fetch
     */
    public static function updateFetchStatus(int $id, bool $success, ?string $error = null): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE product_feeds 
            SET last_fetch_at = NOW(),
                last_fetch_status = ?,
                last_error = ?
            WHERE id = ?
        ');
        
        $stmt->execute([
            $success ? 'success' : 'error',
            $error,
            $id
        ]);
    }
}
