<?php
// PRODUCTION GUARD — smaž nebo zakomentuj pro produkci
if (!isset($_GET['dev_key']) || $_GET['dev_key'] !== (defined('APP_DEBUG_KEY') ? APP_DEBUG_KEY : '')) {
    http_response_code(404);
    die('Not found');
}
/**
 * List soubory na serveru - zjisti kde jsou assets
 */

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>File List</title></head><body><pre>";

echo "=== SERVER FILE LISTING ===\n\n";

echo "Current directory: " . __DIR__ . "\n\n";

echo "=== Contents of " . __DIR__ . " ===\n";
$files = scandir(__DIR__);
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $path = __DIR__ . '/' . $file;
        $type = is_dir($path) ? '[DIR]' : '[FILE]';
        $size = is_file($path) ? filesize($path) . ' bytes' : '';
        echo "$type $file $size\n";
    }
}

echo "\n=== Contents of " . __DIR__ . "/assets ===\n";
if (is_dir(__DIR__ . '/assets')) {
    $cmd = "find " . escapeshellarg(__DIR__ . '/assets') . " -type f";
    $output = shell_exec($cmd);
    echo $output;
} else {
    echo "Directory does not exist!\n";
}

echo "\n=== Contents of /srv/app/public/assets ===\n";
if (is_dir('/srv/app/public/assets')) {
    $cmd = "find /srv/app/public/assets -type f";
    $output = shell_exec($cmd);
    echo $output;
} else {
    echo "Directory does not exist!\n";
}

echo "\n=== Web root test ===\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'not set') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'not set') . "\n";

echo "</pre></body></html>";
