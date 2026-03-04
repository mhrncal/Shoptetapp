<?php $pageTitle = 'Fotorecenze'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<!-- Hlavička — nadpis a export odděleně na mobilu -->
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-camera me-2"></i>Fotorecenze</h4>
            <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
        </div>
        <!-- Export: dropdown na mobilu, řádek na desktopu -->
        <div class="d-flex gap-2 flex-shrink-0">
            <!-- Mobil: jeden dropdown pro export -->
            <div class="d-sm-none">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/reviews/export/csv"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Stáhnout CSV</a></li>
                        <li><a class="dropdown-item" href="/reviews/export/xml"><i class="bi bi-file-earmark-code me-2 text-success"></i>Stáhnout XML</a></li>
                        <?php if (!empty($xmlFeedUrl)): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <div class="px-3 py-1">
                                <div class="small text-muted mb-1"><i class="bi bi-clock me-1"></i>XML Feed (denně 18:00)</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace" value="<?= $e($xmlFeedUrl) ?>" readonly id="feedUrlMobile">
                                    <button class="btn btn-outline-secondary" type="button"
                                            onclick="navigator.clipboard.writeText(document.getElementById('feedUrlMobile').value);this.innerHTML='<i class=\'bi bi-check\'></i>'">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <!-- Desktop: klasická tlačítka -->
            <div class="d-none d-sm-flex gap-2">
                <a href="/reviews/export/csv" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
                </a>
                <a href="/reviews/export/xml" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-code me-1"></i>XML
                </a>
                <?php if (!empty($xmlFeedUrl)): ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-link-45deg me-1"></i>Feed
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:320px;">
                        <li class="px-3 py-2">
                            <small class="text-muted d-block mb-2"><i class="bi bi-clock me-1"></i>Automaticky generováno denně v 18:00</small>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" value="<?= $e($xmlFeedUrl) ?>" readonly id="feedUrl">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(document.getElementById('feedUrl').value);this.innerHTML='<i class=\'bi bi-check\'></i>'">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Status filtry — horizontální scroll -->
<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -1rem;padding:0 1rem 0.5rem;">
    <div class="d-flex gap-2 mb-3" style="flex-wrap:nowrap;min-width:max-content;">
        <?php $tabs = [
            [''        ,'Vše',       $counts['pending']+$counts['approved']+$counts['rejected'], 'secondary'],
            ['pending' ,'Čekající',  $counts['pending'],  'warning'],
            ['approved','Schválené', $counts['approved'], 'success'],
            ['rejected','Zamítnuté', $counts['rejected'], 'danger'],
        ]; ?>
        <?php foreach ($tabs as [$val, $label, $cnt, $color]): ?>
        <a href="?status=<?= $e($val) ?>&search=<?= $e($search) ?>"
           class="btn btn-sm flex-shrink-0 <?= $status === $val ? "btn-{$color}" : "btn-outline-{$color}" ?>">
            <?= $label ?>
            <span class="badge ms-1 <?= $status === $val ? 'bg-white text-'.$color : 'bg-'.$color.' text-white' ?>"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Hledání -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control"
                       placeholder="Jméno, e-mail, SKU..." value="<?= $e($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Hledat</button>
            <?php if ($search): ?>
            <a href="?status=<?= $e($status) ?>" class="btn btn-outline-secondary btn-sm flex-shrink-0">✕</a>
            <?php endif; ?>
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

