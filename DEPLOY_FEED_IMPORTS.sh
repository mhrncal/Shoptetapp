#!/bin/bash
# Kompletní deployment feed importů

echo "======================================"
echo "DEPLOY: Feed Import System"
echo "======================================"

# 1. Migrace
echo ""
echo "1. Spouštím migrace..."
mysql -u infoshop_3356 -pShopcode2024?? infoshop_3356 << 'SQL'
-- Migration 007: Product Feeds
CREATE TABLE IF NOT EXISTS `product_feeds` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Název feedu (pro přehlednost)',
    `url` TEXT NOT NULL COMMENT 'URL k CSV/XML feedu',
    `type` ENUM('csv_simple', 'csv_with_images') NOT NULL DEFAULT 'csv_simple',
    `delimiter` CHAR(1) DEFAULT ';' COMMENT 'CSV oddělovač',
    `encoding` VARCHAR(20) DEFAULT 'UTF-8' COMMENT 'windows-1250, UTF-8, ISO-8859-2',
    `last_fetch_at` DATETIME DEFAULT NULL,
    `last_fetch_status` ENUM('success', 'error') DEFAULT NULL,
    `last_error` TEXT DEFAULT NULL,
    `enabled` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 008: Add pair_code
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS `pair_code` VARCHAR(100) DEFAULT NULL COMMENT 'PairCode pro varianty produktu' 
AFTER `code`;

CREATE INDEX IF NOT EXISTS idx_products_pair_code ON products(pair_code);

-- Migration 009: Sync Log
CREATE TABLE IF NOT EXISTS `feed_sync_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feed_id` INT UNSIGNED NOT NULL,
    `started_at` DATETIME NOT NULL,
    `finished_at` DATETIME DEFAULT NULL,
    `status` ENUM('running', 'success', 'error') NOT NULL DEFAULT 'running',
    `products_inserted` INT DEFAULT 0,
    `products_updated` INT DEFAULT 0,
    `products_total` INT DEFAULT 0,
    `reviews_matched` INT DEFAULT 0,
    `reviews_total` INT DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `duration_seconds` INT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_feed` (`feed_id`),
    KEY `idx_started` (`started_at`),
    FOREIGN KEY (`feed_id`) REFERENCES `product_feeds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL

echo "✅ Migrace dokončeny"

# 2. Složky
echo ""
echo "2. Vytvářím složky..."
mkdir -p tmp/feeds
mkdir -p tmp/logs
mkdir -p public/feeds
chmod 755 tmp/feeds tmp/logs public/feeds
echo "✅ Složky vytvořeny"

# 3. Oprávnění
echo ""
echo "3. Nastavuji oprávnění..."
chmod +x cron/feed_sync.php
chmod +x cron/feed_sync_single.php
echo "✅ Oprávnění nastavena"

# 4. Test
echo ""
echo "4. Test struktury..."
echo "   - product_feeds: $(mysql -u infoshop_3356 -pShopcode2024?? infoshop_3356 -se "SELECT COUNT(*) FROM product_feeds" 2>/dev/null || echo '0') záznamů"
echo "   - feed_sync_log: $(mysql -u infoshop_3356 -pShopcode2024?? infoshop_3356 -se "SELECT COUNT(*) FROM feed_sync_log" 2>/dev/null || echo '0') záznamů"
echo "   - products.pair_code: $(mysql -u infoshop_3356 -pShopcode2024?? infoshop_3356 -se "SHOW COLUMNS FROM products LIKE 'pair_code'" 2>/dev/null | wc -l) sloupec"

echo ""
echo "======================================"
echo "✅ DEPLOYMENT HOTOVÝ!"
echo "======================================"
echo ""
echo "Otevři: https://aplikace.shopcode.cz/feeds"
echo ""
