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
                "ALTER TABLE scraped_reviews ADD COLUMN IF NOT EXISTS source_lang VARCHAR(5) DEFAULT NULL",
                "ALTER TABLE scraped_review_translations ADD COLUMN IF NOT EXISTS is_deepl TINYINT(1) DEFAULT 1",
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
                "ALTER TABLE scraped_reviews ADD COLUMN IF NOT EXISTS source_lang VARCHAR(5) DEFAULT NULL",
                "ALTER TABLE scraped_review_translations ADD COLUMN IF NOT EXISTS is_deepl TINYINT(1) DEFAULT 1",
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

        // Vymaž error log před testem
        $errLog = ini_get('error_log') ?: '/srv/app/public/logs/php_errors.log';
        @file_put_contents($errLog, ''); // reset

        echo "\n--- Scraper výsledek ---\n";
        try {
            $reviews = \ShopCode\Services\ReviewScraper::scrape($url, $platform);
            echo "Nalezeno: " . count($reviews) . "\n";
            foreach (array_slice($reviews, 0, 3) as $i => $r) {
                echo "  [$i] author={$r['author']} rating={$r['rating']}\n";
                echo "      " . mb_substr($r['content'], 0, 120) . "\n";
            }
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

    public function dedupReviews(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        $db = \ShopCode\Core\Database::getInstance();

        // Najdi duplicity — stejný author+content+reviewed_at v rámci jednoho source_id
        $stmt = $db->query("
            SELECT source_id, author, content, reviewed_at, COUNT(*) as cnt, MIN(id) as keep_id, MAX(id) as drop_id
            FROM scraped_reviews
            GROUP BY source_id, author, content, reviewed_at
            HAVING cnt > 1
        ");
        $dupes = $stmt->fetchAll();
        echo "Nalezeno duplicitních skupin: " . count($dupes) . "\n\n";

        $deleted = 0;
        foreach ($dupes as $d) {
            // Přesuň překlady z drop_id na keep_id pokud existují
            $db->prepare("
                INSERT IGNORE INTO scraped_review_translations (review_id, lang, content, is_deepl, translated_at)
                SELECT ?, lang, content, is_deepl, translated_at
                FROM scraped_review_translations WHERE review_id = ?
            ")->execute([$d['keep_id'], $d['drop_id']]);

            // Smaž překlady drop_id
            $db->prepare("DELETE FROM scraped_review_translations WHERE review_id = ?")->execute([$d['drop_id']]);

            // Smaž duplicitní recenzi
            $db->prepare("DELETE FROM scraped_reviews WHERE id = ?")->execute([$d['drop_id']]);

            echo "Smazáno id={$d['drop_id']}, zachováno id={$d['keep_id']} (author={$d['author']})\n";
            $deleted++;
        }

        echo "\nHotovo. Smazáno: $deleted duplicit.\n";
    }

    public function detectLangs(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        // Načti DeepL klíč prvního uživatele který ho má
        $db   = \ShopCode\Core\Database::getInstance();
        $stmt = $db->query("SELECT id, deepl_api_key FROM users WHERE deepl_api_key IS NOT NULL LIMIT 1");
        $user = $stmt->fetch();
        if (!$user) { echo "Žádný uživatel nemá DeepL klíč.\n"; exit; }

        $deepl = new \ShopCode\Services\DeepLTranslator($user['deepl_api_key']);

        // Recenze bez source_lang s neprázdným contentem
        $stmt = $db->query("SELECT id, content FROM scraped_reviews WHERE source_lang IS NULL AND content != '' AND content IS NOT NULL LIMIT 200");
        $rows = $stmt->fetchAll();
        echo "Recenzí bez source_lang: " . count($rows) . "\n\n";

        $updated = 0;
        foreach ($rows as $r) {
            $lang = $deepl->detectLang($r['content']);
            if ($lang) {
                $db->prepare("UPDATE scraped_reviews SET source_lang = ? WHERE id = ?")->execute([$lang, $r['id']]);
                echo "id={$r['id']}: $lang\n";
                $updated++;
            }
            usleep(200000);
        }
        echo "\nHotovo. Aktualizováno: $updated\n";
    }

    public function fixCsTranslations(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        $db = \ShopCode\Core\Database::getInstance();

        // Smaž všechny CS překlady — budou přegenerovány správně
        $stmt = $db->query("DELETE FROM scraped_review_translations WHERE lang = 'CS'");
        $deleted = $stmt->rowCount();
        echo "Smazáno CS překladů: $deleted\n";

        // Reset source_lang — bude znovu detekován
        $db->query("UPDATE scraped_reviews SET source_lang = NULL WHERE source_lang IS NOT NULL");
        echo "Reset source_lang hotov.\n";
        echo "\nNyní klikni 'Přeložit nepřeložené' v modulu.\n";
    }

    public function testHeureka(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        $url = $_GET['url'] ?? '';
        if (!$url) { echo "Chybí ?url=..."; exit; }

        echo "1. Fetching URL: $url\n";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $xml  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        echo "HTTP: $code, cURL error: " . ($err ?: 'none') . "\n";
        echo "Response length: " . strlen($xml) . " bytes\n";
        echo "First 300 chars:\n" . substr($xml, 0, 300) . "\n\n";

        if (!$xml) { echo "Prázdná odpověď."; exit; }

        echo "2. Parsing XML...\n";
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        $errs = libxml_get_errors();
        libxml_clear_errors();

        if (!$doc) {
            echo "XML parse FAILED:\n";
            foreach ($errs as $e) echo "  Line {$e->line}: {$e->message}";
            exit;
        }

        $count = count($doc->review);
        echo "XML OK. Počet <review> elementů: $count\n\n";

        $i = 0;
        foreach ($doc->review as $r) {
            echo "Review #{$i}: rating_id={$r->rating_id}, total_rating={$r->total_rating}, ts={$r->unix_timestamp}\n";
            echo "  pros: " . mb_substr((string)$r->pros, 0, 80) . "\n";
            echo "  summary: " . mb_substr((string)$r->summary, 0, 80) . "\n";
            if (++$i >= 3) break;
        }
    }

    public function heurekaCount(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        $db = \ShopCode\Core\Database::getInstance();

        $stmt = $db->query("
            SELECT ss.id, ss.name, ss.platform, ss.url, COUNT(sr.id) as cnt
            FROM scrape_sources ss
            LEFT JOIN scraped_reviews sr ON sr.source_id = ss.id
            GROUP BY ss.id
        ");
        foreach ($stmt->fetchAll() as $r) {
            echo "source_id={$r['id']} [{$r['platform']}] {$r['name']}: {$r['cnt']} recenzí\n";
            echo "  URL: {$r['url']}\n";
        }

        // Ukázkové volání scraperu
        $url = $_GET['url'] ?? '';
        if ($url) {
            echo "\n--- Scraping $url ---\n";
            $result = \ShopCode\Services\ReviewScraper::scrape($url, 'heureka');
            echo "Počet vrácených recenzí: " . count($result) . "\n";
            if (!empty($result[0])) {
                echo "První: " . json_encode($result[0], JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }

    public function testShoptet(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') { http_response_code(403); die('Forbidden'); }
        header('Content-Type: text/plain; charset=utf-8');

        $url = $_GET['url'] ?? 'https://www.svihej.cz/hodnoceni-obchodu/';
        echo "URL: $url\n---\n";

        $result = \ShopCode\Services\ReviewScraper::scrape($url, 'shoptet');
        echo "Počet recenzí: " . count($result) . "\n";
        if (!empty($result[0])) {
            echo "První: " . json_encode($result[0], JSON_UNESCAPED_UNICODE) . "\n";
        }
        if (!empty($result[1])) {
            echo "Druhá: " . json_encode($result[1], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
