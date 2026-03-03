-- Migration: Remove Selenium-related columns
-- Date: 2026-03-03
-- Reason: Selenium robot removed, using CSV/XML export instead

ALTER TABLE users 
DROP COLUMN IF EXISTS shoptet_email,
DROP COLUMN IF EXISTS shoptet_password_encrypted,
DROP COLUMN IF EXISTS shoptet_url,
DROP COLUMN IF EXISTS shoptet_auto_import;
