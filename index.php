<?php

declare(strict_types=1);

// CORS pro API — musí být před vším ostatním včetně OPTIONS preflight
if (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

if (!defined('ROOT')) {
    define('ROOT', __DIR__);
}

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

try {
    require_once $configFile;
} catch (\Throwable $e) {
    die('Chyba při načítání config.php: ' . $e->getMessage());
}

// Ověř že jsou definované kritické konstanty
$criticalConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'APP_URL', 'APP_DEBUG'];
foreach ($criticalConstants as $const) {
    if (!defined($const)) {
        die("Kritická konstanta není definovaná: $const");
    }
}

// Error handling
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Spuštění aplikace
try {
    \ShopCode\Core\App::run();
} catch (\Throwable $e) {
    if (APP_DEBUG) {
        echo "<h1>Chyba aplikace</h1>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        echo "Omlouváme se, došlo k chybě. Kontaktujte administrátora.";
    }
}
