<?php
/**
 * Test spuštění aplikace s error catchem
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Test App</title></head><body><pre>";

try {
    // ROOT musí být /srv/app (ne /srv)
    define('ROOT', __DIR__);
    echo "ROOT: " . ROOT . "\n\n";
    
    echo "1. Načítám autoloader...\n";
    spl_autoload_register(function (string $class): void {
        $prefix = 'ShopCode\\';
        $base   = ROOT . '/src/';
        if (!str_starts_with($class, $prefix)) return;
        $relative = substr($class, strlen($prefix));
        $file     = $base . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
    echo "✅ Autoloader OK\n";
    
    echo "2. Načítám config...\n";
    require ROOT . '/config/config.php';
    echo "✅ Config OK\n";
    
    echo "3. Testuji třídy...\n";
    echo "   - ShopCode\\Core\\App: " . (class_exists('ShopCode\\Core\\App') ? 'OK' : 'CHYBÍ') . "\n";
    echo "   - ShopCode\\Core\\Session: " . (class_exists('ShopCode\\Core\\Session') ? 'OK' : 'CHYBÍ') . "\n";
    echo "   - ShopCode\\Core\\Router: " . (class_exists('ShopCode\\Core\\Router') ? 'OK' : 'CHYBÍ') . "\n";
    echo "   - ShopCode\\Core\\Request: " . (class_exists('ShopCode\\Core\\Request') ? 'OK' : 'CHYBÍ') . "\n";
    
    echo "4. Spouštím App::run()...\n";
    \ShopCode\Core\App::run();
    
} catch (Throwable $e) {
    echo "\n❌ CHYBA:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if ($prev = $e->getPrevious()) {
        echo "\nPředchozí chyba:\n";
        echo "Message: " . $prev->getMessage() . "\n";
        echo "File: " . $prev->getFile() . ":" . $prev->getLine() . "\n";
    }
    
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    
    if (file_exists($e->getFile())) {
        $lines = file($e->getFile());
        $errorLine = $e->getLine();
        echo "\nKód kolem chyby (řádek $errorLine):\n";
        for ($i = max(0, $errorLine - 3); $i < min(count($lines), $errorLine + 3); $i++) {
            $marker = ($i + 1) == $errorLine ? '>>> ' : '    ';
            echo $marker . ($i + 1) . ": " . htmlspecialchars($lines[$i]);
        }
    }
}

echo "</pre></body></html>";
