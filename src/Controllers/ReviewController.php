<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
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

        $this->view('reviews/index', [
            'pageTitle' => 'Fotorecenze',
            'reviews'   => $reviews,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => 25,
            'counts'    => $counts,
            'status'    => $status,
            'search'    => $search,
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

            default:
                Session::flash('error', 'Neznámá akce.');
        }

        $this->redirect('/reviews');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $id     = (int)$this->request->param('id');
        $userId = $this->user['id'];
        $review = Review::delete($id, $userId);

        if ($review) {
            // Smažeme fotky z disku
            if (!empty($review['photos'])) {
                // UUID ze cesty: {user_id}/{uuid}/filename
                $firstPhoto = $review['photos'][0]['path'] ?? '';
                $parts      = explode('/', $firstPhoto);
                if (count($parts) >= 2) {
                    $uuid = $parts[1];
                    (new ImageHandler())->deleteFolder($userId, $uuid);
                }
            }
            Session::flash('success', 'Recenze byla smazána.');
        } else {
            Session::flash('error', 'Recenze nenalezena.');
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
}
