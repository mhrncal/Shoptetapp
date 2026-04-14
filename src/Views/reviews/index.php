<?php $pageTitle = 'Fotorecenze'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<?php
$daysLeft  = (int)($expiry['days_left'] ?? 30);
$blocked   = !empty($expiry['blocked']);
$noPhotos  = !empty($expiry['no_photos']);
$alertType = $blocked ? 'danger' : ($daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : 'info'));
$icon      = $blocked ? 'lock-fill' : ($daysLeft <= 7 ? 'exclamation-triangle-fill' : 'clock-history');
$dayWord   = $daysLeft === 1 ? 'den' : ($daysLeft <= 4 ? 'dny' : 'dní');
?>

<!-- Banner zálohy fotek — vždy viditelný -->
<div class="alert alert-<?= $alertType ?> d-flex align-items-center gap-3 mb-3">
    <i class="bi bi-<?= $icon ?> fs-5 flex-shrink-0"></i>
    <div class="flex-grow-1 small">
        <?php if ($blocked): ?>
            <strong>Přístup zablokován</strong> — fotky expirovaly. Stáhněte zálohu pro obnovení.
        <?php elseif ($noPhotos): ?>
            Záloha fotek: <strong><?= $daysLeft ?> <?= $dayWord ?></strong> do expirace.
            Fotky se mažou 30 dní po posledním exportu.
        <?php elseif ($daysLeft <= 7): ?>
            <strong>Fotky expirují za <?= $daysLeft ?> <?= $dayWord ?>!</strong> Po expiraci bude přístup zablokován.
        <?php else: ?>
            Záloha fotek vyprší za <strong><?= $daysLeft ?> <?= $dayWord ?></strong>.
            <?php if (!empty($expiry['last_export'])): ?>
            Poslední export: <?= date('d.m.Y', strtotime($expiry['last_export'])) ?>.
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <a href="<?= APP_URL ?>/reviews/export-photos" class="btn btn-sm btn-<?= $alertType ?> flex-shrink-0">
        <i class="bi bi-download me-1"></i><span class="d-none d-sm-inline">Stáhnout zálohu</span>
    </a>
</div>

<?php if ($blocked): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-lock fs-1 d-block mb-3"></i>
    <p>Přístup k fotorecenzím je zablokován. Stáhněte zálohu fotek pro obnovení.</p>
</div></div>
<?php else: ?>

<!-- Hlavička -->
<div class="d-flex justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-camera me-2"></i>Fotorecenze</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
    </div>
    <a href="<?= APP_URL ?>/reviews/export/xml" class="btn btn-sm btn-outline-success flex-shrink-0">
        <i class="bi bi-file-earmark-code me-1"></i>Generovat XML
    </a>
</div>

<!-- XML Feed + Import fotek ze Shoptetu -->
<div class="row g-3 mb-3">

    <!-- XML Feed -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small fw-semibold flex-shrink-0"><i class="bi bi-rss me-1 text-warning"></i>XML feed:</span>
                    <?php if ($xmlFeedExists ?? false): ?>
                    <div class="input-group input-group-sm flex-grow-1">
                        <input type="text" class="form-control form-control-sm font-monospace" value="<?= $e($xmlFeedUrl) ?>" readonly id="feedUrl">
                        <button class="btn btn-outline-secondary" type="button" id="copyFeedUrl" title="Kopírovat URL">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <a href="<?= $e($xmlFeedUrl) ?>" target="_blank" class="btn btn-outline-secondary" title="Otevřít">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>
                    <?php else: ?>
                    <span class="text-muted small">Zatím nevygenerováno — klikněte Generovat XML.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Import fotek ze Shoptetu -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-body py-2">
                <?php
                $hasUrl      = $importConfig && !empty($importConfig['csv_url']);
                $hasImported = $hasUrl && !empty($importConfig['last_imported_at']);
                ?>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small fw-semibold flex-shrink-0">
                        <i class="bi bi-cloud-download me-1 text-primary"></i>Shoptet fotky:
                    </span>
                    <?php if ($hasImported): ?>
                        <span class="badge bg-success flex-shrink-0">
                            <i class="bi bi-check-lg me-1"></i><?= number_format((int)$importConfig['last_row_count'], 0, ',', ' ') ?> produktů
                        </span>
                        <span class="text-muted small flex-shrink-0">
                            <?= number_format((int)$importConfig['last_image_count'], 0, ',', ' ') ?> fotek
                            · <?= date('d.m.Y H:i', strtotime($importConfig['last_imported_at'])) ?>
                        </span>
                    <?php elseif ($hasUrl): ?>
                        <span class="badge bg-warning text-dark flex-shrink-0">Zatím neimportováno</span>
                    <?php else: ?>
                        <span class="badge bg-secondary flex-shrink-0">Nenastaveno</span>
                    <?php endif; ?>
                    <?php if ($hasUrl): ?>
                        <form method="post" action="/reviews/photo-import/run" class="flex-shrink-0 ms-auto">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <button type="submit" class="btn btn-sm <?= $hasImported ? 'btn-outline-primary' : 'btn-primary' ?>"
                                    onclick="this.disabled=true;this.innerHTML='<span class='spinner-border spinner-border-sm'></span> Importuji…';this.form.submit();">
                                <i class="bi bi-arrow-repeat me-1"></i><?= $hasImported ? 'Reimportovat' : 'Importovat' ?>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-secondary flex-shrink-0" type="button"
                                onclick="document.getElementById('importUrlForm').classList.toggle('d-none')"
                                title="Změnit URL">
                            <i class="bi bi-pencil"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <!-- Formulář pro změnu/zadání URL -->
                <div id="importUrlForm" class="mt-2 <?= $hasUrl ? 'd-none' : '' ?>">
                    <form method="post" action="/reviews/photo-import/save-url" class="d-flex gap-2">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="url" name="csv_url" class="form-control form-control-sm font-monospace"
                               placeholder="https://vas-eshop.cz/export/products.csv?patternId=...&hash=..."
                               value="<?= $e($importConfig['csv_url'] ?? '') ?>" required>
                        <button type="submit" class="btn btn-sm btn-success flex-shrink-0">Uložit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
<script>
document.getElementById('copyFeedUrl')?.addEventListener('click', function() {
    navigator.clipboard.writeText(document.getElementById('feedUrl').value);
    this.innerHTML = '<i class="bi bi-check"></i>';
    setTimeout(() => this.innerHTML = '<i class="bi bi-clipboard"></i>', 2000);
});
</script>

<!-- Status filtry — horizontální scroll -->
<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -0.75rem;padding:0 0.75rem 0.5rem;max-width:100vw;">
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
            <span class="badge ms-1 <?= $status === $val ? 'bg-opacity-75 text-'.$color : 'bg-'.$color.' text-white' ?>"><?= $cnt ?></span>
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
                        <td>
                            <span class="badge bg-<?= $st['color'] ?>"><?= $st['label'] ?></span>
                            <?php if (!empty($r['xml_exported_at'])): ?>
                            <span class="badge bg-info ms-1" title="Exportováno <?= date('d.m.Y H:i', strtotime($r['xml_exported_at'])) ?>"><i class="bi bi-filetype-xml"></i></span>
                            <?php endif; ?>
                        </td>
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
                    <?php if (!empty($r['xml_exported_at'])): ?>
                    <span class="text-info"><i class="bi bi-filetype-xml me-1"></i><?= date('d.m.Y', strtotime($r['xml_exported_at'])) ?></span>
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

<?php endif; ?>
