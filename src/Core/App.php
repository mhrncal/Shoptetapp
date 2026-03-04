<?php

namespace ShopCode\Core;

class App
{
    public static function run(): void
    {
        // Inicializace
        View::init();
        Session::start();
        
        // Vygeneruj CSRF token pokud neexistuje
        if (!isset($_SESSION['_csrf'])) {
            Session::regenerateCsrf();
        }

        // Request + Router
        $request = new Request();
        $routes  = require ROOT . '/config/routes.php';
        $router  = new Router($routes, $request);
        $router->dispatch();
    }
}
