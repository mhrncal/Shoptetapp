<?php

namespace ShopCode\Services;

use ShopCode\Models\Webhook;
use ShopCode\Services\AdminNotifier;

/**
 * Odesílá webhook notifikace.
 * Používá cURL, podepisuje payload HMAC-SHA256.
 *
 * Volání z Worker/Controlleru:
 *   WebhookDispatcher::fire($userId, 'product.updated', ['product_id' => 123, ...]);
 */
class WebhookDispatcher
{
    private const TIMEOUT = 10; // sekund

    public static function fire(int $userId, string $event, array $payload): void
    {
        $webhooks = Webhook::getActiveForEvent($userId, $event);
        if (empty($webhooks)) return;

        $body = json_encode([
            'event'      => $event,
            'fired_at'   => date('c'),
            'payload'    => $payload,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($webhooks as $wh) {
            self::deliver($wh, $event, $payload, $body);
        }
    }

    private static function deliver(array $wh, string $event, array $payload, string $body): void
    {
        $signature  = 't=' . time() . ',v1=' . hash_hmac('sha256', $body, $wh['secret']);
        $maxRetries = (int)($wh['retry_count'] ?? 3);
        $lastStatus = null;
        $lastError  = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            [$status, $response, $error] = self::post($wh['url'], $body, $signature);
            $lastStatus = $status;
            $lastError  = $error;

            Webhook::logDelivery($wh['id'], $event, $payload, $status, $response ?? $error, $attempt);

            // Úspěch: 2xx
            if ($status >= 200 && $status < 300) return;

            // Neúspěch — krátká pauza před dalším pokusem
            if ($attempt < $maxRetries) {
                sleep($attempt * 2);
            }
        }

        // Všechny pokusy vyčerpány — notifikuj superadmina
        try {
            $db   = \ShopCode\Core\Database::getInstance();
            $stmt = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$wh['user_id']]);
            $userEmail = $stmt->fetchColumn() ?: 'neznámý';

            AdminNotifier::webhookFailed(
                userId:         $wh['user_id'],
                userEmail:      $userEmail,
                webhookName:    $wh['name'],
                webhookUrl:     $wh['url'],
                eventType:      $event,
                attempts:       $maxRetries,
                lastStatusCode: $lastStatus,
                lastError:      $lastError
            );
        } catch (\Throwable $e) {
            // Tichá chyba — neblokujeme hlavní flow
        }
    }

    /**
     * @return array{int|null, string|null, string|null}  [http_code, body, error]
     */
    private static function post(string $url, string $body, string $signature): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'User-Agent: ShopCode-Webhook/1.0',
                'X-ShopCode-Signature: ' . $signature,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $status   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch) ?: null;
        curl_close($ch);

        return [$status ?: null, $response ?: null, $error];
    }
}
