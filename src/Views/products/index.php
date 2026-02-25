<?php $pageTitle = 'Produkty'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<?php
// Načti kódy variant pro zobrazené produkty (GROUP_CONCAT)
$db = \ShopCode\Core\Database::getInstance();
$productIds = array_column($products, 'id');
$variantCodes = [];
if (!empty($productIds)) {
    $ph   = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("
        SELECT product_id, GROUP_CONCAT(sku ORDER BY sku SEPARATOR ', ') AS codes
        FROM product_variants
        WHERE product_id IN ({$ph}) AND sku IS NOT NULL AND sku != ''
        GROUP BY product_id
    ");
    $stmt->execute($productIds);
    $variantCodes = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-box me-2"></i>Produkty</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= number_format($total) ?></strong> produktů</p>
    </div>
    <a href="<?= APP_URL ?>/xml" class="btn btn-primary btn-sm">
        <i class="bi bi-file-earmark-arrow-down me-1"></i>XML Import
    </a>
</div>

<!-- Filtry -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Název, kód, SKU..."
                           value="<?= $e($filters['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category" class="form-select">
                    <option value="">Kategorie (vše)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $e($cat) ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= $e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-3 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtr</button>
            </div>
            <div class="col-3 col-md-2">
                <a href="<?= APP_URL ?>/products" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabulka produktů -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-box fs-1 d-block mb-3"></i>
            <?php if (!empty(array_filter($filters))): ?>
                <p>Žádné produkty neodpovídají filtru.</p>
                <a href="<?= APP_URL ?>/products" class="btn btn-outline-secondary btn-sm">Zobrazit vše</a>
            <?php else: ?>
                <p>Zatím žádné produkty. Spusťte XML import.</p>
                <a href="<?= APP_URL ?>/xml" class="btn btn-primary btn-sm">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Spustit import
                </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Název produktu</th>
                        <th>Kód</th>
                        <th>Kategorie</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p):
                        // Kód: varianta má skupinové kódy, produkt má svůj sku
                        $hasVariants  = isset($variantCodes[$p['id']]);
                        $displayCode  = $hasVariants
                            ? $variantCodes[$p['id']]   // "KOD1, KOD2, KOD3"
                            : ($p['sku'] ?? null);       // vlastní SKU produktu
                    ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/products/<?= $p['id'] ?>"
                               class="text-decoration-none fw-medium">
                                <?= $e($p['name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($displayCode): ?>
                                <?php if ($hasVariants): ?>
                                    <?php
                                    $codes = explode(', ', $displayCode);
                                    $shown = array_slice($codes, 0, 3);
                                    $more  = count($codes) - 3;
                                    ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($shown as $code): ?>
                                        <span class="badge bg-secondary font-monospace" style="font-size:.75rem;">
                                            <?= $e(trim($code)) ?>
                                        </span>
                                        <?php endforeach; ?>
                                        <?php if ($more > 0): ?>
                                        <span class="text-muted small align-self-center">+<?= $more ?> další</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="font-monospace text-muted small"><?= $e($displayCode) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= $e($p['category'] ?? '—') ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/products/<?= $p['id'] ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stránkování -->
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> z <?= number_format($total) ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $pages = (int)ceil($total / $perPage);
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                $qs    = http_build_query(array_merge($filters, ['page' => 0]));
                ?>
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= str_replace('page=0','page='.($page-1),$qs) ?>">‹</a>
                </li>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= str_replace('page=0','page='.$i,$qs) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= str_replace('page=0','page='.($page+1),$qs) ?>">›</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
