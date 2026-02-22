<?php
namespace ShopCode\Controllers;
use ShopCode\Core\Response;
use ShopCode\Models\Product;

class ProductController extends BaseController
{
    public function index(): void
    {
        $userId  = $this->user['id'];
        $page    = max(1, (int)$this->request->get('page', 1));
        $filters = array_filter([
            'search'   => $this->request->get('search', ''),
            'category' => $this->request->get('category', ''),
            'brand'    => $this->request->get('brand', ''),
            'sort'     => $this->request->get('sort', ''),
        ]);
        $products   = Product::all($userId, $filters, $page, 50);
        $total      = Product::count($userId, $filters);
        $categories = Product::getCategories($userId);
        $brands     = Product::getBrands($userId);
        $this->view('products/index', compact('products','total','page','filters','categories','brands') + ['pageTitle'=>'Produkty','perPage'=>50]);
    }

    public function detail(): void
    {
        $id      = (int)$this->request->param('id');
        $userId  = $this->user['id'];
        $product = Product::findById($id, $userId);

        if (!$product) Response::notFound();

        $variants = Product::getVariants($id, $userId);
        $tabs     = \ShopCode\Models\ProductTab::forProduct($id, $userId);
        $videos   = \ShopCode\Models\ProductVideo::forProduct($id, $userId);

        $this->view('products/detail', [
            'pageTitle' => $product['name'],
            'product'   => $product,
            'variants'  => $variants,
            'tabs'      => $tabs,
            'videos'    => $videos,
        ]);
    }
}
