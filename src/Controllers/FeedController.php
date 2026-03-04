<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Database};
use ShopCode\Models\ProductFeed;
use ShopCode\Services\{FeedParser, ReviewMatcher};

class FeedController extends BaseController
{
    public function index(): void
    {
        $userId = $this->user['id'];
        $feeds = ProductFeed::allForUser($userId);
        
        // Načti timeline (posledních 20 synchronizací)
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT l.*, f.name as feed_name
            FROM feed_sync_log l
            JOIN product_feeds f ON f.id = l.feed_id
            WHERE f.user_id = ?
            ORDER BY l.started_at DESC
            LIMIT 20
        ');
        $stmt->execute([$userId]);
        $timeline = $stmt->fetchAll();
        
        $this->view('feeds/index', [
            'pageTitle' => 'Importy produktů',
            'feeds' => $feeds,
            'timeline' => $timeline
        ]);
    }
    
    public function create(): void
    {
        $this->view('feeds/create', [
            'pageTitle' => 'Nový import'
        ]);
    }
    
    public function store(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'url' => trim($_POST['url'] ?? ''),
            'type' => $_POST['type'] ?? 'csv_simple',
            'delimiter' => $_POST['delimiter'] ?? ';',
            'encoding' => $_POST['encoding'] ?? 'windows-1250',
            'enabled' => isset($_POST['enabled'])
        ];
        
        if (empty($data['name']) || empty($data['url'])) {
            Session::flash('error', 'Vyplňte název a URL');
            $this->redirect('/feeds/create');
        }
        
        $id = ProductFeed::create($userId, $data);
        Session::flash('success', 'Feed byl vytvořen');
        $this->redirect('/feeds');
    }
    
    public function delete(): void
    {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $userId = $this->user['id'];
        
        if (ProductFeed::delete($id, $userId)) {
            Session::flash('success', 'Feed byl smazán');
        }
        
        $this->redirect('/feeds');
    }
    
    /**
     * Manuální spuštění synchronizace
     */
    public function sync(): void
    {
        // Zvyš limity pro velké CSV
        set_time_limit(600); // 10 minut
        ini_set('memory_limit', '256M');
        
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $userId = $this->user['id'];
        
        $feed = ProductFeed::findById($id, $userId);
        if (!$feed) {
            Session::flash('error', 'Feed nenalezen');
            $this->redirect('/feeds');
        }
        
        $db = Database::getInstance();
        $startTime = microtime(true);
        
        // Vytvoř log entry
        $logStmt = $db->prepare('
            INSERT INTO feed_sync_log (feed_id, started_at, status)
            VALUES (?, NOW(), "running")
        ');
        $logStmt->execute([$id]);
        $logId = (int)$db->lastInsertId();
        
        try {
            $parser = new FeedParser();
            
            // Stáhni
            $filepath = $parser->downloadFeed($id, $feed['url']);
            
            if (!$filepath) {
                throw new \RuntimeException("Chyba při stahování");
            }
            
            // Parsuj
            $stats = $parser->parseAndStore($id, $filepath, [
                'user_id' => $userId,
                'type' => $feed['type'],
                'delimiter' => $feed['delimiter'],
                'encoding' => $feed['encoding']
            ]);
            
            ProductFeed::updateFetchStatus($id, true);
            
            // Spáruj
            $matchStats = ReviewMatcher::matchReviews($userId);
            
            // Vygeneruj exporty
            $reviews = ReviewMatcher::getExportableReviews($userId);
            if (!empty($reviews)) {
                $xml = ExportGenerator::generateXML($reviews);
                ExportGenerator::saveToFile($xml, "user_{$userId}_reviews_with_products.xml");
                
                $csv = ExportGenerator::generateCSV($reviews);
                ExportGenerator::saveToFile($csv, "user_{$userId}_reviews_with_products.csv");
            }
            
            // Aktualizuj log - SUCCESS
            $duration = round(microtime(true) - $startTime);
            $updateStmt = $db->prepare('
                UPDATE feed_sync_log 
                SET finished_at = NOW(),
                    status = "success",
                    products_inserted = ?,
                    products_updated = ?,
                    products_total = ?,
                    reviews_matched = ?,
                    reviews_total = ?,
                    duration_seconds = ?
                WHERE id = ?
            ');
            $updateStmt->execute([
                $stats['inserted'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['total'] ?? 0,
                $matchStats['matched'] ?? 0,
                $matchStats['total'] ?? 0,
                $duration,
                $logId
            ]);
            
            Session::flash('success', 
                "Synchronizováno! Produktů: {$stats['inserted']} nových, {$stats['updated']} aktualizováno. " .
                "Recenzí spárováno: {$matchStats['matched']}/{$matchStats['total']}"
            );
            
        } catch (\Exception $e) {
            // Aktualizuj log - ERROR
            $duration = round(microtime(true) - $startTime);
            $updateStmt = $db->prepare('
                UPDATE feed_sync_log 
                SET finished_at = NOW(),
                    status = "error",
                    error_message = ?,
                    duration_seconds = ?
                WHERE id = ?
            ');
            $updateStmt->execute([$e->getMessage(), $duration, $logId]);
            
            ProductFeed::updateFetchStatus($id, false, $e->getMessage());
            Session::flash('error', 'Chyba: ' . $e->getMessage());
        }
        
        $this->redirect('/feeds');
    }

    /**
     * Spusť sync na pozadí (aby nebylo 504)
     */
    public function syncBackground(): void
    {
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $userId = $this->user['id'];
        
        $feed = ProductFeed::findById($id, $userId);
        if (!$feed) {
            Session::flash('error', 'Feed nenalezen');
            $this->redirect('/feeds');
        }
        
        // Spusť na pozadí
        $cmd = sprintf(
            'php %s/cron/feed_sync_single.php %d > /dev/null 2>&1 &',
            ROOT,
            $id
        );
        
        exec($cmd);
        
        Session::flash('info', 'Synchronizace byla spuštěna na pozadí. Obnovte stránku za chvíli.');
        $this->redirect('/feeds');
    }
}
