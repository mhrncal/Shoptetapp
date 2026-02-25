-- Migrace 001: přidat sku (CODE z XML) do products a product_variants
-- Spusť: mysql -u USER -p DB_NAME < database/migrations/001_add_sku_to_products.sql

ALTER TABLE `products`
    ADD COLUMN IF NOT EXISTS `sku` VARCHAR(255) DEFAULT NULL AFTER `shoptet_id`,
    ADD KEY IF NOT EXISTS `idx_products_sku` (`user_id`, `sku`);

ALTER TABLE `product_variants`
    ADD COLUMN IF NOT EXISTS `sku` VARCHAR(255) DEFAULT NULL AFTER `shoptet_variant_id`,
    ADD KEY IF NOT EXISTS `idx_variants_sku` (`user_id`, `sku`);
