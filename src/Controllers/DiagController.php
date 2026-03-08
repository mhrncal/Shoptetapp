<?php

namespace ShopCode\Controllers;

class DiagController extends BaseController
{
    public function index(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') {
            http_response_code(403); die('Forbidden');
        }

        // Spustit migrace
        if (($_GET['migrate'] ?? '') === '1') {
            header('Content-Type: text/plain; charset=utf-8');
            $db = \ShopCode\Core\Database::getInstance();
            $sqls = [
                "ALTER TABLE reviews ADD COLUMN IF NOT EXISTS xml_exported_at DATETIME DEFAULT NULL",
                "ALTER TABLE review_photos ADD COLUMN IF NOT EXISTS shoptet_url VARCHAR(1000) DEFAULT NULL",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('reviews','Fotorecenze','Sběr a správa fotorecenzí zákazníků','camera','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('scraped_reviews','Scrapované recenze','Automatický sběr recenzí z Heureka, Trusted Shops a Shoptet','search','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO user_modules (user_id, module_id, status, activated_at) SELECT u.id, m.id, 'active', NOW() FROM users u CROSS JOIN modules m WHERE u.role='superadmin' ON DUPLICATE KEY UPDATE status='active'",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS deepl_api_key VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_places_api_key VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE scrape_sources MODIFY COLUMN platform ENUM('heureka','trustedshops','shoptet','google') NOT NULL",
                "CREATE TABLE IF NOT EXISTS photo_export_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, exported_at DATETIME NOT NULL, photo_count INT DEFAULT 0, INDEX idx_pel_user (user_id))",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo_warning_sent_at DATETIME DEFAULT NULL",
                "CREATE TABLE IF NOT EXISTS scrape_sources (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(1000) NOT NULL, platform ENUM('heureka','trustedshops','shoptet','google') NOT NULL, is_active TINYINT(1) DEFAULT 1, last_scraped_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NOW(), INDEX idx_ss_user (user_id))",
                "CREATE TABLE IF NOT EXISTS scraped_reviews (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, source_id INT NOT NULL, external_id VARCHAR(255) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, rating TINYINT DEFAULT NULL, content TEXT, reviewed_at DATE DEFAULT NULL, scraped_at DATETIME DEFAULT NOW(), UNIQUE KEY uniq_source_external (source_id, external_id), INDEX idx_sr_user (user_id), INDEX idx_sr_source (source_id))",
                "CREATE TABLE IF NOT EXISTS scraped_review_translations (id INT AUTO_INCREMENT PRIMARY KEY, review_id INT NOT NULL, lang VARCHAR(5) NOT NULL, content TEXT, translated_at DATETIME DEFAULT NOW(), UNIQUE KEY uniq_rt_review_lang (review_id, lang), INDEX idx_srt_review (review_id))",
                "CREATE TABLE IF NOT EXISTS user_translation_langs (user_id INT NOT NULL, lang VARCHAR(5) NOT NULL, PRIMARY KEY (user_id, lang))",
                "ALTER TABLE reviews ADD COLUMN IF NOT EXISTS xml_exported_at DATETIME DEFAULT NULL",
                "ALTER TABLE review_photos ADD COLUMN IF NOT EXISTS shoptet_url VARCHAR(1000) DEFAULT NULL",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('reviews','Fotorecenze','Sběr a správa fotorecenzí zákazníků','camera','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('scraped_reviews','Scrapované recenze','Automatický sběr recenzí z Heureka, Trusted Shops a Shoptet','search','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO user_modules (user_id, module_id, status, activated_at) SELECT u.id, m.id, 'active', NOW() FROM users u CROSS JOIN modules m WHERE u.role='superadmin' ON DUPLICATE KEY UPDATE status='active'",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS deepl_api_key VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS google_places_api_key VARCHAR(255) DEFAULT NULL",
                "ALTER TABLE scrape_sources MODIFY COLUMN platform ENUM('heureka','trustedshops','shoptet','google') NOT NULL",
            ];
            foreach ($sqls as $sql) {
                try { $db->exec($sql); echo "OK: " . substr($sql, 0, 60) . "...\n"; }
                catch (\Exception $e) { echo "ERR: " . $e->getMessage() . "\n"; }
            }
            echo "Hotovo.\n";
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');

        echo "=== ShopCode Diagnostika " . date('Y-m-d H:i:s') . " ===\n\n";

        echo "--- PHP ---\n";
        echo "Verze: " . PHP_VERSION . "\n";
        echo "SAPI: " . PHP_SAPI . "\n";
        echo "memory_limit: " . ini_get('memory_limit') . "\n";
        echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
        echo "fastcgi_finish_request: " . (function_exists('fastcgi_finish_request') ? 'ANO' : 'NE') . "\n";
        echo "shell_exec: " . (function_exists('shell_exec') ? 'ANO' : 'NE') . "\n";
        echo "exec: " . (function_exists('exec') ? 'ANO' : 'NE') . "\n";
        echo "curl: " . (function_exists('curl_init') ? 'ANO' : 'NE') . "\n";
        echo "ignore_user_abort: " . ignore_user_abort() . "\n\n";

        echo "--- ROOT a složky ---\n";
        echo "ROOT: " . ROOT . "\n";
        $dirs = [
            ROOT . '/public/tmp',
            ROOT . '/public/feeds',
            ROOT . '/public/uploads',
            ROOT . '/tmp',
        ];
        foreach ($dirs as $dir) {
            $exists   = is_dir($dir);
            $writable = $exists && is_writable($dir);
            $created  = !$exists && @mkdir($dir, 0775, true);
            echo str_pad(str_replace(ROOT, '', $dir), 25)
                . " exists=" . ($exists  ? 'ANO' : 'NE')
                . " writable=" . ($writable ? 'ANO' : 'NE')
                . ($created ? ' (vytvořena)' : '') . "\n";
        }
        echo "\n";

        echo "--- Progress soubory ---\n";
        $tmpDir = ROOT . '/tmp';
        $files  = is_dir($tmpDir) ? (glob($tmpDir . '/feed_progress_*.json') ?: []) : [];
        if (empty($files)) {
            echo "Žádné progress soubory\n";
        }
        foreach ($files as $f) {
            $age  = time() - filemtime($f);
            $data = json_decode(file_get_contents($f), true);
            echo basename($f) . " — stáří: {$age}s — " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        }

        echo "\n--- Test zápisu /public/tmp ---\n";
        @mkdir(ROOT . '/public/tmp', 0775, true);
        $testFile = ROOT . '/public/tmp/diag_test.json';
        $ok = file_put_contents($testFile, json_encode(['ok' => true]));
        echo "Zápis: " . ($ok !== false ? "OK ({$ok} bytů)" : "SELHAL") . "\n";
        @unlink($testFile);

        echo "\n--- Databáze ---\n";
        try {
            $pdo  = \ShopCode\Core\Database::getInstance();
            echo "Připojení: OK\n";

            $cols = $pdo->query("SHOW COLUMNS FROM feed_sync_log LIKE 'log_text'")->fetchAll();
            echo "Sloupec log_text: " . (empty($cols) ? 'CHYBÍ — spusť migraci 010!' : 'OK') . "\n\n";

            $logs = $pdo->query("
                SELECT id, feed_id, status, started_at, finished_at, duration_seconds,
                       error_message, LEFT(log_text, 300) as log_preview
                FROM feed_sync_log ORDER BY started_at DESC LIMIT 5
            ")->fetchAll(\PDO::FETCH_ASSOC);

            echo "Posledních " . count($logs) . " sync logů:\n";
            foreach ($logs as $l) {
                echo "  #{$l['id']} feed={$l['feed_id']} status={$l['status']} started={$l['started_at']} duration=" . ($l['duration_seconds'] ?? '?') . "s\n";
                if ($l['error_message']) echo "    ERROR: {$l['error_message']}\n";
                if ($l['log_preview'])   echo "    LOG: " . str_replace("\n", " | ", $l['log_preview']) . "\n";
            }

            $cnt = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            echo "\nProduktů v DB: $cnt\n";
            $cnt2 = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
            echo "Recenzí v DB: $cnt2\n";

        } catch (\Exception $e) {
            echo "CHYBA: " . $e->getMessage() . "\n";
        }

        echo "\n--- Test cURL ---\n";
        $ch = curl_init('https://httpbin.org/get');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        echo "httpbin.org: HTTP $code " . ($err ? "ERROR: $err" : "OK") . "\n";

        // Index check
        $idxCheck = $pdo->query("SHOW INDEX FROM product_videos")->fetchAll(\PDO::FETCH_ASSOC);
        echo "\n=== Indexy product_videos ===\n";
        foreach ($idxCheck as $idx) {
            echo $idx['Key_name'] . ' -> ' . $idx['Column_name'] . "\n";
        }

        // Photo paths check
        echo "\n=== Foto cesty ===\n";
        $photos = $pdo->query("SELECT id, review_id, path, thumb FROM review_photos WHERE path IS NOT NULL LIMIT 5")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($photos as $p) {
            $abs = ROOT . '/public/uploads/' . ltrim($p['path'], '/');
            $exists = file_exists($abs) ? 'OK' : 'CHYBÍ';
            echo "  [{$exists}] {$p['path']}\n";
            echo "         => {$abs}\n";
        }
        if (empty($photos)) echo "  Žádné fotky v DB\n";

        // Hledej soubory kdekoliv na serveru
        echo "\n=== Hledám uploads složky ===\n";
        $dirs = [
            ROOT . '/public/uploads',
            '/srv/app/public/uploads',
            '/var/www/uploads',
        ];
        foreach ($dirs as $d) {
            echo "  " . $d . ": " . (is_dir($d) ? "EXISTS" : "ne") . "\n";
            if (is_dir($d)) {
                $items = scandir($d);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    echo "    - " . $item . "\n";
                }
            }
        }

        // Syntax check klíčových souborů
        echo "\n=== Syntax check ===\n";
        // Načti závislosti přes token_get_all — najde syntax error
        $deps = [
            'src/Core/Database.php',
            'src/Core/Session.php',
            'src/Core/Response.php',
            'src/Core/View.php',
            'src/Core/Mailer.php',
            'src/Models/Review.php',
            'src/Models/Product.php',
            'src/Services/ImageHandler.php',
            'src/Services/CsvGenerator.php',
            'src/Services/XmlFeedGenerator.php',
            'src/Services/Mailer.php',
            'src/Models/WatermarkSettings.php',
        ];
        foreach ($deps as $rel) {
            $path = ROOT . '/' . $rel;
            if (!file_exists($path)) { echo "  CHYBÍ: $rel\n"; continue; }
            $src = file_get_contents($path);
            try {
                $tokens = @token_get_all($src, TOKEN_PARSE);
                echo "  OK: $rel (" . strlen($src) . "b)\n";
            } catch (\ParseError $ex) {
                echo "  SYNTAX ERR: $rel => " . $ex->getMessage() . " (line " . $ex->getLine() . ")\n";
            }
        }
        $checkFiles = [
            'src/Services/Mailer.php',
            'src/Controllers/ReviewController.php',
            'src/Models/Review.php',
        ];
        foreach ($checkFiles as $rel) {
            $path = ROOT . '/' . $rel;
            if (!file_exists($path)) { echo "  CHYBÍ: $rel\n"; continue; }
            $content = file_get_contents($path);
            // Zkontroluj use PHPMailer
            if (strpos($content, 'use PHPMailer') !== false) {
                echo "  PROBLÉM (use PHPMailer): $rel\n";
            } else {
                echo "  OK: $rel (" . strlen($content) . " bytů)\n";
            }
        }

        echo "\n=== Feeds & URL debug ===\n";
        echo "  DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
        echo "  ROOT konstanta: " . ROOT . "\n";
        echo "  Script: " . __FILE__ . "\n";
        // Zkus vytvořit testovací soubor a zjistit jeho URL
        $feedsDir = ROOT . '/public/feeds';
        $feedsDir = ROOT . '/public/feeds';
        echo "  Path: $feedsDir\n";
        echo "  Exists: " . (is_dir($feedsDir) ? 'ANO' : 'NE') . "\n";
        echo "  Writable: " . (is_writable($feedsDir) ? 'ANO' : 'NE') . "\n";
        if (is_dir($feedsDir)) {
            $files = glob($feedsDir . '/*.xml');
            echo "  XML soubory: " . count($files) . "\n";
            foreach ($files as $f) {
                echo "    - " . basename($f) . " (" . filesize($f) . "b) " . date('Y-m-d H:i', filemtime($f)) . "\n";
            }
        }
        echo "\n=== Konec diagnostiky ===\n";
        exit;
    }

    public function scrapeDiag(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') {
            http_response_code(403); die('Forbidden');
        }
        header('Content-Type: text/plain; charset=utf-8');

        $url      = $_GET['url'] ?? '';
        $platform = $_GET['platform'] ?? 'heureka';

        echo "=== Scrape diagnostika ===\n\n";

        if (!$url) {
            echo "Použití: /diag/scrape?key=shopcode_diag&url=https://...&platform=heureka|trustedshops|shoptet\n";
            exit;
        }

        echo "URL: $url\n";
        echo "Platform: $platform\n\n";

        // cURL fetch
        echo "--- cURL fetch ---\n";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: cs-CZ,cs;q=0.9'],
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        echo "HTTP status: $code\n";
        echo "HTML délka: " . strlen((string)$html) . " bytů\n";
        if ($err) echo "cURL error: $err\n";
        if (!$html || $code !== 200) { echo "Nelze stáhnout.\n"; exit; }

        // JSON-LD
        echo "\n--- JSON-LD ---\n";
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $m);
        echo "Bloků: " . count($m[1]) . "\n";
        foreach ($m[1] as $i => $json) {
            $data = @json_decode(trim($json), true);
            $type = $data['@type'] ?? ($data[0]['@type'] ?? 'N/A');
            echo "  [$i] @type=$type";
            if (isset($data['review'])) echo " → recenzí: " . count($data['review']);
            if (isset($data['reviews'])) echo " → recenzí: " . count($data['reviews']);
            echo "\n";
        }

        // CSS třídy
        echo "\n--- CSS třídy ---\n";
        foreach (['c-review','review-item','review-body','review__text','rating-list__item','productReview'] as $cls) {
            $cnt = substr_count($html, $cls);
            if ($cnt > 0) echo "  .$cls: $cnt výskytů\n";
        }

        // TrustedShops pagination debug
        echo "\n--- TrustedShops pagination debug ---\n";
        if (preg_match('/([\d\.]+)\s*Bewertungen\s*insgesamt/i', $html, $pm)) {
            echo "Regex 'insgesamt' match: " . $pm[0] . "\n";
        } else {
            echo "Regex 'insgesamt': NENALEZENO\n";
            // Hledej okolí slova insgesamt
            $pos = stripos($html, 'insgesamt');
            if ($pos !== false) {
                echo "Raw HTML okolí: " . htmlspecialchars(substr($html, max(0,$pos-50), 120)) . "\n";
            }
        }
        // Zkus alternativní pattern
        if (preg_match('/>(\d+)<\/\w+>\s*Bewertungen/i', $html, $pm2)) {
            echo "Alt regex match: " . $pm2[0] . "\n";
        }
        // Zkus page=4 URL a porovnej external_id s page=3
        $testUrl3 = preg_replace('/[?&]page=\d+/', '', $url) . (str_contains($url,'?') ? '&' : '?') . 'page=3';
        $testUrl4 = preg_replace('/[?&]page=\d+/', '', $url) . (str_contains($url,'?') ? '&' : '?') . 'page=4';
        $fetch = function($u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                CURLOPT_ENCODING=>'',CURLOPT_SSL_VERIFYPEER=>false]);
            $r = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            return ($r && $code===200) ? $r : null;
        };
        $getIds = function($html) {
            preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $html, $m);
            $ids = [];
            foreach ($m[1] as $json) {
                $d = @json_decode(trim($json), true);
                foreach ($d['review'] ?? [] as $r) {
                    $ids[] = md5(($r['author']['name']??'').($r['reviewBody']??'').($r['datePublished']??''));
                }
            }
            return $ids;
        };
        $h3 = $fetch($testUrl3);
        $h4 = $fetch($testUrl4);
        $ids3 = $h3 ? $getIds($h3) : [];
        $ids4 = $h4 ? $getIds($h4) : [];
        echo "page=3 IDs: " . count($ids3) . ", page=4 IDs: " . count($ids4) . "\n";
        $overlap = count(array_intersect($ids3, $ids4));
        echo "Překryv page3/page4: $overlap\n";
        if ($overlap === count($ids3) && count($ids3) > 0) echo "⚠ page4 == page3 (duplikát) — stránkování nefunguje za page=3\n";
        else echo "✓ page4 je jiná stránka\n";

        // Zkus page=3 URL
        $testUrl = preg_replace('/[?&]page=\d+/', '', $url) . (str_contains($url,'?') ? '&' : '?') . 'page=3';
        $testHtml = (function($u) {
            $ch = curl_init($u);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                CURLOPT_ENCODING=>'',CURLOPT_SSL_VERIFYPEER=>false]);
            $r = curl_exec($ch); $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            return ($r && $code===200) ? $r : null;
        })($testUrl);
        echo "page=3 fetch: " . ($testHtml ? strlen($testHtml)." bytů" : "FAILED") . "\n";
        if ($testHtml) {
            preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $testHtml, $pm3);
            echo "page=3 JSON-LD bloků: " . count($pm3[1]) . "\n";
            foreach ($pm3[1] as $json) {
                $d = @json_decode(trim($json),true);
                if (isset($d['review'])) echo "page=3 recenzí v JSON-LD: " . count($d['review']) . "\n";
            }
        }

        // Přímý pagination test
        echo "\n--- Přímý pagination test (stránky 1-5) ---\n";
        $baseUrl2 = preg_replace('/[?&]page=\d+/', '', $url);
        $sep2 = str_contains($baseUrl2, '?') ? '&' : '?';
        $allIds = [];
        for ($pg = 1; $pg <= 5; $pg++) {
            $pgUrl = $pg === 1 ? $baseUrl2 : $baseUrl2 . $sep2 . 'page=' . $pg;
            $pgCh = curl_init($pgUrl);
            curl_setopt_array($pgCh, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                CURLOPT_ENCODING=>'',CURLOPT_SSL_VERIFYPEER=>false]);
            $pgHtml = curl_exec($pgCh); $pgCode = curl_getinfo($pgCh,CURLINFO_HTTP_CODE); curl_close($pgCh);
            if (!$pgHtml || $pgCode !== 200) { echo "page=$pg: FAILED\n"; break; }
            preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si', $pgHtml, $pgM);
            $pgIds = [];
            foreach ($pgM[1] as $pgJson) {
                $pgData = @json_decode(trim($pgJson), true);
                foreach ($pgData['review'] ?? [] as $pgR) {
                    $pgIds[] = md5(($pgR['author']['name']??'').($pgR['reviewBody']??'').($pgR['datePublished']??''));
                }
            }
            $overlap2 = count(array_intersect($pgIds, $allIds));
            echo "page=$pg: " . count($pgIds) . " recenzí, překryv s předchozími: $overlap2, první ID: " . ($pgIds[0] ?? 'N/A') . "\n";
            $allIds = array_merge($allIds, $pgIds);
        }

        // Přímý scraper debug — kolik stránek projde
        echo "\n--- Scraper interní debug ---\n";
        $allR2 = [];
        $dbHtml = (function($u) {
            $ch = curl_init($u);
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,
                CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                CURLOPT_ENCODING=>'',CURLOPT_SSL_VERIFYPEER=>false]);
            $r=curl_exec($ch);$c2=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
            return($r&&$c2===200)?$r:null;
        });
        // Zjisti total a pages stejně jako scraper
        $dbScripts=[]; preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si',$html,$dbScripts);
        $dbTotal=0;
        foreach($dbScripts[1] as $j){$d=@json_decode(trim($j),true);if(isset($d['aggregateRating']['reviewCount'])){$dbTotal=(int)$d['aggregateRating']['reviewCount'];break;}}
        if(!$dbTotal){
            $dec=html_entity_decode(html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8'),ENT_QUOTES|ENT_HTML5,'UTF-8');
            if(preg_match('/(\d+)\s*Bewertungen\s*insgesamt/i',$dec,$dm))$dbTotal=(int)str_replace('.','',$dm[1]);
        }
        $dbPages=$dbTotal>0?min((int)ceil($dbTotal/20),50):50;
        echo "total=$dbTotal, pages=$dbPages\n";
        $baseDb=preg_replace('/[?&]page=\d+/','',$url);
        $sepDb=str_contains($baseDb,'?')?'&':'?';
        $p1r=[];preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si',$html,$p1m);
        foreach($p1m[1] as $j){$d=@json_decode(trim($j),true);foreach($d['review']??[]as $r)$p1r[]=$r;}
        echo "page=1: ".count($p1r)." recenzí\n";
        for($pg=2;$pg<=min($dbPages,5);$pg++){
            usleep(200000);
            $pgH=$dbHtml($baseDb.$sepDb.'page='.$pg);
            if(!$pgH){echo "page=$pg: FAILED\n";break;}
            $pgR=[];preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si',$pgH,$pgM);
            foreach($pgM[1] as $j){$d=@json_decode(trim($j),true);foreach($d['review']??[]as $r)$pgR[]=$r;}
            echo "page=$pg: ".count($pgR)." recenzí\n";
            if(empty($pgR))break;
        }

        // Verze scraperu
        echo "\n--- Verze scraperu ---\n";
        $scraperFile = ROOT . '/src/Services/ReviewScraper.php';
        echo "mtime: " . date('Y-m-d H:i:s', filemtime($scraperFile)) . "\n";
        $scraperContent = file_get_contents($scraperFile);
        echo "obsahuje 'seenIds': " . (str_contains($scraperContent, 'seenIds') ? 'ANO (stará verze!)' : 'NE (nová verze OK)') . "\n";
        echo "obsahuje 'error_log': " . (str_contains($scraperContent, 'TS scraper') ? 'ANO' : 'NE') . "\n";
        $linesCount = substr_count($scraperContent, '\n');
        echo "řádků v souboru: $linesCount\n";

        // Vymaž error log před testem
        $errLog = ini_get('error_log') ?: '/srv/app/public/logs/php_errors.log';
        @file_put_contents($errLog, ''); // reset

        // Scraper — přímé volání s detailním logem
        echo "\n--- Scraper výsledek (přímý) ---\n";
        try {
            $fetchFn = function($u) {
                $ch = curl_init($u);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>20,
                    CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
                    CURLOPT_ENCODING=>'',CURLOPT_SSL_VERIFYPEER=>false,
                    CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml','Accept-Language: cs-CZ,cs;q=0.9'],
                ]);
                $r=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
                return($r&&$code===200)?$r:null;
            };
            $extractFn = function($h) {
                preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/si',$h,$m);
                $out=[];
                foreach($m[1] as $json){
                    $d=@json_decode(trim($json),true);
                    foreach($d['review']??[]as $r){
                        $c2=$r['reviewBody']??$r['description']??null;
                        if(!$c2)continue;
                        $out[]=['author'=>(is_string($r['author']['name']??null)?$r['author']['name']:'Anon'),'content'=>trim($c2),'rating'=>(int)($r['reviewRating']['ratingValue']??0)];
                    }
                }
                return $out;
            };
            $base2=preg_replace('/[?&]page=\d+/','',$url);
            $sep2=str_contains($base2,'?')?'&':'?';
            $all2=[];
            for($pg=1;$pg<=9;$pg++){
                $pgUrl=$pg===1?$base2:$base2.$sep2.'page='.$pg;
                $pgH=$fetchFn($pgUrl);
                if(!$pgH){echo "page=$pg: FETCH FAILED\n";break;}
                $pgR=$extractFn($pgH);
                echo "page=$pg: ".count($pgR)." recenzí\n";
                if(empty($pgR))break;
                $all2=array_merge($all2,$pgR);
                usleep(200000);
            }
            echo "CELKEM: ".count($all2)."\n";
            foreach(array_slice($all2,0,2) as $i=>$r){
                echo "  [$i] {$r['author']}: ".mb_substr($r['content'],0,80)."\n";
            }
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }

        // Přes ReviewScraper::scrape()
        echo "\n--- ReviewScraper::scrape() ---\n";
        echo "mtime: " . date('Y-m-d H:i:s', filemtime(ROOT.'/src/Services/ReviewScraper.php')) . "\n";
        try {
            $reviews = \ShopCode\Services\ReviewScraper::scrape($url, $platform);
            echo "Nalezeno: " . count($reviews) . "\n";
        } catch (\Throwable $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }

        // Error log výstup
        echo "\n--- PHP error_log (scraper) ---\n";
        $errLog = ini_get('error_log') ?: '/srv/app/public/logs/php_errors.log';
        echo "Log file: $errLog\n";
        if (file_exists($errLog)) {
            $lines = file($errLog);
            foreach ($lines as $line) {
                if (str_contains($line, 'TS scraper')) echo trim($line) . "\n";
            }
        } else {
            echo "Soubor neexistuje\n";
        }

        // HTML ukázka
        echo "\n--- HTML body (2000 znaků) ---\n";
        preg_match('/<body[^>]*>(.*)/si', $html, $bm);
        $body = preg_replace('/\s+/', ' ', strip_tags($bm[1] ?? $html));
        echo mb_substr(trim($body), 0, 2000) . "\n";
    }
}
