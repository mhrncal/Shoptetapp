<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<?php
$platformLabels = ['heureka' => 'Heureka', 'trustedshops' => 'Trusted Shops', 'shoptet' => 'Shoptet', 'google' => 'Google'];
$platformColors = ['heureka' => 'warning', 'trustedshops' => 'success', 'shoptet' => 'primary', 'google' => 'danger'];
?>

<!-- Hlavička -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-star me-2"></i>Scrapované recenze</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
    </div>
    <?php if (!empty($userLangs) && $hasDeepL): ?>
    <button id="btnTranslate" class="btn btn-sm btn-outline-primary" onclick="startTranslate()">
        <i class="bi bi-translate me-1"></i>Přeložit nepřeložené
    </button>
    <?php endif; ?>
</div>



<div class="row g-3 mb-4">

    <!-- Zdroje -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-link-45deg me-1"></i>Zdroje</h6>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addSourceForm">
                    <i class="bi bi-plus"></i> Přidat
                </button>
            </div>

            <!-- Formulář přidání zdroje -->
            <div class="collapse" id="addSourceForm">
                <div class="card-body border-bottom">
                    <form method="POST" action="/scraped-reviews/add-source">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <div class="mb-2">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Název (např. Heureka CZ)" required>
                        </div>
                        <div class="mb-2">
                            <input type="url" name="url" class="form-control form-control-sm" placeholder="URL stránky s recenzemi" required>
                        </div>
                        <div class="mb-2">
                            <select name="platform" class="form-select form-select-sm" required>
                                <option value="">— Platforma —</option>
                                <option value="heureka">Heureka</option>
                                <option value="trustedshops">Trusted Shops</option>
                                <option value="shoptet">Shoptet</option>
                                <option value="google">Google (Place ID)</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Přidat zdroj</button>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($sources)): ?>
                <div class="text-center text-muted py-4 small">Zatím žádné zdroje.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($sources as $s): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $platformColors[$s['platform']] ?? 'secondary' ?> flex-shrink-0"><?= $platformLabels[$s['platform']] ?? $s['platform'] ?></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate"><?= $e($s['name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;word-break:break-all;overflow-wrap:anywhere;"><?= $e($s['url']) ?></div>
                                <?php if ($s['last_scraped_at']): ?>
                                <div class="text-muted" style="font-size:.7rem;">Naposledy: <?= date('d.m.Y H:i', strtotime($s['last_scraped_at'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <form method="POST" action="/scraped-reviews/scrape">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="source_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Scrape nyní"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                                <form method="POST" action="/scraped-reviews/delete-source" onsubmit="return confirm('Smazat zdroj i recenze?')">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: DeepL klíč + jazyky -->
    <div class="col-12 col-lg-7 d-flex flex-column gap-3">

        <!-- DeepL API klíč -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-1"></i>DeepL API klíč</h6>
                <?php if ($hasDeepL): ?>
                <span class="badge bg-success ms-auto">Aktivní</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark ms-auto">Nenastaveno</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="/scraped-reviews/save-api-key">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <div class="input-group input-group-sm">
                        <input type="password" name="deepl_api_key" class="form-control font-monospace"
                               placeholder="váš-deepl-api-klíč:fx"
                               value="<?= $hasDeepL ? '••••••••••••••••' : '' ?>">
                        <button type="submit" class="btn btn-primary">Uložit</button>
                        <?php if ($hasDeepL): ?>
                        <button type="submit" name="deepl_api_key" value="" class="btn btn-outline-danger" onclick="return confirm('Odstranit klíč?')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.75rem;">
                        Free klíč z <a href="https://www.deepl.com/pro-api" target="_blank">deepl.com/pro-api</a> — 500 000 znaků/měsíc zdarma. Klíč končí <code>:fx</code>.
                    </div>
                </form>
            </div>
        </div>

        <!-- Google Places API klíč -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-geo-alt me-1"></i>Google Places API klíč</h6>
                <?php if ($hasGoogleKey): ?>
                <span class="badge bg-success ms-auto">Aktivní</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark ms-auto">Nenastaveno</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="/scraped-reviews/save-google-api-key">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <div class="input-group input-group-sm">
                        <input type="password" name="google_places_api_key" class="form-control font-monospace"
                               placeholder="AIza..."
                               value="<?= $hasGoogleKey ? '••••••••••••••••' : '' ?>">
                        <button type="submit" class="btn btn-primary">Uložit</button>
                        <?php if ($hasGoogleKey): ?>
                        <button type="submit" name="google_places_api_key" value="" class="btn btn-outline-danger" onclick="return confirm('Odstranit klíč?')"><i class="bi bi-trash"></i></button>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.75rem;">
                        Zdarma na <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a> — Places API. Jako URL zdroje zadejte <strong>Place ID</strong> (např. <code>ChIJN1t_tDeuEmsR...</code>).
                    </div>
                </form>
            </div>
        </div>

        <!-- Jazyky překladů -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-translate me-1"></i>Jazyky překladů</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="/scraped-reviews/save-langs">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($allLangs as $code => $label): ?>
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="checkbox" name="langs[]"
                                   value="<?= $e($code) ?>" id="lang_<?= $e($code) ?>"
                                   <?= in_array($code, $userLangs) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="lang_<?= $e($code) ?>"><?= $e($label) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary <?= !$hasDeepL ? 'disabled' : '' ?>">Uložit jazyky</button>
                    <?php if (!$hasDeepL): ?>
                    <span class="text-muted small ms-2">Nejprve zadejte DeepL klíč.</span>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Synchronizace -->
<?php if (!empty($sources)): ?>
<div class="card mb-3" id="syncCard">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button id="btnSyncAll" class="btn btn-sm btn-primary" onclick="startSyncAll()">
                <i class="bi bi-arrow-repeat me-1"></i>Synchronizovat vše
            </button>
            <?php foreach ($sources as $s): ?>
            <button class="btn btn-sm btn-outline-<?= $platformColors[$s['platform']] ?? 'secondary' ?> btn-sync-one"
                    data-id="<?= $s['id'] ?>" data-name="<?= $e($s['name']) ?>"
                    onclick="startSyncOne(<?= $s['id'] ?>, '<?= $e(addslashes($s['name'])) ?>')">
                <i class="bi bi-arrow-clockwise me-1"></i><?= $e($s['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
        <!-- Progress bar -->
        <div id="syncProgress" class="mt-2" style="display:none;">
            <div class="progress" style="height:6px;">
                <div id="syncProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div>
            </div>
            <div id="syncProgressMsg" class="text-muted mt-1" style="font-size:.75rem;"></div>
        </div>
    </div>
</div>

<script>
(function() {
    const csrf = <?= json_encode($csrfToken) ?>;
    let syncing = false;
    let syncQueue = [];
    let syncTotal = 0;
    let syncDone = 0;

    function setButtons(disabled) {
        syncing = disabled;
        document.getElementById('btnSyncAll').disabled = disabled;
        document.querySelectorAll('.btn-sync-one').forEach(b => b.disabled = disabled);
    }

    function setProgress(pct, msg, color) {
        const el = document.getElementById('syncProgress');
        el.style.display = '';
        const bar = document.getElementById('syncProgressBar');
        bar.style.width = pct + '%';
        bar.className = 'progress-bar progress-bar-striped' + (pct < 100 ? ' progress-bar-animated' : '') + (color ? ' bg-' + color : '');
        document.getElementById('syncProgressMsg').textContent = msg;
    }

    function hideProgress() {
        setTimeout(() => { document.getElementById('syncProgress').style.display = 'none'; }, 3000);
    }

    async function runSync(sourceId, sourceName) {
        setProgress(Math.round((syncDone / syncTotal) * 80), 'Synchronizuji: ' + sourceName + '…');
        try {
            const r = await fetch('/scraped-reviews/sync-one', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_csrf=' + encodeURIComponent(csrf) + '&source_id=' + sourceId
            });
            const d = await r.json();
            syncDone++;
            const pct = Math.round((syncDone / syncTotal) * 100);
            if (!d.ok) {
                setProgress(pct, '⚠ ' + sourceName + ': ' + (d.error || 'chyba'), 'warning');
            } else {
                setProgress(pct, 'Hotovo: ' + sourceName + ' — nových: ' + d.new + ', přeloženo: ' + d.translated);
            }
        } catch(e) {
            syncDone++;
            setProgress(Math.round((syncDone / syncTotal) * 100), '⚠ Chyba sítě: ' + sourceName, 'danger');
        }
    }

    async function processQueue() {
        for (const item of syncQueue) {
            await runSync(item.id, item.name);
        }
        setProgress(100, '✓ Synchronizace dokončena. Stránka se obnoví…', 'success');
        setButtons(false);
        hideProgress();
        setTimeout(() => location.reload(), 2000);
    }

    window.startSyncAll = async function() {
        if (syncing) return;
        setButtons(true);
        const r = await fetch('/scraped-reviews/sync-all');
        const d = await r.json();
        syncQueue = d.sources;
        syncTotal = syncQueue.length;
        syncDone = 0;
        setProgress(0, 'Připravuji synchronizaci ' + syncTotal + ' zdrojů…');
        processQueue();
    };

    window.startSyncOne = function(id, name) {
        if (syncing) return;
        setButtons(true);
        syncQueue = [{id, name}];
        syncTotal = 1;
        syncDone = 0;
        setProgress(0, 'Připravuji…');
        processQueue();
    };
})();

    window.startTranslate = async function() {
        if (syncing) return;
        const btn = document.getElementById('btnTranslate');
        if (!btn) return;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Překládám…';

        // Ukaž progress bar
        const prog = document.getElementById('syncProgress');
        if (prog) {
            prog.style.display = '';
            document.getElementById('syncProgressBar').style.width = '30%';
            document.getElementById('syncProgressBar').className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
            document.getElementById('syncProgressMsg').textContent = 'Překládám nepřeložené recenze…';
        }

        try {
            const r = await fetch('/scraped-reviews/translate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: '_csrf=' + encodeURIComponent(csrf)
            });
            const d = await r.json();
            if (prog) {
                document.getElementById('syncProgressBar').style.width = '100%';
                document.getElementById('syncProgressBar').className = 'progress-bar bg-success';
                document.getElementById('syncProgressMsg').textContent = d.ok
                    ? '✓ Přeloženo ' + d.translated + ' textů do ' + d.langs + ' jazyků.'
                    : '⚠ Chyba překladu.';
                setTimeout(() => { prog.style.display = 'none'; location.reload(); }, 2500);
            }
        } catch(e) {
            if (prog) {
                document.getElementById('syncProgressBar').className = 'progress-bar bg-danger';
                document.getElementById('syncProgressMsg').textContent = '⚠ Chyba sítě.';
            }
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-translate me-1"></i>Přeložit nepřeložené';
    };
})();
</script>
<?php endif; ?>

<!-- Filtr zdrojů -->
<?php if (count($sources) > 1): ?>
<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -0.75rem;padding:0 0.75rem 0.5rem;">
    <div class="d-flex gap-2 mb-3" style="flex-wrap:nowrap;min-width:max-content;">
        <a href="/scraped-reviews" class="btn btn-sm <?= !$sourceFilter ? 'btn-secondary' : 'btn-outline-secondary' ?>">Vše</a>
        <?php foreach ($sources as $s): ?>
        <a href="?source=<?= $s['id'] ?>" class="btn btn-sm <?= $sourceFilter == $s['id'] ? 'btn-'.$platformColors[$s['platform']] : 'btn-outline-'.$platformColors[$s['platform']] ?>">
            <?= $e($s['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Seznam recenzí -->
<?php if (empty($reviews)): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-star fs-1 d-block mb-3"></i>
    <p>Žádné recenze. Přidejte zdroj a spusťte scraping.</p>
</div></div>
<?php else: ?>
<div class="d-flex flex-column gap-2 mb-3">
    <?php foreach ($reviews as $r): ?>
    <a href="/scraped-reviews/<?= $r['id'] ?>" class="text-decoration-none">
    <div class="card card-hover">
        <div class="card-body py-2 px-3">
            <?php
                $hasCs      = !empty($r['cs_content']);
                $hasContent = !empty(trim($r['content'] ?? ''));
                $displayTxt = $hasCs ? $r['cs_content'] : $r['content'];
                $srcLang    = strtoupper($r['source_lang'] ?? '');
                $isCS       = $srcLang === 'CS';
            ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-<?= $platformColors[$r['platform']] ?? 'secondary' ?> flex-shrink-0" style="font-size:.65rem;"><?= $platformLabels[$r['platform']] ?? $r['platform'] ?></span>
                <strong class="small flex-grow-1 text-dark text-truncate"><?= $e($r['author']) ?></strong>
                <?php if ($r['rating']): ?>
                <span class="text-warning flex-shrink-0" style="font-size:.75rem;"><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5-(int)$r['rating']) ?></span>
                <?php endif; ?>
                <span class="text-muted flex-shrink-0" style="font-size:.7rem;"><?= $r['reviewed_at'] ? date('d.m.Y', strtotime($r['reviewed_at'])) : '' ?></span>
            </div>
            <?php if ($hasContent || $hasCs): ?>
            <p class="small mb-1" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;color:#333;"><?= $e($displayTxt) ?></p>
            <?php else: ?>
            <p class="small text-muted mb-1 fst-italic">Bez textu</p>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-1">
                <?php if ($hasCs): ?>
                <span class="badge bg-success" style="font-size:.6rem;">🇨🇿 CS</span>
                <?php if ($srcLang && !$isCS): ?>
                <span class="badge bg-light text-muted border" style="font-size:.6rem;"><?= $e(strtolower($srcLang)) ?> → cs</span>
                <?php endif; ?>
                <?php elseif ($isCS || !$srcLang): ?>
                <span class="badge bg-secondary" style="font-size:.6rem;">cs</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark" style="font-size:.6rem;" title="Nepřeloženo">⚠ <?= $e(strtolower($srcLang)) ?></span>
                <?php endif; ?>
                <span class="ms-auto text-muted" style="font-size:.65rem;"><?= $e($r['source_name']) ?></span>
            </div>
        </div>
    </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total > $perPage): ?>
<?php $pages = ceil($total / $perPage); ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&source=<?= $sourceFilter ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>

<style>.card-hover:hover{background:#f8f9fa;transition:background .15s;}</style>
