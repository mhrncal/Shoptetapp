-- Zdroje scrapování
CREATE TABLE IF NOT EXISTS scrape_sources (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    url         VARCHAR(1000) NOT NULL,
    platform    ENUM('heureka','trustedshops','shoptet') NOT NULL,
    is_active   TINYINT(1) DEFAULT 1,
    last_scraped_at DATETIME DEFAULT NULL,
    created_at  DATETIME DEFAULT NOW(),
    INDEX idx_ss_user (user_id)
);

-- Scrapované recenze
CREATE TABLE IF NOT EXISTS scraped_reviews (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    source_id   INT NOT NULL,
    external_id VARCHAR(255) DEFAULT NULL,
    author      VARCHAR(255) DEFAULT NULL,
    rating      TINYINT DEFAULT NULL,
    content     TEXT,
    reviewed_at DATE DEFAULT NULL,
    scraped_at  DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_source_external (source_id, external_id),
    INDEX idx_sr_user (user_id),
    INDEX idx_sr_source (source_id)
);

-- Překlady recenzí
CREATE TABLE IF NOT EXISTS scraped_review_translations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    review_id   INT NOT NULL,
    lang        VARCHAR(5) NOT NULL,
    content     TEXT,
    translated_at DATETIME DEFAULT NOW(),
    UNIQUE KEY uniq_rt_review_lang (review_id, lang),
    INDEX idx_srt_review (review_id)
);

-- Jazyky které chce uživatel překládat
CREATE TABLE IF NOT EXISTS user_translation_langs (
    user_id     INT NOT NULL,
    lang        VARCHAR(5) NOT NULL,
    PRIMARY KEY (user_id, lang)
);
