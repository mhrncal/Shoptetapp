<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\{XmlImport, User};
use ShopCode\Services\XmlDownloader;

class XmlController extends BaseController
{
    // Výchozí mapování pro CSV
    private const CSV_DEFAULT_MAP = [
        'code'     => 'code',
        'pairCode' => 'pairCode',
        'name'     => 'name',
        'category' => 'defaultCategory',
    ];

    // Dostupná CSV pole (sloupce které může CSV mít)
    private const CSV_AVAILABLE_FIELDS = [
        'code'             => 'Kód produktu (code) *',
        'pairCode'         => 'Grupování variant (pairCode)',
        'name'             => 'Název produktu (name)',
        'category'         => 'Kategorie (defaultCategory)',
        'price'            => 'Cena (price)',
        'originalPrice'    => 'Původní cena (originalPrice)',
        'vat'              => 'DPH % (vat)',
        'stock'            => 'Sklad (stock)',
        'brand'            => 'Značka (brand)',
        'ean'              => 'EAN (ean)',
        'weight'           => 'Hmotnost (weight)',
        'description'      => 'Popis (description)',
        'url'              => 'URL (url)',
        'image'            => 'Obrázek (image)',
        'availability'     => 'Dostupnost (availability)',
    ];

    // Výchozí mapování pro XML (tag → interní název)
    private const XML_DEFAULT_MAP = [
        'code'         => 'CODE',
        'name'         => 'n',
        'category'     => 'defaultCategory',
        'price'        => 'PRICE_VAT',
        'availability' => 'AVAILABILITY_OUT_OF_STOCK',
    ];

    public function index(): void
    {
        $userId  = $this->user['id'];
        $user    = User::findById($userId);
        $active  = XmlImport::getActiveQueueItem($userId);
        $history = XmlImport::getHistoryForUser($userId, 15);
        $queue   = XmlImport::getQueueForUser($userId, 10);

        $this->view('xml/index', [
            'pageTitle'       => 'Import produktů',
            'user'            => $user,
            'activeItem'      => $active,
            'history'         => $history,
            'queue'           => $queue,
            'csvFields'       => self::CSV_AVAILABLE_FIELDS,
            'csvDefaultMap'   => self::CSV_DEFAULT_MAP,
            'xmlDefaultMap'   => self::XML_DEFAULT_MAP,
        ]);
    }

    public function start(): void
    {
        $this->validateCsrf();

        $userId   = $this->user['id'];
        $user     = User::findById($userId);
        $feedUrl  = trim($this->request->post('feed_url', $user['xml_feed_url'] ?? ''));
        $format   = $this->request->post('feed_format', 'xml') === 'csv' ? 'csv' : 'xml';
        $priority = max(1, min(10, (int)$this->request->post('priority', 5)));

        if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Zadejte platnou URL feedu.');
            $this->redirect('/xml');
        }

        // Sestav field_map z POST
        $fieldMap = [];
        $rawMap   = $this->request->post('field_map', []);
        if ($format === 'csv') {
            foreach (self::CSV_AVAILABLE_FIELDS as $internal => $label) {
                $col = trim($rawMap[$internal] ?? '');
                if ($col !== '') {
                    $fieldMap[$internal] = $col;
                }
            }
            // code je povinný
            if (empty($fieldMap['code'])) {
                $fieldMap['code'] = 'code';
            }
        } else {
            // XML mapování
            foreach (self::XML_DEFAULT_MAP as $internal => $tag) {
                $col = trim($rawMap[$internal] ?? $tag);
                if ($col !== '') {
                    $fieldMap[$internal] = $col;
                }
            }
        }

        // Ulož URL pokud se změnila
        if ($feedUrl !== ($user['xml_feed_url'] ?? '')) {
            User::update($userId, ['xml_feed_url' => $feedUrl]);
        }

        $probe = XmlDownloader::probe($feedUrl);
        if (!$probe['ok']) {
            Session::flash('error', "URL není dostupná (HTTP {$probe['http_code']}). Zkontrolujte adresu feedu.");
            $this->redirect('/xml');
        }

        XmlImport::addToQueue($userId, $feedUrl, $priority, $format, $fieldMap);

        Session::flash('success', 'Import byl přidán do fronty (' . strtoupper($format) . ').');
        $this->redirect('/xml');
    }

    public function status(): void
    {
        $userId = $this->user['id'];
        $itemId = (int)$this->request->get('id');

        $item = $itemId
            ? XmlImport::getQueueItem($itemId, $userId)
            : XmlImport::getActiveQueueItem($userId);

        $this->json([
            'item'   => $item,
            'active' => $item && in_array($item['status'], ['pending', 'processing']),
        ]);
    }

    public function cancel(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $itemId = (int)$this->request->post('id');

        $ok = XmlImport::cancelQueueItem($itemId, $userId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Import byl zrušen.' : 'Import nelze zrušit.');
        $this->redirect('/xml');
    }
}
