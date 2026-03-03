-- Migration: Add shadow_enabled column if missing
-- Date: 2026-03-03

-- Přidej shadow_enabled pokud neexistuje
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'watermark_settings' 
    AND COLUMN_NAME = 'shadow_enabled'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE watermark_settings ADD COLUMN shadow_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER padding',
    'SELECT "Column shadow_enabled already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
