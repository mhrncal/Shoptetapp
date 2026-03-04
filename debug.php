<?php
/**
 * Debug skript - zjisti proč je prázdná stránka
 * Otevři: https://aplikace.shopcode.cz/debug.php
 */

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug</title></head><body>";
echo "<h1>ShopCode Debug</h1>";
echo "<pre>";

echo "=== PHP INFO ===\n";
echo "PHP verze: " . phpversion() . "\n";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' . "\n\n";

echo "=== CESTY ===\n";
echo "ROOT: " . __DIR__ . "\n";
echo "Config: " . __DIR__ . '/config/config.php' . "\n";
echo "Index: " . __DIR__ . '/index.php' . "\n\n";

echo "=== SOUBORY ===\n";
echo ".env existuje: " . (file_exists(__DIR__ . '/.env') ? 'ANO' : 'NE') . "\n";
if (file_exists(__DIR__ . '/.env')) {
    echo ".env velikost: " . filesize(__DIR__ . '/.env') . " bytes\n";
    $envContent = file_get_contents(__DIR__ . '/.env');
    echo ".env řádků: " . substr_count($envContent, "\n") . "\n";
    echo ".env obsahuje DB_HOST: " . (strpos($envContent, 'DB_HOST') !== false ? 'ANO' : 'NE') . "\n";
}

echo "\nconfig.php existuje: " . (file_exists(__DIR__ . '/config/config.php') ? 'ANO' : 'NE') . "\n";
echo "index.php existuje: " . (file_exists(__DIR__ . '/index.php') ? 'ANO' : 'NE') . "\n\n";

echo "=== TEST CONFIG NAČTENÍ ===\n";
try {
    define('ROOT', __DIR__);
    
    if (!file_exists(__DIR__ . '/.env')) {
        throw new Exception('.env soubor neexistuje!');
    }
    
    if (!file_exists(__DIR__ . '/config/config.php')) {
        throw new Exception('config.php neexistuje!');
    }
    
    require __DIR__ . '/config/config.php';
    
    echo "✅ Config načten úspěšně!\n\n";
    
    echo "=== DEFINOVANÉ KONSTANTY ===\n";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'UNDEFINED') . "\n";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'UNDEFINED') . "\n";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'UNDEFINED') . "\n";
    echo "DB_PASS: " . (defined('DB_PASS') ? '***' : 'UNDEFINED') . "\n";
    echo "APP_URL: " . (defined('APP_URL') ? APP_URL : 'UNDEFINED') . "\n";
    echo "APP_ENV: " . (defined('APP_ENV') ? APP_ENV : 'UNDEFINED') . "\n";
    echo "APP_DEBUG: " . (defined('APP_DEBUG') ? (APP_DEBUG ? 'true' : 'false') : 'UNDEFINED') . "\n";
    echo "APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'UNDEFINED') . "\n\n";
    
    echo "=== TEST DATABASE ===\n";
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        echo "✅ Připojení k DB úspěšné!\n";
    } catch (PDOException $e) {
        echo "❌ Chyba DB: " . $e->getMessage() . "\n";
    }
    
} catch (Throwable $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
    echo "Soubor: " . $e->getFile() . "\n";
    echo "Řádek: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== KONEC DEBUGU ===\n";
echo "</pre>";
echo "<p><a href='/'>← Zpět na homepage</a></p>";
echo "</body></html>";
