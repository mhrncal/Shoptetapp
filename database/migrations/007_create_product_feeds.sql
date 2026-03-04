-- Migration: Product Feeds pro import produktových dat
-- Date: 2026-03-04

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

-- Tabulka pro cache produktů z feedu
CREATE TABLE IF NOT EXISTS `feed_products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feed_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(100) NOT NULL COMMENT 'SKU/code produktu',
    `pair_code` VARCHAR(100) DEFAULT NULL COMMENT 'PairCode pro varianty',
    `name` VARCHAR(255) NOT NULL,
    `default_image` TEXT DEFAULT NULL,
    `images` JSON DEFAULT NULL COMMENT 'Pole URL obrázků',
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feed` (`feed_id`),
    KEY `idx_code` (`code`),
    KEY `idx_pair_code` (`pair_code`),
    FOREIGN KEY (`feed_id`) REFERENCES `product_feeds`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pro rychlé vyhledávání
CREATE INDEX idx_feed_code ON feed_products(feed_id, code);
