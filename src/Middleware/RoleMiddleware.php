<?php

namespace ShopCode\Middleware;

use ShopCode\Core\{Session, Response, Request};

class RoleMiddleware
{
    public static function handle(Request $request, string $role): void
    {
        $user = Session::get('user');

        if (!$user || $user['role'] !== $role) {
            Response::forbidden();
        }
    }
}
