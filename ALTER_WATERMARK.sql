-- Přidání shadow_enabled do watermark_settings
-- Spusť přímo v MySQL/phpMyAdmin

ALTER TABLE watermark_settings 
ADD COLUMN shadow_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER padding;

ALTER TABLE watermark_settings 
ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER enabled;

ALTER TABLE watermark_settings 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
