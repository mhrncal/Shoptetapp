<?php

namespace ShopCode\Middleware;

use ShopCode\Core\{Session, Response, Request};

class ApprovedMiddleware
{
    public static function handle(Request $request): void
    {
        $user = Session::get('user');

        if (!$user) {
            Response::redirect('/login');
        }

        if ($user['status'] === 'pending') {
            Response::redirect('/pending');
        }

        if ($user['status'] === 'rejected') {
            Session::destroy();
            Session::flash('error', 'Váš účet byl zamítnut. Kontaktujte administrátora.');
            Response::redirect('/login');
        }
    }
}