<form method="POST" action="/reviews/bulk" id="bulkForm">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <!-- Hromadné akce -->
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="form-check mb-0 flex-shrink-0">
                    <input class="form-check-input" type="checkbox" id="selectAll"
                           onchange="document.querySelectorAll('.review-cb').forEach(cb=>cb.checked=this.checked)">
                    <label class="form-check-label small text-muted" for="selectAll">Vše</label>
                </div>
                <select name="bulk_action" class="form-select form-select-sm flex-grow-1" style="min-width:0;">
                    <option value="">Hromadná akce…</option>
                    <option value="approve">✅ Schválit</option>
                    <option value="reject">❌ Zamítnout</option>
                    <option value="mark_imported">📦 Označit importováno</option>
                    <option value="unmark_imported">📭 Odznačit importováno</option>
                    <option value="download_zip">📥 ZIP fotek</option>
                    <option value="delete">🗑️ Smazat</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0"
                        onclick="return document.querySelectorAll('.review-cb:checked').length>0||(alert('Nevybrali jste žádné recenze.'),false)">
                    Provést
                </button>
            </div>
        </div>
    </div>

    <!-- DESKTOP: tabulka -->
    <div class="card d-none d-md-block mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Autor</th>
                            <th>SKU</th>
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
                        <td><input class="form-check-input review-cb" type="checkbox" name="ids[]" value="<?= $r['id'] ?>"></td>
                        <td>
                            <div class="fw-semibold"><?= $e($r['author_name']) ?></div>
                            <div class="text-muted small"><?= $e($r['author_email']) ?></div>
                        </td>
                        <td><?= $r['sku'] ? '<code class="text-primary">'.$e($r['sku']).'</code>' : '<span class="text-muted">—</span>' ?></td>
                        <td><?= $r['photo_count'] > 0 ? '<span class="badge bg-primary">'.$r['photo_count'].'</span>' : '<span class="text-muted">0</span>' ?></td>
                        <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($r['created_at'])) ?></td>
                        <td><span class="badge bg-<?= $st['color'] ?>"><?= $st['label'] ?></span></td>
                        <td>
                            <?php if ($r['imported']): ?>
                            <form method="POST" action="/reviews/bulk" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="bulk_action" value="unmark_imported">
                                <input type="hidden" name="ids[]" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-link p-0 text-success" onclick="return confirm('Odznačit?')">
                                    <i class="bi bi-check-circle-fill"></i>
                                </button>
                            </form>
                            <?php else: ?><i class="bi bi-dash text-muted"></i><?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/reviews/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                                <?php if ($r['status'] !== 'approved'): ?>
                                <form method="POST" action="/reviews/change-status">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-sm btn-outline-success" title="Schválit"><i class="bi bi-check-lg"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?>
                                <form method="POST" action="/reviews/change-status">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Zamítnout"><i class="bi bi-x-lg"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" action="/reviews/delete" onsubmit="return confirm('Smazat recenzi?')">
                                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Smazat"><i class="bi bi-trash"></i></button>
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
    <div class="d-md-none d-flex flex-column gap-2 mb-3">
        <?php foreach ($reviews as $r):
            $st = \ShopCode\Models\Review::STATUSES[$r['status']] ?? ['label'=>$r['status'],'color'=>'secondary'];
        ?>
        <div class="card">
            <div class="card-body p-3">

                <!-- Řádek 1: checkbox + jméno + badge stavu -->
                <div class="d-flex align-items-center gap-2 mb-1">
                    <input class="form-check-input review-cb flex-shrink-0" type="checkbox"
                           name="ids[]" value="<?= $r['id'] ?>">
                    <div class="fw-semibold flex-grow-1 text-truncate"><?= $e($r['author_name']) ?></div>
                    <span class="badge bg-<?= $st['color'] ?> flex-shrink-0"><?= $st['label'] ?></span>
                </div>

                <!-- Řádek 2: email -->
                <div class="text-muted small text-truncate mb-2 ps-4"><?= $e($r['author_email']) ?></div>

                <!-- Řádek 3: meta info v jednom řádku -->
                <div class="d-flex flex-wrap gap-2 mb-3 ps-4" style="font-size:.8rem;">
                    <?php if ($r['sku']): ?>
                    <span class="text-muted"><i class="bi bi-upc me-1"></i><code class="text-primary" style="font-size:.8rem;"><?= $e($r['sku']) ?></code></span>
                    <?php endif; ?>
                    <?php if ($r['photo_count'] > 0): ?>
                    <span class="text-muted"><i class="bi bi-images me-1"></i><?= $r['photo_count'] ?>&nbsp;fotek</span>
                    <?php endif; ?>
                    <span class="text-muted"><i class="bi bi-clock me-1"></i><?= date('d.m.Y', strtotime($r['created_at'])) ?></span>
                    <?php if ($r['imported']): ?>
                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Import.</span>
                    <?php endif; ?>
                </div>

                <!-- Řádek 4: akce -->
                <div class="d-flex gap-2">
                    <a href="/reviews/<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($r['status'] !== 'approved'): ?>
                    <form method="POST" action="/reviews/change-status" class="flex-grow-1">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="btn btn-sm btn-success w-100">
                            <i class="bi bi-check-lg me-1"></i>Schválit
                        </button>
                    </form>
                    <?php elseif ($r['status'] !== 'rejected'): ?>
                    <form method="POST" action="/reviews/change-status" class="flex-grow-1">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                            <i class="bi bi-x-lg me-1"></i>Zamítnout
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="flex-grow-1"></div>
                    <?php endif; ?>
                    <form method="POST" action="/reviews/delete" onsubmit="return confirm('Smazat recenzi?')" class="flex-shrink-0">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

</form>

<!-- Stránkování -->
<?php if ($total > $perPage): ?>
<?php $pages = ceil($total / $perPage); ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center mb-0 flex-wrap">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $e($status) ?>&search=<?= $e($search) ?>">‹</a>
        </li>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($pages,$page+2); $p++): ?>
        <li class="page-item <?= $p===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $p ?>&status=<?= $e($status) ?>&search=<?= $e($search) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
        <li class="page-item">
            <a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $e($status) ?>&search=<?= $e($search) ?>">›</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>
