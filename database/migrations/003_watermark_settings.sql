-- Migration: Watermark settings
-- Date: 2026-03-03
-- Purpose: Configuration for automatic photo watermarks

CREATE TABLE IF NOT EXISTS watermark_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    text VARCHAR(255) NOT NULL DEFAULT 'Zákaznická fotka',
    font VARCHAR(100) NOT NULL DEFAULT 'Arial',
    position ENUM('TL','TC','TR','ML','MC','MR','BL','BC','BR') NOT NULL DEFAULT 'BR',
    color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
    size ENUM('small','medium','large') NOT NULL DEFAULT 'medium',
    opacity INT NOT NULL DEFAULT 80,
    padding INT NOT NULL DEFAULT 20,
    shadow_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings for existing users
INSERT INTO watermark_settings (user_id, text, position)
SELECT id, 'Zákaznická fotka', 'BR'
FROM users
WHERE role IN ('user', 'superadmin')
ON DUPLICATE KEY UPDATE user_id = user_id;
