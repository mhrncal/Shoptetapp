<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ApiToken
{
    public const PERMISSIONS = ['products:read', 'faq:read', 'branches:read', 'events:read'];

    public static function allForUser(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM api_tokens WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM api_tokens WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Vytvoří nový token, vrátí plaintext (zobrazit jen jednou!)
     */
    public static function create(int $userId, string $name, array $permissions, ?string $expiresAt): array
    {
        $db          = Database::getInstance();
        $plaintext   = 'sc_' . bin2hex(random_bytes(32)); // 67 znaků, prefix sc_
        $prefix      = substr($plaintext, 0, 10);
        $hash        = hash('sha256', $plaintext);

        $stmt = $db->prepare('
            INSERT INTO api_tokens (user_id, name, token_hash, token_prefix, permissions, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([
            $userId,
            $name,
            $hash,
            $prefix,
            json_encode($permissions),
            $expiresAt ?: null,
        ]);

        return [
            'id'        => (int)$db->lastInsertId(),
            'plaintext' => $plaintext,
        ];
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM api_tokens WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    public static function revoke(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('UPDATE api_tokens SET is_active = 0 WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Ověření tokenu při API requestu
     */
    public static function findByPlaintext(string $plaintext): ?array
    {
        $db   = Database::getInstance();
        $hash = hash('sha256', $plaintext);
        $stmt = $db->prepare('
            SELECT t.*, u.id AS uid, u.status, u.role
            FROM api_tokens t
            JOIN users u ON u.id = t.user_id
            WHERE t.token_hash = ?
              AND t.is_active = 1
              AND (t.expires_at IS NULL OR t.expires_at > NOW())
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        $token = $stmt->fetch();
        if (!$token) return null;

        // Aktualizuj last_used_at
        $db->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')
           ->execute([$token['id']]);

        return $token;
    }

    public static function count(int $userId): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = ? AND is_active = 1');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }
}
