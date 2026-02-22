<?php $pageTitle = 'XML fronta'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-list-task me-2"></i>XML fronta</h4>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php
    $colors = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
    $labels = ['pending' => 'Čeká', 'processing' => 'Zpracovává', 'completed' => 'Dokončeno', 'failed' => 'Chyba'];
    foreach ($colors as $s => $c):
    ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-value text-<?= $c ?>"><?= $stats[$s] ?? 0 ?></div>
            <div class="stat-label"><?= $labels[$s] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="mb-3">
    <div class="btn-group btn-group-sm">
        <a href="<?= APP_URL ?>/admin/xml-queue" class="btn btn-<?= !$statusFilter ? 'primary' : 'outline-secondary' ?>">Vše</a>
        <?php foreach ($labels as $s => $l): ?>
        <a href="?status=<?= $s ?>" class="btn btn-<?= $statusFilter === $s ? $colors[$s] : 'outline-secondary' ?>">
            <?= $l ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Uživatel</th>
                        <th>XML URL</th>
                        <th>Stav</th>
                        <th>Priorita</th>
                        <th>Pokrytí</th>
                        <th>Vytvořeno</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $item): ?>
                    <tr>
                        <td class="text-muted"><?= $item['id'] ?></td>
                        <td>
                            <div class="small"><?= $e($item['shop_name'] ?? '—') ?></div>
                            <div class="text-muted" style="font-size:.7rem;"><?= $e($item['email']) ?></div>
                        </td>
                        <td class="text-muted" style="max-width:200px;">
                            <div class="text-truncate" title="<?= $e($item['xml_feed_url']) ?>">
                                <?= $e($item['xml_feed_url']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $colors[$item['status']] ?? 'secondary' ?>">
                                <?= $labels[$item['status']] ?? $e($item['status']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= $item['priority'] ?></td>
                        <td>
                            <?php if ($item['status'] === 'processing'): ?>
                            <div class="progress" style="height:6px;width:80px;">
                                <div class="progress-bar bg-info" style="width:<?= $item['progress_percentage'] ?>%"></div>
                            </div>
                            <div class="text-muted" style="font-size:.7rem;"><?= $item['progress_percentage'] ?>%</div>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= date('d.m.Y H:i', strtotime($item['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($queue)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            Fronta je prázdná
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
