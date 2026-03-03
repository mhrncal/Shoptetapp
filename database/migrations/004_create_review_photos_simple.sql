-- Migration: Create review_photos table (SIMPLE VERSION)
-- No foreign keys to avoid constraint issues

CREATE TABLE IF NOT EXISTS review_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    path VARCHAR(500) NOT NULL,
    thumb VARCHAR(500),
    mime_type VARCHAR(50) NOT NULL DEFAULT 'image/jpeg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
