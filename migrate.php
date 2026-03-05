<?php
/**
 * Migrace databáze — spusť z rootu projektu:
 *   php migrate.php
 */

define('ROOT', __DIR__);
require ROOT . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Vytvoř tabulku pro sledování migrací
$pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`   VARCHAR(255) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Načti již aplikované migrace
$applied = $pdo->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

// Načti všechny SQL soubory seřazené
$files = glob(ROOT . '/database/migrations/*.sql');
sort($files);

$ran = 0;
$skipped = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        echo "  SKIP  $name\n";
        $skipped++;
        continue;
    }

    $sql = file_get_contents($file);

    // Rozdělení na příkazy (odstraň komentáře)
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    try {
        foreach ($statements as $stmt) {
            if (!empty($stmt)) $pdo->exec($stmt);
        }
        $pdo->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$name]);
        echo "  OK    $name\n";
        $ran++;
    } catch (PDOException $e) {
        echo "  CHYBA $name: " . $e->getMessage() . "\n";
        // Pokračuj na další migraci
    }
}

echo "\nHotovo: $ran aplikováno, $skipped přeskočeno.\n";
