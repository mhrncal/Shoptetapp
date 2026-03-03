-- Migration: Create review_photos table
-- Date: 2026-03-03
-- Reason: Move from JSON photos column to proper relational table

CREATE TABLE IF NOT EXISTS review_photos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    review_id INT NOT NULL,
    path VARCHAR(500) NOT NULL,
    thumb VARCHAR(500),
    mime_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
    INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing photos from JSON to table (if any)
-- This is safe to run multiple times
INSERT IGNORE INTO review_photos (review_id, path, thumb, mime_type)
SELECT 
    r.id,
    JSON_UNQUOTE(JSON_EXTRACT(photo, '$.path')) as path,
    JSON_UNQUOTE(JSON_EXTRACT(photo, '$.thumb')) as thumb,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(photo, '$.mime_type')), 'image/jpeg') as mime_type
FROM reviews r
CROSS JOIN JSON_TABLE(
    COALESCE(r.photos, '[]'),
    '$[*]' COLUMNS(
        photo JSON PATH '$'
    )
) jt
WHERE r.photos IS NOT NULL AND JSON_LENGTH(r.photos) > 0;
