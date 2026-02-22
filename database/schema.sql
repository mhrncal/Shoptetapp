-- ============================================================
-- ShopCode PHP — Databázové schéma
-- MySQL 8.0+ · utf8mb4
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- UŽIVATELÉ
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`              VARCHAR(255) NOT NULL,
    `password_hash`      VARCHAR(255) NOT NULL,
    `first_name`         VARCHAR(100) DEFAULT NULL,
    `last_name`          VARCHAR(100) DEFAULT NULL,
    `shop_name`          VARCHAR(255) DEFAULT NULL,
    `shop_url`           VARCHAR(500) DEFAULT NULL,
    `xml_feed_url`       VARCHAR(500) DEFAULT NULL,
    `role`               ENUM('superadmin','user') NOT NULL DEFAULT 'user',
    `status`             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `last_login_at`      DATETIME DEFAULT NULL,
    `login_attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`       DATETIME DEFAULT NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- REMEMBER ME TOKENY
-- ============================================================
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rt_token` (`token_hash`),
    KEY `idx_rt_user` (`user_id`),
    CONSTRAINT `fk_rt_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULY
-- ============================================================
CREATE TABLE IF NOT EXISTS `modules` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(100) NOT NULL,
    `label`            VARCHAR(255) NOT NULL,
    `description`      TEXT DEFAULT NULL,
    `icon`             VARCHAR(100) NOT NULL DEFAULT 'box',
    `version`          VARCHAR(20) NOT NULL DEFAULT '1.0.0',
    `is_system_module` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_modules_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_modules` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `module_id`    INT UNSIGNED NOT NULL,
    `status`       ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    `activated_at` DATETIME DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_module` (`user_id`, `module_id`),
    KEY `idx_um_user` (`user_id`),
    KEY `idx_um_module` (`module_id`),
    CONSTRAINT `fk_um_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
    CONSTRAINT `fk_um_module` FOREIGN KEY (`module_id`) REFERENCES `modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PRODUKTY
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `shoptet_id`   VARCHAR(255) NOT NULL,
    `name`         VARCHAR(500) NOT NULL,
    `description`  LONGTEXT DEFAULT NULL,
    `price`        DECIMAL(12,2) DEFAULT NULL,
    `currency`     VARCHAR(10) NOT NULL DEFAULT 'CZK',
    `category`     VARCHAR(255) DEFAULT NULL,
    `brand`        VARCHAR(255) DEFAULT NULL,
    `availability` VARCHAR(100) DEFAULT NULL,
    `images`       JSON DEFAULT NULL,
    `parameters`   JSON DEFAULT NULL,
    `xml_data`     JSON DEFAULT NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_user_shoptet` (`user_id`, `shoptet_id`),
    KEY `idx_products_user` (`user_id`),
    CONSTRAINT `fk_products_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_variants` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`            INT UNSIGNED NOT NULL,
    `product_id`         INT UNSIGNED NOT NULL,
    `shoptet_variant_id` VARCHAR(255) NOT NULL,
    `name`               VARCHAR(500) DEFAULT NULL,
    `price`              DECIMAL(12,2) DEFAULT NULL,
    `stock`              INT NOT NULL DEFAULT 0,
    `parameters`         JSON DEFAULT NULL,
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_variant_user_shoptet` (`user_id`, `shoptet_variant_id`),
    KEY `idx_variants_product` (`product_id`),
    CONSTRAINT `fk_variants_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FAQ
-- ============================================================
CREATE TABLE IF NOT EXISTS `faqs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED DEFAULT NULL,
    `question`   TEXT NOT NULL,
    `answer`     LONGTEXT NOT NULL,
    `is_public`  TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_faqs_user` (`user_id`),
    KEY `idx_faqs_product` (`product_id`),
    CONSTRAINT `fk_faqs_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_faqs_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- POBOČKY
-- ============================================================
CREATE TABLE IF NOT EXISTS `branches` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `name`            VARCHAR(255) NOT NULL,
    `description`     TEXT DEFAULT NULL,
    `street_address`  VARCHAR(500) DEFAULT NULL,
    `city`            VARCHAR(255) DEFAULT NULL,
    `postal_code`     VARCHAR(20) DEFAULT NULL,
    `image_url`       VARCHAR(1000) DEFAULT NULL,
    `branch_url`      VARCHAR(1000) DEFAULT NULL,
    `google_maps_url` VARCHAR(1000) DEFAULT NULL,
    `latitude`        DECIMAL(10,7) DEFAULT NULL,
    `longitude`       DECIMAL(10,7) DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_branches_user` (`user_id`),
    CONSTRAINT `fk_branches_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OTEVÍRACÍ DOBY POBOČEK
