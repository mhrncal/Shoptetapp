<?php
// ============================================================
// ShopCode — Konfigurační šablona
// Zkopíruj jako config/config.php a uprav hodnoty
// config/config.php je v .gitignore — nikdy se necommituje!
// ============================================================

// ROOT může být definováno z index.php nebo cron skriptů.
if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

// ---- Aplikace -----------------------------------------------
define('APP_NAME',    'ShopCode');
define('APP_URL',     'https://domena.cz');  // bez trailing slash
define('ASSETS_URL',  APP_URL . '/public');
define('APP_ENV',     'production');         // development | production
define('APP_DEBUG',   false);                // false v produkci!
define('APP_DEBUG_KEY', '');                 // klíč pro debug.php (nastavit v dev)

// ---- Databáze -----------------------------------------------
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'shopcode_db');
define('DB_USER',     'shopcode_user');
define('DB_PASS',     'SILNE_HESLO_ZDE');
define('DB_CHARSET',  'utf8mb4');

// ---- Session ------------------------------------------------
define('SESSION_NAME',     'shopcode_sess');
define('SESSION_LIFETIME', 7200);           // sekundy (2 hodiny)

// ---- Remember me --------------------------------------------
define('REMEMBER_LIFETIME', 2592000);       // 30 dní v sekundách

// ---- Superadmin (pouze pro seed.php) ------------------------
define('SUPERADMIN_EMAIL', 'admin@vase-domena.cz');
define('SUPERADMIN_PASS',  'DOCASNE_HESLO_ZMENIT_PO_PRVNIM_PRIHLASENI');

// ---- Bezpečnost ---------------------------------------------
define('CSRF_TOKEN_LENGTH', 32);
define('LOGIN_MAX_ATTEMPTS', 5);            // max pokusů před lockoutem
define('LOGIN_LOCKOUT_MINUTES', 15);        // délka lockouta

// ---- Upload -------------------------------------------------
define('UPLOAD_DIR',      ROOT . '/public/uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB

// ---- EMAIL / SMTP (PHPMailer) --------------------------------
define('MAIL_HOST',       'smtp.example.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'smtp_uzivatel@example.com');
define('MAIL_PASSWORD',   'SMTP_HESLO_ZDE');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM',       'noreply@vase-domena.cz');
define('MAIL_FROM_NAME',  APP_NAME);
