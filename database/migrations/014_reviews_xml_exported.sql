-- Sledování XML exportu pro jednotlivé recenze
ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS xml_exported_at DATETIME DEFAULT NULL COMMENT 'Kdy byla recenze exportována do XML';

ALTER TABLE review_photos
    ADD COLUMN IF NOT EXISTS shoptet_url VARCHAR(1000) DEFAULT NULL COMMENT 'URL fotky na Shoptet CDN po importu';
