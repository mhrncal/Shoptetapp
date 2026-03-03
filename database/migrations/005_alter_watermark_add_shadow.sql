-- Migration: Add missing columns to watermark_settings
-- Date: 2026-03-03

-- Přidej shadow_enabled
ALTER TABLE watermark_settings 
ADD COLUMN IF NOT EXISTS shadow_enabled BOOLEAN NOT NULL DEFAULT TRUE AFTER padding;

-- Přidej timestamps
ALTER TABLE watermark_settings 
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER enabled;

ALTER TABLE watermark_settings 
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
