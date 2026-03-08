<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\ScrapedReview;
use ShopCode\Services\{ReviewScraper, DeepLTranslator};

class ScrapedReviewController extends BaseController
{
    private function getDeeplKey(): ?string
    {
        $db   = \ShopCode\Core\Database::getInstance();
        $stmt = $db->prepare("SELECT deepl_api_key FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $key  = $stmt->fetchColumn();
        return $key ?: null;
    }

    private function getDeepL(): ?DeepLTranslator
    {
        $key = $this->getDeeplKey();
        return $key ? new DeepLTranslator($key) : null;
    }

    private function hasDeepL(): bool
    {
        return (bool)$this->getDeeplKey();
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
            'hasDeepL'   => $this->hasDeepL(),
            'deeplKey'   => !empty($this->user['deepl_api_key']),
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

        // Překlad — volitelný, jen pokud má uživatel klíč a nastavené jazyky
        $translated = 0;
        $deepl = $this->getDeepL();
        $langs = ScrapedReview::getUserLangs($userId);
        if ($deepl && !empty($langs) && $new > 0) {
            $pending = ScrapedReview::getUntranslated($userId, $langs);
            foreach ($pending as $review) {
                foreach ($langs as $lang) {
                    $text = $deepl->translate($review['content'], $lang);
                    if ($text) { ScrapedReview::saveTranslation($review['id'], $lang, $text); $translated++; }
                }
            }
        }

        $msg = "Nascrapováno " . count($scraped) . " recenzí, {$new} nových.";
        if ($translated) $msg .= " Přeloženo: {$translated}.";
        elseif ($deepl && !empty($langs)) $msg .= " Žádné nové k překladu.";
        Session::flash('success', $msg);
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
            Session::flash('error', 'DeepL API klíč není nastaven. Zadejte jej v nastavení modulu.');
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

    // Uložit DeepL API klíč
    public function saveApiKey(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $key    = trim($this->request->post('deepl_api_key', ''));

        $db = \ShopCode\Core\Database::getInstance();
        $db->prepare("UPDATE users SET deepl_api_key = ? WHERE id = ?")
           ->execute([$key ?: null, $userId]);

        Session::flash('success', $key ? 'DeepL API klíč uložen.' : 'DeepL API klíč odstraněn.');
        $this->redirect('/scraped-reviews');
    }
}
