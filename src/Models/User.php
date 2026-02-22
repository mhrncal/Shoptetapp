<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;
use PDO;

class User
{
    public static function findById(int $id): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function findByRememberToken(string $token): ?array
    {
        $db   = Database::getInstance();
        $hash = hash('sha256', $token);
        $stmt = $db->prepare('
            SELECT u.* FROM users u
            INNER JOIN remember_tokens rt ON rt.user_id = u.id
            WHERE rt.token_hash = ? AND rt.expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$hash]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, shop_name, role, status)
            VALUES (:email, :password_hash, :first_name, :last_name, :shop_name, :role, :status)
        ');
        $stmt->execute([
            'email'         => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'first_name'    => $data['first_name'] ?? null,
            'last_name'     => $data['last_name']  ?? null,
            'shop_name'     => $data['shop_name']  ?? null,
            'role'          => $data['role']   ?? 'user',
            'status'        => $data['status'] ?? 'pending',
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db      = Database::getInstance();
        $allowed = ['first_name', 'last_name', 'shop_name', 'shop_url', 'xml_feed_url', 'status', 'role', 'password_hash'];
        $sets    = [];
        $values  = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]        = "{$field} = ?";
                $values[]      = $data[$field];
            }
        }

        if (empty($sets)) return false;

        $values[] = $id;
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($values);
    }

    public static function updatePassword(int $id, string $newPassword): bool
    {
        $db   = Database::getInstance();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $id]);
    }

    public static function delete(int $id): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND role != "superadmin"');
        return $stmt->execute([$id]);
    }

    public static function all(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db     = Database::getInstance();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR shop_name LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }

        $offset = ($page - 1) * $perPage;
        $sql    = 'SELECT * FROM users WHERE ' . implode(' AND ', $where)
                . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $perPage;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(array $filters = []): int
    {
        $db     = Database::getInstance();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR shop_name LIKE ?)';
            $s        = '%' . $filters['search'] . '%';
            array_push($params, $s, $s, $s, $s);
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function updateLastLogin(int $id): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET last_login_at = NOW(), login_attempts = 0, locked_until = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Zvýší počítadlo neúspěšných přihlášení.
     * Vrátí true pokud byl účet právě v tomto volání zamknut (přechod na locked).
     */
    public static function incrementLoginAttempts(string $email): bool
    {
        $db   = Database::getInstance();

        // Načteme aktuální stav před inkrementem
        $stmt = $db->prepare('SELECT id, login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) return false;

        $wasAlreadyLocked = $user['locked_until'] && strtotime($user['locked_until']) > time();

        $stmt = $db->prepare('
            UPDATE users
            SET login_attempts = login_attempts + 1,
                locked_until = CASE
                    WHEN login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE)
                    ELSE locked_until
                END
            WHERE email = ?
        ');
        $stmt->execute([LOGIN_MAX_ATTEMPTS, LOGIN_LOCKOUT_MINUTES, $email]);

        // Zjistíme jestli jsme právě zamkli
        if (!$wasAlreadyLocked && ($user['login_attempts'] + 1) >= LOGIN_MAX_ATTEMPTS) {
            return true; // Právě zamknuto
        }

        return false;
    }

    public static function isLocked(array $user): bool
    {
        if (!$user['locked_until']) return false;
        return strtotime($user['locked_until']) > time();
    }

    public static function saveRememberToken(int $userId, string $token): void
    {
        $db   = Database::getInstance();
        $hash = hash('sha256', $token);

        // Smaž staré tokeny pro tohoto uživatele
        $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);

        $stmt = $db->prepare('
            INSERT INTO remember_tokens (user_id, token_hash, expires_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
        ');
        $stmt->execute([$userId, $hash, REMEMBER_LIFETIME]);
    }

    public static function deleteRememberToken(int $userId): void
    {
        $db = Database::getInstance();
        $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
    }

    public static function fullName(array $user): string
    {
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email'];
    }

    public static function countByStatus(): array
    {
        $db   = Database::getInstance();
        $stmt = $db->query('SELECT status, COUNT(*) as cnt FROM users GROUP BY status');
        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }
}
