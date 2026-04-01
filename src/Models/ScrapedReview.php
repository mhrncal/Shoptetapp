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

    /**
     * Batch INSERT — vloží pole recenzí najednou, vrátí počet nových
     */
    public static function insertReviews(int $userId, int $sourceId, array $reviews): int
    {
        if (empty($reviews)) return 0;
        $db    = Database::getInstance();
        $new   = 0;
        $batch = array_chunk($reviews, 100);

        foreach ($batch as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?)'));
            $params = [];
            foreach ($chunk as $r) {
                $params[] = $userId;
                $params[] = $sourceId;
                $params[] = $r['external_id'];
                $params[] = $r['author'];
                $params[] = $r['rating'];
                $params[] = $r['content'];
                $params[] = $r['date'] ?? null;
                $params[] = $r['source_lang'] ?? null;
            }
            try {
                $stmt = $db->prepare("
                    INSERT IGNORE INTO scraped_reviews
                        (user_id, source_id, external_id, author, rating, content, reviewed_at, source_lang)
                    VALUES $placeholders
                ");
                $stmt->execute($params);
                $new += $stmt->rowCount();
            } catch (\Exception $e) {
                // fallback: jednotlivě
                foreach ($chunk as $r) {
                    if (self::insertReview($userId, $sourceId, $r['external_id'], $r['author'], $r['rating'], $r['content'], $r['date'] ?? null)) {
                        $new++;
                    }
                }
            }
        }
        return $new;
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

    /** Načte překlady pro pole review ID najednou — vrací [review_id => [lang => content]] */
    public static function getTranslationsForIds(array $ids): array
    {
        if (empty($ids)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT review_id, lang, content FROM scraped_review_translations WHERE review_id IN ($placeholders) ORDER BY review_id, lang");
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['review_id']][$row['lang']] = $row['content'];
        }
        return $result;
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

    public static function getUntranslated(int $userId, array $langs, int $limit = 50): array
    {
        if (empty($langs)) return [];
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($langs), '?'));

        // Vrať recenze kde chybí alespoň jeden jazyk, včetně seznamu chybějících jazyků
        $stmt = $db->prepare("
            SELECT sr.id, sr.content, sr.source_lang,
                GROUP_CONCAT(DISTINCT srt.lang) as existing_langs
            FROM scraped_reviews sr
            LEFT JOIN scraped_review_translations srt
                ON srt.review_id = sr.id AND srt.lang IN ($placeholders)
            WHERE sr.user_id = ?
              AND sr.content IS NOT NULL AND sr.content != ''
            GROUP BY sr.id, sr.content, sr.source_lang
            HAVING COUNT(DISTINCT srt.lang) < ?
            LIMIT $limit
        ");
        $stmt->execute(array_merge($langs, [$userId], [count($langs)]));
        $rows = $stmt->fetchAll();

        // Doplň missing_langs
        foreach ($rows as &$row) {
            $existing = $row['existing_langs'] ? explode(',', $row['existing_langs']) : [];
            $row['missing_langs'] = array_values(array_diff($langs, $existing));
        }
        return $rows;
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
