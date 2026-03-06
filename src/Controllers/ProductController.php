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
    public function search(): void
    {
        $userId = $this->user['id'];
        $q      = trim($this->request->get('search', ''));
        $limit  = min(20, max(1, (int)$this->request->get('limit', 10)));

        if (strlen($q) < 2) {
            header('Content-Type: application/json');
            echo json_encode(['products' => []]);
            exit;
        }

        $db   = \ShopCode\Core\Database::getInstance();
        $like = '%' . $q . '%';

        // Najdi matchující produkty
        $stmt = $db->prepare("
            SELECT id, name, code, pair_code
            FROM products
            WHERE user_id = ? AND (name LIKE ? OR code LIKE ?)
            ORDER BY name ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $like, $like, $limit]);
        $matched = $stmt->fetchAll();

        if (empty($matched)) {
            header('Content-Type: application/json');
            echo json_encode(['products' => []]);
            exit;
        }

        // Seber pair_codes pro načtení sourozenců
        $pairCodes = array_filter(array_unique(array_column($matched, 'pair_code')));
        $allProducts = $matched;

        if (!empty($pairCodes)) {
            $ph   = implode(',', array_fill(0, count($pairCodes), '?'));
            $stmt2 = $db->prepare("
                SELECT id, name, code, pair_code
                FROM products
                WHERE user_id = ? AND pair_code IN ($ph)
            ");
            $stmt2->execute(array_merge([$userId], $pairCodes));
            // Slouč — deduplikuj podle id
            $extra = $stmt2->fetchAll();
            $seen  = array_flip(array_column($matched, 'id'));
            foreach ($extra as $p) {
                if (!isset($seen[$p['id']])) $allProducts[] = $p;
            }
        }

        // Seskup podle pair_code (nebo id pro jednotlivce)
        $groups = [];
        foreach ($allProducts as $p) {
            $key = $p['pair_code'] ?: ('__' . $p['id']);
            $groups[$key][] = $p;
        }

        // Sestav výsledky — jeden záznam na skupinu
        $results = [];
        foreach ($groups as $prods) {
            $main  = $prods[0];
            $codes = array_values(array_unique(array_filter(array_column($prods, 'code'))));
            $results[] = [
                'id'       => $main['id'],
                'name'     => $main['name'],
                'code'     => implode(', ', $codes),
                'pair_code'=> $main['pair_code'],
            ];
        }

        // Seřaď podle jména
        usort($results, fn($a, $b) => strcmp($a['name'], $b['name']));

        header('Content-Type: application/json');
        echo json_encode(['products' => array_slice($results, 0, $limit)]);
        exit;
    }

}