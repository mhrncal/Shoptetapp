<?php $pageTitle = 'Fotorecenze'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<!-- Hlavička stránky -->
<div class="d-flex justify-content-between align-items-start mb-4 gap-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-camera me-2"></i>Fotorecenze</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
    </div>
    <!-- Export tlačítka — scrollovatelný řádek na mobilu -->
    <div class="d-flex gap-2 flex-shrink-0" style="overflow-x:auto; max-width:100%;">
        <a href="/reviews/export/csv"
           class="btn btn-sm btn-outline-primary flex-shrink-0"
           title="Stáhnout CSV">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i><span class="d-none d-sm-inline">Stáhnout </span>CSV
        </a>
        <a href="/reviews/export/xml"
           class="btn btn-sm btn-outline-success flex-shrink-0"
           title="Stáhnout XML">
            <i class="bi bi-file-earmark-code me-1"></i><span class="d-none d-sm-inline">Stáhnout </span>XML
        </a>
        <?php if (!empty($xmlFeedUrl)): ?>
        <div class="dropdown flex-shrink-0">
            <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-link-45deg me-1"></i>Feed
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:320px; max-width:90vw;">
                <li class="px-3 py-2">
                    <small class="text-muted d-block mb-2">
                        <i class="bi bi-clock me-1"></i>Automaticky generováno denně v 18:00
                    </small>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace small"
                               value="<?= $e($xmlFeedUrl) ?>" readonly id="feedUrl">
                        <button class="btn btn-outline-secondary" type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('feedUrl').value); this.innerHTML='<i class=\'bi bi-check\'></i>'">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status filtry — scrollovatelné na mobilu -->
<div style="overflow-x:auto; -webkit-overflow-scrolling:touch; margin:0 -1rem; padding:0 1rem;">
    <div class="d-flex gap-2 mb-4" style="flex-wrap:nowrap; min-width:min-content;">
        <?php $tabs = [
            [''         , 'Vše',       ($counts['pending'] + $counts['approved'] + $counts['rejected']), 'secondary'],
            ['pending'  , 'Čekající',  $counts['pending'],  'warning'],
            ['approved' , 'Schválené', $counts['approved'], 'success'],
            ['rejected' , 'Zamítnuté',$counts['rejected'], 'danger'],
        ]; ?>
        <?php foreach ($tabs as [$val, $label, $cnt, $color]): ?>
        <a href="?status=<?= $e($val) ?>&search=<?= $e($search) ?>"
           class="btn btn-sm flex-shrink-0 <?= $status === $val ? "btn-{$color}" : "btn-outline-{$color}" ?>">
            <?= $label ?> <span class="badge bg-white text-<?= $color ?> ms-1"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Hledání -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" class="row g-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <div class="col">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Jméno, e-mail, SKU..." value="<?= $e($search) ?>">
                </div>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Hledat</button>
                <a href="?status=<?= $e($status) ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($reviews)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-camera fs-1 d-block mb-3"></i>
        <p>Žádné recenze<?= $status ? ' s tímto filtrem' : '' ?>.</p>
    </div>
</div>
<?php else: ?>

<!-- Desktop: tabulka | Mobil: kartičky -->
<form method="POST" action="/reviews/bulk" id="bulkForm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <!-- Hromadné akce -->
    <div class="card mb-2">
        <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="selectAll"
                       onchange="document.querySelectorAll('.review-cb').forEach(cb=>cb.checked=this.checked)">
                <label class="form-check-label small text-muted" for="selectAll">Vybrat vše</label>
            </div>
            <select name="bulk_action" class="form-select form-select-sm" style="width:auto; min-width:160px;">
                <option value="">Hromadná akce...</option>
                <option value="approve">✅ Schválit</option>
                <option value="reject">❌ Zamítnout</option>
                <option value="mark_imported">📦 Označit jako importováno</option>
                <option value="unmark_imported">📭 Odznačit jako importováno</option>
                <option value="download_zip">📥 Stáhnout fotky (ZIP)</option>
                <option value="delete">🗑️ Smazat</option>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    onclick="return document.querySelectorAll('.review-cb:checked').length > 0 || (alert('Nevybrali jste žádné recenze.'), false)">
                Provést
            </button>
        </div>
    </div>

    <!-- DESKTOP: tabulka -->
    <div class="card d-none d-md-block">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Autor</th>
                            <th>Kód produktu</th>
                            <th>Fotek</th>
                            <th>Datum</th>
                            <th>Stav</th>
                            <th>Import</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reviews as $r):
                        $st = \ShopCode\Models\Review::STATUSES[$r['status']] ?? ['label'=>$r['status'],'color'=>'secondary'];
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
                        <td>
                            <?php if ($r['sku']): ?>
                                <code class="text-primary"><?= $e($r['sku']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['photo_count'] > 0): ?>
                                <span class="badge bg-primary"><?= $r['photo_count'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                        <td><span class="badge bg-<?= $st['color'] ?>"><?= $st['label'] ?></span></td>
                        <td>
                            <?php if ($r['imported']): ?>
                                <form method="POST" action="/reviews/bulk" style="display:inline;">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="bulk_action" value="unmark_imported">
                                    <input type="hidden" name="ids[]" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-link p-0 text-success"
                                            title="Importováno <?= date('d.m.Y', strtotime($r['imported_at'])) ?>"
                                            onclick="return confirm('Odznačit jako importováno?')">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <i class="bi bi-dash text-muted"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/reviews/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($r['status'] !== 'approved'): ?>
                                <form method="POST" action="/reviews/change-status">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?? '' ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Schválit">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?>
                                <form method="POST" action="/reviews/change-status">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?? '' ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Zamítnout">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="/reviews/delete"
                                      onsubmit="return confirm('Opravdu smazat tuto recenzi?')">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?? '' ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MOBIL: kartičky -->
    <div class="d-md-none d-flex flex-column gap-2">
        <?php foreach ($reviews as $r):
            $st = \ShopCode\Models\Review::STATUSES[$r['status']] ?? ['label'=>$r['status'],'color'=>'secondary'];
        ?>
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-2">
                    <input class="form-check-input review-cb mt-1 flex-shrink-0" type="checkbox"
                           name="ids[]" value="<?= $r['id'] ?>">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold text-truncate"><?= $e($r['author_name']) ?></div>
                            <span class="badge bg-<?= $st['color'] ?> flex-shrink-0"><?= $st['label'] ?></span>
                        </div>
                        <div class="text-muted small text-truncate"><?= $e($r['author_email']) ?></div>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-3 small text-muted">
                    <?php if ($r['sku']): ?>
                    <span><i class="bi bi-upc me-1"></i><code class="text-primary"><?= $e($r['sku']) ?></code></span>
                    <?php endif; ?>
                    <?php if ($r['photo_count'] > 0): ?>
                    <span><i class="bi bi-images me-1"></i><?= $r['photo_count'] ?> fotek</span>
                    <?php endif; ?>
                    <span><i class="bi bi-clock me-1"></i><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
                    <?php if ($r['imported']): ?>
                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Importováno</span>
                    <?php endif; ?>
                </div>

                <!-- Akční tlačítka -->
                <div class="d-flex gap-2">
                    <a href="/reviews/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1">
                        <i class="bi bi-eye me-1"></i>Detail
                    </a>
                    <?php if ($r['status'] !== 'approved'): ?>
                    <form method="POST" action="/reviews/change-status" class="flex-grow-1">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?? '' ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-check-lg me-1"></i>Schválit
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'rejected'): ?>
                    <form method="POST" action="/reviews/change-status" class="flex-shrink-0">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?? '' ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Zamítnout">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
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
