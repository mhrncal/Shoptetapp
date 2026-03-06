<?php

namespace ShopCode\Controllers;

class DiagController extends BaseController
{
    public function index(): void
    {
        if (($_GET['key'] ?? '') !== 'shopcode_diag') {
            http_response_code(403); die('Forbidden');
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

        echo "\n=== Konec diagnostiky ===\n";
        exit;
    }
}
