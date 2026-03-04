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
        
        $this->view('feeds/index', [
            'pageTitle' => 'Importy produktů',
            'feeds' => $feeds
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
        $this->validateCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $userId = $this->user['id'];
        
        $feed = ProductFeed::findById($id, $userId);
        if (!$feed) {
            Session::flash('error', 'Feed nenalezen');
            $this->redirect('/feeds');
        }
        
        try {
            $parser = new FeedParser();
            
            // Stáhni
            error_log("Downloading feed from: {$feed['url']}");
            $filepath = $parser->downloadFeed($id, $feed['url']);
            
            if (!$filepath) {
                $lastError = error_get_last();
                $errorMsg = $lastError ? $lastError['message'] : 'Neznámá chyba při stahování';
                throw new \RuntimeException("Chyba při stahování: $errorMsg");
            }
            
            error_log("Downloaded to: $filepath");
            
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
            
            Session::flash('success', 
                "Synchronizováno! Produktů: {$stats['inserted']} nových, {$stats['updated']} aktualizováno. " .
                "Recenzí spárováno: {$matchStats['matched']}/{$matchStats['total']}"
            );
            
        } catch (\Exception $e) {
            ProductFeed::updateFetchStatus($id, false, $e->getMessage());
            Session::flash('error', 'Chyba: ' . $e->getMessage());
        }
        
        $this->redirect('/feeds');
    }
}
