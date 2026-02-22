<?php $pageTitle = 'Produkty'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

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
<div class="card border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Název, ID, značka..."
                           value="<?= $e($filters['search'] ?? '') ?>">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">Kategorie (vše)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $e($cat) ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= $e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="brand" class="form-select form-select-sm">
                    <option value="">Značka (vše)</option>
                    <?php foreach ($brands as $brand): ?>
                    <option value="<?= $e($brand) ?>" <?= ($filters['brand'] ?? '') === $brand ? 'selected' : '' ?>>
                        <?= $e($brand) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="sort" class="form-select form-select-sm">
                    <option value="">Řadit: Nejnovější</option>
                    <option value="name_asc"   <?= ($filters['sort'] ?? '') === 'name_asc'   ? 'selected' : '' ?>>Název A–Z</option>
                    <option value="price_asc"  <?= ($filters['sort'] ?? '') === 'price_asc'  ? 'selected' : '' ?>>Cena ↑</option>
                    <option value="price_desc" <?= ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Cena ↓</option>
                </select>
            </div>
            <div class="col-3 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtr</button>
            </div>
            <div class="col-3 col-md-1">
                <a href="<?= APP_URL ?>/products" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabulka produktů -->
<div class="card border-0">
    <div class="card-body p-0">
        <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-box fs-1 d-block mb-3"></i>
            <?php if (!empty($filters)): ?>
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
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem;">
                <thead>
                    <tr>
                        <th style="width:60px;">Foto</th>
                        <th>Název produktu</th>
                        <th>Shoptet ID</th>
                        <th>Kategorie</th>
                        <th>Značka</th>
                        <th class="text-end">Cena</th>
                        <th>Dostupnost</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p):
                        $images = $p['images'] ? json_decode($p['images'], true) : [];
                        $thumb  = $images[0] ?? null;
                    ?>
                    <tr>
                        <td>
                            <?php if ($thumb): ?>
                            <img src="<?= $e($thumb) ?>" alt=""
                                 style="width:44px;height:44px;object-fit:cover;border-radius:6px;"
                                 loading="lazy" onerror="this.style.display='none'">
                            <?php else: ?>
                            <div style="width:44px;height:44px;background:rgba(255,255,255,.05);border-radius:6px;"
                                 class="d-flex align-items-center justify-content-center text-muted">
                                <i class="bi bi-image"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/products/<?= $p['id'] ?>" class="text-decoration-none fw-semibold">
                                <?= $e($p['name']) ?>
                            </a>
                        </td>
                        <td class="text-muted font-monospace small"><?= $e($p['shoptet_id']) ?></td>
                        <td class="text-muted small"><?= $e($p['category'] ?? '—') ?></td>
                        <td class="text-muted small"><?= $e($p['brand'] ?? '—') ?></td>
                        <td class="text-end fw-semibold">
                            <?php if ($p['price'] !== null): ?>
                            <?= number_format((float)$p['price'], 2, ',', ' ') ?>
                            <span class="text-muted small"><?= $e($p['currency']) ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['availability']): ?>
                            <span class="badge bg-success bg-opacity-20 text-success small">
                                <?= $e($p['availability']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
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
                $pages    = (int)ceil($total / $perPage);
                $start    = max(1, $page - 2);
                $end      = min($pages, $page + 2);
                $qs       = http_build_query(array_merge($filters, ['page' => 0]));
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
