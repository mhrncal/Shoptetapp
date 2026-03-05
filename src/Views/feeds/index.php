<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-cloud-download me-2"></i>Importy produktů</h4>
        <?php if (!empty($feeds)): ?>
        <p class="text-muted small mb-0"><?= count($feeds) ?> import<?= count($feeds) > 1 ? 'y' : '' ?></p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
        <form method="POST" action="/feeds/unlock-all" onsubmit="return confirm('Odblokovat zamrzlé?')">
            <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
            <button type="submit" class="btn btn-sm btn-outline-warning" title="Odblokovat zamrzlé">
                <i class="bi bi-unlock"></i><span class="d-none d-sm-inline ms-1">Odblokovat</span>
            </button>
        </form>
        <a href="/feeds/create" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i><span class="d-none d-sm-inline">Nový </span>Import
        </a>
    </div>
</div>

<!-- Progress po kliknutí sync -->
<div id="syncProgress" class="alert alert-info mb-3" style="display:none;">
    <div class="d-flex align-items-center gap-2">
        <div class="spinner-border spinner-border-sm flex-shrink-0" role="status"></div>
        <div>
            <strong>Synchronizace spuštěna</strong>
            <div class="small text-muted mt-1">Stránka se obnoví za <span id="countdown">10</span>s</div>
        </div>
    </div>
</div>

<!-- Běžící syncy -->
<?php
$runningFeeds = array_filter($timeline ?? [], fn($log) => $log['status'] === 'running');
if (!empty($runningFeeds)):
?>
<div class="alert alert-warning mb-3">
    <div class="d-flex align-items-start gap-2">
        <div class="spinner-border spinner-border-sm flex-shrink-0 mt-1" role="status"></div>
        <div>
            <strong>Právě běží <?= count($runningFeeds) ?> synchronizace</strong>
            <?php foreach ($runningFeeds as $log): ?>
            <div class="small mt-1"><?= $e($log['feed_name']) ?> <span class="text-muted">• <?= date('H:i', strtotime($log['started_at'])) ?></span></div>
            <?php endforeach; ?>
            <button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">
                <i class="bi bi-arrow-repeat me-1"></i>Obnovit
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Poslední chyba -->
<?php
$latestCompleted = array_values(array_filter($timeline ?? [], function($log) {
    if ($log['status'] === 'success') return false;
    $msg = $log['error_message'] ?? '';
    if ($log['status'] === 'error' && (
        str_contains($msg, 'killed manually') || str_contains($msg, 'killed after') ||
        str_contains($msg, 'Odblokováno')     || str_contains($msg, 'Process hung')
    )) return false;
    return $log['status'] !== 'running';
}));
if (!empty($latestCompleted)):
    $latest = $latestCompleted[0];
?>
<div class="alert alert-<?= $latest['status'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-3">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <div class="fw-semibold mb-1">
        <i class="bi bi-<?= $latest['status'] === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-1"></i>
        <?= $e($latest['feed_name'] ?? '') ?> — <?= $latest['status'] === 'success' ? 'úspěch' : 'chyba' ?>
    </div>
    <?php if ($latest['status'] !== 'success'): ?>
    <code class="text-danger small"><?= $e($latest['error_message'] ?? 'Neznámá chyba') ?></code>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Žádné feedy -->
