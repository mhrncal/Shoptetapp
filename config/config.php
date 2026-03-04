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
