<?php
// ============================================================
// ShopCode — Konfigurace
// Zkopíruj jako config/config.php a uprav hodnoty
// config/config.php je v .gitignore — nikdy se necommituje!
// ============================================================

// ---- Aplikace -----------------------------------------------
define('APP_NAME',    'ShopCode');
define('APP_URL',     'http://localhost');   // bez trailing slash
define('APP_ENV',     'development');        // development | production
define('APP_DEBUG',   true);                 // false v produkci!

// ---- Databáze -----------------------------------------------
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'shopcode');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ---- Session ------------------------------------------------
define('SESSION_NAME',     'shopcode_sess');
define('SESSION_LIFETIME', 7200);           // sekundy (2 hodiny)

// ---- Remember me --------------------------------------------
define('REMEMBER_LIFETIME', 2592000);       // 30 dní v sekundách

// ---- Superadmin ---------------------------------------------
define('SUPERADMIN_EMAIL', 'info@shopcode.cz');
define('SUPERADMIN_PASS',  'Shopcode2024!'); // jen pro seed.php

// ---- Bezpečnost ---------------------------------------------
define('CSRF_TOKEN_LENGTH', 32);
define('LOGIN_MAX_ATTEMPTS', 5);            // max pokusů před lockoutem
define('LOGIN_LOCKOUT_MINUTES', 15);        // délka lockouta

// ---- Upload -------------------------------------------------
define('UPLOAD_DIR',      ROOT . '/public/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
