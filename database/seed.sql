-- ============================================================
-- ShopCode PHP — Seed data
-- Spustit PO schema.sql
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- MODULY
-- ============================================================
INSERT INTO `modules` (`name`, `label`, `description`, `icon`, `version`, `is_system_module`) VALUES
('xml_import',      'XML Import',         'Import produktů z XML feedu Shoptetu',         'file-earmark-arrow-down', '1.0.0', 1),
('faq',             'FAQ',                'Správa FAQ — obecné i k produktům',             'question-circle',         '1.0.0', 1),
('branches',        'Pobočky',            'Správa poboček s Google Maps',                  'geo-alt',                 '1.0.0', 1),
('event_calendar',  'Kalendář akcí',      'Správa akcí a událostí s ICS exportem',         'calendar-event',          '1.0.0', 1),
('product_tabs',    'Záložky produktů',   'Vlastní záložky k produktům',                   'layout-text-window',      '1.0.0', 1),
('product_videos',  'Videa k produktům',  'Přiřazení videí k produktům',                   'play-circle',             '1.0.0', 1),
('api_access',      'API přístup',        'API tokeny a přístup k datům přes REST API',    'key',                     '1.0.0', 1),
('webhooks',        'Webhooky',           'Webhooky pro externí integrace',                'broadcast',               '1.0.0', 1),
('statistics',      'Statistiky',         'Přehledy a reporty aktivity',                   'bar-chart-line',          '1.0.0', 1),
('settings',        'Nastavení',          'Nastavení systému a profilu',                   'gear',                    '1.0.0', 1)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

-- ============================================================
-- SUPERADMIN
-- Heslo: Shopcode2024!
-- Hash se generuje v PHP: password_hash('Shopcode2024!', PASSWORD_BCRYPT)
-- Placeholder hash níže — při první migraci spustit seed přes PHP CLI nebo změnit heslo v admin UI
-- ============================================================
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `shop_name`, `role`, `status`)
VALUES (
    'info@shopcode.cz',
    '$2y$12$placeholder.replace.me.with.real.bcrypt.hash.generated',
    'Milan',
    'Hrnčál',
    'ShopCode',
    'superadmin',
    'approved'
)
ON DUPLICATE KEY UPDATE
    `first_name` = 'Milan',
    `last_name`  = 'Hrnčál',
    `shop_name`  = 'ShopCode',
    `role`       = 'superadmin',
    `status`     = 'approved';

-- Přiřadit všechny moduly superadminovi jako active
INSERT INTO `user_modules` (`user_id`, `module_id`, `status`, `activated_at`)
SELECT u.id, m.id, 'active', NOW()
FROM `users` u
CROSS JOIN `modules` m
WHERE u.email = 'info@shopcode.cz'
ON DUPLICATE KEY UPDATE `status` = 'active', `activated_at` = NOW();
