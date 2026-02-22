<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\{XmlImport, User};
use ShopCode\Services\XmlDownloader;

class XmlController extends BaseController
{
    public function index(): void
    {
        $userId  = $this->user['id'];
        $user    = User::findById($userId);
        $active  = XmlImport::getActiveQueueItem($userId);
        $history = XmlImport::getHistoryForUser($userId, 15);
        $queue   = XmlImport::getQueueForUser($userId, 10);

        $this->view('xml/index', [
            'pageTitle'  => 'XML Import',
            'user'       => $user,
            'activeItem' => $active,
            'history'    => $history,
            'queue'      => $queue,
        ]);
    }

    public function start(): void
    {
        $this->validateCsrf();

        $userId  = $this->user['id'];
        $user    = User::findById($userId);
        $feedUrl = trim($this->request->post('xml_feed_url', $user['xml_feed_url'] ?? ''));

        if (empty($feedUrl) || !filter_var($feedUrl, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Zadejte platnou URL XML feedu.');
            $this->redirect('/xml');
        }

        if (XmlImport::hasActiveImport($userId)) {
            Session::flash('warning', 'Import již probíhá. Počkejte na jeho dokončení.');
            $this->redirect('/xml');
        }

        $probe = XmlDownloader::probe($feedUrl);
        if (!$probe['ok']) {
            Session::flash('error', "URL není dostupná (HTTP {$probe['http_code']}). Zkontrolujte adresu feedu.");
            $this->redirect('/xml');
        }

        if ($feedUrl !== ($user['xml_feed_url'] ?? '')) {
            User::update($userId, ['xml_feed_url' => $feedUrl]);
        }

        $priority = max(1, min(10, (int)$this->request->post('priority', 5)));
        XmlImport::addToQueue($userId, $feedUrl, $priority);

        Session::flash('success', 'Import byl přidán do fronty a bude zpracován automaticky.');
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
