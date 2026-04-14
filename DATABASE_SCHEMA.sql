-- ShopCode Database Schema
-- Updated: 2026-04-01
-- Reflects all migrations applied via DiagController

-- ============================================================
-- 1. USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    shop_name VARCHAR(255),
    shop_url VARCHAR(255),
    xml_feed_url VARCHAR(255),
    role ENUM('superadmin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    last_login_at DATETIME,
    login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME,
    deepl_api_key VARCHAR(255) DEFAULT NULL,
    google_places_api_key VARCHAR(255) DEFAULT NULL,
    outscraper_api_key VARCHAR(255) DEFAULT NULL,
    last_ui_sync_at DATETIME DEFAULT NULL,
    last_ui_translate_at DATETIME DEFAULT NULL,
    photo_warning_sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. REVIEWS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    shoptet_id VARCHAR(50) DEFAULT NULL,
    sku VARCHAR(50) DEFAULT NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(255) NOT NULL,
    rating TINYINT DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    photos JSON DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT DEFAULT NULL,
    imported TINYINT(1) NOT NULL DEFAULT 0,
    imported_at DATETIME DEFAULT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_sku (sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. REVIEW_PHOTOS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS review_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    path VARCHAR(500) NOT NULL,
    thumb VARCHAR(500),
    mime_type VARCHAR(50) NOT NULL,
    shoptet_url VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. WATERMARK_SETTINGS TABLE
-- ============================================================
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

-- ============================================================
-- 5. PASSWORD_RESETS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. AUDIT_LOG TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MODULES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(100) DEFAULT NULL,
    version VARCHAR(20) DEFAULT '1.0.0',
    is_system_module TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. USER_MODULES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS user_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    activated_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_user_module (user_id, module_id),
    INDEX idx_um_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. SCRAPE_SOURCES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS scrape_sources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    platform ENUM('heureka','trustedshops','shoptet','google','outscraper') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_scraped_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NOW(),
    INDEX idx_ss_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. SCRAPED_REVIEWS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS scraped_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    source_id INT NOT NULL,
    external_id VARCHAR(255) DEFAULT NULL,
    author VARCHAR(255) DEFAULT NULL,
    rating TINYINT DEFAULT NULL,
    content TEXT,
    source_lang VARCHAR(5) DEFAULT NULL,
    reviewed_at DATE DEFAULT NULL,
    scraped_at DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_source_external (source_id, external_id),
    INDEX idx_sr_user (user_id),
    INDEX idx_sr_source (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. SCRAPED_REVIEW_TRANSLATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS scraped_review_translations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    content TEXT,
    is_deepl TINYINT(1) DEFAULT 1,
    translated_at DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_rt_review_lang (review_id, lang),
    INDEX idx_srt_review (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. USER_TRANSLATION_LANGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS user_translation_langs (
    user_id INT NOT NULL,
    lang VARCHAR(5) NOT NULL,
    PRIMARY KEY (user_id, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. PHOTO_EXPORT_LOG TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS photo_export_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exported_at DATETIME NOT NULL,
    photo_count INT DEFAULT 0,
    INDEX idx_pel_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES
    ('reviews',         'Fotorecenze',        'Sběr a správa fotorecenzí zákazníků',                     'camera', '1.0.0', 1),
    ('scraped_reviews', 'Scrapované recenze', 'Automatický sběr recenzí z Heureka, Trusted Shops a Shoptet', 'search', '1.0.0', 1)
ON DUPLICATE KEY UPDATE label=VALUES(label);

INSERT INTO user_modules (user_id, module_id, status, activated_at)
    SELECT u.id, m.id, 'active', NOW() FROM users u CROSS JOIN modules m WHERE u.role='superadmin'
ON DUPLICATE KEY UPDATE status='active';

INSERT INTO watermark_settings (user_id, text, position)
    SELECT id, 'Zákaznická fotka', 'BR' FROM users WHERE role IN ('user', 'superadmin')
ON DUPLICATE KEY UPDATE user_id = user_id;
