<?php

namespace ShopCode\Controllers;

use ShopCode\Middleware\ApiAuthMiddleware;
use ShopCode\Models\{Product, Faq, Branch, Event, ScrapedReview};
use ShopCode\Core\Database;

/**
 * REST API v1
 * Autorizace: Authorization: Bearer sc_...
 *
 * GET /api/v1/products        ?page=1&per_page=50&search=&category=&brand=
 * GET /api/v1/products/{id}
 * GET /api/v1/faq             ?product_id=
 * GET /api/v1/branches
 * GET /api/v1/events          ?upcoming=1
 */
class ApiController
{
    private const DEFAULT_PER_PAGE = 50;
    private const MAX_PER_PAGE     = 200;

    private \ShopCode\Core\Request $request;

    public function __construct(\ShopCode\Core\Request $request)
    {
        $this->request = $request;

        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        ApiAuthMiddleware::handle($request);
    }

    // ---- Products ----

    public function products(): void
    {
        $this->requirePermission('products:read');

        $userId  = ApiAuthMiddleware::userId();
        $page    = max(1, (int)($this->request->get('page', 1)));
        $perPage = min(self::MAX_PER_PAGE, max(1, (int)($this->request->get('per_page') ?? self::DEFAULT_PER_PAGE)));
        $filters = array_filter([
            'search'   => $this->request->get('search')   ?? '',
            'category' => $this->request->get('category', ''),
            'brand'    => $this->request->get('brand')    ?? '',
            'sort'     => $this->request->get('sort')     ?? '',
        ]);

        $items = Product::all($userId, $filters, $page, $perPage);
        $total = Product::count($userId, $filters);

        // Dekódujeme JSON pole
        foreach ($items as &$p) {
            $p['images']     = $p['images']     ? json_decode($p['images'],     true) : [];
            $p['parameters'] = $p['parameters'] ? json_decode($p['parameters'], true) : [];
            unset($p['xml_data']);
        }

        $this->json([
            'data'       => $items,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }

    public function product(): void
    {
        $this->requirePermission('products:read');
        $userId  = ApiAuthMiddleware::userId();
        $id      = (int)($this->routeParam('id') ?? 0);
        $product = Product::findById($id, $userId);

        if (!$product) { $this->notFound('Produkt nenalezen'); }

        $product['images']     = $product['images']     ? json_decode($product['images'],     true) : [];
        $product['parameters'] = $product['parameters'] ? json_decode($product['parameters'], true) : [];
        $product['variants']   = Product::getVariants($id, $userId);
        unset($product['xml_data']);

        $this->json(['data' => $product]);
    }

    // ---- FAQ ----

    public function faq(): void
    {
        $this->requirePermission('faq:read');
        $userId    = ApiAuthMiddleware::userId();
        $productId = ($this->request->get('product_id') !== null) ? (int)$this->request->get('product_id', 0) : null;

        $filters = ['search' => $this->request->get('search', '')];
        if ($productId) $filters['product_id'] = $productId;

        $items = Faq::allForUser($userId, $filters);

        // Pouze veřejné
        $items = array_values(array_filter($items, fn($f) => $f['is_public']));

        $this->json(['data' => $items, 'total' => count($items)]);
    }

    // ---- Branches ----

    public function branches(): void
    {
        $this->requirePermission('branches:read');
        $userId   = ApiAuthMiddleware::userId();
        $branches = Branch::allForUser($userId);

        foreach ($branches as &$b) {
            $hours = Branch::getHours($b['id']);
            $b['opening_hours'] = [];
            foreach (Branch::DAYS as $d => $dayName) {
                $h = $hours[$d] ?? null;
                $b['opening_hours'][] = [
                    'day'       => $dayName,
                    'day_index' => $d,
                    'closed'    => !$h || (bool)$h['is_closed'],
                    'open_from' => $h && !$h['is_closed'] ? substr($h['open_from'], 0, 5) : null,
                    'open_to'   => $h && !$h['is_closed'] ? substr($h['open_to'],   0, 5) : null,
                    'note'      => $h['note'] ?? null,
                ];
            }
        }

        $this->json(['data' => $branches, 'total' => count($branches)]);
    }

    // ---- Events ----

    public function events(): void
    {
        $this->requirePermission('events:read');
        $userId  = ApiAuthMiddleware::userId();
        $filters = ['is_active' => 1];
        if ((bool)$this->request->get('upcoming')) $filters['upcoming'] = true;
        if ((bool)$this->request->get('past'))     $filters['past']     = true;

        $items = Event::allForUser($userId, $filters);
        $this->json(['data' => $items, 'total' => count($items)]);
    }

    // ---- Scraped Reviews ----

    public function scrapedReviews(): void
    {
        $this->requirePermission('scraped_reviews:read');
        $userId  = ApiAuthMiddleware::userId();
        $page    = max(1, (int)($this->request->get('page', 1)));
        $perPage = min(200, max(1, (int)($this->request->get('per_page', 28))));

        $filters = array_filter([
            'source_id' => $this->request->get('source_id') ? (int)$this->request->get('source_id') : null,
            'rating'    => $this->request->get('rating')    ? (int)$this->request->get('rating')    : null,
            'lang'      => $this->request->get('lang')      ? strtoupper($this->request->get('lang')) : null,
        ]);

        $items = ScrapedReview::getReviews($userId, $page, $perPage, $filters);
        $total = ScrapedReview::countReviews($userId, $filters);

        // Načti překlady pro všechny recenze najednou
        $ids = array_column($items, 'id');
        $translations = ScrapedReview::getTranslationsForIds($ids);

        foreach ($items as &$r) {
            $r['display_content'] = !empty($r['cs_content']) ? $r['cs_content'] : $r['content'];
            $r['has_cs_translation'] = !empty($r['cs_content']);
            $r['translations'] = $translations[$r['id']] ?? [];
            unset($r['cs_is_deepl']);
        }

        $this->json([
            'stats'      => ScrapedReview::getStats($userId, $filters),
            'data'       => $items,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
            'filters'    => [
                'source_id' => $filters['source_id'] ?? null,
                'rating'    => $filters['rating']    ?? null,
                'lang'      => $filters['lang']      ?? null,
            ],
        ]);
    }

    public function scrapedReview(): void
    {
        $this->requirePermission('scraped_reviews:read');
        $userId = ApiAuthMiddleware::userId();
        $id     = (int)($this->routeParam('id') ?? 0);
        $review = ScrapedReview::getReviewWithTranslations($id, $userId);

        if (!$review) { $this->notFound('Recenze nenalezena'); }

        // Normalizuj translations na jednoduché pole text+meta
        $translations = [];
        foreach ($review['translations'] as $lang => $t) {
            $translations[$lang] = [
                'content'   => $t['content'],
                'is_deepl'  => (bool)$t['is_deepl'],
            ];
        }
        $review['translations']   = $translations;
        $review['display_content'] = $translations['CS']['content'] ?? $review['content'];

        $this->json(['data' => $review]);
    }

    public function scrapedSources(): void
    {
        $this->requirePermission('scraped_reviews:read');
        $userId  = ApiAuthMiddleware::userId();
        $sources = ScrapedReview::getSources($userId);
        $this->json(['data' => $sources, 'total' => count($sources)]);
    }

    public function scrapedStats(): void
    {
        $this->requirePermission('scraped_reviews:read');
        $userId = ApiAuthMiddleware::userId();
        $this->json(['data' => ScrapedReview::getStats($userId)]);
    }

    // ---- Helpers ----

    private function requirePermission(string $perm): void
    {
        if (!ApiAuthMiddleware::hasPermission($perm)) {
            http_response_code(403);
            echo json_encode(['error' => "Chybí oprávnění: {$perm}", 'code' => 403]);
            exit;
        }
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    private function notFound(string $message): never
    {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $message, 'code' => 404]);
        exit;
    }

    private function routeParam(string $key): ?string
    {
        return $this->request->param($key);
    }
}
