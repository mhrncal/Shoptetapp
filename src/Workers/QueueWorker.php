<?php

namespace ShopCode\Workers;

use ShopCode\Core\Database;
use ShopCode\Services\{XmlDownloader, XmlParser, XmlImporter};
use PDO;

/**
 * Zpracuje jednu položku XML fronty.
 * Volá se z cron scriptu.
 *
 * Flow:
 * 1. Zamkne položku (status = processing, FOR UPDATE)
 * 2. Stáhne XML feed na disk
 * 3. Streamovacím parserem zpracuje produkt po produktu
 * 4. Batch INSERT do DB po 500 produktech
 * 5. Označí jako completed / failed (retry)
 */
class QueueWorker
{
    private const TMP_DIR      = ROOT . '/tmp/xml/';
    private const LOCK_TIMEOUT = 7200; // 2h — po této době se zaseknutá položka uvolní

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTmpDir();
    }

    /**
     * Zpracuje jednu položku fronty.
     * Vrátí true pokud bylo co zpracovat, false pokud je fronta prázdná.
     */
    public function processNext(): bool
    {
        $item = $this->lockNextItem();
        if (!$item) return false;

        $tmpFile = self::TMP_DIR . "feed_{$item['id']}_{$item['user_id']}.xml";

        try {
            $this->log($item['id'], "🚀 Zahájení zpracování | URL: {$item['xml_feed_url']}");

            // ---- Krok 1: Stažení feedu ----
            $this->log($item['id'], "⬇️  Stahuji XML feed...");
            $download = XmlDownloader::download($item['xml_feed_url'], $tmpFile);

            if (!$download['ok']) {
                throw new \RuntimeException("Stahování selhalo: {$download['error']}");
            }

            $sizeMb = round($download['size'] / 1024 / 1024, 2);
            $this->log($item['id'], "✅ Staženo {$sizeMb} MB");

            // ---- Krok 2: Zaznamenáme XML import ----
            $importId = $this->createImportRecord($item['user_id'], $item['id']);

            // ---- Krok 3: Parsování + import ----
            $importer = new XmlImporter($item['user_id'], $item['id']);

            $parseResult = XmlParser::stream(
                $tmpFile,
                fn($product, $variants) => $importer->addProduct($product, $variants),
                fn($count) => $this->log($item['id'], "  ↻ Zpracováno: {$count}")
            );

            $stats = $importer->finish();

            $this->log($item['id'],
                "✅ Hotovo | Produktů: {$stats['processed']} | "
                . "Nových: {$stats['inserted']} | Akt.: {$stats['updated']} | "
                . "Chyb parseru: {$parseResult['errors']}"
            );

            // ---- Krok 4: Označení jako completed ----
            $this->markCompleted($item['id'], $stats, $importId);

        } catch (\Throwable $e) {
            $this->log($item['id'], "❌ CHYBA: " . $e->getMessage());
            $this->markFailed($item, $e->getMessage());
        } finally {
            // Vždy smaž tmp soubor
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        return true;
    }

    /**
     * Uvolní zaseknuté položky (déle než LOCK_TIMEOUT ve stavu processing)
     */
    public function releaseStuck(): int
    {
        $stmt = $this->db->prepare("
            UPDATE xml_processing_queue
            SET status      = 'pending',
                updated_at  = NOW()
            WHERE status     = 'processing'
              AND started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
              AND retry_count < max_retries
        ");
        $stmt->execute([self::LOCK_TIMEOUT]);
        return $stmt->rowCount();
    }

    // ---- Private helpers ----

    /**
     * Atomicky zamkne další čekající položku fronty.
     * Používá SELECT ... FOR UPDATE aby nehrozil race condition při více cron instancích.
     */
    private function lockNextItem(): ?array
    {
        $this->db->beginTransaction();

        $stmt = $this->db->query("
            SELECT * FROM xml_processing_queue
            WHERE status = 'pending'
              AND retry_count < max_retries
            ORDER BY priority ASC, created_at ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
        ");
        $item = $stmt->fetch();

        if (!$item) {
            $this->db->commit();
            return null;
        }

        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status     = 'processing',
                started_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$item['id']]);

        $this->db->commit();
        return $item;
    }

    private function markCompleted(int $queueId, array $stats, int $importId): void
    {
        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status               = 'completed',
                progress_percentage  = 100,
                products_processed   = ?,
                completed_at         = NOW(),
                updated_at           = NOW()
            WHERE id = ?
        ")->execute([$stats['processed'], $queueId]);

        $this->db->prepare("
            UPDATE xml_imports
            SET status             = 'completed',
                products_imported  = ?,
                products_updated   = ?,
                completed_at       = NOW()
            WHERE id = ?
        ")->execute([$stats['inserted'], $stats['updated'], $importId]);
    }

    private function markFailed(array $item, string $error): void
    {
        $newRetryCount = (int)$item['retry_count'] + 1;
        $maxRetries    = (int)$item['max_retries'];
        $isFinal       = $newRetryCount >= $maxRetries;
        $newStatus     = $isFinal ? 'failed' : 'pending'; // pending = zkusí znovu příště

        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status        = ?,
                retry_count   = ?,
                error_message = ?,
                updated_at    = NOW()
            WHERE id = ?
        ")->execute([$newStatus, $newRetryCount, substr($error, 0, 1000), $item['id']]);
    }

    private function createImportRecord(int $userId, int $queueId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO xml_imports (user_id, status, started_at, queue_id)
            VALUES (?, 'processing', NOW(), ?)
        ");
        $stmt->execute([$userId, $queueId]);
        return (int)$this->db->lastInsertId();
    }

    private function log(int $queueId, string $message): void
    {
        $time = date('Y-m-d H:i:s');
        // Výstup na stdout (cron to zachytí do logu)
        echo "[{$time}] [Queue#{$queueId}] {$message}\n";
        flush();
    }

    private function ensureTmpDir(): void
    {
        if (!is_dir(self::TMP_DIR)) {
            mkdir(self::TMP_DIR, 0750, true);
        }
    }
}
