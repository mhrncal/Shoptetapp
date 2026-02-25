<?php $pageTitle = 'Import produktů'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-arrow-down me-2"></i>Import produktů</h4>
</div>

<div class="row g-4">

    <!-- Levý sloupec: formulář -->
    <div class="col-12 col-lg-5">

        <!-- Aktivní import -->
        <?php if ($activeItem): ?>
        <div class="card mb-4 border-<?= $activeItem['status'] === 'processing' ? 'info' : 'warning' ?> border-opacity-50" id="activeImportCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <?php if ($activeItem['status'] === 'processing'): ?>
                        <span class="spinner-border spinner-border-sm text-info me-2"></span>Zpracovává se
                    <?php else: ?>
                        <i class="bi bi-hourglass-split text-warning me-2"></i>Čeká ve frontě
                    <?php endif; ?>
                </h6>
                <span class="badge bg-<?= $activeItem['status'] === 'processing' ? 'info' : 'warning text-dark' ?>">
                    <?= strtoupper($activeItem['feed_format'] ?? 'XML') ?> · <?= $e($activeItem['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="text-muted small text-truncate mb-3" title="<?= $e($activeItem['xml_feed_url']) ?>">
                    <i class="bi bi-link-45deg me-1"></i><?= $e($activeItem['xml_feed_url']) ?>
                </div>
                <?php if ($activeItem['status'] === 'processing'): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Zpracováno</span>
                        <strong id="progressCount"><?= number_format($activeItem['products_processed']) ?></strong>
                    </div>
                    <div class="progress mb-1" style="height:8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                             id="progressBar" style="width:<?= $activeItem['progress_percentage'] ?>%"></div>
                    </div>
                    <div class="text-end text-muted small" id="progressPct"><?= $activeItem['progress_percentage'] ?>%</div>
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
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-plus-circle me-2 text-muted"></i>Nový import</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/xml/start" id="importForm">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <!-- Formát -->
                    <div class="mb-3">
                        <label class="form-label fw-medium">Formát feedu</label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="feed_format" id="fmt_xml" value="xml" checked>
                            <label class="btn btn-outline-secondary btn-sm flex-fill" for="fmt_xml">
                                <i class="bi bi-filetype-xml me-1"></i>XML
                            </label>
                            <input type="radio" class="btn-check" name="feed_format" id="fmt_csv" value="csv">
                            <label class="btn btn-outline-secondary btn-sm flex-fill" for="fmt_csv">
                                <i class="bi bi-filetype-csv me-1"></i>CSV
                            </label>
                        </div>
                    </div>

                    <!-- URL feedu -->
                    <div class="mb-3">
                        <label class="form-label">URL feedu <span class="text-danger">*</span></label>
                        <input type="url" name="feed_url" class="form-control"
                               placeholder="https://mujshop.cz/export/feed.xml"
                               value="<?= $e($user['xml_feed_url'] ?? '') ?>" required>
                    </div>

                    <!-- Priorita -->
                    <div class="mb-3">
                        <label class="form-label">Priorita</label>
                        <select name="priority" class="form-select">
                            <option value="1">🔴 Vysoká (1)</option>
                            <option value="5" selected>🟡 Normální (5)</option>
                            <option value="10">🟢 Nízká (10)</option>
                        </select>
                    </div>

                    <!-- CSV Mapování sloupců (zobrazí se jen pro CSV) -->
                    <div id="csvMapping" style="display:none;">
                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-medium mb-0">Mapování sloupců CSV</label>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetMapping">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Výchozí
                            </button>
                        </div>
                        <div class="text-muted small mb-3">
                            Zadej <strong>přesný název sloupce</strong> v CSV pro každé pole.
                            Prázdné pole = bude ignorováno.
                        </div>

                        <?php
                        // Povinná pole
                        $required = ['code' => 'Kód (code) *', 'pairCode' => 'Grupování variant (pairCode)'];
                        // Volitelná pole
                        $optional = [
                            'name'         => 'Název produktu',
                            'category'     => 'Kategorie',
                            'price'        => 'Cena',
                            'brand'        => 'Značka',
                            'description'  => 'Popis',
                            'availability' => 'Dostupnost',
                            'images'       => 'Obrázek (URL)',
                        ];
                        ?>

                        <div class="table-responsive">
                            <table class="table table-sm mb-0" style="font-size:.8rem;">
                                <thead>
                                    <tr>
                                        <th style="width:45%">Pole v aplikaci</th>
                                        <th>Sloupec v CSV</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach (array_merge($required, $optional) as $internal => $label):
                                    $default = $csvDefaultMap[$internal] ?? $internal;
                                ?>
                                <tr>
                                    <td class="align-middle">
                                        <?= $e($label) ?>
                                    </td>
                                    <td>
                                        <input type="text"
                                               name="field_map[<?= $e($internal) ?>]"
                                               class="form-control form-control-sm csv-map-input"
                                               data-default="<?= $e($default) ?>"
                                               value="<?= $e($default) ?>"
                                               placeholder="název sloupce">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-secondary py-2 px-3 mt-2 small">
                            <i class="bi bi-lightbulb me-1"></i>
                            <strong>Tip:</strong> Shoptet výchozí CSV má sloupce:
                            <code>code;pairCode;name;defaultCategory</code>
                        </div>
                    </div>

                    <!-- XML Mapování (volitelné, skryté v accordionu) -->
                    <div id="xmlMapping">
                        <div class="accordion accordion-flush mt-3" id="xmlAccordion">
                            <div class="accordion-item border rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 px-3 small" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#xmlMapBody">
                                        <i class="bi bi-sliders me-2 text-muted"></i>Pokročilé: vlastní mapování XML tagů
                                    </button>
                                </h2>
                                <div id="xmlMapBody" class="accordion-collapse collapse">
                                    <div class="accordion-body py-2">
                                        <div class="text-muted small mb-2">
                                            XPath tagy pro Shoptet XML (výchozí fungují pro standardní feed).
                                        </div>
                                        <table class="table table-sm mb-0" style="font-size:.8rem;">
                                            <thead><tr><th>Pole</th><th>XML tag</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($xmlDefaultMap as $internal => $tag): ?>
                                            <tr>
                                                <td class="align-middle text-muted"><?= $e($internal) ?></td>
                                                <td>
                                                    <input type="text"
                                                           name="field_map[<?= $e($internal) ?>]"
                                                           class="form-control form-control-sm"
                                                           value="<?= $e($tag) ?>">
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-play-fill me-2"></i>Spustit import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: fronta + historie -->
    <div class="col-12 col-lg-7">

        <?php if (!empty($queue)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-task me-2 text-muted"></i>Fronta zpracování</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr>
                            <th>#</th><th>Formát</th><th>Stav</th><th>Pokrok</th><th>Pokusů</th><th>Přidáno</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($queue as $q):
                            $colors = ['pending'=>'warning','processing'=>'info','completed'=>'success','failed'=>'danger'];
                        ?>
                        <tr>
                            <td class="text-muted"><?= $q['id'] ?></td>
                            <td><span class="badge bg-secondary"><?= strtoupper($q['feed_format'] ?? 'XML') ?></span></td>
                            <td><span class="badge bg-<?= $colors[$q['status']] ?? 'secondary' ?>"><?= $e($q['status']) ?></span></td>
                            <td>
                                <?php if ($q['status'] === 'processing'): ?>
                                <div class="progress" style="height:5px;width:80px;">
                                    <div class="progress-bar bg-info" style="width:<?= $q['progress_percentage'] ?>%"></div>
                                </div>
                                <span class="text-muted" style="font-size:.7rem;"><?= $q['progress_percentage'] ?>%</span>
                                <?php elseif ($q['status'] === 'completed'): ?>
                                <span class="text-success small"><?= number_format($q['products_processed']) ?> ks</span>
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

        <!-- Historie -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-muted"></i>Historie importů</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Zatím žádné importy
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr>
                            <th>Formát</th><th>Stav</th><th>Nové</th><th>Akt.</th><th>Čas</th><th>Trvání</th>
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
                            <td><span class="badge bg-secondary"><?= strtoupper($h['feed_format'] ?? 'XML') ?></span></td>
                            <td>
                                <span class="badge bg-<?= $colors[$h['status']] ?? 'secondary' ?>">
                                    <?= $labels[$h['status']] ?? $e($h['status']) ?>
                                </span>
                                <?php if ($h['error_message']): ?>
                                <i class="bi bi-info-circle text-danger ms-1"
                                   title="<?= $e($h['error_message']) ?>" data-bs-toggle="tooltip"></i>
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

<script>
(function() {
    var fmtXml = document.getElementById('fmt_xml');
    var fmtCsv = document.getElementById('fmt_csv');
    var csvDiv  = document.getElementById('csvMapping');
    var xmlDiv  = document.getElementById('xmlMapping');

    function toggleFormat() {
        var isCsv = fmtCsv.checked;
        csvDiv.style.display = isCsv ? '' : 'none';
        xmlDiv.style.display = isCsv ? 'none' : '';
    }

    fmtXml.addEventListener('change', toggleFormat);
    fmtCsv.addEventListener('change', toggleFormat);
    toggleFormat();

    // Reset mapování na výchozí
    document.getElementById('resetMapping').addEventListener('click', function() {
        document.querySelectorAll('.csv-map-input').forEach(function(el) {
            el.value = el.dataset.default;
        });
    });

    <?php if ($activeItem && in_array($activeItem['status'], ['pending', 'processing'])): ?>
    // Polling pro live progress
    var itemId   = <?= (int)$activeItem['id'] ?>;
    var interval = setInterval(function() {
        fetch('<?= APP_URL ?>/xml/status?id=' + itemId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.item) { clearInterval(interval); return; }
                var item = data.item;
                if (item.status === 'processing') {
                    document.getElementById('progressBar').style.width = item.progress_percentage + '%';
                    document.getElementById('progressPct').textContent  = item.progress_percentage + '%';
                    document.getElementById('progressCount').textContent = parseInt(item.products_processed).toLocaleString('cs');
                }
                if (!data.active) {
                    clearInterval(interval);
                    setTimeout(function() { window.location.reload(); }, 1500);
                }
            });
    }, 3000);
    <?php endif; ?>
})();
</script>
