-- Migration: Create review_photos table
-- Date: 2026-03-03
-- Reason: Move from JSON photos column to proper relational table

-- Nejdřív zkontroluj jestli reviews existuje
-- Pokud ne, vytvoř ji

CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100),
    rating TINYINT,
    comment TEXT,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    shoptet_imported BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_sku (product_sku)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teď vytvoř review_photos bez foreign key nejdřív
CREATE TABLE IF NOT EXISTS review_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    path VARCHAR(500) NOT NULL,
    thumb VARCHAR(500),
    mime_type VARCHAR(50) NOT NULL DEFAULT 'image/jpeg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Přidej foreign key samostatně (pokud ještě není)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'review_photos' 
    AND CONSTRAINT_NAME = 'review_photos_ibfk_1'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE review_photos ADD CONSTRAINT review_photos_ibfk_1 FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE',
    'SELECT "Foreign key already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
