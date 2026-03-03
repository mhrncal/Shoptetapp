<?php
// ============================================================
// ShopCode — Konfigurace
// Zkopíruj jako config/config.php a uprav hodnoty
// config/config.php je v .gitignore — nikdy se necommituje!

// ROOT může být definováno z index.php nebo cron skriptů.
// Pokud není (přímé volání seed.php, schema.sql, apod.), definujeme zde.
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
// ============================================================

// ---- Aplikace -----------------------------------------------
define('APP_NAME',    'ShopCode');
define('APP_URL',     'http://localhost');   // bez trailing slash
define('ASSETS_URL',  APP_URL . '/public');  // cesta ke statickým souborům
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

// ============================================================
// EMAIL / SMTP (PHPMailer)
// ============================================================
define('MAIL_HOST',       'smtp.rosti.cz');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   '8787@rostiapp.cz');
define('MAIL_PASSWORD',   '5fbf97d622ed43c5a4578fe5d4fc86c0');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM',       '8787@rostiapp.cz');
define('MAIL_FROM_NAME',  APP_NAME);

// ============================================================
// ============================================================
