<?php

namespace ShopCode\Services;

use ShopCode\Core\Database;

class ReviewMatcher
{
    /**
     * Spáruj recenze s produkty podle SKU
     * 
     * @return array ['matched' => int, 'total' => int]
     */
    public static function matchReviews(int $userId): array
    {
        $db = Database::getInstance();
        $stats = ['matched' => 0, 'total' => 0];
        
        // Najdi všechny schválené recenze bez spárování
        $stmt = $db->prepare('
            SELECT id, sku 
            FROM reviews 
            WHERE user_id = ? 
              AND status = "approved"
              AND sku IS NOT NULL
        ');
        $stmt->execute([$userId]);
        $reviews = $stmt->fetchAll();
        
        $stats['total'] = count($reviews);
        
        foreach ($reviews as $review) {
            // Zkus najít produkt podle code NEBO pair_code
            $stmt = $db->prepare('
                SELECT id, code, pair_code, name, images 
                FROM products 
                WHERE user_id = ? 
                  AND (code = ? OR pair_code = ?)
                LIMIT 1
            ');
            
            $stmt->execute([$userId, $review['sku'], $review['sku']]);
            $product = $stmt->fetch();
            
            if ($product) {
                // Spárováno! Aktualizuj review s product_id
                $updateStmt = $db->prepare('
                    UPDATE reviews 
                    SET product_id = ? 
                    WHERE id = ?
                ');
                $updateStmt->execute([$product['id'], $review['id']]);
                $stats['matched']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Vrať seznam recenzí připravených k exportu
     */
    public static function getExportableReviews(int $userId): array
    {
        $db = Database::getInstance();
        
        $stmt = $db->prepare('
            SELECT 
                r.id,
                r.sku,
                r.author_name,
                r.author_email,
                r.rating,
                r.comment,
                r.created_at,
                p.code,
                p.pair_code,
                p.name as product_name,
                p.images as product_images
            FROM reviews r
            LEFT JOIN products p ON p.id = r.product_id
            WHERE r.user_id = ?
              AND r.status = "approved"
              AND p.id IS NOT NULL
            ORDER BY r.created_at DESC
        ');
        
        $stmt->execute([$userId]);
        $reviews = $stmt->fetchAll();
        
        // Přidej fotky z review_photos
        foreach ($reviews as &$review) {
            $photoStmt = $db->prepare('
                SELECT path FROM review_photos 
                WHERE review_id = ?
            ');
            $photoStmt->execute([$review['id']]);
            $photos = $photoStmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Překonvertuj na absolutní URL
            $review['review_photos'] = array_map(function($path) {
                $appUrl = defined('APP_URL') && APP_URL ? APP_URL : '';
                return $appUrl . '/public/uploads/' . $path;
            }, $photos);
            
            // Dekóduj product images
            $review['product_images'] = json_decode($review['product_images'], true) ?? [];
        }
        
        return $reviews;
    }
}
