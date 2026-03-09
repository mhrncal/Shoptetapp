-- Přidej outscraper API klíč do users
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS outscraper_api_key VARCHAR(255) DEFAULT NULL;

-- Přidej outscraper do platform ENUM
ALTER TABLE scrape_sources
    MODIFY COLUMN platform ENUM('heureka','trustedshops','shoptet','google','outscraper') NOT NULL;
