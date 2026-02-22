<?php

namespace ShopCode\Middleware;

use ShopCode\Core\{Request, Response};
use ShopCode\Models\ApiToken;

class ApiAuthMiddleware
{
    public static function handle(Request $request): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!str_starts_with($header, 'Bearer ')) {
            self::unauthorized('Chybí Authorization: Bearer token');
        }

        $plaintext = substr($header, 7);
        $token     = ApiToken::findByPlaintext($plaintext);

        if (!$token) {
            self::unauthorized('Neplatný nebo vypršelý token');
        }

        if ($token['status'] !== 'approved') {
            self::unauthorized('Účet není schválený');
        }

        // Uložíme do requestu pro controller
        $_REQUEST['_api_user_id']     = $token['user_id'];
        $_REQUEST['_api_permissions'] = json_decode($token['permissions'] ?? '[]', true);
    }

    public static function hasPermission(string $perm): bool
    {
        $perms = $_REQUEST['_api_permissions'] ?? [];
        return in_array($perm, $perms);
    }

    public static function userId(): int
    {
        return (int)($_REQUEST['_api_user_id'] ?? 0);
    }

    private static function unauthorized(string $message): never
    {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $message, 'code' => 401]);
        exit;
    }
}
