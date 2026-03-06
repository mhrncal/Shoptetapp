-- Tracking exportů fotek — reset 30denního timeru
CREATE TABLE IF NOT EXISTS `photo_export_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `exported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `photo_count` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_pel_user` (`user_id`, `exported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Přidej warning_sent do users pro tracking odeslaného upozornění
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `photo_warning_sent_at` DATETIME DEFAULT NULL;
