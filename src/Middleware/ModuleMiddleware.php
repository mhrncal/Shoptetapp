<?php

namespace ShopCode\Middleware;

use ShopCode\Core\{Session, Response, Request};
use ShopCode\Models\UserModule;

class ModuleMiddleware
{
    public static function handle(Request $request, string $moduleName): void
    {
        $user = Session::get('user');

        // Superadmin má vždy přístup
        if ($user && $user['role'] === 'superadmin') {
            return;
        }

        if (!$user || !UserModule::isActive($user['id'], $moduleName)) {
            Session::flash('error', 'Nemáte přístup k tomuto modulu.');
            Response::redirect('/dashboard');
        }
    }
}
