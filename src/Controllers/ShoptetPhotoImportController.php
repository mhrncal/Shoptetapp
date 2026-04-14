<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Services\ShoptetCsvImporter;

class ShoptetPhotoImportController extends BaseController
{
    /**
     * Stránka nastavení importu fotek ze Shoptetu.
     */
    public function index(): void
    {
        $userId = $this->user['id'];
        $config = ShoptetCsvImporter::getImportConfig($userId);

        $this->view('reviews/photo_import', [
            'pageTitle' => 'Import fotek ze Shoptetu',
            'config'    => $config,
        ]);
    }

    /**
     * Uloží URL exportu.
     */
    public function saveUrl(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $url    = trim($this->request->post('csv_url', ''));

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Zadejte platnou URL adresu.');
            $this->redirect('/reviews');
        }

        ShoptetCsvImporter::saveImportUrl($userId, $url);
        Session::flash('success', 'URL exportu byla uložena.');
        $this->redirect('/reviews');
    }

    /**
     * SSE endpoint – live progress importu.
     * Volá se přes fetch/EventSource, streamuje JSON events.
     */
    public function runImportStream(): void
    {
        $userId = $this->user['id'];
        $config = ShoptetCsvImporter::getImportConfig($userId);

        // SSE hlavičky
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // vypne nginx buffering
        while (ob_get_level() > 0) ob_end_flush();

        $send = function(string $event, array $data) {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data) . "\n\n";
            flush();
        };

        if (!$config || empty($config['csv_url'])) {
            $send('error', ['message' => 'URL exportu není nastavena.']);
            return;
        }

        try {
            set_time_limit(300);
            $send('start', ['message' => 'Stahuji CSV...']);

            // Snapshot před importem – zapamatujeme co Shoptet měl
            $snapshot = ShoptetCsvImporter::snapshotUrls($userId);

            $importer = new ShoptetCsvImporter($userId);
            $result   = $importer->importFromUrl(
                $config['csv_url'],
                function(int $rows, int $images) use ($send) {
                    $send('progress', ['rows' => $rows, 'images' => $images]);
                }
            );

            ShoptetCsvImporter::updateImportStats($userId, $result['rows'], $result['images']);

            // Spáruj nové CDN URL s review_photos
            $matched = ShoptetCsvImporter::matchNewUrlsToReviews($userId, $snapshot);

            $send('done', [
                'rows'    => $result['rows'],
                'images'  => $result['images'],
                'matched' => $matched,
            ]);
        } catch (\Exception $e) {
            $send('error', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Spustí import synchronně (fallback, používá cron).
     */
    public function runImport(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $config = ShoptetCsvImporter::getImportConfig($userId);

        if (!$config || empty($config['csv_url'])) {
            Session::flash('error', 'Nejprve nastavte URL exportu fotek.');
            $this->redirect('/reviews');
        }

        try {
            set_time_limit(300);
            $snapshot = ShoptetCsvImporter::snapshotUrls($userId);

            $importer = new ShoptetCsvImporter($userId);
            $result   = $importer->importFromUrl($config['csv_url']);

            ShoptetCsvImporter::updateImportStats($userId, $result['rows'], $result['images']);
            $matched = ShoptetCsvImporter::matchNewUrlsToReviews($userId, $snapshot);

            Session::flash('success', sprintf(
                'Import dokončen: %s produktů, %s fotek%s.',
                number_format($result['rows'], 0, ',', ' '),
                number_format($result['images'], 0, ',', ' '),
                $matched > 0 ? ", {$matched} fotek spárováno se Shoptetem" : ''
            ));
        } catch (\Exception $e) {
            Session::flash('error', 'Chyba importu: ' . $e->getMessage());
        }

        $this->redirect('/reviews');
    }
}
