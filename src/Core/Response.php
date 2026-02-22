<?php

namespace ShopCode\Core;

class Response
{
    public static function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . APP_URL . $url, true, $code);
        exit;
    }

    public static function redirectExternal(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', [], 'auth');
        exit;
    }

    public static function forbidden(): never
    {
        http_response_code(403);
        View::render('errors/403', [], 'auth');
        exit;
    }
}
