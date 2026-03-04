-- Migration: Feed sync log pro časovou osu
-- Date: 2026-03-04

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
