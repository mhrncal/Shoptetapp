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
            'name' => trim($this->request->post('name', '')),
            'url' => trim($this->request->post('url', '')),
            'type' => $this->request->post('type', 'csv_simple'),
            'delimiter' => $this->request->post('delimiter', ';'),
            'encoding' => $this->request->post('encoding', 'windows-1250'),
            'enabled' => ($this->request->post('enabled') !== null)
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
        $id = (int)$this->request->post('id', 0);
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
        $id = (int)$this->request->post('id', 0);
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
        $id = (int)$this->request->post('id', 0);
        $userId = $this->user['id'];
        
        $feed = ProductFeed::findById($id, $userId);
        if (!$feed) {
            Session::flash('error', 'Feed nenalezen');
            $this->redirect('/feeds');
        }
        
        // Vytvoř log entry před spuštěním
        $db = Database::getInstance();
        $logStmt = $db->prepare('
            INSERT INTO feed_sync_log (feed_id, started_at, status)
            VALUES (?, NOW(), "running")
        ');
        $logStmt->execute([$id]);
        
        // Spusť na pozadí - log do souboru pro debug
        $logFile = ROOT . '/public/logs/feed_sync_' . $id . '_' . date('Y-m-d_H-i-s') . '.log';
        $cmd = sprintf(
            'php %s/cron/feed_sync_single.php %d > %s 2>&1 &',
            ROOT,
            $id,
            $logFile
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Session::flash('error', 'Nepodařilo se spustit synchronizaci. Zkuste to znovu.');
        } else {
            Session::flash('info', 'Synchronizace byla spuštěna na pozadí. Stránka se automaticky obnoví.');
        }
        
        $this->redirect('/feeds');
    }

    /**
     * Odblokuj všechny zamrzlé synchronizace
     */
    public function unlockAll(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        
        $db = Database::getInstance();
        
        // Najdi všechny running syncs pro tohoto usera
        $stmt = $db->prepare('
            SELECT l.id 
            FROM feed_sync_log l
            JOIN product_feeds f ON f.id = l.feed_id
            WHERE f.user_id = ? AND l.status = "running"
        ');
        $stmt->execute([$userId]);
        $running = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        if (empty($running)) {
            Session::flash('info', 'Žádné zamrzlé synchronizace nenalezeny');
            $this->redirect('/feeds');
        }
        
        // Označ jako error
        $stmt = $db->prepare('
            UPDATE feed_sync_log 
            SET status = "error",
                error_message = "Odblokováno uživatelem",
                finished_at = NOW(),
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
            WHERE id IN (' . implode(',', array_fill(0, count($running), '?')) . ')
        ');
        $stmt->execute($running);
        
        $count = count($running);
        Session::flash('success', "Odblokováno $count zamrzlých synchronizací");
        
        $this->redirect('/feeds');
    }

    /**
     * AJAX endpoint pro zjištění progress synchronizace
     */
    public function syncProgress(): void
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        $feedId = (int)($this->request->get('feed_id', 0));
        if (!$feedId) {
            echo json_encode(['error' => 'Missing feed_id']);
            exit;
        }

        // Přečti progress JSON soubor (zapisuje feed_sync_single.php)
        $progressFile = ROOT . '/tmp/feed_progress_' . $feedId . '.json';

        if (file_exists($progressFile)) {
            $data = @file_get_contents($progressFile);
            $progress = $data ? json_decode($data, true) : null;

            if ($progress) {
                $phase = $progress['phase'] ?? 'running';

                // Ikona a stav pro UI
                $icons = [
                    'start'    => '🚀',
                    'download' => '⬇️',
                    'parse'    => '⚙️',
                    'match'    => '🔗',
                    'export'   => '📄',
                    'done'     => '✅',
                    'error'    => '❌',
                ];
                $icon = $icons[$phase] ?? '⏳';

                echo json_encode([
                    'status'    => $phase === 'done' ? 'done' : ($phase === 'error' ? 'error' : 'running'),
                    'phase'     => $phase,
                    'message'   => $icon . ' ' . ($progress['message'] ?? 'Synchronizuje se...'),
                    'percent'   => $progress['percent'] ?? null,
                    'elapsed'   => $progress['elapsed'] ?? 0,
                    'details'   => array_filter([
                        'downloaded_mb' => $progress['downloaded_mb'] ?? null,
                        'total_mb'      => $progress['total_mb'] ?? null,
                        'done'          => $progress['done'] ?? null,
                        'total'         => $progress['total'] ?? null,
                        'inserted'      => $progress['inserted'] ?? null,
                        'updated'       => $progress['updated'] ?? null,
                        'matched'       => $progress['matched'] ?? null,
                    ]),
                ]);
                exit;
            }
        }

        // Progress soubor neexistuje — zkontroluj DB jestli sync běží
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT status, started_at, TIMESTAMPDIFF(SECOND, started_at, NOW()) as elapsed
            FROM feed_sync_log
            WHERE feed_id = ?
            ORDER BY started_at DESC
            LIMIT 1
        ');
        $stmt->execute([$feedId]);
        $log = $stmt->fetch();

        if (!$log || $log['status'] !== 'running') {
            echo json_encode(['status' => 'not_running']);
        } else {
            echo json_encode([
                'status'  => 'running',
                'phase'   => 'start',
                'message' => '🚀 Spouští se... (' . $log['elapsed'] . 's)',
                'elapsed' => (int)$log['elapsed'],
            ]);
        }
        exit;
    }
}
