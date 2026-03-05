-- Migration: přidej log_text do feed_sync_log
-- Date: 2026-03-05

ALTER TABLE `feed_sync_log`
    ADD COLUMN `log_text` MEDIUMTEXT DEFAULT NULL AFTER `error_message`;
