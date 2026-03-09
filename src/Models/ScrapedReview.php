<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class ScrapedReview
{
    // --- Sources ---

    public static function getSources(int $userId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM scrape_sources WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function urlExists(int $userId, string $url): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM scrape_sources WHERE user_id = ? AND url = ? LIMIT 1");
        $stmt->execute([$userId, $url]);
        return (bool)$stmt->fetch();
    }

    public static function addSource(int $userId, string $name, string $url, string $platform): int
    {
        $db = Database::getInstance();
        $db->prepare("INSERT INTO scrape_sources (user_id, name, url, platform) VALUES (?, ?, ?, ?)")
           ->execute([$userId, $name, $url, $platform]);
        return (int)$db->lastInsertId();
    }

    public static function deleteSource(int $id, int $userId): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM scrape_sources WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    }

    public static function getSource(int $id, int $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM scrape_sources WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    public static function updateLastScraped(int $sourceId): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE scrape_sources SET last_scraped_at = NOW() WHERE id = ?")->execute([$sourceId]);
    }

    // --- Reviews ---

    public static function insertReview(int $userId, int $sourceId, string $externalId, string $author, ?int $rating, string $content, ?string $date, ?string $sourceLang = null): bool
    {
        $db = Database::getInstance();
        try {
            $db->prepare("
                INSERT IGNORE INTO scraped_reviews (user_id, source_id, external_id, author, rating, content, reviewed_at, source_lang)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$userId, $sourceId, $externalId, $author, $rating, $content, $date, $sourceLang]);
            return $db->lastInsertId() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function updateSourceLang(int $reviewId, string $lang): void
    {
        $db = Database::getInstance();
        $db->prepare("UPDATE scraped_reviews SET source_lang = ? WHERE id = ? AND source_lang IS NULL")
           ->execute([$lang, $reviewId]);
    }

    public static function getReviews(int $userId, int $page = 1, int $perPage = 25, array $filters = []): array
    {
        $db     = Database::getInstance();
        $offset = ($page - 1) * $perPage;
        $where  = ['sr.user_id = ?'];
        $params = [$userId];

        if (!empty($filters['source_id'])) {
            $where[] = 'sr.source_id = ?';
            $params[] = (int)$filters['source_id'];
        }
        if (!empty($filters['lang'])) {
            // Filtr jen přeložené
        }

        $sql = "
            SELECT sr.*, ss.name AS source_name, ss.platform,
                   cs_t.content AS cs_content, cs_t.is_deepl AS cs_is_deepl
            FROM scraped_reviews sr
            JOIN scrape_sources ss ON ss.id = sr.source_id
            LEFT JOIN scraped_review_translations cs_t ON cs_t.review_id = sr.id AND cs_t.lang = 'CS'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sr.reviewed_at DESC, sr.scraped_at DESC
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countReviews(int $userId, array $filters = []): int
    {
        $db     = Database::getInstance();
        $where  = ['sr.user_id = ?'];
        $params = [$userId];
        if (!empty($filters['source_id'])) {
            $where[] = 'sr.source_id = ?';
            $params[] = (int)$filters['source_id'];
        }
        $stmt = $db->prepare("SELECT COUNT(*) FROM scraped_reviews sr WHERE " . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function getReviewWithTranslations(int $id, int $userId): ?array
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT sr.*, ss.name AS source_name, ss.platform
            FROM scraped_reviews sr
            JOIN scrape_sources ss ON ss.id = sr.source_id
            WHERE sr.id = ? AND sr.user_id = ?
        ");
        $stmt->execute([$id, $userId]);
        $review = $stmt->fetch();
        if (!$review) return null;

        $stmt2 = $db->prepare("SELECT lang, content, is_deepl, translated_at FROM scraped_review_translations WHERE review_id = ? ORDER BY lang");
        $stmt2->execute([$id]);
        $rows = $stmt2->fetchAll();
        $review['translations'] = [];
        foreach ($rows as $row) {
            $review['translations'][$row['lang']] = [
                'content'  => $row['content'],
                'is_deepl' => (bool)$row['is_deepl'],
                'translated_at' => $row['translated_at'],
            ];
        }
        return $review;
    }

    public static function getUntranslated(int $userId, array $langs): array
    {
        if (empty($langs)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($langs), '?'));
        $stmt = $db->prepare("
            SELECT sr.id, sr.content, sr.user_id
            FROM scraped_reviews sr
            WHERE sr.user_id = ?
              AND sr.content IS NOT NULL AND sr.content != ''
              AND sr.id NOT IN (
                  SELECT review_id FROM scraped_review_translations
                  WHERE lang IN ($placeholders)
                  GROUP BY review_id
                  HAVING COUNT(DISTINCT lang) = ?
              )
            LIMIT 50
        ");
        $stmt->execute(array_merge([$userId], $langs, [count($langs)]));
        return $stmt->fetchAll();
    }

    public static function saveTranslation(int $reviewId, string $lang, string $content, bool $isDeepL = true): void
    {
        $db = Database::getInstance();
        $db->prepare("
            INSERT INTO scraped_review_translations (review_id, lang, content, is_deepl)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE content = VALUES(content), translated_at = NOW(), is_deepl = VALUES(is_deepl)
        ")->execute([$reviewId, $lang, $content, $isDeepL ? 1 : 0]);
    }

    // --- User language settings ---

    public static function getUserLangs(int $userId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT lang FROM user_translation_langs WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function setUserLangs(int $userId, array $langs): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM user_translation_langs WHERE user_id = ?")->execute([$userId]);
        foreach ($langs as $lang) {
            $db->prepare("INSERT IGNORE INTO user_translation_langs (user_id, lang) VALUES (?, ?)")
               ->execute([$userId, $lang]);
        }
    }

    // --- Pending translations for all users ---
    public static function deleteReviewsBySource(int $sourceId, int $userId): void
    {
        $db = Database::getInstance();
        $db->prepare("DELETE FROM scraped_reviews WHERE source_id = ? AND user_id = ?")->execute([$sourceId, $userId]);
    }

    public static function getAllSourcesForCron(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM scrape_sources WHERE is_active = 1");
        return $stmt->fetchAll();
    }
}
