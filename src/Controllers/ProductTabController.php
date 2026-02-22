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

        $url = trim($this->request->post('url', ''));
        if (!ProductVideo::embedUrl($url)) {
            Session::flash('error', 'Zadejte platnou YouTube nebo Vimeo URL.');
            $this->redirect('/products/' . $productId . '#videos');
        }

        ProductVideo::create($userId, $productId, [
            'title'      => trim($this->request->post('title', '')),
            'url'        => $url,
            'sort_order' => (int)$this->request->post('sort_order', 0),
        ]);

        Session::flash('success', 'Video přidáno.');
        $this->redirect('/products/' . $productId . '#videos');
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
}
