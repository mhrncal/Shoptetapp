<?php $pageTitle = 'Produkty'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<?php
$db = \ShopCode\Core\Database::getInstance();
$productIds = array_column($products, 'id');
$variantCodes = [];
if (!empty($productIds)) {
    $ph   = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $db->prepare("
        SELECT product_id, GROUP_CONCAT(code ORDER BY code SEPARATOR ', ') AS codes
        FROM product_variants
        WHERE product_id IN ({$ph}) AND code IS NOT NULL AND code != ''
        GROUP BY product_id
    ");
    $stmt->execute($productIds);
    $variantCodes = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-box me-2"></i>Produkty</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= number_format($total) ?></strong> produktů</p>
    </div>
    <a href="<?= APP_URL ?>/feeds" class="btn btn-primary btn-sm flex-shrink-0">
        <i class="bi bi-file-earmark-arrow-down me-1"></i><span class="d-none d-sm-inline">XML </span>Import
    </a>
</div>

<!-- Filtry -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Název, kód, SKU..."
                           value="<?= $e($filters['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <select name="category" class="form-select">
                    <option value="">Kategorie (vše)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $e($cat) ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= $e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-sm-4 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtr</button>
            </div>
            <div class="col-6 col-sm-4 col-md-1">
                <a href="<?= APP_URL ?>/products" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($products)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-box fs-1 d-block mb-3"></i>
        <?php if (!empty(array_filter($filters))): ?>
            <p>Žádné produkty neodpovídají filtru.</p>
            <a href="<?= APP_URL ?>/products" class="btn btn-outline-secondary btn-sm">Zobrazit vše</a>
        <?php else: ?>
            <p>Zatím žádné produkty. Spusťte XML import.</p>
            <a href="<?= APP_URL ?>/feeds" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-arrow-down me-1"></i>Spustit import
            </a>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>

<!-- DESKTOP: tabulka -->
<div class="card d-none d-md-block">
    <div class="card-body p-0">
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
                        $hasVariants  = isset($variantCodes[$p['id']]);
                        $displayCode  = $hasVariants ? $variantCodes[$p['id']] : ($p['code'] ?? null);
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
                                    <?php $codes = explode(', ', $displayCode); $shown = array_slice($codes, 0, 3); $more = count($codes) - 3; ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($shown as $code): ?>
                                        <span class="badge bg-secondary font-monospace" style="font-size:.75rem;"><?= $e(trim($code)) ?></span>
                                        <?php endforeach; ?>
                                        <?php if ($more > 0): ?><span class="text-muted small align-self-center">+<?= $more ?> další</span><?php endif; ?>
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
                            <a href="<?= APP_URL ?>/products/<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted"><?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> z <?= number_format($total) ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
<?php
$pages = (int)ceil($total / $perPage);
$start = max(1, $page - 2);
$end   = min($pages, $page + 2);
$qs    = http_build_query(array_merge($filters, ['page' => 0]));
?>
<?php if ($page > 1): ?>
<li class="page-item"><a class="page-link" href="?<?= str_replace('page=0','page='.($page-1),$qs) ?>">&#8249;</a></li>
<?php endif; ?>
<?php for ($i = $start; $i <= $end; $i++): ?>
<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= str_replace('page=0','page='.$i,$qs) ?>"><?= $i ?></a></li>
<?php endfor; ?>
<?php if ($page < $pages): ?>
<li class="page-item"><a class="page-link" href="?<?= str_replace('page=0','page='.($page+1),$qs) ?>">&#8250;</a></li>
<?php endif; ?>
</ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- MOBIL: kompaktní list -->
<div class="d-md-none">
    <div class="d-flex flex-column gap-2">
        <?php foreach ($products as $p):
            $hasVariants = isset($variantCodes[$p['id']]);
            $displayCode = $hasVariants ? $variantCodes[$p['id']] : ($p['code'] ?? null);
        ?>
        <a href="<?= APP_URL ?>/products/<?= $p['id'] ?>"
           class="card text-decoration-none" style="color:inherit;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-medium text-truncate"><?= $e($p['name']) ?></div>
                        <div class="d-flex gap-2 mt-1 flex-wrap">
                            <?php if ($displayCode): ?>
                                <?php if ($hasVariants): ?>
                                    <?php $codes = explode(', ', $displayCode); $shown = array_slice($codes, 0, 2); $more = count($codes) - 2; ?>
                                    <?php foreach ($shown as $code): ?>
                                    <span class="badge bg-secondary font-monospace" style="font-size:.7rem;"><?= $e(trim($code)) ?></span>
                                    <?php endforeach; ?>
                                    <?php if ($more > 0): ?><span class="text-muted" style="font-size:.75rem;">+<?= $more ?></span><?php endif; ?>
                                <?php else: ?>
                                    <code class="text-muted" style="font-size:.8rem;"><?= $e($displayCode) ?></code>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($p['category'] ?? null): ?>
                            <span class="text-muted" style="font-size:.78rem;"><i class="bi bi-tag me-1"></i><?= $e($p['category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Stránkování mobil -->
    <?php if ($total > $perPage): ?>
    <?php
        $pages = (int)ceil($total / $perPage);
        $qs    = http_build_query(array_merge($filters, ['page' => 0]));
    ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <small class="text-muted"><?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> z <?= number_format($total) ?></small>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?<?= str_replace('page=0','page='.($page-1),$qs) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-chevron-left"></i> Zpět
            </a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?<?= str_replace('page=0','page='.($page+1),$qs) ?>" class="btn btn-sm btn-outline-secondary">
                Další <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
