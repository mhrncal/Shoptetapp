<?php

declare(strict_types=1);

define('ROOT', __DIR__);

// Autoloader (PSR-4, bez Composeru)
spl_autoload_register(function (string $class): void {
    $prefix = 'ShopCode\\';
    $base   = ROOT . '/src/';

    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Konfigurace
$configFile = ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    die('Chybí config/config.php — zkopíruj config/config.example.php a uprav nastavení.');
}
require_once $configFile;

// Error handling
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Spuštění aplikace
\ShopCode\Core\App::run();
