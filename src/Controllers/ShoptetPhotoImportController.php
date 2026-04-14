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
     * Spustí import – streamově stáhne a zparsuje CSV.
     * Běží synchronně (vhodné pro manuální spuštění, cron pro automatiku).
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
            $importer = new ShoptetCsvImporter($userId);
            $result   = $importer->importFromUrl($config['csv_url']);

            ShoptetCsvImporter::updateImportStats($userId, $result['rows'], $result['images']);

            Session::flash('success', sprintf(
                'Import dokončen: %s produktů, %s fotek.',
                number_format($result['rows'], 0, ',', ' '),
                number_format($result['images'], 0, ',', ' ')
            ));
        } catch (\Exception $e) {
            Session::flash('error', 'Chyba importu: ' . $e->getMessage());
        }

        $this->redirect('/reviews');
    }
}
