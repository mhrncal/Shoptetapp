<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Database, Session, Response, View};
use ShopCode\Models\{Review, Product};
use ShopCode\Services\{ImageHandler, CsvGenerator, XmlFeedGenerator};

class ReviewController extends BaseController
{
    public function index(): void
    {
        $userId  = $this->user['id'];
        $status  = $this->request->get('status', '');
        $search  = $this->request->get('search', '');
        $page    = max(1, (int)$this->request->get('page', 1));
        $filters = array_filter(['status' => $status, 'search' => $search]);

        $reviews = Review::allForUser($userId, $filters, $page, 25);
        $total   = Review::count($userId, $filters);
        $counts  = Review::countByStatus($userId);
        
        // XML feed URL — vždy stejný název
        $appUrl     = defined('APP_URL') ? APP_URL : '';
        $feedPath   = ROOT . '/public/feeds/user_' . $userId . '_reviews.xml';
        $xmlFeedUrl = $appUrl . '/public/feeds/user_' . $userId . '_reviews.xml';
        $xmlFeedExists = file_exists($feedPath);

        $expiry = self::photoExpiryStatus($userId);

        $this->view('reviews/index', [
            'pageTitle' => 'Fotorecenze',
            'expiry'    => $expiry,
            'reviews'   => $reviews,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => 25,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
            'xmlFeedUrl'    => $xmlFeedUrl,
            'xmlFeedExists' => $xmlFeedExists ?? false,
            // csrfToken je automaticky dostupný z View::render()
        ]);
    }

    public function detail(): void
    {
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $review = Review::findById($id, $userId);
        if (!$review) Response::notFound();

        $this->view('reviews/detail', [
            'pageTitle' => 'Recenze #' . $id,
            'review'    => $review,
        ]);
    }

    public function approve(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $note   = trim($this->request->post('admin_note', ''));

        $review = Review::findById($id, $userId);
        if (!$review) Response::notFound();

        Review::setStatus($id, $userId, 'approved', $note ?: null);
        Session::flash('success', 'Recenze byla schválena.');
        $this->redirect('/reviews/' . $id);
    }

    public function reject(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $note   = trim($this->request->post('admin_note', ''));

        $review = Review::findById($id, $userId);
        if (!$review) Response::notFound();

        Review::setStatus($id, $userId, 'rejected', $note ?: null);
        Session::flash('success', 'Recenze byla zamítnuta.');
        $this->redirect('/reviews/' . $id);
    }

    public function bulkAction(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $action = $this->request->post('bulk_action', '');
        $ids    = array_filter(array_map('intval', (array)$this->request->post('ids', [])));

        if (empty($ids)) {
            Session::flash('error', 'Nevybrali jste žádné recenze.');
            $this->redirect('/reviews');
        }

        switch ($action) {
            case 'approve':
                $count = Review::bulkSetStatus($ids, $userId, 'approved');
                Session::flash('success', "Schváleno {$count} recenzí.");
                break;

            case 'reject':
                $count = Review::bulkSetStatus($ids, $userId, 'rejected');
                Session::flash('success', "Zamítnuto {$count} recenzí.");
                break;

            case 'mark_imported':
                $count = Review::markImported($ids, $userId);
                Session::flash('success', "Označeno jako importováno: {$count} recenzí.");
                break;
                
            case 'unmark_imported':
                $count = Review::unmarkImported($ids, $userId);
                Session::flash('success', "Odznačeno: {$count} recenzí.");
                break;

            case 'download_zip':
                $this->downloadZip($ids);
                return;
                
            case 'delete':
                $count = Review::bulkDelete($ids, $userId);
                Session::flash('success', "Smazáno {$count} recenzí.");
                break;

            default:
                Session::flash('error', 'Neznámá akce.');
        }

        $this->redirect('/reviews');
    }


    public function exportCsv(): void
    {
        $userId  = $this->user['id'];
        $reviews = Review::getPendingImport($userId);

        if (empty($reviews)) {
            Session::flash('error', 'Žádné schválené neimportované recenze pro export.');
            $this->redirect('/reviews');
        }

        try {
            $gen      = new CsvGenerator();
            $csvPath  = $gen->generate($reviews);

            // Stažení souboru
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="shoptet_photos_' . date('Ymd_His') . '.csv"');
            header('Content-Length: ' . filesize($csvPath));
            readfile($csvPath);
            $gen->cleanup($csvPath);
            exit;

        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect('/reviews');
        }
    }

