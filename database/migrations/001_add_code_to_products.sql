-- Migrace 001: přidat code (CODE z XML) do products a product_variants
-- Spusť: mysql -u USER -p DB_NAME < database/migrations/001_add_code_to_products.sql
ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `code` VARCHAR(255) DEFAULT NULL AFTER `shoptet_id`,
    ADD KEY IF NOT EXISTS `idx_products_code` (`user_id`, `code`);

ALTER TABLE `product_variants`
    ADD COLUMN IF NOT EXISTS `code` VARCHAR(255) DEFAULT NULL AFTER `shoptet_variant_id`,
    ADD KEY IF NOT EXISTS `idx_variants_code` (`user_id`, `code`);
