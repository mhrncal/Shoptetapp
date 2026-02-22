<?php $pageTitle = 'XML Import'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-arrow-down me-2"></i>XML Import</h4>
</div>

<div class="row g-4">

    <!-- Levý sloupec: formulář + aktivní import -->
    <div class="col-12 col-lg-5">

        <!-- Aktivní / čekající import -->
        <?php if ($activeItem): ?>
        <div class="card border-0 border-<?= $activeItem['status'] === 'processing' ? 'info' : 'warning' ?> border-opacity-50 mb-4" id="activeImportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <?php if ($activeItem['status'] === 'processing'): ?>
                        <span class="spinner-border spinner-border-sm text-info me-2"></span>Zpracovává se
                    <?php else: ?>
                        <i class="bi bi-hourglass-split text-warning me-2"></i>Čeká ve frontě
                    <?php endif; ?>
                </h6>
                <span class="badge bg-<?= $activeItem['status'] === 'processing' ? 'info' : 'warning text-dark' ?>">
                    <?= $e($activeItem['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="text-muted small text-truncate mb-3" title="<?= $e($activeItem['xml_feed_url']) ?>">
                    <i class="bi bi-link-45deg me-1"></i><?= $e($activeItem['xml_feed_url']) ?>
                </div>

                <?php if ($activeItem['status'] === 'processing'): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Zpracováno produktů</span>
                        <strong id="progressCount"><?= number_format($activeItem['products_processed']) ?></strong>
                    </div>
                    <div class="progress mb-1" style="height:8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                             id="progressBar"
                             style="width:<?= $activeItem['progress_percentage'] ?>%"></div>
                    </div>
                    <div class="text-end text-muted small" id="progressPct"><?= $activeItem['progress_percentage'] ?>%</div>
                </div>
                <?php endif; ?>

                <?php if ($activeItem['retry_count'] > 0): ?>
                <div class="alert alert-warning py-1 px-2 small mb-2">
                    <i class="bi bi-arrow-repeat me-1"></i>Pokus <?= $activeItem['retry_count'] ?>/<?= $activeItem['max_retries'] ?>
                </div>
                <?php endif; ?>

                <?php if ($activeItem['status'] === 'pending'): ?>
                <form method="POST" action="<?= APP_URL ?>/xml/cancel">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $activeItem['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Zrušit import?')">
                        <i class="bi bi-x-circle me-1"></i>Zrušit
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulář nového importu -->
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-2 text-muted"></i>Nový import</h6>
            </div>
            <div class="card-body">
                <?php if ($activeItem): ?>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-info-circle me-1"></i>
                    Nový import lze přidat i při probíhajícím — zařadí se do fronty.
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= APP_URL ?>/xml/start">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label">URL XML feedu <span class="text-danger">*</span></label>
                        <input type="url" name="xml_feed_url" class="form-control"
                               placeholder="https://mujshop.cz/export/feed.xml"
                               value="<?= $e($user['xml_feed_url'] ?? '') ?>" required>
                        <div class="form-text">Odkaz na váš Shoptet XML produktový feed</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Priorita</label>
                        <select name="priority" class="form-select">
                            <option value="1">🔴 Vysoká (1)</option>
                            <option value="5" selected>🟡 Normální (5)</option>
                            <option value="10">🟢 Nízká (10)</option>
                        </select>
                        <div class="form-text">Nižší číslo = vyšší priorita ve frontě</div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-play-fill me-2"></i>Spustit import
                    </button>
                </form>

                <hr class="border-secondary my-4">

                <div class="small text-muted">
                    <p class="fw-semibold text-body mb-2"><i class="bi bi-info-circle me-1"></i>Jak to funguje?</p>
                    <ol class="ps-3 mb-0">
                        <li>Feed se zařadí do fronty ke zpracování</li>
                        <li>Cron job automaticky stáhne XML (i 500MB+)</li>
                        <li>Produkty se importují po dávkách 500 ks</li>
                        <li>Při chybě proběhnou až 3 automatické pokusy</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: historie a fronta -->
    <div class="col-12 col-lg-7">

        <!-- Fronta -->
        <?php if (!empty($queue)): ?>
        <div class="card border-0 mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-task me-2 text-muted"></i>Fronta zpracování</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr>
                            <th>#</th><th>Stav</th><th>Pokrok</th><th>Pokusů</th><th>Přidáno</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($queue as $q):
                            $colors = ['pending'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger'];
                        ?>
                        <tr>
                            <td class="text-muted"><?= $q['id'] ?></td>
                            <td><span class="badge bg-<?= $colors[$q['status']] ?? 'secondary' ?>"><?= $e($q['status']) ?></span></td>
                            <td>
                                <?php if ($q['status'] === 'processing'): ?>
                                <div class="progress" style="height:5px;width:80px;">
                                    <div class="progress-bar bg-info" style="width:<?= $q['progress_percentage'] ?>%"></div>
                                </div>
                                <span class="text-muted" style="font-size:.7rem;"><?= $q['progress_percentage'] ?>% · <?= number_format($q['products_processed']) ?> ks</span>
                                <?php elseif ($q['status'] === 'completed'): ?>
                                <span class="text-success small"><?= number_format($q['products_processed']) ?> ks</span>
                                <?php elseif ($q['error_message']): ?>
                                <span class="text-danger small" title="<?= $e($q['error_message']) ?>">
                                    <i class="bi bi-exclamation-triangle"></i> Chyba
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= $q['retry_count'] ?>/<?= $q['max_retries'] ?></td>
                            <td class="text-muted"><?= date('d.m. H:i', strtotime($q['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Historie importů -->
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-muted"></i>Historie importů</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Zatím žádné importy
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr>
                            <th>Stav</th><th>Nové</th><th>Aktualizováno</th><th>Čas</th><th>Trvání</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($history as $h):
                            $colors = ['completed'=>'success','failed'=>'danger','processing'=>'info','pending'=>'secondary'];
                            $labels = ['completed'=>'Dokončen','failed'=>'Chyba','processing'=>'Probíhá','pending'=>'Čeká'];
                            $duration = ($h['completed_at'] && $h['started_at'])
                                ? gmdate('H:i:s', strtotime($h['completed_at']) - strtotime($h['started_at']))
                                : '—';
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= $colors[$h['status']] ?? 'secondary' ?>">
                                    <?= $labels[$h['status']] ?? $e($h['status']) ?>
                                </span>
                                <?php if ($h['error_message']): ?>
                                <i class="bi bi-info-circle text-danger ms-1"
                                   title="<?= $e($h['error_message']) ?>"
                                   data-bs-toggle="tooltip"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($h['products_imported']) ?></td>
                            <td><?= number_format($h['products_updated']) ?></td>
                            <td class="text-muted"><?= date('d.m.Y H:i', strtotime($h['created_at'])) ?></td>
                            <td class="text-muted"><?= $duration ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($activeItem && in_array($activeItem['status'], ['pending', 'processing'])): ?>
<script>
// Polling pro live progress
(function() {
    var itemId   = <?= (int)$activeItem['id'] ?>;
    var interval = setInterval(function() {
        $.getJSON('<?= APP_URL ?>/xml/status?id=' + itemId, function(data) {
            if (!data.item) { clearInterval(interval); return; }
            var item = data.item;

            if (item.status === 'processing') {
                $('#progressBar').css('width', item.progress_percentage + '%');
                $('#progressPct').text(item.progress_percentage + '%');
                $('#progressCount').text(parseInt(item.products_processed).toLocaleString('cs'));
            }

            if (!data.active) {
                clearInterval(interval);
                // Reload stránky po dokončení
                setTimeout(function() { window.location.reload(); }, 1500);
            }
        });
    }, 3000); // Každé 3 sekundy
})();
</script>
<?php endif; ?>
