<?php $pageTitle = 'Audit log'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Audit log</h4>
    <span class="text-muted small"><?= $total ?> záznamů</span>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="text-center py-5 text-muted">Žádné záznamy</div>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="px-3 py-2 border-bottom">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <span class="badge bg-secondary bg-opacity-25 text-body font-monospace"><?= $e($log['action']) ?></span>
                <span class="text-muted small ms-auto"><?= date('d.m.Y H:i', strtotime($log['created_at'])) ?></span>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-1" style="font-size:.78rem;">
                <span class="text-muted"><i class="bi bi-person me-1"></i><?= $e($log['email'] ?? '—') ?></span>
                <span class="text-muted"><i class="bi bi-box me-1"></i><?= $e($log['resource_type']) ?><?= $log['resource_id'] ? ' #'.$e($log['resource_id']) : '' ?></span>
                <?php if ($log['ip_address'] ?? null): ?>
                <span class="text-muted font-monospace"><?= $e($log['ip_address']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total > $perPage): ?>
    <?php $pages = (int)ceil($total / $perPage); ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">Strana <?= $page ?> z <?= $pages ?></small>
        <div class="d-flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