    /**
     * Export schválených recenzí do XML — uloží trvalý feed + nabídne stažení
     */
    public function exportXml(): void
    {
        $userId  = $this->user['id'];
        $reviews = Review::allApproved($userId);

        try {
            $gen     = new XmlFeedGenerator();
            error_log("[exportXml] userId=$userId reviews=" . count($reviews ?? []));
            // Vždy přegeneruj trvalý feed se stejným názvem
            $gen->generatePermanentFeed($userId, $reviews ?: []);
            Review::markAsXmlExported($userId);


            Session::flash('success', 'XML feed byl vygenerován. Použijte URL níže pro import do Shoptetu.');
            $this->redirect('/reviews');

        } catch (\Throwable $e) {
            error_log('[exportXml] ERR: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            Session::flash('error', $e->getMessage());
            $this->redirect('/reviews');
        }
    }

    /**
     * Změna stavu recenze (approve/reject jsou obousměrné)
     */
    public function changeStatus(): void
    {
        $this->validateCsrf();
        $id = (int)$this->request->post('id', 0);
        $newStatus = $this->request->post('status', '');
        
        if (!in_array($newStatus, ['approved', 'rejected'])) {
            Session::flash('error', 'Neplatný stav');
            $this->redirect('/reviews');
        }
        
        $db = Database::getInstance();
        
        // Admin note (pokud je zadaná)
        $adminNote = $this->request->post('admin_note', null);
        
        if ($adminNote) {
            $stmt = $db->prepare('UPDATE reviews SET status = ?, admin_note = ? WHERE id = ?');
            $result = $stmt->execute([$newStatus, $adminNote, $id]);
        } else {
            $stmt = $db->prepare('UPDATE reviews SET status = ? WHERE id = ?');
            $result = $stmt->execute([$newStatus, $id]);
        }
        
        if ($result) {
            $label = $newStatus === 'approved' ? 'schválena' : 'zamítnuta';
            Session::flash('success', "Recenze byla {$label}");
        } else {
            Session::flash('error', 'Chyba při změně stavu');
        }
        
        $this->redirect('/reviews');
    }

    /**
     * Hromadné stažení fotek jako ZIP
     */
    public function downloadZip(?array $ids = null): void
    {
        if ($ids === null) {
            $ids = array_filter(array_map('intval', (array)$this->request->post('ids', [])));
        }
        
        if (empty($ids)) {
            Session::flash('error', 'Nevybrali jste žádné recenze');
            $this->redirect('/reviews');
        }
        
        $db = Database::getInstance();
        
        // Načti všechny fotky z vybraných recenzí
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("
            SELECT rp.*, r.author_name, r.sku
            FROM review_photos rp
            JOIN reviews r ON r.id = rp.review_id
            WHERE r.id IN ({$placeholders})
            ORDER BY r.id, rp.id
        ");
        $stmt->execute($ids);
        $photos = $stmt->fetchAll();
        
        if (empty($photos)) {
            Session::flash('error', 'Žádné fotky k stažení');
            $this->redirect('/reviews');
        }
        
        // Vytvoř ZIP
        $zipFilename = 'fotorecenze_' . date('Y-m-d_His') . '.zip';
        $zipPath = ROOT . '/tmp/' . $zipFilename;
        
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Session::flash('error', 'Chyba při vytváření ZIP');
            $this->redirect('/reviews');
        }
        
        $counter = 1;
        foreach ($photos as $photo) {
            // Preferuj originál (bez watermarku)
            $originalPath = preg_replace('/\.(jpg|png|webp)$/', '_original.$1', $photo['path']);
            $filepath = ROOT . '/public/uploads/' . $originalPath;
            
            if (!file_exists($filepath)) {
                $filepath = ROOT . '/public/uploads/' . $photo['path'];
            }
            
            if (file_exists($filepath)) {
                $ext = pathinfo($filepath, PATHINFO_EXTENSION);
                $name = $photo['author_name'] ?? 'zakaznik';
                $sku = $photo['sku'] ?? 'produkt';
                
                // Sanitize názvu
                $name = preg_replace('/[^a-z0-9_-]/i', '_', $name);
                $sku = preg_replace('/[^a-z0-9_-]/i', '_', $sku);
                
                $zipName = sprintf('%03d_%s_%s.%s', $counter++, $name, $sku, $ext);
                $zip->addFile($filepath, $zipName);
            }
        }
        
        $zip->close();
        
        // Stáhni ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        
        // Smaž dočasný ZIP
        unlink($zipPath);
        exit;
    }

    /**
     * Smazání jednotlivé recenze
     */
    public function delete(): void
    {
        $this->validateCsrf();
        $id = (int)$this->request->post('id', 0);
        $userId = $this->user['id'];
        
        if (!$id) {
            Session::flash('error', 'Neplatné ID recenze');
            $this->redirect('/reviews');
        }
        
        $count = Review::bulkDelete([$id], $userId);
        
        if ($count > 0) {
            Session::flash('success', 'Recenze byla smazána');
        } else {
            Session::flash('error', 'Recenze nebyla nalezena');
        }
        
        $this->redirect('/reviews');
    }

    /**
     * Aktualizace interní poznámky (bez změny stavu)
     */
    public function updateNote(): void
    {
        $this->validateCsrf();
        $id = (int)$this->request->post('id', 0);
        $adminNote = trim($this->request->post('admin_note', ''));
        $userId = $this->user['id'];
        
        if (!$id) {
            Session::flash('error', 'Neplatné ID recenze');
            $this->redirect('/reviews');
        }
        
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE reviews 
            SET admin_note = ? 
            WHERE id = ? AND user_id = ?
        ');
        
        if ($stmt->execute([$adminNote, $id, $userId])) {
            Session::flash('success', 'Poznámka byla uložena');
        } else {
            Session::flash('error', 'Nepodařilo se uložit poznámku');
        }
        
        $this->redirect('/reviews/' . $id);
    }
    /**
     * Stav expirace fotek pro aktuálního uživatele
     * Vrátí: ['blocked' => bool, 'days_left' => int|null, 'last_export' => string|null]
     */
    public static function photoExpiryStatus(int $userId): array
    {
        try {
        $db = \ShopCode\Core\Database::getInstance();

        // Datum posledního exportu
        $stmt = $db->prepare("SELECT MAX(exported_at) FROM photo_export_log WHERE user_id = ?");
        $stmt->execute([$userId]);
        $lastExport = $stmt->fetchColumn();

        // Nejstarší fotka
        $stmt2 = $db->prepare("
            SELECT MIN(rp.created_at) FROM review_photos rp
            JOIN reviews r ON r.id = rp.review_id
            WHERE r.user_id = ? AND rp.path IS NOT NULL
        ");
        $stmt2->execute([$userId]);
        $oldestPhoto = $stmt2->fetchColumn();

        if (!$oldestPhoto) {
            // Žádné fotky — zobraz info s plnou lhůtou od posledního exportu nebo 30 dní
            $refDate  = $lastExport ?: null;
            $daysLeft = $refDate ? max(0, 30 - (int)(new \DateTime())->diff(new \DateTime($refDate))->days) : 30;
            return ['blocked' => false, 'days_left' => $daysLeft, 'last_export' => $lastExport, 'no_photos' => true];
        }

        $refDate  = $lastExport ?: $oldestPhoto;
        $daysOld  = (int)(new \DateTime())->diff(new \DateTime($refDate))->days;
        $daysLeft = 30 - $daysOld;

        return [
            'blocked'     => $daysLeft <= 0,
            'days_left'   => max(0, $daysLeft),
            'last_export' => $lastExport,
        ];
        } catch (\Exception $e) {
            return ['blocked' => false, 'days_left' => null, 'last_export' => null];
        }
    }

    /**
     * ZIP export fotek + reset 30denního timeru
     */
    public function exportPhotosZip(): void
    {
        $userId = $this->user['id'];
        $db     = \ShopCode\Core\Database::getInstance();

        $stmt = $db->prepare("
            SELECT rp.path, rp.thumb, r.author_name, rp.id
            FROM review_photos rp
            JOIN reviews r ON r.id = rp.review_id
            WHERE r.user_id = ? AND rp.path IS NOT NULL
            ORDER BY rp.created_at DESC
        ");
        $stmt->execute([$userId]);
        $photos = $stmt->fetchAll();

        if (empty($photos)) {
            \ShopCode\Core\Session::flash('error', 'Žádné fotky k exportu.');
            $this->redirect('/reviews');
        }

        $zipFile = sys_get_temp_dir() . '/shopcode_photos_' . $userId . '_' . time() . '.zip';
        $zip     = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::CREATE) !== true) {
            \ShopCode\Core\Session::flash('error', 'Nepodařilo se vytvořit ZIP.');
            $this->redirect('/reviews');
        }

        $count = 0;
        foreach ($photos as $photo) {
            $abs = ROOT . '/public/uploads/' . ltrim($photo['path'], '/');
            if (file_exists($abs)) {
                $zip->addFile($abs, 'foto_' . $photo['id'] . '_' . basename($abs));
                $count++;
            }
        }
        $zip->close();

        // Zaznamenej export → reset timeru
        $db->prepare("INSERT INTO photo_export_log (user_id, exported_at, photo_count) VALUES (?, NOW(), ?)")
           ->execute([$userId, $count]);

        // Pošli soubor
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="fotorecenze_' . date('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        header('Cache-Control: no-cache');
        readfile($zipFile);
        unlink($zipFile);

        // Zmenš originály na 300px náhled — záloha je u uživatele v ZIP
        foreach ($photos as $photo) {
            $abs = ROOT . '/public/uploads/' . ltrim($photo['path'], '/');
            \ShopCode\Services\ImageHandler::downsizeToPreview($abs, 300);
        }

        exit;
    }

}