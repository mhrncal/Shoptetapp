<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class PasswordReset
{
    private const EXPIRY_HOURS = 24;

    /**
     * Vytvoří reset token pro uživatele.
     * Vrátí plaintext token (poslat emailem).
     */
    public static function create(int $userId): string
    {
        $db        = Database::getInstance();
        $plaintext = bin2hex(random_bytes(32)); // 64 hex znaků
        $hash      = hash('sha256', $plaintext);
        $expires   = date('Y-m-d H:i:s', strtotime('+' . self::EXPIRY_HOURS . ' hours'));

        // Zruš staré tokeny pro tohoto uživatele
        $db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

        $db->prepare('
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (?, ?, ?)
        ')->execute([$userId, $hash, $expires]);

        return $plaintext;
    }

    /**
     * Ověří token a vrátí user_id nebo null.
     */
    public static function verify(string $plaintext): ?array
    {
        $db   = Database::getInstance();
        $hash = hash('sha256', $plaintext);

        $stmt = $db->prepare('
            SELECT pr.*, u.email, u.first_name
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token_hash = ?
              AND pr.expires_at > NOW()
              AND pr.used_at IS NULL
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Označí token jako použitý.
     */
    public static function markUsed(string $plaintext): void
    {
        $db   = Database::getInstance();
        $hash = hash('sha256', $plaintext);
        $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?')->execute([$hash]);
    }
}
