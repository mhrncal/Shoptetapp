<?php

namespace ShopCode\Controllers;

use ShopCode\Models\Review;
use ShopCode\Services\{CsvGenerator, XmlFeedGenerator};

class ReviewExportController extends BaseController
{
    /**
     * Export schválených recenzí do CSV (okamžitě)
     */
    public function exportCsv(): void
    {
        $userId = $this->user['id'];
        
        // Najdi schválené recenze
        $reviews = Review::getPendingImport($userId);
        
        if (empty($reviews)) {
            $this->json(['success' => false, 'error' => 'Žádné schválené recenze k exportu.'], 404);
        }
        
        try {
            $csvGen = new CsvGenerator();
            $csvPath = $csvGen->generate($reviews);
            
            // Vrať CSV jako download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="shoptet-fotky-' . date('Y-m-d-His') . '.csv"');
            header('Content-Length: ' . filesize($csvPath));
            
            readfile($csvPath);
            
            // Cleanup
            $csvGen->cleanup($csvPath);
            
            exit;
            
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Export schválených recenzí do XML (okamžitě)
     */
    public function exportXml(): void
    {
        $userId = $this->user['id'];
        
        // Najdi schválené recenze
        $reviews = Review::getPendingImport($userId);
        
        if (empty($reviews)) {
            $this->json(['success' => false, 'error' => 'Žádné schválené recenze k exportu.'], 404);
        }
        
        try {
            $xmlGen = new XmlFeedGenerator();
            $xmlPath = $xmlGen->generate($userId, $reviews);
            
            // Vrať XML jako download
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="shoptet-fotky-' . date('Y-m-d-His') . '.xml"');
            header('Content-Length: ' . filesize($xmlPath));
            
            readfile($xmlPath);
            
            // Cleanup
            $xmlGen->cleanup($xmlPath);
            
            exit;
            
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Označit exportované recenze jako importované
     */
    public function markAsImported(): void
    {
        $this->validateCsrf();
        
        $userId = $this->user['id'];
        $reviewIds = $this->request->post('review_ids', []);
        
        if (empty($reviewIds)) {
            $this->json(['success' => false, 'error' => 'Žádné recenze k označení.'], 400);
        }
        
        try {
            $count = Review::markImported($reviewIds, $userId);
            $this->json(['success' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
