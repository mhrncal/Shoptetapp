-- Migration: Migrate JSON photos to review_photos table
-- Date: 2026-03-03

-- Migruj fotky z JSON do tabulky
INSERT INTO review_photos (review_id, path, thumb, mime_type)
SELECT 
    r.id as review_id,
    CONCAT(r.user_id, '/', JSON_UNQUOTE(JSON_EXTRACT(photo_item, '$.path'))) as path,
    CONCAT(r.user_id, '/', JSON_UNQUOTE(JSON_EXTRACT(photo_item, '$.thumb'))) as thumb,
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(photo_item, '$.mime_type')), 'image/jpeg') as mime_type
FROM reviews r
CROSS JOIN JSON_TABLE(
    COALESCE(r.photos, '[]'),
    '$[*]' COLUMNS(
        photo_item JSON PATH '$'
    )
) AS jt
WHERE r.photos IS NOT NULL 
  AND JSON_LENGTH(r.photos) > 0
  AND NOT EXISTS (
    SELECT 1 FROM review_photos rp WHERE rp.review_id = r.id
  );
