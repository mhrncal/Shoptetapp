ALTER TABLE `product_videos`
    MODIFY COLUMN `url` VARCHAR(500) NULL COMMENT 'YouTube/Vimeo URL nebo NULL pokud je nahráno lokálně',
    ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL AFTER `url`,
    ADD COLUMN `autoplay`  TINYINT(1)  NOT NULL DEFAULT 0 AFTER `file_path`;
