<?php
/**
 * Diagnostika syncu — pouze pro admina
 * URL: /diag.php?key=shopcode_diag
 */
if (($_GET['key'] ?? '') !== 'shopcode_diag') {
    http_response_code(403); die('Forbidden');
}

define('ROOT', dirname(__DIR__));
header('Content-Type: text/plain; charset=utf-8');

echo "=== ShopCode Diagnostika " . date('Y-m-d H:i:s') . " ===\n\n";

// 1. PHP info
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

// 2. Složky
echo "--- Složky a práva ---\n";
$dirs = [
    ROOT . '/public/tmp',
    ROOT . '/public/feeds',
    ROOT . '/public/uploads',
    ROOT . '/tmp',
];
foreach ($dirs as $dir) {
    $exists   = is_dir($dir);
    $writable = $exists && is_writable($dir);
    $created  = false;
    if (!$exists) {
        $created = @mkdir($dir, 0775, true);
    }
    echo str_pad(str_replace(ROOT, '', $dir), 25) . " exists=" . ($exists?'ANO':'NE') 
        . " writable=" . ($writable?'ANO':'NE')
        . ($created ? ' (právě vytvořena)' : '')
        . "\n";
}
echo "\n";

// 3. Progress soubory
echo "--- Progress soubory ---\n";
$tmpDir = ROOT . '/public/tmp';
if (is_dir($tmpDir)) {
    $files = glob($tmpDir . '/feed_progress_*.json') ?: [];
    if (empty($files)) {
        echo "Žádné progress soubory\n";
    }
    foreach ($files as $f) {
        $age  = time() - filemtime($f);
        $data = json_decode(file_get_contents($f), true);
        echo basename($f) . " — stáří: {$age}s — obsah: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "Složka /public/tmp neexistuje!\n";
}
echo "\n";

// 4. DB spojení a feed_sync_log
echo "--- Databáze ---\n";
try {
    require ROOT . '/config/config.php';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Připojení: OK\n";

    // Posledních 5 logů
    $logs = $pdo->query("SELECT id, feed_id, status, started_at, finished_at, duration_seconds, error_message, LEFT(log_text,200) as log_preview FROM feed_sync_log ORDER BY started_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "Posledních " . count($logs) . " sync logů:\n";
    foreach ($logs as $l) {
        echo "  #{$l['id']} feed={$l['feed_id']} status={$l['status']} started={$l['started_at']} duration=" . ($l['duration_seconds'] ?? '?') . "s\n";
        if ($l['error_message']) echo "    ERROR: {$l['error_message']}\n";
        if ($l['log_preview'])   echo "    LOG: " . str_replace("\n", " | ", $l['log_preview']) . "\n";
    }

    // Počty
    $cnt = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "\nProduktů v DB: $cnt\n";
    $cnt2 = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
    echo "Recenzí v DB: $cnt2\n";

    // Zkontroluj log_text sloupec
    $cols = $pdo->query("SHOW COLUMNS FROM feed_sync_log LIKE 'log_text'")->fetchAll();
    echo "Sloupec log_text: " . (empty($cols) ? 'CHYBÍ — spusť migraci 010!' : 'OK') . "\n";

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. Test zápisu progress souboru
echo "--- Test zápisu progress souboru ---\n";
$testFile = ROOT . '/public/tmp/diag_test.json';
@mkdir(dirname($testFile), 0775, true);
$ok = file_put_contents($testFile, json_encode(['test' => true, 'time' => date('H:i:s')]));
echo "Zápis do /public/tmp: " . ($ok !== false ? "OK ({$ok} bytů)" : "SELHAL") . "\n";
@unlink($testFile);

// 6. Test cURL
echo "\n--- Test cURL (malý soubor) ---\n";
$ch = curl_init('https://httpbin.org/get');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$r = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);
echo "httpbin.org: HTTP $code " . ($err ? "ERROR: $err" : "OK") . "\n";

echo "\n=== Konec diagnostiky ===\n";