-- ============================================================
CREATE TABLE IF NOT EXISTS `branch_hours` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id`  INT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Po, 1=Út, 2=St, 3=Čt, 4=Pá, 5=So, 6=Ne',
    `is_closed`  TINYINT(1) NOT NULL DEFAULT 0,
    `open_from`  TIME DEFAULT NULL,
    `open_to`    TIME DEFAULT NULL,
    `note`       VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_branch_day` (`branch_id`, `day_of_week`),
    CONSTRAINT `fk_bh_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UDÁLOSTI
-- ============================================================
CREATE TABLE IF NOT EXISTS `events` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `title`           VARCHAR(500) NOT NULL,
    `description`     LONGTEXT DEFAULT NULL,
    `start_date`      DATETIME NOT NULL,
    `end_date`        DATETIME NOT NULL,
    `event_url`       VARCHAR(1000) DEFAULT NULL,
    `image_url`       VARCHAR(1000) DEFAULT NULL,
    `address`         VARCHAR(500) DEFAULT NULL,
    `google_maps_url` VARCHAR(1000) DEFAULT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_events_user` (`user_id`),
    KEY `idx_events_start` (`start_date`),
    CONSTRAINT `fk_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- XML IMPORT
-- ============================================================
CREATE TABLE IF NOT EXISTS `xml_imports` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `status`            ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `products_imported` INT NOT NULL DEFAULT 0,
    `products_updated`  INT NOT NULL DEFAULT 0,
    `products_deleted`  INT NOT NULL DEFAULT 0,
    `error_message`     TEXT DEFAULT NULL,
    `started_at`        DATETIME DEFAULT NULL,
    `completed_at`      DATETIME DEFAULT NULL,
    `queue_id`          INT UNSIGNED DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_xml_user` (`user_id`),
    KEY `idx_xml_status` (`status`),
    CONSTRAINT `fk_xml_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `xml_processing_queue` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`              INT UNSIGNED NOT NULL,
    `xml_feed_url`         TEXT NOT NULL,
    `status`               ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `priority`             TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `progress_percentage`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `products_processed`   INT NOT NULL DEFAULT 0,
    `error_message`        TEXT DEFAULT NULL,
    `retry_count`          TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_retries`          TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `started_at`           DATETIME DEFAULT NULL,
    `completed_at`         DATETIME DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_queue_user` (`user_id`),
    KEY `idx_queue_status_priority` (`status`, `priority`, `created_at`),
    CONSTRAINT `fk_queue_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API TOKENY
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED NOT NULL,
    `name`         VARCHAR(255) NOT NULL,
    `token_hash`   VARCHAR(255) NOT NULL,
    `token_prefix` VARCHAR(10) NOT NULL,
    `permissions`  JSON DEFAULT NULL,
    `last_used_at` DATETIME DEFAULT NULL,
    `expires_at`   DATETIME DEFAULT NULL,
    `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_api_token_hash` (`token_hash`),
    KEY `idx_api_user` (`user_id`),
    CONSTRAINT `fk_api_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEBHOOKY
-- ============================================================
CREATE TABLE IF NOT EXISTS `webhooks` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NOT NULL,
    `name`        VARCHAR(255) NOT NULL,
    `url`         TEXT NOT NULL,
    `events`      JSON NOT NULL,
    `secret`      VARCHAR(255) DEFAULT NULL,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_webhooks_user` (`user_id`),
    CONSTRAINT `fk_webhooks_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `webhook_logs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `webhook_id`     INT UNSIGNED NOT NULL,
    `event_type`     VARCHAR(100) NOT NULL,
    `payload`        JSON NOT NULL,
    `response_status` SMALLINT DEFAULT NULL,
    `response_body`  TEXT DEFAULT NULL,
    `attempt_number` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_wl_webhook` (`webhook_id`),
    CONSTRAINT `fk_wl_webhook` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AUDITNÍ LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `action`        VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(100) NOT NULL,
    `resource_id`   VARCHAR(100) DEFAULT NULL,
    `old_values`    JSON DEFAULT NULL,
    `new_values`    JSON DEFAULT NULL,
    `ip_address`    VARCHAR(45) DEFAULT NULL,
    `user_agent`    TEXT DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_al_user` (`user_id`),
    KEY `idx_al_action` (`action`),
    KEY `idx_al_created` (`created_at`),
    CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
