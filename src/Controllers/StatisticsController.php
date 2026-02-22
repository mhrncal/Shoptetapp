<?php
namespace ShopCode\Controllers;

use ShopCode\Core\Database;

class StatisticsController extends BaseController
{
    public function index(): void
    {
        $userId = $this->user['id'];
        $db     = Database::getInstance();

        // Přehledové počty
        $counts = [];
        foreach (['products','faqs','branches','events'] as $tbl) {
            $s = $db->prepare("SELECT COUNT(*) FROM {$tbl} WHERE user_id = ?");
            $s->execute([$userId]);
            $counts[$tbl] = (int)$s->fetchColumn();
        }

        // Počet produktů po kategorii (top 10)
        $stmt = $db->prepare("
            SELECT category, COUNT(*) AS cnt
            FROM products WHERE user_id = ? AND category IS NOT NULL
            GROUP BY category ORDER BY cnt DESC LIMIT 10
        ");
        $stmt->execute([$userId]);
        $byCategory = $stmt->fetchAll();

        // Počet produktů po značce (top 10)
        $stmt = $db->prepare("
            SELECT brand, COUNT(*) AS cnt
            FROM products WHERE user_id = ? AND brand IS NOT NULL
            GROUP BY brand ORDER BY cnt DESC LIMIT 10
        ");
        $stmt->execute([$userId]);
        $byBrand = $stmt->fetchAll();

        // Cenové rozsahy
        $stmt = $db->prepare("
            SELECT
                MIN(price) AS price_min,
                MAX(price) AS price_max,
                AVG(price) AS price_avg,
                COUNT(CASE WHEN price IS NOT NULL THEN 1 END) AS with_price
            FROM products WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $priceStats = $stmt->fetch();

        // Importy za posledních 30 dní
        $stmt = $db->prepare("
            SELECT DATE(created_at) AS day, COUNT(*) AS cnt, status
            FROM xml_imports
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY day, status ORDER BY day ASC
        ");
        $stmt->execute([$userId]);
        $importHistory = $stmt->fetchAll();

        // Poslední import
        $stmt = $db->prepare("
            SELECT * FROM xml_imports WHERE user_id = ? ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $lastImport = $stmt->fetch();

        // Aktivní webhooky a tokeny
        $stmt = $db->prepare("SELECT COUNT(*) FROM webhooks WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $counts['webhooks'] = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM api_tokens WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $counts['api_tokens'] = (int)$stmt->fetchColumn();

        $this->view('statistics/index', [
            'pageTitle'     => 'Statistiky',
            'counts'        => $counts,
            'byCategory'    => $byCategory,
            'byBrand'       => $byBrand,
            'priceStats'    => $priceStats,
            'importHistory' => $importHistory,
            'lastImport'    => $lastImport,
        ]);
    }
}
