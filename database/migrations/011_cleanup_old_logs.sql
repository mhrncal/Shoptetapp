-- Migration: smaž logy starší 3 dní (jednorázový cleanup)
DELETE FROM feed_sync_log WHERE started_at < DATE_SUB(NOW(), INTERVAL 3 DAY);
