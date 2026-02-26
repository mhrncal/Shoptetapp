-- ============================================================
-- Migration: Add Shoptet credentials to users table
-- Date: 2026-02-25
-- Description: Allows each user to have their own Shoptet login
-- ============================================================

ALTER TABLE `users`
ADD COLUMN `shoptet_email` VARCHAR(255) DEFAULT NULL COMMENT 'Email pro Shoptet admin' AFTER `xml_feed_url`,
ADD COLUMN `shoptet_password_encrypted` TEXT DEFAULT NULL COMMENT 'Šifrované heslo pro Shoptet admin' AFTER `shoptet_email`,
ADD COLUMN `shoptet_url` VARCHAR(500) DEFAULT 'https://admin.shoptet.cz' COMMENT 'URL Shoptet admin' AFTER `shoptet_password_encrypted`,
ADD COLUMN `shoptet_auto_import` TINYINT(1) DEFAULT 1 COMMENT 'Povolit automatický import recenzí do Shoptetu' AFTER `shoptet_url`;

-- Index pro rychlé hledání uživatelů s povoleným auto-importem
ALTER TABLE `users`
ADD KEY `idx_users_auto_import` (`shoptet_auto_import`);

-- ============================================================
-- Poznámky:
-- ============================================================
-- 
-- shoptet_email: Email pro přihlášení do Shoptet adminu
-- shoptet_password_encrypted: Heslo šifrované pomocí openssl_encrypt()
-- shoptet_url: URL Shoptet admin (výchozí: https://admin.shoptet.cz)
-- shoptet_auto_import: Povolit/zakázat automatický import (1/0)
--
-- Hesla budou šifrována pomocí AES-256-CBC s klíčem z config.php
-- Každý uživatel má své vlastní přihlašovací údaje
-- CRON worker dešifruje heslo při použití
