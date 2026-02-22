#!/usr/bin/env php
<?php

/**
 * XML Queue Worker — Cron script
 *
 * Doporučené nastavení crontabu (každých 5 minut):
 *   * /5 * * * * php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode-xml.log 2>&1
 *
 * Nebo pro provoz po celý den každou minutu:
 *   * * * * * php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode-xml.log 2>&1
 *
 * Script sám hlídá:
 * - Nepustí druhou instanci (lock soubor)
 * - Zpracuje max. N položek za jedno spuštění
 * - Uvolní zaseknuté položky (> 2h ve stavu processing)
 * - Nastaví PHP limity pro dlouhé běhy
 */

define('ROOT', dirname(__DIR__));

// ---- PHP limity pro velké soubory ----
ini_set('memory_limit', '512M');
set_time_limit(0);                    // Bez časového limitu
ini_set('max_execution_time', 0);

// ---- Autoloader ----
spl_autoload_register(function (string $class): void {
    $prefix = 'ShopCode\\';
    $base   = ROOT . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $file = $base . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) require $file;
});

// ---- Konfigurace ----
$configFile = ROOT . '/config/config.php';
if (!file_exists($configFile)) {
    echo "[ERROR] Chybí config/config.php\n";
    exit(1);
}
require_once $configFile;

// ---- Lock soubor (zabrání souběžnému spuštění) ----
$lockFile = ROOT . '/tmp/xml-worker.lock';
if (!is_dir(ROOT . '/tmp')) mkdir(ROOT . '/tmp', 0750, true);

$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "[" . date('Y-m-d H:i:s') . "] Jiná instance workeru již běží, přeskakuji.\n";
    fclose($lockFp);
    exit(0);
}

// Zapiš PID
ftruncate($lockFp, 0);
fwrite($lockFp, (string)getmypid());

echo "[" . date('Y-m-d H:i:s') . "] ===== XML Worker START (PID: " . getmypid() . ") =====\n";

$maxItemsPerRun = defined('WORKER_MAX_ITEMS') ? WORKER_MAX_ITEMS : 5;
$processed      = 0;

try {
    $worker = new \ShopCode\Workers\QueueWorker();

    // Uvolni zaseknuté položky
    $stuck = $worker->releaseStuck();
    if ($stuck > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ♻️  Uvolněno {$stuck} zaseknutých položek\n";
    }

    // Zpracuj položky fronty
    while ($processed < $maxItemsPerRun) {
        if (!$worker->processNext()) {
            echo "[" . date('Y-m-d H:i:s') . "] 📭 Fronta je prázdná\n";
            break;
        }
        $processed++;
    }

} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ FATÁLNÍ CHYBA: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    // Uvolni lock
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);
}

echo "[" . date('Y-m-d H:i:s') . "] ===== XML Worker END | Zpracováno: {$processed} =====\n";
exit(0);
