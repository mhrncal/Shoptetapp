<?php $pageTitle = 'Systémový přehled'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4 gap-2 flex-wrap">
    <h4 class="fw-bold mb-0"><i class="bi bi-cpu me-2"></i>Systémový přehled</h4>
    <form method="POST" action="/admin/system/run-scrape">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button type="submit" class="btn btn-sm btn-outline-primary" onclick="return confirm('Spustit scraping všech zdrojů na pozadí?')">
            <i class="bi bi-arrow-clockwise me-1"></i>Spustit scraping recenzí
        </button>
    </form>
</div>

<div class="d-flex flex-column gap-3">

    <!-- Databáze -->
    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-database me-2 text-muted"></i>Databáze — počty záznamů</h6>
        </div>
        <div class="card-body p-0">
            <?php foreach ($tables as $table => $count): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <code class="small text-muted"><?= $e($table) ?></code>
                <strong class="ms-3 flex-shrink-0"><?= number_format($count) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- PHP prostředí -->
    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-code-square me-2 text-muted"></i>PHP prostředí</h6>
        </div>
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="small text-muted">PHP verze</span>
                <span class="badge bg-primary"><?= $e($phpInfo['version']) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="small text-muted">Memory limit</span>
                <span class="small"><?= $e($phpInfo['memory']) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="small text-muted">Upload max</span>
                <span class="small"><?= $e($phpInfo['upload_max']) ?></span>
            </div>
            <?php foreach ($phpInfo['extensions'] as $ext => $loaded): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <code class="small text-muted"><?= $e($ext) ?></code>
                <span class="badge bg-<?= $loaded ? 'success' : 'danger' ?>"><?= $loaded ? '✓' : '✗' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- XML fronta -->
    <div class="card border-0">
        <div class="card-header">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-list-task me-2 text-muted"></i>XML fronta</h6>
        </div>
        <div class="card-body p-0">
            <?php
            $queueColors = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
            $queueLabels = ['pending' => 'Čeká', 'processing' => 'Zpracovává se', 'completed' => 'Dokončeno', 'failed' => 'Chyba'];
            foreach ($queueColors as $s => $color):
                $cnt = $queueStats[$s] ?? 0;
            ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <span class="badge bg-<?= $color ?>"><?= $queueLabels[$s] ?></span>
                <strong><?= $cnt ?></strong>
            </div>
            <?php endforeach; ?>
            <div class="px-3 py-2">
                <a href="<?= APP_URL ?>/admin/xml-queue" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-right me-1"></i>Zobrazit frontu
                </a>
            </div>
        </div>
    </div>

    <!-- Chybné importy -->
    <?php if (!empty($failedImports)): ?>
    <div class="card border-danger border-opacity-50">
        <div class="card-header text-danger">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Chybné XML importy</h6>
        </div>
        <div class="card-body p-0">
            <?php foreach ($failedImports as $imp): ?>
            <div class="px-3 py-2 border-bottom">
                <div class="small fw-semibold"><?= $e($imp['email'] ?? '—') ?></div>
                <div class="small text-danger text-truncate"><?= $e($imp['error_message'] ?? '—') ?></div>
                <div class="small text-muted"><?= date('d.m.Y H:i', strtotime($imp['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