<?php if (empty($feeds)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-cloud-slash fs-1 d-block mb-3"></i>
        <p>Zatím žádné importy.</p>
        <a href="/feeds/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Vytvořit první import
        </a>
    </div>
</div>
<?php else: ?>

<!-- DESKTOP: tabulka -->
<div class="card d-none d-md-block mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Název</th>
                        <th>Typ</th>
                        <th>Poslední sync</th>
                        <th>Status</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($feeds as $feed):
                    $isRunning = false;
                    foreach ($timeline ?? [] as $log) {
                        if ($log['feed_id'] == $feed['id'] && $log['status'] === 'running') { $isRunning = true; break; }
                    }
                ?>
                <tr class="<?= $isRunning ? 'table-warning' : '' ?>">
                    <td>
                        <div class="fw-semibold"><?= $e($feed['name']) ?></div>
                        <div class="text-muted font-monospace" style="font-size:.72rem;"><?= $e(substr($feed['url'], 0, 60)) ?>…</div>
                        <?php if ($isRunning): ?>
                        <span class="badge bg-warning text-dark mt-1" id="progress-<?= $feed['id'] ?>">
                            <span class="spinner-border spinner-border-sm"></span>
                            <span class="progress-text ms-1">Synchronizuje…</span>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $feed['type'] === 'csv_with_images' ? 'bg-info' : 'bg-secondary' ?>"><?= $feed['type'] === 'csv_with_images' ? 'S obrázky' : 'Základní' ?></span></td>
                    <td class="small text-muted"><?= $feed['last_fetch_at'] ? date('d.m.Y H:i', strtotime($feed['last_fetch_at'])) : '—' ?></td>
                    <td>
                        <?php if ($feed['last_fetch_status'] === 'success'): ?>
                            <span class="badge bg-success">OK</span>
                        <?php elseif ($feed['last_fetch_status'] === 'error'): ?>
                            <span class="badge bg-danger" title="<?= $e($feed['last_error']) ?>" data-bs-toggle="tooltip">Chyba</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" action="/feeds/sync" class="sync-form">
                                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary" <?= $isRunning ? 'disabled' : '' ?> title="Synchronizovat">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                            <form method="POST" action="/feeds/delete" onsubmit="return confirm('Smazat feed?')">
                                <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                                <input type="hidden" name="id" value="<?= $feed['id'] ?>">
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
<div class="d-md-none d-flex flex-column gap-2 mb-4">
    <?php foreach ($feeds as $feed):
        $isRunning = false;
        foreach ($timeline ?? [] as $log) {
            if ($log['feed_id'] == $feed['id'] && $log['status'] === 'running') { $isRunning = true; break; }
        }
        $statusErr = $feed['last_fetch_status'] === 'error';
    ?>
    <div class="card <?= $isRunning ? 'border-warning' : ($statusErr ? 'border-danger border-opacity-50' : '') ?>">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= $e($feed['name']) ?></div>
                    <div class="text-muted" style="font-size:.72rem; word-break:break-all;"><?= $e(substr($feed['url'], 0, 70)) ?>…</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                    <span class="badge <?= $feed['type'] === 'csv_with_images' ? 'bg-info' : 'bg-secondary' ?>"><?= $feed['type'] === 'csv_with_images' ? 'S obrázky' : 'Základní' ?></span>
                    <?php if ($isRunning): ?>
                        <span class="badge bg-warning text-dark"><span class="spinner-border spinner-border-sm"></span> Běží</span>
                    <?php elseif ($feed['last_fetch_status'] === 'success'): ?>
                        <span class="badge bg-success">OK</span>
                    <?php elseif ($statusErr): ?>
                        <span class="badge bg-danger">Chyba</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($feed['last_fetch_at']): ?>
            <div class="text-muted small mb-3"><i class="bi bi-clock me-1"></i><?= date('d.m.Y H:i', strtotime($feed['last_fetch_at'])) ?></div>
            <?php endif; ?>
            <?php if ($statusErr && $feed['last_error']): ?>
            <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:.8rem;">
                <i class="bi bi-exclamation-triangle me-1"></i><?= $e(substr($feed['last_error'], 0, 100)) ?>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2">
                <form method="POST" action="/feeds/sync" class="sync-form flex-grow-1">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                    <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100" <?= $isRunning ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-repeat me-1"></i>Synchronizovat
                    </button>
                </form>
                <form method="POST" action="/feeds/delete" onsubmit="return confirm('Smazat feed?')">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                    <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<!-- TIMELINE -->
<?php if (!empty($timeline)): ?>
<div class="mt-2">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2 text-muted"></i>Historie</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="bi bi-arrow-repeat"></i></button>
    </div>
    <div class="d-flex flex-column gap-2">
        <?php foreach (array_slice($timeline, 0, 10) as $log):
            $isRunning = $log['status'] === 'running';
            $isSuccess = $log['status'] === 'success';
            $isError   = $log['status'] === 'error';
            $runTime = '';
            if ($isRunning) {
                $diff = (new DateTime())->getTimestamp() - (new DateTime($log['started_at']))->getTimestamp();
                $runTime = " • {$diff}s";
            }
        ?>
        <div class="card <?= $isError ? 'border-danger border-opacity-50' : ($isRunning ? 'border-warning' : '') ?>"
             style="cursor:pointer;"
             onclick="this.querySelector('.log-details')?.classList.toggle('d-none')">
            <div class="card-body py-3">
                <div class="d-flex align-items-start gap-2">
                    <div class="flex-shrink-0 mt-1">
                        <?php if ($isRunning): ?>
                            <span class="spinner-border spinner-border-sm text-warning"></span>
                        <?php elseif ($isSuccess): ?>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill text-danger"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="fw-medium text-truncate"><?= $e($log['feed_name']) ?></span>
                            <span class="text-muted small flex-shrink-0">
                                <?= date('d.m. H:i', strtotime($log['started_at'])) ?><?php if ($log['duration_seconds']): ?> • <?= $log['duration_seconds'] ?>s<?php endif; ?><?= $runTime ?>
                            </span>
                        </div>
                        <?php if ($isSuccess): ?>
                        <div class="d-flex flex-wrap gap-2 mt-1" style="font-size:.8rem;">
                            <span class="text-success"><i class="bi bi-plus-circle me-1"></i><?= $log['products_inserted'] ?> nových</span>
                            <span class="text-info"><i class="bi bi-arrow-repeat me-1"></i><?= $log['products_updated'] ?> upd.</span>
                            <span class="text-primary"><i class="bi bi-box me-1"></i><?= $log['products_total'] ?> celkem</span>
                            <?php if (($log['reviews_matched'] ?? 0) > 0): ?>
                            <span class="text-warning"><i class="bi bi-link-45deg me-1"></i><?= $log['reviews_matched'] ?>/<?= $log['reviews_total'] ?> recenzí</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($isError && $log['error_message']): ?>
                        <div class="text-danger mt-1" style="font-size:.8rem;"><i class="bi bi-exclamation-triangle me-1"></i><?= $e(substr($log['error_message'], 0, 150)) ?></div>
                        <?php endif; ?>
                        <!-- Log detail -->
                        <div class="log-details d-none mt-3">
                            <div class="p-2 rounded" style="background:hsl(var(--muted));">
                                <?php if (!empty($log['log_text'])): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-medium">Log synchronizace</span>
                                    <a href="/feeds/log/<?= $log['id'] ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-download me-1"></i>Stáhnout</a>
                                </div>
                                <pre class="mb-0" style="font-size:11px;max-height:250px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"><?= $e($log['log_text']) ?></pre>
                                <?php elseif (!empty($log['error_message'])): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-medium text-danger">Chyba synchronizace</span>
                                </div>
                                <p class="small text-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i><?= $e($log['error_message']) ?></p>
                                <?php else: ?>
                                <p class="small text-muted mb-0">Log není k dispozici (starší synchronizace)</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <i class="bi bi-chevron-down text-muted flex-shrink-0 mt-1" style="font-size:.75rem;"></i>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
<?php if (!empty($runningFeeds)): ?>
let cd = 10;
const cdEl = document.getElementById('countdown');
setInterval(() => { cd--; if (cdEl) cdEl.textContent = cd; if (cd <= 0) location.reload(); }, 1000);
<?php endif; ?>
document.querySelectorAll('.sync-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const feedId = form.querySelector('input[name="id"]').value;
        const btn    = form.querySelector('button[type="submit"]');
        const progEl = document.getElementById('progress-' + feedId);

        // Zobraz spinner
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
        document.getElementById('syncProgress').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Odešli form přes AJAX
        const fd = new FormData(form);
        fetch(form.action, { method: 'POST', body: fd })
            .then(r => { if (r.redirected || r.ok) location.reload(); })
            .catch(() => location.reload());

        // Spusť polling ihned
        startProgressPolling(feedId);
    });
});

