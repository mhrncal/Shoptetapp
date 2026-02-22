<?php

namespace ShopCode\Core;

class App
{
    public static function run(): void
    {
        // Inicializace
        View::init();
        Session::start();

        // Request + Router
        $request = new Request();
        $routes  = require ROOT . '/config/routes.php';
        $router  = new Router($routes, $request);
        $router->dispatch();
    }
}
