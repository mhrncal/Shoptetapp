<?php $e = fn($v) => htmlspecialchars($v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Importy produktů</h1>
    <a href="/feeds/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nový import
    </a>
</div>

<!-- Progress bar pro synchronizaci -->
<div id="syncProgress" class="alert alert-info" style="display:none;">
    <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm me-2" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="flex-grow-1">
            <strong>Synchronizace spuštěna na pozadí</strong>
            <div class="progress mt-2" style="height: 20px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 100%">
                    Stránka se automaticky obnoví za <span id="countdown">10</span>s
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Running feeds notification -->
<?php 
$runningFeeds = array_filter($timeline ?? [], fn($log) => $log['status'] === 'running');
if (!empty($runningFeeds)): 
?>
<div class="alert alert-warning">
    <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
        <div class="flex-grow-1">
            <strong>Právě běží <?= count($runningFeeds) ?> synchronizace</strong>
            <div class="mt-1 small">
                <?php foreach ($runningFeeds as $log): ?>
                    • <?= $e($log['feed_name']) ?> 
                    <span class="text-muted">(spuštěno <?= date('H:i', strtotime($log['started_at'])) ?>)</span><br>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">
                <i class="bi bi-arrow-repeat"></i> Obnovit stránku
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Latest completed sync -->
<?php 
$latestCompleted = array_filter($timeline ?? [], fn($log) => $log['status'] !== 'running');
if (!empty($latestCompleted)):
    $latest = $latestCompleted[0];
?>
<div class="alert alert-<?= $latest['status'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    
    <?php if ($latest['status'] === 'success'): ?>
        <h5 class="alert-heading">
            <i class="bi bi-check-circle-fill"></i> 
            Synchronizace úspěšná: <?= $e($latest['feed_name']) ?>
        </h5>
        <p class="mb-1">
            Dokončeno <?= date('d.m.Y H:i', strtotime($latest['finished_at'])) ?> 
            <span class="badge bg-success"><?= $latest['duration_seconds'] ?>s</span>
        </p>
        <div class="small">
            <span class="me-3">
                <i class="bi bi-plus-circle"></i> <strong><?= $latest['products_inserted'] ?></strong> nových produktů
            </span>
            <span class="me-3">
                <i class="bi bi-arrow-repeat"></i> <strong><?= $latest['products_updated'] ?></strong> aktualizováno
            </span>
            <span class="me-3">
                <i class="bi bi-box"></i> <strong><?= $latest['products_total'] ?></strong> celkem zpracováno
            </span>
            <?php if ($latest['reviews_matched'] > 0): ?>
            <span class="me-3">
                <i class="bi bi-link-45deg"></i> <strong><?= $latest['reviews_matched'] ?></strong>/<?= $latest['reviews_total'] ?> recenzí spárováno
            </span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h5 class="alert-heading">
            <i class="bi bi-exclamation-triangle-fill"></i> 
            Chyba při synchronizaci: <?= $e($latest['feed_name']) ?>
        </h5>
        <p class="mb-1">
            Selhalo <?= date('d.m.Y H:i', strtotime($latest['finished_at'])) ?>
            <span class="badge bg-danger"><?= $latest['duration_seconds'] ?>s</span>
        </p>
        <hr>
        <div class="mb-0">
            <strong>Chybová hláška:</strong><br>
            <code class="text-danger"><?= $e($latest['error_message']) ?></code>
        </div>
        <div class="mt-3">
            <strong>Možná řešení:</strong>
            <ul class="mb-0 small">
                <?php if (strpos($latest['error_message'], 'HTTP') !== false): ?>
                    <li>Zkontrolujte že URL je správná a dostupná</li>
                    <li>Ověřte že hash parametr v URL je stále platný</li>
                <?php elseif (strpos($latest['error_message'], 'encoding') !== false): ?>
                    <li>Zkuste změnit kódování na UTF-8 nebo ISO-8859-2</li>
                    <li>Ověřte formát CSV (oddělovače, uvozovky)</li>
                <?php elseif (strpos($latest['error_message'], 'columns') !== false || strpos($latest['error_message'], 'code') !== false): ?>
                    <li>CSV musí obsahovat sloupce: <code>code</code> a <code>name</code></li>
                    <li>Zkontrolujte že hlavička má správné názvy sloupců</li>
                <?php else: ?>
                    <li>Zkuste synchronizaci spustit znovu</li>
                    <li>Zkontrolujte formát CSV (oddělovače, kódování)</li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($feeds)): ?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Název</th>
                <th>Typ</th>
                <th>Poslední stažení</th>
                <th>Status</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($feeds as $feed): 
            // Zjisti jestli tento feed právě běží
            $isRunning = false;
            foreach ($timeline ?? [] as $log) {
                if ($log['feed_id'] == $feed['id'] && $log['status'] === 'running') {
                    $isRunning = true;
                    break;
                }
            }
        ?>
            <tr class="<?= $isRunning ? 'table-warning' : '' ?>">
                <td>
                    <strong><?= $e($feed['name']) ?></strong><br>
                    <small class="text-muted"><?= $e(substr($feed['url'], 0, 60)) ?>...</small>
                    <?php if ($isRunning): ?>
                        <br><span class="badge bg-warning text-dark">
                            <span class="spinner-border spinner-border-sm"></span> Synchronizuje se...
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($feed['type'] === 'csv_with_images'): ?>
                        <span class="badge bg-info">S obrázky</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Základní</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($feed['last_fetch_at']): ?>
                        <?= date('d.m.Y H:i', strtotime($feed['last_fetch_at'])) ?>
                    <?php else: ?>
                        <span class="text-muted">Nikdy</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($feed['last_fetch_status'] === 'success'): ?>
                        <span class="badge bg-success">OK</span>
                    <?php elseif ($feed['last_fetch_status'] === 'error'): ?>
                        <span class="badge bg-danger" 
                              title="<?= $e($feed['last_error']) ?>" 
                              data-bs-toggle="tooltip">Chyba</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" action="/feeds/sync-background" class="sync-form">
                            <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" 
                                    title="Synchronizovat teď"
                                    <?= $isRunning ? 'disabled' : '' ?>>
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
<?php else: ?>
<div class="alert alert-info">
    Zatím nemáte žádné importy. <a href="/feeds/create">Vytvořte první import</a>
