ALTER TABLE `product_videos`
    ADD COLUMN `autoplay` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`;
