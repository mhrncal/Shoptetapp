<?php

namespace ShopCode\Workers;

use ShopCode\Core\Database;
use ShopCode\Services\{XmlDownloader, XmlParser, CsvParser, XmlImporter};
use PDO;

class QueueWorker
{
    private const TMP_DIR      = ROOT . '/tmp/xml/';
    private const LOCK_TIMEOUT = 7200;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTmpDir();
    }

    public function processNext(): bool
    {
        $item = $this->lockNextItem();
        if (!$item) return false;

        $format   = $item['feed_format'] ?? 'xml';
        $fieldMap = $item['field_map'] ? json_decode($item['field_map'], true) : [];
        $ext      = $format === 'csv' ? 'csv' : 'xml';
        $tmpFile  = self::TMP_DIR . "feed_{$item['id']}_{$item['user_id']}.{$ext}";

        try {
            $this->log($item['id'], "🚀 Zahájení zpracování | Formát: " . strtoupper($format) . " | URL: {$item['xml_feed_url']}");

            // Stažení feedu
            $this->log($item['id'], "⬇️  Stahuji feed...");
            $download = XmlDownloader::download($item['xml_feed_url'], $tmpFile);
            if (!$download['ok']) {
                throw new \RuntimeException("Stahování selhalo: {$download['error']}");
            }
            $sizeMb = round($download['size'] / 1024 / 1024, 2);
            $this->log($item['id'], "✅ Staženo {$sizeMb} MB");

            $importId = $this->createImportRecord($item['user_id'], $item['id']);
            $importer = new XmlImporter($item['user_id'], $item['id']);

            // Parsování podle formátu
            if ($format === 'csv') {
                $parseResult = $this->parseCsv($item, $tmpFile, $importer, $fieldMap);
            } else {
                $parseResult = $this->parseXml($item, $tmpFile, $importer, $fieldMap);
            }

            $stats = $importer->finish();

            $this->log($item['id'],
                "✅ Hotovo | Produktů: {$stats['processed']} | " .
                "Nových: {$stats['inserted']} | Akt.: {$stats['updated']} | " .
                "Chyb parseru: {$parseResult['errors']}"
            );

            $this->markCompleted($item['id'], $stats, $importId);

            try {
                \ShopCode\Services\WebhookDispatcher::fire($item['user_id'], 'import.completed', [
                    'queue_id'          => $item['id'],
                    'format'            => $format,
                    'products_inserted' => $stats['inserted'],
                    'products_updated'  => $stats['updated'],
                    'products_total'    => $stats['processed'],
                ]);
            } catch (\Throwable $e) {
                $this->log($item['id'], "⚠️ Webhook fire failed: " . $e->getMessage());
            }

        } catch (\Throwable $e) {
            $this->log($item['id'], "❌ CHYBA: " . $e->getMessage());
            $this->markFailed($item, $e->getMessage());

            try {
                \ShopCode\Services\WebhookDispatcher::fire($item['user_id'], 'import.failed', [
                    'queue_id' => $item['id'],
                    'feed_url' => $item['xml_feed_url'],
                    'error'    => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {}

        } finally {
            if (file_exists($tmpFile)) @unlink($tmpFile);
        }

        return true;
    }

    // ---- CSV parsování ----

    private function parseCsv(array $item, string $tmpFile, XmlImporter $importer, array $fieldMap): array
    {
        return CsvParser::stream(
            $tmpFile,
            function (array $product, array $variantCodes) use ($importer, $fieldMap) {
                $mapped = $this->remapCsvProduct($product, $variantCodes);
                $importer->addProduct($mapped['product'], $mapped['variants']);
            },
            $fieldMap,  // předáme fieldMap přímo do parseru
            fn($count) => $this->log($item['id'], "  ↻ Zpracováno: {$count}")
        );
    }

    /**
     * Převede výstup z CsvParser do formátu pro XmlImporter.
     * CsvParser (s fieldMap) vrací: code, name, category, price, brand, description,
     * availability, images, pair_code — všechna pole jsou již správně namapována.
     */
    private function remapCsvProduct(array $product, array $variantCodes): array
    {
        // Price — převod na float
        $price = null;
        if (!empty($product['price'])) {
            $priceStr = str_replace([' ', ','], ['', '.'], $product['price']);
            $price    = is_numeric($priceStr) ? (float)$priceStr : null;
        }

        // Images — z CSV typicky URL v textovém poli
        $images = null;
        if (!empty($product['images'])) {
            $urls = array_filter(array_map('trim', explode('|', $product['images'])));
            if ($urls) $images = json_encode(array_values($urls), JSON_UNESCAPED_UNICODE);
        }

        $mapped = [
            'shoptet_id'   => $product['pair_code'] ?? $product['code'] ?? null,
            'code'         => $product['code'],
            'name'         => $product['name']         ?? null,
            'description'  => $product['description']  ?? null,
            'price'        => $price,
            'currency'     => $product['currency']     ?? 'CZK',
            'category'     => $product['category']     ?? null,
            'brand'        => $product['brand']        ?? null,
            'availability' => $product['availability'] ?? null,
            'images'       => $images,
            'parameters'   => null,
            'xml_data'     => null,
        ];

        $variants = [];
        foreach ($variantCodes as $vCode) {
            $variants[] = [
                'shoptet_variant_id' => $vCode,
                'code'               => $vCode,
                'name'               => null,
                'price'              => null,
                'stock'              => 0,
                'parameters'         => null,
            ];
        }

        return ['product' => $mapped, 'variants' => $variants];
    }

    // ---- XML parsování ----

    private function parseXml(array $item, string $tmpFile, XmlImporter $importer, array $fieldMap): array
    {
        // fieldMap pro XML (zatím ignorujeme — XmlParser má svou vlastní logiku)
        return XmlParser::stream(
            $tmpFile,
            fn($product, $variants) => $importer->addProduct($product, $variants),
            fn($count) => $this->log($item['id'], "  ↻ Zpracováno: {$count}")
        );
    }

    public function releaseStuck(): int
    {
        $stmt = $this->db->prepare("
            UPDATE xml_processing_queue
            SET status     = 'pending', updated_at = NOW()
            WHERE status    = 'processing'
              AND started_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
              AND retry_count < max_retries
        ");
        $stmt->execute([self::LOCK_TIMEOUT]);
        return $stmt->rowCount();
    }

    private function lockNextItem(): ?array
    {
        $this->db->beginTransaction();
        $stmt = $this->db->query("
            SELECT * FROM xml_processing_queue
            WHERE status = 'pending' AND retry_count < max_retries
            ORDER BY priority ASC, created_at ASC
            LIMIT 1 FOR UPDATE SKIP LOCKED
        ");
        $item = $stmt->fetch();
        if (!$item) { $this->db->commit(); return null; }

        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status = 'processing', started_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$item['id']]);
        $this->db->commit();
        return $item;
    }

    private function markCompleted(int $queueId, array $stats, int $importId): void
    {
        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status='completed', progress_percentage=100, products_processed=?,
                completed_at=NOW(), updated_at=NOW()
            WHERE id=?
        ")->execute([$stats['processed'], $queueId]);

        $this->db->prepare("
            UPDATE xml_imports
            SET status='completed', products_imported=?, products_updated=?, completed_at=NOW()
            WHERE id=?
        ")->execute([$stats['inserted'], $stats['updated'], $importId]);
    }

    private function markFailed(array $item, string $error): void
    {
        $newRetry = (int)$item['retry_count'] + 1;
        $isFinal  = $newRetry >= (int)$item['max_retries'];

        $this->db->prepare("
            UPDATE xml_processing_queue
            SET status=?, retry_count=?, error_message=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$isFinal ? 'failed' : 'pending', $newRetry, substr($error, 0, 1000), $item['id']]);

        if ($isFinal) {
            try {
                $email = $this->db->prepare('SELECT email FROM users WHERE id=? LIMIT 1');
                $email->execute([$item['user_id']]);
                \ShopCode\Services\AdminNotifier::xmlImportFailed(
                    userId: $item['user_id'],
                    userEmail: $email->fetchColumn() ?: 'neznámý',
                    feedUrl: $item['xml_feed_url'],
                    errorMessage: $error,
                    retryCount: $newRetry,
                    maxRetries: (int)$item['max_retries']
                );
            } catch (\Throwable $e) {
                $this->log($item['id'], "⚠️ Email notifikace selhala: " . $e->getMessage());
            }
        }
    }

    private function createImportRecord(int $userId, int $queueId): int
    {
        $stmt = $this->db->prepare("INSERT INTO xml_imports (user_id, status, started_at, queue_id) VALUES (?, 'processing', NOW(), ?)");
        $stmt->execute([$userId, $queueId]);
        return (int)$this->db->lastInsertId();
    }

    private function log(int $queueId, string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] [Queue#{$queueId}] {$message}\n";
        flush();
    }

    private function ensureTmpDir(): void
    {
        if (!is_dir(self::TMP_DIR)) mkdir(self::TMP_DIR, 0750, true);
    }
}
