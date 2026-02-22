<?php $pageTitle = 'Audit log'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Audit log</h4>
    <span class="text-muted small">Celkem: <?= $total ?> záznamů</span>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>Akce</th>
                        <th>Objekt</th>
                        <th>ID objektu</th>
                        <th>Uživatel</th>
                        <th>IP</th>
                        <th>Čas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary bg-opacity-25 text-body font-monospace">
                                <?= $e($log['action']) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= $e($log['resource_type']) ?></td>
                        <td class="text-muted font-monospace"><?= $e($log['resource_id'] ?? '—') ?></td>
                        <td><?= $e($log['email'] ?? '—') ?></td>
                        <td class="text-muted font-monospace"><?= $e($log['ip_address'] ?? '—') ?></td>
                        <td class="text-muted"><?= date('d.m.Y H:i:s', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">Žádné záznamy</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between">
        <small class="text-muted">Strana <?= $page ?> z <?= ceil($total / $perPage) ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php for ($p = 1; $p <= ceil($total / $perPage); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
