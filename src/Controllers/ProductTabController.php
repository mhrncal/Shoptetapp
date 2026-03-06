<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\{Product, ProductTab, ProductVideo};

class ProductTabController extends BaseController
{
    // ---- TABS ----

    public function tabStore(): void
    {
        $this->validateCsrf();
        $userId    = $this->user['id'];
        $productId = (int)$this->request->param('product_id');

        if (!Product::findById($productId, $userId)) Response::notFound();

        $data = [
            'title'      => trim($this->request->post('title', '')),
            'content'    => trim($this->request->post('content', '')),
            'sort_order' => (int)$this->request->post('sort_order', 0),
            'is_active'  => $this->request->post('is_active'),
        ];

        if (empty($data['title']) || empty($data['content'])) {
            Session::flash('error', 'Název a obsah záložky jsou povinné.');
            $this->redirect('/products/' . $productId);
        }

        ProductTab::create($userId, $productId, $data);
        Session::flash('success', 'Záložka přidána.');
        $this->redirect('/products/' . $productId . '#tabs');
    }

    public function tabUpdate(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $tabId  = (int)$this->request->param('id');
        $tab    = ProductTab::findById($tabId, $userId);
        if (!$tab) Response::notFound();

        $data = [
            'title'      => trim($this->request->post('title', '')),
            'content'    => trim($this->request->post('content', '')),
            'sort_order' => (int)$this->request->post('sort_order', 0),
            'is_active'  => $this->request->post('is_active'),
        ];

        ProductTab::update($tabId, $userId, $data);
        Session::flash('success', 'Záložka uložena.');
        $this->redirect('/products/' . $tab['product_id'] . '#tabs');
    }

    public function tabDelete(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $tabId  = (int)$this->request->param('id');
        $tab    = ProductTab::findById($tabId, $userId);
        if (!$tab) Response::notFound();

        ProductTab::delete($tabId, $userId);
        Session::flash('success', 'Záložka smazána.');
        $this->redirect('/products/' . $tab['product_id'] . '#tabs');
    }

    // ---- VIDEOS ----

    public function videoStore(): void
    {
        $this->validateCsrf();
        $userId    = $this->user['id'];
        $productId = (int)$this->request->param('product_id');

        if (!Product::findById($productId, $userId)) Response::notFound();

        $referer   = $this->request->post('_referer', '');
        $title     = trim($this->request->post('title', ''));
        $autoplay  = $this->request->post('autoplay');
        $url       = trim($this->request->post('url', ''));
        $filePath  = null;

        // Upload souboru nebo URL
        $hasUpload = !empty($_FILES['video_file']['name']);
        if ($hasUpload) {
            $result = ProductVideo::handleUpload($_FILES['video_file'], $userId);
            if (isset($result['error'])) {
                Session::flash('error', $result['error']);
                $this->redirect($referer === 'videos' ? '/product-videos' : '/products/' . $productId . '#videos');
            }
            $filePath = $result['file_path'];
            $url      = null;
        } elseif ($url) {
            if (!ProductVideo::embedUrl($url)) {
                Session::flash('error', 'Zadejte platnou YouTube nebo Vimeo URL.');
                $this->redirect($referer === 'videos' ? '/product-videos' : '/products/' . $productId . '#videos');
            }
        } else {
            Session::flash('error', 'Nahrajte video nebo zadejte URL.');
            $this->redirect($referer === 'videos' ? '/product-videos' : '/products/' . $productId . '#videos');
        }

        ProductVideo::create($userId, $productId, [
            'title'      => $title,
            'url'        => $url,
            'file_path'  => $filePath,
            'sort_order' => (int)$this->request->post('sort_order', 0),
            'autoplay'   => $autoplay,
        ]);

        Session::flash('success', 'Video přidáno.');
        $this->redirect($referer === 'videos' ? '/product-videos' : '/products/' . $productId . '#videos');
    }

    public function videoDelete(): void
    {
        $this->validateCsrf();
        $userId  = $this->user['id'];
        $videoId = (int)$this->request->param('id');
        $video   = ProductVideo::findById($videoId, $userId);
        if (!$video) Response::notFound();

        ProductVideo::delete($videoId, $userId);
        Session::flash('success', 'Video smazáno.');
        $this->redirect('/products/' . $video['product_id'] . '#videos');
    }
    // ── Přehledové stránky ──────────────────────────────────────────

    public function videosIndex(): void
    {
        $userId = $this->user['id'];
        $db     = \ShopCode\Core\Database::getInstance();

        // Produkty seskupené podle pair_code
        $stmt = $db->prepare('SELECT id, name, code, pair_code FROM products WHERE user_id = ? ORDER BY name ASC');
        $stmt->execute([$userId]);
        $allProducts = $stmt->fetchAll();

        $groups = [];
        foreach ($allProducts as $p) {
            $key = $p['pair_code'] ?: ('__single_' . $p['id']);
            $groups[$key][] = $p;
        }

        // Videa indexovaná podle product_id
        $videoStmt = $db->prepare('
            SELECT pv.*, p.name as product_name, p.code as product_code
            FROM product_videos pv
            JOIN products p ON p.id = pv.product_id
            WHERE p.user_id = ?
            ORDER BY pv.sort_order ASC, pv.id ASC
        ');
        $videoStmt->execute([$userId]);
        $videosByProduct = [];
        foreach ($videoStmt->fetchAll() as $v) {
            $videosByProduct[$v['product_id']][] = $v;
        }

        $this->view('product_videos/index', [
            'pageTitle'       => 'Videa k produktům',
            'groups'          => $groups,
            'videosByProduct' => $videosByProduct,
            'csrfToken'       => \ShopCode\Core\Session::getCsrfToken(),
        ]);
    }

    public function tabsIndex(): void
    {
        $userId = $this->user['id'];
        $db     = \ShopCode\Core\Database::getInstance();

        $tabs = $db->prepare('
            SELECT pt.*, p.name as product_name, p.id as product_id
            FROM product_tabs pt
            JOIN products p ON p.id = pt.product_id
            WHERE p.user_id = ?
            ORDER BY p.name, pt.sort_order
        ');
        $tabs->execute([$userId]);

        $this->view('product_tabs/index', [
            'pageTitle' => 'Vlastní záložky',
            'tabs'      => $tabs->fetchAll(),
        ]);
    }

    public function videoToggleAutoplay(): void
    {
        $this->validateCsrf();
        $userId  = $this->user['id'];
        $videoId = (int)$this->request->param('id');
        $video   = ProductVideo::findById($videoId, $userId);
        if (!$video) Response::notFound();

        $newVal = (int)!$video['autoplay'];
        ProductVideo::update($videoId, $userId, [
            'title'    => $video['title'],
            'autoplay' => $newVal,
        ]);

        header('Content-Type: application/json');
        echo json_encode(['autoplay' => $newVal]);
        exit;
    }

    public function videoDeleteFromIndex(): void
    {
        $this->validateCsrf();
        $userId  = $this->user['id'];
        $videoId = (int)$this->request->param('id');
        $video   = ProductVideo::findById($videoId, $userId);
        if (!$video) Response::notFound();

        ProductVideo::delete($videoId, $userId);
        Session::flash('success', 'Video smazáno.');
        $this->redirect('/product-videos');
    }

}