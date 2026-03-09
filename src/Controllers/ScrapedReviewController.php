<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\ScrapedReview;
use ShopCode\Services\{ReviewScraper, DeepLTranslator};

class ScrapedReviewController extends BaseController
{
    public function saveOutscraperApiKey(): void
    {
        $this->validateCsrf();
        $key = trim($this->request->post('outscraper_api_key', ''));
        $db  = \ShopCode\Core\Database::getInstance();
        $db->prepare("UPDATE users SET outscraper_api_key = ? WHERE id = ?")
           ->execute([$key ?: null, $this->user['id']]);
        Session::flash('success', $key ? 'Outscraper API klíč uložen.' : 'Outscraper API klíč odstraněn.');
        $this->redirect('/scraped-reviews');
    }

    private function getOutscraperApiKey(): ?string
    {
        $db   = \ShopCode\Core\Database::getInstance();
        $stmt = $db->prepare("SELECT outscraper_api_key FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $row  = $stmt->fetch();
        return !empty($row['outscraper_api_key']) ? $row['outscraper_api_key'] : null;
    }

    private function getGoogleApiKey(): ?string
    {
        $db   = \ShopCode\Core\Database::getInstance();
        $stmt = $db->prepare("SELECT google_places_api_key FROM users WHERE id = ?");
        $stmt->execute([$this->user['id']]);
        $key  = $stmt->fetchColumn();
        return $key ?: null;
    }

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
            'hasDeepL'       => $this->hasDeepL(),
            'hasGoogleKey'       => (bool)$this->getGoogleApiKey(),
            'outscraperKey'      => $this->getOutscraperApiKey() ?? '',
            'googlePlacesKey'    => $this->getGoogleApiKey() ?? '',
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

        if (!$name || !$url || !in_array($platform, ['heureka', 'trustedshops', 'shoptet', 'google', 'outscraper'])) {
            Session::flash('error', 'Vyplňte všechna pole.');
            $this->redirect('/scraped-reviews');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Neplatná URL adresa.');
            $this->redirect('/scraped-reviews');
        }

        if (ScrapedReview::urlExists($userId, $url)) {
            Session::flash('error', 'Zdroj s touto URL již existuje.');
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

        if ($source['platform'] === 'google') {
            $googleKey = $this->getGoogleApiKey();
            if (!$googleKey) { Session::flash('error', 'Google Places API klíč není nastaven.'); $this->redirect('/scraped-reviews'); }
            $scraped = ReviewScraper::scrapeGooglePlaces($source['url'], $googleKey);
        } elseif ($source['platform'] === 'outscraper') {
            $outKey = $this->getOutscraperApiKey();
            if (!$outKey) { Session::flash('error', 'Outscraper API klíč není nastaven.'); $this->redirect('/scraped-reviews'); }
            $scraped = ReviewScraper::scrapeOutscraper($source['url'], $outKey);
        } else {
            $scraped = ReviewScraper::scrape($source['url'], $source['platform']);
        }
        $new = ScrapedReview::insertReviews($userId, $sourceId, $scraped);
        ScrapedReview::updateLastScraped($sourceId);

        // Překlad — volitelný, CS je primární jazyk (vždy se přeloží + detekuje source_lang)
        $translated = 0;
        $deepl = $this->getDeepL();
        $langs = ScrapedReview::getUserLangs($userId);
        if ($deepl && $new > 0) {
            $pending = ScrapedReview::getUntranslated($userId, array_unique(array_merge(['CS'], $langs)));
            foreach ($pending as $review) {
                if (empty(trim($review['content']))) continue;
                // CS vždy — detekuj zdrojový jazyk
                $csText = $deepl->translate($review['content'], 'CS');
                if ($csText) {
                    ScrapedReview::saveTranslation($review['id'], 'CS', $csText, true);
                    $srcLang = $deepl->detectLang($review['content']);
                    if ($srcLang) ScrapedReview::updateSourceLang($review['id'], $srcLang);
                    $translated++;
                }
                // Ostatní jazyky
                foreach ($langs as $lang) {
                    if (strtoupper($lang) === 'CS') continue;
                    $text = $deepl->translate($review['content'], $lang);
                    if ($text) { ScrapedReview::saveTranslation($review['id'], $lang, $text, true); $translated++; }
                }
                usleep(200000);
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
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->validateCsrf();
        $userId = $this->user['id'];
        $deepl  = $this->getDeepL();

        if (!$deepl) {
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'DeepL klíč není nastaven.']); exit; }
            Session::flash('error', 'DeepL API klíč není nastaven.'); $this->redirect('/scraped-reviews');
        }

        $langs    = ScrapedReview::getUserLangs($userId);
        $allLangs = array_unique(array_merge(['CS'], $langs));
        $reviews  = ScrapedReview::getUntranslated($userId, $allLangs);
        $count    = 0;

        foreach ($reviews as $review) {
            if (empty(trim($review['content']))) continue;
            $missingLangs = $review['missing_langs'] ?? $allLangs;

            if (in_array('CS', $missingLangs)) {
                $csText = $deepl->translate($review['content'], 'CS');
                if ($csText) {
                    ScrapedReview::saveTranslation($review['id'], 'CS', $csText, true);
                    $srcLang = $deepl->detectLang($review['content']);
                    if ($srcLang) ScrapedReview::updateSourceLang($review['id'], $srcLang);
                    $count++;
                }
            }
            foreach ($missingLangs as $lang) {
                if (strtoupper($lang) === 'CS') continue;
                $text = $deepl->translate($review['content'], $lang);
                if ($text) { ScrapedReview::saveTranslation($review['id'], $lang, $text, true); $count++; }
            }
            usleep(200000);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'translated' => $count, 'langs' => count($allLangs), 'reviews' => count($reviews)]);
            exit;
        }
        Session::flash('success', "Přeloženo {$count} textů.");
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

    // Uložit Google Places API klíč
    public function saveGoogleApiKey(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];
        $key    = trim($this->request->post('google_places_api_key', ''));

        $db = \ShopCode\Core\Database::getInstance();
        $db->prepare("UPDATE users SET google_places_api_key = ? WHERE id = ?")
           ->execute([$key ?: null, $userId]);

        Session::flash('success', $key ? 'Google Places API klíč uložen.' : 'Google Places API klíč odstraněn.');
        $this->redirect('/scraped-reviews');
    }

    // Stav synchronizace (polling)
    public function syncStatus(): void
    {
        header('Content-Type: application/json');
        $userId  = $this->user['id'];
        $lockKey = "sync_lock_{$userId}";
        $progKey = "sync_progress_{$userId}";

        $lock = $_SESSION[$lockKey] ?? null;
        $prog = $_SESSION[$progKey] ?? null;

        // Zkontroluj jestli proces stále běží (max 5 minut)
        $running = $lock && (time() - ($lock['started'] ?? 0)) < 300;

        echo json_encode([
            'running'  => $running,
            'progress' => $prog,
            'lock'     => $lock,
        ]);
        exit;
    }

    // Synchronizuj jeden zdroj (AJAX)
    public function syncOne(): void
    {
        header('Content-Type: application/json');
        $this->validateCsrf();
        $userId   = $this->user['id'];
        $sourceId = (int)$this->request->post('source_id', 0);
        $lockKey  = "sync_lock_{$userId}";
        $progKey  = "sync_progress_{$userId}";
        // Zkontroluj lock
        $lock = $_SESSION[$lockKey] ?? null;
        if ($lock && (time() - ($lock['started'] ?? 0)) < 300) {
            echo json_encode(['ok' => false, 'error' => 'Synchronizace již probíhá.']);
            exit;
        }

        $source = ScrapedReview::getSource($sourceId, $userId);
        if (!$source) {
            echo json_encode(['ok' => false, 'error' => 'Zdroj nenalezen.']);
            exit;
        }

        // Nastav lock + progress
        $_SESSION[$lockKey] = ['started' => time(), 'source_id' => $sourceId, 'source_name' => $source['name']];
        $_SESSION[$progKey] = ['step' => 'scraping', 'msg' => 'Stahuji recenze z ' . $source['name'] . '…', 'new' => 0, 'translated' => 0];
        session_write_close();

        // Shoptet má stovky stránek — spusť jako background CLI proces
        if ($source['platform'] === 'shoptet') {
            $script  = dirname(__DIR__, 2) . '/cron/scrape_one.php';
            $logFile = dirname(__DIR__, 2) . '/public/logs/scrape-' . $sourceId . '.log';
            $phpBin  = str_replace('php-fpm', 'php', PHP_BINARY ?: '/usr/bin/php');
            $cmd     = sprintf('%s %s %d %d > %s 2>&1 &',
                escapeshellarg($phpBin), escapeshellarg($script),
                $userId, $sourceId, escapeshellarg($logFile)
            );
            // Zkus background exec, ověř spuštění podle logu
            @exec($cmd);
            sleep(1);
            $launched = file_exists($logFile) && filesize($logFile) > 0;
            if ($launched) {
                echo json_encode(['ok' => true, 'background' => true, 'new' => 0, 'translated' => 0,
                    'msg' => 'Shoptet scraping spuštěn na pozadí (může trvat 2–3 minuty). Log: /public/logs/scrape-' . $sourceId . '.log']);
                exit;
            }
            // Fallback: synchronní scrape (exec nefunguje)
            ignore_user_abort(true);
            set_time_limit(300);
        }

        // Scrape
        if ($source['platform'] === 'google') {
            $googleKey = $this->getGoogleApiKey();
            $scraped = $googleKey ? ReviewScraper::scrapeGooglePlaces($source['url'], $googleKey) : [];
        } elseif ($source['platform'] === 'outscraper') {
            $outKey = $this->getOutscraperApiKey();
            $scraped = $outKey ? ReviewScraper::scrapeOutscraper($source['url'], $outKey) : [];
        } else {
            $scraped = ReviewScraper::scrape($source['url'], $source['platform']);
        }

        $new = ScrapedReview::insertReviews($userId, $sourceId, $scraped);
        ScrapedReview::updateLastScraped($sourceId);

        // Překlad
        session_start();
        $_SESSION[$progKey] = ['step' => 'translating', 'msg' => "Nascrapováno {$new} nových. Překládám…", 'new' => $new, 'translated' => 0];
        session_write_close();

        $translated = 0;
        $deepl = $this->getDeepL();
        $langs = ScrapedReview::getUserLangs($userId);
        if ($deepl && $new > 0) {
            $pending = ScrapedReview::getUntranslated($userId, array_unique(array_merge(['CS'], $langs)));
            foreach ($pending as $review) {
                if (empty(trim($review['content']))) continue;
                $missingLangs = $review['missing_langs'] ?? array_unique(array_merge(['CS'], $langs));
                // CS jako první — detekuj zdrojový jazyk
                if (in_array('CS', $missingLangs)) {
                    $csText = $deepl->translate($review['content'], 'CS');
                    if ($csText) {
                        ScrapedReview::saveTranslation($review['id'], 'CS', $csText, true);
                        $srcLang = $deepl->detectLang($review['content']);
                        if ($srcLang) ScrapedReview::updateSourceLang($review['id'], $srcLang);
                        $translated++;
                    }
                }
                foreach ($missingLangs as $lang) {
                    if (strtoupper($lang) === 'CS') continue;
                    $text = $deepl->translate($review['content'], $lang);
                    if ($text) { ScrapedReview::saveTranslation($review['id'], $lang, $text, true); $translated++; }
                }
                usleep(200000);
            }
        }

        // Uvolni lock
        session_start();
        $_SESSION[$lockKey] = null;
        $_SESSION[$progKey] = ['step' => 'done', 'msg' => "Hotovo. Nových: {$new}, přeloženo: {$translated}.", 'new' => $new, 'translated' => $translated];
        session_write_close();

        echo json_encode(['ok' => true, 'new' => $new, 'translated' => $translated]);
        exit;
    }

    // Synchronizuj všechny zdroje postupně (AJAX — volá se per-source)
    public function syncAll(): void
    {
        header('Content-Type: application/json');
        $userId  = $this->user['id'];
        $sources = ScrapedReview::getSources($userId);
        $active  = array_filter($sources, fn($s) => $s['is_active']);
        echo json_encode(['ok' => true, 'sources' => array_values(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name'], 'platform' => $s['platform']], $active))]);
        exit;
    }

    // Import Outscraper XLSX
    public function importXlsx(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];

        if (empty($_FILES['xlsx_file']['tmp_name'])) {
            Session::flash('error', 'Nebyl nahrán žádný soubor.');
            $this->redirect('/scraped-reviews');
        }

        $sourceId = (int)$this->request->post('source_id', 0);
        $file     = $_FILES['xlsx_file']['tmp_name'];

        // Parsuj XLSX (ZIP + XML)
        $rows = $this->parseXlsx($file);
        if ($rows === false || count($rows) < 2) {
            Session::flash('error', 'Nepodařilo se načíst soubor nebo je prázdný.');
            $this->redirect('/scraped-reviews');
        }

        // Hlavička — najdi indexy sloupců
        $header = array_map('trim', $rows[0]);
        $col = array_flip($header);

        $required = ['review_id', 'author_title', 'review_rating', 'review_text', 'review_datetime_utc'];
        foreach ($required as $r) {
            if (!isset($col[$r])) {
                Session::flash('error', "Chybí sloupec: {$r}. Nahraj export z Outscraper.");
                $this->redirect('/scraped-reviews');
            }
        }

        // Pokud není vybrán zdroj, vytvoř nový podle name sloupce
        if (!$sourceId) {
            $shopName = isset($col['name']) && !empty($rows[1][$col['name']]) ? $rows[1][$col['name']] : 'Outscraper import';
            $placeId  = isset($col['place_id']) && !empty($rows[1][$col['place_id']]) ? $rows[1][$col['place_id']] : '';
            $url      = $placeId ?: 'outscraper-import-' . time();

            $db = \ShopCode\Core\Database::getInstance();
            // Zkontroluj duplicitu
            $ex = $db->prepare("SELECT id FROM scrape_sources WHERE user_id=? AND url=?");
            $ex->execute([$userId, $url]);
            $existing = $ex->fetchColumn();
            if ($existing) {
                $sourceId = (int)$existing;
            } else {
                $db->prepare("INSERT INTO scrape_sources (user_id, name, url, platform, is_active) VALUES (?,?,?,'outscraper',1)")
                   ->execute([$userId, $shopName, $url]);
                $sourceId = (int)$db->lastInsertId();
            }
        }

        // Importuj recenze
        $reviews = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $text = isset($col['review_text']) ? trim($row[$col['review_text']] ?? '') : '';
            if ($text === '') continue;

            $extId  = trim($row[$col['review_id']] ?? '');
            $author = trim($row[$col['author_title']] ?? 'Zákazník');
            $rating = (int)round((float)($row[$col['review_rating']] ?? 0));
            $dtRaw  = trim($row[$col['review_datetime_utc']] ?? '');
            $date   = null;
            if ($dtRaw) {
                $ts = strtotime($dtRaw);
                $date = $ts ? date('Y-m-d', $ts) : null;
            }

            $reviews[] = [
                'user_id'     => $userId,
                'source_id'   => $sourceId,
                'external_id' => $extId ?: null,
                'author'      => $author,
                'rating'      => $rating ?: null,
                'content'     => $text,
                'reviewed_at' => $date,
                'source_lang' => null,
            ];
        }

        $new = ScrapedReview::insertReviews($reviews);

        // Aktualizuj last_scraped_at
        $db = \ShopCode\Core\Database::getInstance();
        $db->prepare("UPDATE scrape_sources SET last_scraped_at=NOW() WHERE id=?")->execute([$sourceId]);

        Session::flash('success', "Import dokončen. Nových recenzí: {$new}.");
        $this->redirect('/scraped-reviews');
    }

    private function parseXlsx(string $file): array|false
    {
        if (!class_exists('ZipArchive')) return false;
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) return false;

        // Načti shared strings
        $strings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            if ($ss) {
                foreach ($ss->si as $si) {
                    // Složené stringy (r elementy)
                    if (isset($si->r)) {
                        $val = '';
                        foreach ($si->r as $r) {
                            $val .= (string)($r->t ?? '');
                        }
                        $strings[] = $val;
                    } else {
                        $strings[] = (string)($si->t ?? '');
                    }
                }
            }
        }

        // Načti první sheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheetXml) return false;

        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet) return false;

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            $prevIdx = -1;
            foreach ($row->c as $cell) {
                // Zjisti index sloupce z ref (A1, B1...)
                $ref = (string)$cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $colIdx = 0;
                foreach (str_split($m[1]) as $ch) {
                    $colIdx = $colIdx * 26 + (ord($ch) - ord('A') + 1);
                }
                $colIdx--; // 0-based

                // Doplň prázdné buňky
                while (count($rowData) < $colIdx) $rowData[] = '';

                $t = (string)($cell['t'] ?? '');
                $v = (string)($cell->v ?? '');
                if ($t === 's') {
                    $rowData[] = $strings[(int)$v] ?? '';
                } elseif ($t === 'inlineStr') {
                    $rowData[] = (string)($cell->is->t ?? '');
                } else {
                    $rowData[] = $v;
                }
            }
            $rows[] = $rowData;
        }

        return $rows;
    }
}
