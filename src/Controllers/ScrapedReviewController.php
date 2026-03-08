<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\ScrapedReview;
use ShopCode\Services\{ReviewScraper, DeepLTranslator};

class ScrapedReviewController extends BaseController
{
    private function getDeepL(): ?DeepLTranslator
    {
        $key = defined('DEEPL_API_KEY') ? DEEPL_API_KEY : null;
        return $key ? new DeepLTranslator($key) : null;
    }

    // Seznam recenzí + správa zdrojů
    public function index(): void
    {
        $userId  = $this->user['id'];
        $page    = max(1, (int)$this->request->get('page', 1));
        $sourceId = (int)$this->request->get('source', 0);
        $filters = $sourceId ? ['source_id' => $sourceId] : [];

        $sources  = ScrapedReview::getSources($userId);
        $reviews  = ScrapedReview::getReviews($userId, $page, 25, $filters);
        $total    = ScrapedReview::countReviews($userId, $filters);
        $userLangs = ScrapedReview::getUserLangs($userId);

        $this->view('scraped_reviews/index', [
            'pageTitle'  => 'Scrapované recenze',
            'sources'    => $sources,
            'reviews'    => $reviews,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => 25,
            'sourceFilter' => $sourceId,
            'userLangs'  => $userLangs,
            'allLangs'   => DeepLTranslator::LANGUAGES,
            'hasDeepL'   => (bool)(defined('DEEPL_API_KEY') && DEEPL_API_KEY),
        ]);
    }

    // Přidat zdroj
    public function addSource(): void
    {
        $this->validateCsrf();
        $userId   = $this->user['id'];
        $name     = trim($this->request->post('name', ''));
        $url      = trim($this->request->post('url', ''));
        $platform = $this->request->post('platform', '');

        if (!$name || !$url || !in_array($platform, ['heureka', 'trustedshops', 'shoptet'])) {
            Session::flash('error', 'Vyplňte všechna pole.');
            $this->redirect('/scraped-reviews');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Neplatná URL adresa.');
            $this->redirect('/scraped-reviews');
        }

        ScrapedReview::addSource($userId, $name, $url, $platform);
        Session::flash('success', 'Zdroj přidán.');
        $this->redirect('/scraped-reviews');
    }

    // Smazat zdroj
    public function deleteSource(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $id     = (int)$this->request->post('id', 0);
        ScrapedReview::deleteSource($id, $userId);
        Session::flash('success', 'Zdroj smazán.');
        $this->redirect('/scraped-reviews');
    }

    // Ruční scrape jednoho zdroje
    public function scrapeSource(): void
    {
        $this->validateCsrf();
        $userId   = $this->user['id'];
        $sourceId = (int)$this->request->post('source_id', 0);

        $source = ScrapedReview::getSource($sourceId, $userId);
        if (!$source) {
            Session::flash('error', 'Zdroj nenalezen.');
            $this->redirect('/scraped-reviews');
        }

        $scraped = ReviewScraper::scrape($source['url'], $source['platform']);
        $new = 0;
        foreach ($scraped as $r) {
            $inserted = ScrapedReview::insertReview(
                $userId, $sourceId,
                $r['external_id'], $r['author'],
                $r['rating'], $r['content'], $r['date']
            );
            if ($inserted) $new++;
        }

        ScrapedReview::updateLastScraped($sourceId);
        Session::flash('success', "Nascrapováno " . count($scraped) . " recenzí, {$new} nových.");
        $this->redirect('/scraped-reviews');
    }

    // Uložit jazyky překladů
    public function saveLangs(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $langs  = $this->request->post('langs', []);
        if (!is_array($langs)) $langs = [];

        // Pouze platné jazyky
        $valid = array_keys(DeepLTranslator::LANGUAGES);
        $langs = array_filter($langs, fn($l) => in_array($l, $valid));

        ScrapedReview::setUserLangs($userId, array_values($langs));
        Session::flash('success', 'Jazyky překladů uloženy.');
        $this->redirect('/scraped-reviews');
    }

    // Přeložit recenze (AJAX nebo ruční spuštění)
    public function translatePending(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $deepl  = $this->getDeepL();

        if (!$deepl) {
            Session::flash('error', 'DeepL API klíč není nastaven. Přidejte DEEPL_API_KEY do .env');
            $this->redirect('/scraped-reviews');
        }

        $langs    = ScrapedReview::getUserLangs($userId);
        if (empty($langs)) {
            Session::flash('error', 'Nejsou vybrány žádné jazyky pro překlad.');
            $this->redirect('/scraped-reviews');
        }

        $reviews  = ScrapedReview::getUntranslated($userId, $langs);
        $count    = 0;

        foreach ($reviews as $review) {
            $texts = array_fill(0, count($langs), $review['content']);
            foreach ($langs as $lang) {
                $translated = $deepl->translate($review['content'], $lang);
                if ($translated) {
                    ScrapedReview::saveTranslation($review['id'], $lang, $translated);
                    $count++;
                }
            }
        }

        Session::flash('success', "Přeloženo {$count} textů do " . count($langs) . " jazyků.");
        $this->redirect('/scraped-reviews');
    }

    // Detail jedné recenze
    public function detail(): void
    {
        $userId = $this->user['id'];
        $id     = (int)$this->request->params['id'];

        $review = ScrapedReview::getReviewWithTranslations($id, $userId);
        if (!$review) {
            Response::notFound();
        }

        $this->view('scraped_reviews/detail', [
            'pageTitle' => 'Recenze #' . $id,
            'review'    => $review,
            'allLangs'  => DeepLTranslator::LANGUAGES,
        ]);
    }
}
