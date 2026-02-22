<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class Webhook
{
    public const EVENTS = [
        'product.created'  => 'Produkt vytvořen',
        'product.updated'  => 'Produkt aktualizován',
        'product.deleted'  => 'Produkt smazán',
        'import.completed' => 'Import dokončen',
        'import.failed'    => 'Import selhal',
    ];

    public static function allForUser(int $userId): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM webhooks WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['events'] = json_decode($r['events'], true) ?? [];
        }
        return $rows;
    }

    public static function findById(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM webhooks WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['events'] = json_decode($row['events'], true) ?? [];
        return $row;
    }

    public static function create(int $userId, array $data): int
    {
        $db     = Database::getInstance();
        $secret = 'whsec_' . bin2hex(random_bytes(24));
        $stmt   = $db->prepare('
            INSERT INTO webhooks (user_id, name, url, events, secret, is_active, retry_count)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $data['name'],
            $data['url'],
            json_encode($data['events']),
            $secret,
            isset($data['is_active']) ? 1 : 0,
            (int)($data['retry_count'] ?? 3),
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE webhooks
            SET name = ?, url = ?, events = ?, is_active = ?, retry_count = ?
            WHERE id = ? AND user_id = ?
        ');
        return $stmt->execute([
            $data['name'],
            $data['url'],
            json_encode($data['events']),
            isset($data['is_active']) ? 1 : 0,
            (int)($data['retry_count'] ?? 3),
            $id,
            $userId,
        ]);
    }

    public static function delete(int $id, int $userId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM webhooks WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Vrátí všechny aktivní webhooky pro daný event a uživatele
     */
    public static function getActiveForEvent(int $userId, string $event): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM webhooks
            WHERE user_id = ? AND is_active = 1
              AND JSON_CONTAINS(events, ?)
        ");
        $stmt->execute([$userId, json_encode($event)]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['events'] = json_decode($r['events'], true) ?? [];
        }
        return $rows;
    }

    public static function getRecentLogs(int $webhookId, int $limit = 20): array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM webhook_logs
            WHERE webhook_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$webhookId, $limit]);
        return $stmt->fetchAll();
    }

    public static function logDelivery(int $webhookId, string $event, array $payload, ?int $status, ?string $response, int $attempt): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO webhook_logs (webhook_id, event_type, payload, response_status, response_body, attempt_number)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $webhookId,
            $event,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $status,
            $response ? substr($response, 0, 2000) : null,
            $attempt,
        ]);
    }
}