function startProgressPolling(feedId) {
    const interval = setInterval(() => {
        fetch('/feeds/sync-progress?feed_id=' + feedId)
            .then(r => r.json())
            .then(data => {
                updateProgressUI(feedId, data);
                if (data.status === 'done' || data.status === 'not_running') {
                    clearInterval(interval);
                    setTimeout(() => location.reload(), 1500);
                }
            })
            .catch(() => {});
    }, 2000);
}
[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).map(el => new bootstrap.Tooltip(el));
<?php
$runningFeedIds = [];
foreach ($feeds as $f) {
    foreach ($timeline ?? [] as $log) {
        if ($log['feed_id'] == $f['id'] && $log['status'] === 'running') { $runningFeedIds[] = $f['id']; break; }
    }
}
if (!empty($runningFeedIds)):
?>
function updateProgressUI(feedId, data) {
    const el = document.getElementById('progress-' + feedId);
    if (!el) return;
    const textEl  = el.querySelector('.progress-text');
    const barWrap = el.querySelector('.progress-bar-wrap');
    if (data.message && textEl) textEl.textContent = data.message;
    if (data.percent != null) {
        if (!barWrap) {
            const bar = document.createElement('div');
            bar.className = 'progress progress-bar-wrap mt-1';
            bar.style.height = '4px';
            bar.innerHTML = '<div class="progress-bar bg-primary" style="width:' + data.percent + '%"></div>';
            textEl && textEl.parentNode.appendChild(bar);
        } else {
            const inner = barWrap.querySelector('.progress-bar');
            if (inner) inner.style.width = data.percent + '%';
        }
    }
    let detail = '';
    if (data.details) {
        const d = data.details;
        if (d.downloaded_mb) detail = d.downloaded_mb + (d.total_mb && d.total_mb !== '?' ? ' / ' + d.total_mb : '') + ' MB';
        else if (d.done && d.total) detail = d.done.toLocaleString('cs') + ' / ' + d.total.toLocaleString('cs') + ' řádků';
        if (d.inserted !== undefined) detail += (detail ? ' • ' : '') + d.inserted + ' nových, ' + d.updated + ' aktualizovaných';
    }
    const detailEl = el.querySelector('.progress-detail');
    if (detailEl) detailEl.textContent = detail;
    else if (detail && textEl) {
        const span = document.createElement('span');
        span.className = 'progress-detail text-muted d-block';
        span.style.fontSize = '.75rem';
        span.textContent = detail;
        textEl.parentNode.insertBefore(span, textEl.nextSibling);
    }
    if (data.elapsed) {
        const min = Math.floor(data.elapsed / 60), sec = data.elapsed % 60;
        const timeStr = (min > 0 ? min + 'm ' : '') + sec + 's';
        const timeEl = el.querySelector('.progress-elapsed');
        if (timeEl) timeEl.textContent = timeStr;
        else if (textEl) {
            const span = document.createElement('span');
            span.className = 'progress-elapsed text-muted ms-2';
            span.style.fontSize = '.75rem';
            span.textContent = timeStr;
            textEl.after(span);
        }
    }
    if (data.status === 'done' || data.status === 'not_running') {
        setTimeout(() => location.reload(), 1500);
    }
}
function updateProgress() {
    <?php foreach ($runningFeedIds as $fid): ?>
    fetch('/feeds/sync-progress?feed_id=<?= $fid ?>').then(r=>r.json()).then(d => updateProgressUI(<?= $fid ?>, d)).catch(()=>{});
    <?php endforeach; ?>
}
setInterval(updateProgress, 2000);
updateProgress();
<?php endif; ?>
</script>
