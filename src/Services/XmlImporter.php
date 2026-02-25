<?php

namespace ShopCode\Services;

use ShopCode\Core\Database;
use PDO;

class XmlImporter
{
    private const BATCH_SIZE = 500;

    private PDO $db;
    private int $userId;
    private int $queueId;
    private int $totalInFeed = 0;
    private int $inserted    = 0;
    private int $updated     = 0;
    private int $processed   = 0;
    private array $productBatch = [];
    private array $variantBatch = [];

    public function __construct(int $userId, int $queueId)
    {
        $this->db      = Database::getInstance();
        $this->userId  = $userId;
        $this->queueId = $queueId;
    }

    public function setTotalInFeed(int $total): void { $this->totalInFeed = $total; }

    public function addProduct(array $product, array $variants): void
    {
        $this->productBatch[] = [$product, $variants];
        if (count($this->productBatch) >= self::BATCH_SIZE) {
            $this->flushProducts();
        }
    }

    public function finish(): array
    {
        $this->flushProducts();
        return ['inserted' => $this->inserted, 'updated' => $this->updated, 'processed' => $this->processed];
    }

    private function flushProducts(): void
    {
        if (empty($this->productBatch)) return;
        $this->db->beginTransaction();
        try {
            $this->upsertProducts();
            $this->upsertVariants();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->processed   += count($this->productBatch);
        $this->productBatch = [];
        $this->variantBatch = [];
        $this->updateProgress();
    }

    private function upsertProducts(): void
    {
        $batch  = $this->productBatch;
        // 13 hodnot na řádek (přidáno sku)
        $ph     = implode(', ', array_fill(0, count($batch), '(?,?,?,?,?,?,?,?,?,?,?,?,?)'));
        $values = [];

        foreach ($batch as [$p, $variants]) {
            array_push($values,
                $this->userId,
                $p['shoptet_id'],
                $p['code']        ?? null,   // CODE z XML
                $p['name']        ?? '',
                $p['description'] ?? null,
                $p['price']       ?? null,
                $p['currency']    ?? 'CZK',
                $p['category']    ?? null,
                $p['brand']       ?? null,
                $p['availability']?? null,
                $p['images']      ?? null,
                $p['parameters']  ?? null,
                $p['xml_data']    ?? null
            );
        }

        $this->db->prepare("
            INSERT INTO products
                (user_id, shoptet_id, code, name, description, price, currency,
                 category, brand, availability, images, parameters, xml_data)
            VALUES {$ph}
            ON DUPLICATE KEY UPDATE
                code=VALUES(code), name=VALUES(name), description=VALUES(description),
                price=VALUES(price), currency=VALUES(currency),
                category=VALUES(category), brand=VALUES(brand),
                availability=VALUES(availability), images=VALUES(images),
                parameters=VALUES(parameters), xml_data=VALUES(xml_data),
                updated_at=NOW()
        ")->execute($values);

        $affected        = $this->db->query('SELECT ROW_COUNT()')->fetchColumn();
        $this->updated  += (int)($affected / 2);
        $this->inserted += $affected - (int)($affected / 2);

        // Načteme DB id pro varianty
        $shoptetIds = array_column(array_column($batch, 0), 'shoptet_id');
        $inPh       = implode(',', array_fill(0, count($shoptetIds), '?'));
        $stmt2 = $this->db->prepare("SELECT shoptet_id, id FROM products WHERE user_id=? AND shoptet_id IN ({$inPh})");
        $stmt2->execute(array_merge([$this->userId], $shoptetIds));
        $idMap = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($batch as [$p, $variants]) {
            $dbId = $idMap[$p['shoptet_id']] ?? null;
            if ($dbId && !empty($variants)) {
                foreach ($variants as $v) {
                    $this->variantBatch[] = array_merge($v, ['product_id' => $dbId]);
                }
            }
        }
    }

    private function upsertVariants(): void
    {
        if (empty($this->variantBatch)) return;
        foreach (array_chunk($this->variantBatch, self::BATCH_SIZE) as $chunk) {
            // 8 hodnot na řádek (přidáno sku)
            $ph     = implode(', ', array_fill(0, count($chunk), '(?,?,?,?,?,?,?,?)'));
            $values = [];
            foreach ($chunk as $v) {
                array_push($values,
                    $this->userId,
                    $v['product_id'],
                    $v['shoptet_variant_id'],
                    $v['code']       ?? null,  // CODE varianty z XML
                    $v['name']       ?? null,
                    $v['price']      ?? null,
                    $v['stock']      ?? 0,
                    $v['parameters'] ?? null
                );
            }
            $this->db->prepare("
                INSERT INTO product_variants
                    (user_id, product_id, shoptet_variant_id, code, name, price, stock, parameters)
                VALUES {$ph}
                ON DUPLICATE KEY UPDATE
                    code=VALUES(code), name=VALUES(name), price=VALUES(price),
                    stock=VALUES(stock), parameters=VALUES(parameters), updated_at=NOW()
            ")->execute($values);
        }
    }

    private function updateProgress(): void
    {
        $pct = $this->totalInFeed > 0 ? min(99, (int)(($this->processed / $this->totalInFeed) * 100)) : 0;
        $this->db->prepare("
            UPDATE xml_processing_queue
            SET progress_percentage=?, products_processed=?, updated_at=NOW()
            WHERE id=?
        ")->execute([$pct, $this->processed, $this->queueId]);
    }
}
