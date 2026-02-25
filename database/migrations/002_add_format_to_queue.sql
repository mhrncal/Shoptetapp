-- Migrace 002: přidat feed_format a field_map do xml_processing_queue
ALTER TABLE `xml_processing_queue`
    ADD COLUMN `feed_format` ENUM('xml','csv') NOT NULL DEFAULT 'xml' AFTER `xml_feed_url`,
    ADD COLUMN `field_map`   JSON DEFAULT NULL                         AFTER `feed_format`;
-- field_map příklad pro CSV:
-- {"code":"code","name":"name","category":"defaultCategory","price":"price","pairCode":"pairCode"}
-- field_map příklad pro XML:
-- {"code":"CODE","name":"n","category":"defaultCategory","price":"PRICE_VAT"}
