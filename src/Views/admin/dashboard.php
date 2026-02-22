<?php $pageTitle = 'Admin Dashboard'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h4>
</div>

<!-- Stat karty -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card border-warning border-opacity-25">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Uživatelů celkem</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card border-danger border-opacity-25">
            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-value text-danger"><?= $userStats['pending'] ?? 0 ?></div>
            <div class="stat-label">Čeká na schválení</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-box"></i>
            </div>
            <div class="stat-value"><?= number_format($totalProducts) ?></div>
            <div class="stat-label">Produktů (celkem)</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-list-task"></i>
            </div>
            <div class="stat-value"><?= $queuePending ?></div>
            <div class="stat-label">Fronta (čeká)</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Nové registrace -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-person-plus me-2 text-muted"></i>Poslední registrace</h6>
                <a href="<?= APP_URL ?>/admin/users" class="btn btn-sm btn-outline-secondary">Všichni</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Uživatel</th>
                                <th>Stav</th>
                                <th>Datum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold small"><?= $e($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= $e($u['email']) ?></div>
                                </td>
                                <td>
                                    <?php
                                    $bs = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'];
                                    $labels = ['approved' => 'Schválen', 'pending' => 'Čeká', 'rejected' => 'Zamítnut'];
                                    ?>
                                    <span class="badge bg-<?= $bs[$u['status']] ?? 'secondary' ?>">
                                        <?= $labels[$u['status']] ?? $u['status'] ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="<?= APP_URL ?>/admin/users/<?= $u['id'] ?>" class="btn btn-xs btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit log -->
    <div class="col-12 col-xl-6">
        <div class="card border-0 h-100">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-journal-text me-2 text-muted"></i>Poslední akce</h6>
                <a href="<?= APP_URL ?>/admin/audit-log" class="btn btn-sm btn-outline-secondary">Celý log</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Akce</th>
                                <th>Uživatel</th>
                                <th>Čas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-25 text-body small">
                                        <?= $e($log['action']) ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?= $e($log['email'] ?? '—') ?></td>
                                <td class="text-muted small"><?= date('d.m. H:i', strtotime($log['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
