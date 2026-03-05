<?php
if (($_GET['key'] ?? '') !== 'shopcode_diag') {
    http_response_code(403); die('Forbidden');
}

define('ROOT', dirname(__DIR__));
require ROOT . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->exec("ALTER TABLE `product_videos` ADD COLUMN `autoplay` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`");
    echo "OK — sloupec autoplay přidán do product_videos";
} catch (PDOException $e) {
    echo "INFO: " . $e->getMessage();
}
