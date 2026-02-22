<?php $pageTitle = 'Fotorecenze'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-camera me-2"></i>Fotorecenze</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="<?= APP_URL ?>/reviews/export-csv">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    title="Stáhnout CSV pro ruční import do Shoptetu">
                <i class="bi bi-download me-1"></i>Export CSV
            </button>
        </form>
    </div>
</div>

<!-- Status tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php $tabs = [
        [''         , 'Vše',       ($counts['pending'] + $counts['approved'] + $counts['rejected']), 'secondary'],
        ['pending'  , 'Čekající',  $counts['pending'],  'warning'],
        ['approved' , 'Schválené', $counts['approved'], 'success'],
        ['rejected' , 'Zamítnuté',$counts['rejected'], 'danger'],
    ]; ?>
    <?php foreach ($tabs as [$val, $label, $cnt, $color]): ?>
    <a href="?status=<?= $e($val) ?>&search=<?= $e($search) ?>"
       class="btn btn-sm <?= $status === $val ? "btn-{$color}" : "btn-outline-{$color}" ?>">
        <?= $label ?> <span class="badge bg-white text-<?= $color ?> ms-1"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Hledání -->
<div class="card border-0 mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Jméno, e-mail, SKU..." value="<?= $e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Hledat</button>
                <a href="?status=<?= $e($status) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($reviews)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-camera fs-1 d-block mb-3"></i>
        <p>Žádné recenze<?= $status ? ' s tímto filtrem' : '' ?>.</p>
    </div>
</div>
<?php else: ?>

<!-- Hromadné akce -->
<form method="POST" action="<?= APP_URL ?>/reviews/bulk" id="bulkForm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="card border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAll"
                           onchange="document.querySelectorAll('.review-cb').forEach(cb=>cb.checked=this.checked)">
                    <label class="form-check-label small text-muted" for="selectAll">Vybrat vše</label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <select name="bulk_action" class="form-select form-select-sm" style="width:auto;">
                    <option value="">Hromadná akce...</option>
                    <option value="approve">✅ Schválit</option>
                    <option value="reject">❌ Zamítnout</option>
                    <option value="mark_imported">📦 Označit jako importováno</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary"
                        onclick="return document.querySelectorAll('.review-cb:checked').length > 0 || (alert('Nevybrali jste žádné recenze.'), false)">
                    Provést
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:40px;"></th>
                        <th>Autor</th>
                        <th>Produkt / SKU</th>
                        <th class="text-center">Fotek</th>
                        <th>Datum</th>
                        <th>Stav</th>
                        <th>Import</th>
                        <th class="text-end">Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $r):
                    $st = Review::STATUSES[$r['status']] ?? ['label'=>$r['status'],'color'=>'secondary'];
                ?>
                <tr>
                    <td>
                        <input class="form-check-input review-cb" type="checkbox"
                               name="ids[]" value="<?= $r['id'] ?>">
                    </td>
                    <td>
                        <div class="fw-semibold"><?= $e($r['author_name']) ?></div>
                        <div class="text-muted small"><?= $e($r['author_email']) ?></div>
                    </td>
                    <td class="small text-muted">
                        <?php if ($r['product_name']): ?>
                            <span class="text-body"><?= $e($r['product_name']) ?></span><br>
                        <?php endif; ?>
                        <?php if ($r['sku']): ?><code><?= $e($r['sku']) ?></code><?php endif; ?>
                        <?php if (!$r['product_name'] && !$r['sku'] && !$r['shoptet_id']): ?>—<?php endif; ?>
                    </td>
                    <td class="text-center">
                        <!-- Thumbnaily fotek -->
                        <div class="d-flex gap-1 justify-content-center flex-wrap">
                            <?php foreach (array_slice($r['photos'], 0, 3) as $photo): ?>
                            <img src="<?= $e(APP_URL . '/uploads/' . $photo['thumb']) ?>"
                                 style="width:36px;height:36px;object-fit:cover;border-radius:4px;"
                                 alt="" onerror="this.style.display='none'">
                            <?php endforeach; ?>
                            <?php if (count($r['photos']) > 3): ?>
                            <span class="badge bg-secondary align-self-center">+<?= count($r['photos'])-3 ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                    <td>
                        <span class="badge bg-<?= $st['color'] ?>">
                            <?= $st['label'] ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <?php if ($r['imported']): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Importováno <?= date('d.m.Y', strtotime($r['imported_at'])) ?>"></i>
                        <?php else: ?>
                            <i class="bi bi-dash text-muted"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= APP_URL ?>/reviews/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Stránkování -->
<?php if ($total > $perPage): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php $pages = ceil($total / $perPage); ?>
        <?php for ($p = 1; $p <= $pages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&status=<?= $e($status) ?>&search=<?= $e($search) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>
