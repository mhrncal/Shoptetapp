<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Database, Session, Response};
use ShopCode\Models\{Review, Product};
use ShopCode\Services\{ImageHandler, CsvGenerator};

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
        
        // URL k automaticky generovanému XML feedu
        $appUrl = defined('APP_URL') ? APP_URL : '';
        $xmlFeedUrl = $appUrl . '/feeds/user_' . $userId . '_reviews.xml';
        
        // Zkontroluj jestli feed existuje
        $feedPath = ROOT . '/public/feeds/user_' . $userId . '_reviews.xml';
        if (!file_exists($feedPath)) {
            $xmlFeedUrl = null;
        }

        $this->view('reviews/index', [
            'pageTitle' => 'Fotorecenze',
            'reviews'   => $reviews,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => 25,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
            'xmlFeedUrl' => $xmlFeedUrl,
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
                // Redirect na downloadZip metodu
                $_POST['ids'] = $ids;
                $this->downloadZip();
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
        $this->validateCsrf();
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
    public function downloadZip(): void
    {
        $ids = (array)$this->request->post('ids', []);
        $ids = array_filter(array_map('intval', $ids));
        
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
}
