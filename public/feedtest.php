<?php
$feedsDir = dirname(__DIR__) . '/public/feeds';
$docRoot  = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';
$scriptDir = __DIR__;

echo "DOCUMENT_ROOT: $docRoot\n";
echo "Script dir: $scriptDir\n";
echo "Feeds dir: $feedsDir\n";
echo "Feeds exists: " . (is_dir($feedsDir) ? 'ANO' : 'NE') . "\n";
echo "Feeds writable: " . (is_writable($feedsDir) ? 'ANO' : 'NE') . "\n\n";

$xml = $feedsDir . '/user_1_reviews.xml';
echo "XML path: $xml\n";
echo "XML exists: " . (file_exists($xml) ? 'ANO' : 'NE') . "\n";
if (file_exists($xml)) {
    echo "XML size: " . filesize($xml) . "b\n";
    echo "XML mtime: " . date('Y-m-d H:i:s', filemtime($xml)) . "\n";
}

echo "\nFiles in feeds dir:\n";
if (is_dir($feedsDir)) {
    foreach (scandir($feedsDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "  $f (" . filesize("$feedsDir/$f") . "b)\n";
    }
}

echo "\nURL test — zkus:\n";
echo "  https://aplikace.shopcode.cz/public/feeds/user_1_reviews.xml\n";
