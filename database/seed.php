#!/usr/bin/env php
<?php
/**
 * Seed script — vytvoří superadmina s validním bcrypt heslem
 * Spustit: php database/seed.php
 * Vyžaduje: config/config.php musí existovat
 */

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Moduly
    $modules = [
        ['xml_import',     'XML Import',         'Import produktů z XML feedu Shoptetu',         'file-earmark-arrow-down'],
        ['faq',            'FAQ',                'Správa FAQ — obecné i k produktům',             'question-circle'],
        ['branches',       'Pobočky',            'Správa poboček s Google Maps',                  'geo-alt'],
        ['event_calendar', 'Kalendář akcí',      'Správa akcí a událostí s ICS exportem',         'calendar-event'],
        ['product_tabs',   'Záložky produktů',   'Vlastní záložky k produktům',                   'layout-text-window'],
        ['product_videos', 'Videa k produktům',  'Přiřazení videí k produktům',                   'play-circle'],
        ['api_access',     'API přístup',        'API tokeny a přístup k datům přes REST API',    'key'],
        ['webhooks',       'Webhooky',           'Webhooky pro externí integrace',                'broadcast'],
        ['statistics',     'Statistiky',         'Přehledy a reporty aktivity',                   'bar-chart-line'],
        ['settings',       'Nastavení',          'Nastavení systému a profilu',                   'gear'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO `modules` (`name`, `label`, `description`, `icon`)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)
    ");
    foreach ($modules as $m) {
        $stmt->execute($m);
    }
    echo "✅ Moduly vloženy\n";

    // Superadmin
    $password = SUPERADMIN_PASS ?? 'Shopcode2024!';
    $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare("
        INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `shop_name`, `role`, `status`)
        VALUES (?, ?, 'Milan', 'Hrnčál', 'ShopCode', 'superadmin', 'approved')
        ON DUPLICATE KEY UPDATE
            `password_hash` = VALUES(`password_hash`),
            `role`          = 'superadmin',
            `status`        = 'approved'
    ");
    $stmt->execute([SUPERADMIN_EMAIL ?? 'info@shopcode.cz', $hash]);

    $adminId = $pdo->query("SELECT id FROM users WHERE email = '" . (SUPERADMIN_EMAIL ?? 'info@shopcode.cz') . "'")->fetchColumn();

    // Přiřadit všechny moduly
    $stmt = $pdo->prepare("
        INSERT INTO `user_modules` (`user_id`, `module_id`, `status`, `activated_at`)
        SELECT ?, id, 'active', NOW() FROM `modules`
        ON DUPLICATE KEY UPDATE `status` = 'active', `activated_at` = NOW()
    ");
    $stmt->execute([$adminId]);
    echo "✅ Superadmin vytvořen: " . (SUPERADMIN_EMAIL ?? 'info@shopcode.cz') . " / {$password}\n";
    echo "✅ Všechny moduly přiřazeny\n";

} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage() . "\n";
    exit(1);
}
