-- Migration: Přidej pairCode do products pro varianty
-- Date: 2026-03-04

ALTER TABLE products 
ADD COLUMN `pair_code` VARCHAR(100) DEFAULT NULL COMMENT 'PairCode pro varianty produktu' 
AFTER `code`;

CREATE INDEX idx_products_pair_code ON products(pair_code);
