-- Přidej google do platform ENUM
ALTER TABLE scrape_sources
    MODIFY COLUMN platform ENUM('heureka','trustedshops','shoptet','google') NOT NULL;
