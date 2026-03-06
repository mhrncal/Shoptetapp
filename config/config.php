<?php
/**
 * Hlavní konfigurační soubor
 * Načítá .env a nastavuje konstanty
 */

// Načti .env soubor
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die('Chybí .env soubor — zkopíruj .env.example a uprav nastavení.');
}

// Parsuj .env
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    // Přeskoč komentáře
    if (strpos(trim($line), '#') === 0) {
        continue;
    }
    
    // Parsuj KEY=VALUE
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Odstraň uvozovky
        $value = trim($value, '"\'');
        
        // Nastav konstantu
        if (!defined($key)) {
            define($key, $value);
        }
    }
}

// Kontrola povinných konstant
$required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($required as $const) {
    if (!defined($const)) {
        die("Chybějící konfigurace: $const v .env souboru");
    }
}

// Definuj další potřebné konstanty (pokud nejsou v .env)

// APP_ENV musí být první (ostatní na něm závisí)
if (!defined('APP_ENV')) {
    define('APP_ENV', 'production');
}

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', APP_ENV === 'development');
}

if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost');
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'ShopCode');
}

if (!defined('CSRF_TOKEN_LENGTH')) {
    define('CSRF_TOKEN_LENGTH', 32);
}

if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', 5);
}

if (!defined('LOGIN_LOCKOUT_MINUTES')) {
    define('LOGIN_LOCKOUT_MINUTES', 15);
}

if (!defined('REMEMBER_LIFETIME')) {
    define('REMEMBER_LIFETIME', 2592000); // 30 dní
}

if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 7200); // 2 hodiny
}

if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', ROOT . '/public/uploads/');
}

if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
}

if (!defined('DB_PORT')) {
    define('DB_PORT', '3306');
}

if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'shopcode_session');
}

if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', ''); // Prázdný string = relativní cesty
}

// Timezone
date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Europe/Prague');

// Error reporting (podle prostředí)
if (defined('APP_ENV') && APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Mail
if (!defined('MAIL_HOST'))      define('MAIL_HOST',      getenv('MAIL_HOST')      ?: '');
if (!defined('MAIL_PORT'))      define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
if (!defined('MAIL_USER'))      define('MAIL_USER',      getenv('MAIL_USER')      ?: '');
if (!defined('MAIL_PASS'))      define('MAIL_PASS',      getenv('MAIL_PASS')      ?: '');
if (!defined('MAIL_FROM'))      define('MAIL_FROM',      getenv('MAIL_FROM')      ?: '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'ShopCode');
