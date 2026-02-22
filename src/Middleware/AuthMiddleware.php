<?php

namespace ShopCode\Middleware;

use ShopCode\Core\{Session, Response, Request};
use ShopCode\Models\User;

class AuthMiddleware
{
    public static function handle(Request $request): void
    {
        // Zkontroluj session
        if (Session::has('user')) {
            return;
        }

        // Zkontroluj remember-me cookie
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $user  = User::findByRememberToken($token);

            if ($user) {
                // Obnov session
                Session::regenerate();
                Session::set('user', [
                    'id'         => $user['id'],
                    'email'      => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name'  => $user['last_name'],
                    'role'       => $user['role'],
                    'status'     => $user['status'],
                    'shop_name'  => $user['shop_name'],
                ]);
                return;
            }

            // Neplatný token — smaž cookie
            setcookie('remember_token', '', time() - 3600, '/', '', APP_ENV === 'production', true);
        }

        // Nepřihlášen — přesměruj na login
        Session::flash('info', 'Pro přístup se prosím přihlaste.');
        Response::redirect('/login');
    }
}
