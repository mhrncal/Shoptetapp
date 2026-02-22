<?php $pageTitle = 'Systémový přehled'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-cpu me-2"></i>Systémový přehled</h4>
</div>

<div class="row g-4">
    <!-- Databázové tabulky -->
    <div class="col-12 col-lg-6">
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-database me-2 text-muted"></i>Databáze — počty záznamů</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tbody>
                        <?php foreach ($tables as $table => $count): ?>
                        <tr>
                            <td class="text-muted font-monospace small"><?= $e($table) ?></td>
                            <td class="text-end fw-semibold"><?= number_format($count) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <!-- PHP Info -->
        <div class="card border-0 mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-code-square me-2 text-muted"></i>PHP prostředí</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <tr>
                        <td class="text-muted small">PHP verze</td>
                        <td><span class="badge bg-primary"><?= $e($phpInfo['version']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Memory limit</td>
                        <td class="small"><?= $e($phpInfo['memory']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Upload max</td>
                        <td class="small"><?= $e($phpInfo['upload_max']) ?></td>
                    </tr>
                    <?php foreach ($phpInfo['extensions'] as $ext => $loaded): ?>
                    <tr>
                        <td class="text-muted small font-monospace"><?= $e($ext) ?></td>
                        <td>
                            <span class="badge bg-<?= $loaded ? 'success' : 'danger' ?>">
                                <?= $loaded ? '✓ načten' : '✗ chybí' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- XML fronta stats -->
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-list-task me-2 text-muted"></i>XML fronta</h6>
            </div>
            <div class="card-body">
                <?php
                $queueColors = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'failed' => 'danger'];
                $queueLabels = ['pending' => 'Čeká', 'processing' => 'Zpracovává se', 'completed' => 'Dokončeno', 'failed' => 'Chyba'];
                foreach ($queueColors as $s => $color):
                    $cnt = $queueStats[$s] ?? 0;
                ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary border-opacity-25">
                    <span class="badge bg-<?= $color ?>"><?= $queueLabels[$s] ?></span>
                    <strong><?= $cnt ?></strong>
                </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <a href="<?= APP_URL ?>/admin/xml-queue" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-right me-1"></i>Zobrazit frontu
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Chybné importy -->
    <?php if (!empty($failedImports)): ?>
    <div class="col-12">
        <div class="card border-0 border-danger border-opacity-25">
            <div class="card-header text-danger">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-exclamation-triangle me-2"></i>Chybné XML importy</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr>
                            <th>Uživatel</th>
                            <th>Chyba</th>
                            <th>Datum</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($failedImports as $imp): ?>
                            <tr>
                                <td class="small"><?= $e($imp['email'] ?? '—') ?></td>
                                <td class="small text-danger"><?= $e($imp['error_message'] ?? '—') ?></td>
                                <td class="small text-muted"><?= date('d.m.Y H:i', strtotime($imp['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
