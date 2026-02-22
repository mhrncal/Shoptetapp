<?php
namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\{Faq, Product};

class FaqController extends BaseController
{
    public function index(): void
    {
        $userId  = $this->user['id'];
        $search  = $this->request->get('search', '');
        $filter  = $this->request->get('filter', ''); // 'general' | 'products' | ''
        $filters = array_filter(['search' => $search]);
        if ($filter === 'general')  $filters['general_only'] = true;

        $faqs     = Faq::allForUser($userId, $filters);
        $products = Product::all($userId, [], 1, 500); // Pro dropdown

        $this->view('faq/index', [
            'pageTitle' => 'FAQ',
            'faqs'      => $faqs,
            'products'  => $products,
            'search'    => $search,
            'filter'    => $filter,
            'total'     => Faq::count($userId),
        ]);
    }

    public function store(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];

        $data = [
            'product_id' => (int)$this->request->post('product_id') ?: null,
            'question'   => trim($this->request->post('question', '')),
            'answer'     => trim($this->request->post('answer', '')),
            'is_public'  => $this->request->post('is_public'),
            'sort_order' => (int)$this->request->post('sort_order', 0),
        ];

        if (empty($data['question']) || empty($data['answer'])) {
            Session::flash('error', 'Otázka a odpověď jsou povinné.');
            $this->redirect('/faq');
        }

        Faq::create($userId, $data);
        Session::flash('success', 'FAQ položka byla přidána.');
        $this->redirect('/faq');
    }

    public function update(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $id     = (int)$this->request->param('id');

        $faq = Faq::findById($id, $userId);
        if (!$faq) { \ShopCode\Core\Response::notFound(); }

        $data = [
            'product_id' => (int)$this->request->post('product_id') ?: null,
            'question'   => trim($this->request->post('question', '')),
            'answer'     => trim($this->request->post('answer', '')),
            'is_public'  => $this->request->post('is_public'),
            'sort_order' => (int)$this->request->post('sort_order', 0),
        ];

        if (empty($data['question']) || empty($data['answer'])) {
            Session::flash('error', 'Otázka a odpověď jsou povinné.');
            $this->redirect('/faq');
        }

        Faq::update($id, $userId, $data);
        Session::flash('success', 'FAQ položka byla upravena.');
        $this->redirect('/faq');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $id     = (int)$this->request->param('id');

        $ok = Faq::delete($id, $userId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'FAQ položka smazána.' : 'Položka nenalezena.');
        $this->redirect('/faq');
    }
}