</div>
<?php endif; ?>

<!-- Časová osa synchronizací -->
<?php if (!empty($timeline)): ?>
<div class="mt-5">
    <h5 class="mb-3">
        Časová osa synchronizací 
        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="location.reload()">
            <i class="bi bi-arrow-repeat"></i> Obnovit
        </button>
    </h5>
    
    <div class="timeline">
        <?php foreach (array_slice($timeline, 0, 10) as $log): 
            $isRunning = $log['status'] === 'running';
            $isSuccess = $log['status'] === 'success';
            $isError = $log['status'] === 'error';
            
            // Vypočítej jak dlouho běží
            $runningTime = '';
            if ($isRunning) {
                $started = new DateTime($log['started_at']);
                $now = new DateTime();
                $diff = $now->getTimestamp() - $started->getTimestamp();
                $runningTime = "{$diff}s";
            }
        ?>
        <div class="timeline-item mb-3">
            <div class="card <?= $isError ? 'border-danger' : ($isSuccess ? 'border-success' : 'border-warning') ?>">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <!-- Status badge -->
                            <?php if ($isRunning): ?>
                                <span class="badge bg-warning text-dark">
                                    <span class="spinner-border spinner-border-sm"></span> Běží... <?= $runningTime ?>
                                </span>
                            <?php elseif ($isSuccess): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Úspěch
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-x-circle"></i> Chyba
                                </span>
                            <?php endif; ?>
                            
                            <!-- Feed název -->
                            <strong class="ms-2"><?= $e($log['feed_name']) ?></strong>
                            
                            <!-- Čas -->
                            <span class="text-muted ms-2">
                                <?= date('d.m.Y H:i', strtotime($log['started_at'])) ?>
                            </span>
                            
                            <!-- Trvání -->
                            <?php if ($log['duration_seconds']): ?>
                                <span class="badge bg-secondary ms-2">
                                    <?= $log['duration_seconds'] ?>s
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Statistiky -->
                    <?php if ($isSuccess): ?>
                    <div class="mt-2 small">
                        <span class="text-success me-3">
                            <i class="bi bi-plus-circle"></i> <?= $log['products_inserted'] ?> nových
                        </span>
                        <span class="text-info me-3">
                            <i class="bi bi-arrow-repeat"></i> <?= $log['products_updated'] ?> aktualizováno
                        </span>
                        <span class="text-primary me-3">
                            <i class="bi bi-box"></i> <?= $log['products_total'] ?> celkem
                        </span>
                        <?php if ($log['reviews_matched'] > 0): ?>
                        <span class="text-warning">
                            <i class="bi bi-link-45deg"></i> <?= $log['reviews_matched'] ?>/<?= $log['reviews_total'] ?> recenzí spárováno
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Error message -->
                    <?php if ($isError && $log['error_message']): ?>
                    <div class="mt-2 small text-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?= $e($log['error_message']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 15px;
    bottom: -15px;
    width: 2px;
    background: #dee2e6;
}

.timeline-item:last-child::before {
    display: none;
}

.timeline-item::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 15px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6c757d;
    border: 2px solid white;
}
</style>

<script>
// Auto-refresh když něco běží
<?php if (!empty($runningFeeds)): ?>
let countdown = 10;
const countdownEl = document.getElementById('countdown');

setInterval(() => {
    countdown--;
    if (countdownEl) countdownEl.textContent = countdown;
    if (countdown <= 0) {
        location.reload();
    }
}, 1000);
<?php endif; ?>

// Zobraz progress bar při kliknutí sync
document.querySelectorAll('.sync-form').forEach(form => {
    form.addEventListener('submit', function() {
        document.getElementById('syncProgress').style.display = 'block';
        window.scrollTo(0, 0);
    });
});

// Inicializuj Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>
