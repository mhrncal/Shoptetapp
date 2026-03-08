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
                "CREATE TABLE IF NOT EXISTS photo_export_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, exported_at DATETIME NOT NULL, photo_count INT DEFAULT 0, INDEX idx_pel_user (user_id))",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo_warning_sent_at DATETIME DEFAULT NULL",
                "CREATE TABLE IF NOT EXISTS scrape_sources (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(1000) NOT NULL, platform ENUM('heureka','trustedshops','shoptet') NOT NULL, is_active TINYINT(1) DEFAULT 1, last_scraped_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NOW(), INDEX idx_ss_user (user_id))",
                "CREATE TABLE IF NOT EXISTS scraped_reviews (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, source_id INT NOT NULL, external_id VARCHAR(255) DEFAULT NULL, author VARCHAR(255) DEFAULT NULL, rating TINYINT DEFAULT NULL, content TEXT, reviewed_at DATE DEFAULT NULL, scraped_at DATETIME DEFAULT NOW(), UNIQUE KEY uniq_source_external (source_id, external_id), INDEX idx_sr_user (user_id), INDEX idx_sr_source (source_id))",
                "CREATE TABLE IF NOT EXISTS scraped_review_translations (id INT AUTO_INCREMENT PRIMARY KEY, review_id INT NOT NULL, lang VARCHAR(5) NOT NULL, content TEXT, translated_at DATETIME DEFAULT NOW(), UNIQUE KEY uniq_rt_review_lang (review_id, lang), INDEX idx_srt_review (review_id))",
                "CREATE TABLE IF NOT EXISTS user_translation_langs (user_id INT NOT NULL, lang VARCHAR(5) NOT NULL, PRIMARY KEY (user_id, lang))",
                "ALTER TABLE reviews ADD COLUMN IF NOT EXISTS xml_exported_at DATETIME DEFAULT NULL",
                "ALTER TABLE review_photos ADD COLUMN IF NOT EXISTS shoptet_url VARCHAR(1000) DEFAULT NULL",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('reviews','Fotorecenze','Sběr a správa fotorecenzí zákazníků','camera','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO modules (name, label, description, icon, version, is_system_module) VALUES ('scraped_reviews','Scrapované recenze','Automatický sběr recenzí z Heureka, Trusted Shops a Shoptet','search','1.0.0',1) ON DUPLICATE KEY UPDATE label=VALUES(label)",
                "INSERT INTO user_modules (user_id, module_id, status, activated_at) SELECT u.id, m.id, 'active', NOW() FROM users u CROSS JOIN modules m WHERE u.role='superadmin' ON DUPLICATE KEY UPDATE status='active'",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS deepl_api_key VARCHAR(255) DEFAULT NULL",
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
}
