-- Uložení URL exportu fotek ze Shoptetu per user
CREATE TABLE IF NOT EXISTS shoptet_photo_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    csv_url VARCHAR(2000) NOT NULL,
    last_imported_at DATETIME DEFAULT NULL,
    last_row_count INT DEFAULT 0,
    last_image_count INT DEFAULT 0,
    INDEX idx_spi_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Namapované fotky produktů ze Shoptetu (SKU → URLs)
CREATE TABLE IF NOT EXISTS shoptet_product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sku VARCHAR(100) NOT NULL,
    image_urls JSON NOT NULL,
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    UNIQUE KEY uniq_spi_user_sku (user_id, sku),
    INDEX idx_spim_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
