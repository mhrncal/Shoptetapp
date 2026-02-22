<?php $pageTitle = 'Dashboard'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0">Dashboard</h4>
        <p class="text-muted small mb-0">
            Vítejte zpět, <?= $e($currentUser['first_name'] ?? $currentUser['email']) ?>!
        </p>
    </div>
</div>

<?php if ($activeImport): ?>
<div class="alert alert-info d-flex align-items-center gap-3 mb-4">
    <span class="spinner-border spinner-border-sm flex-shrink-0"></span>
    <div>
        <strong>Probíhá XML import</strong> —
        <?= number_format($activeImport['products_processed']) ?> produktů zpracováno
        (<?= $activeImport['progress_percentage'] ?>%)
        <a href="<?= APP_URL ?>/xml" class="ms-2 alert-link">Zobrazit detail →</a>
    </div>
</div>
<?php endif; ?>

<!-- Stat karty -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="bi bi-box"></i>
            </div>
            <div class="stat-value"><?= number_format($counts["products"]) ?></div>
            <div class="stat-label">Produktů</div>
        </div>
    </div>
    <?php if (in_array('faq', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="bi bi-question-circle"></i>
            </div>
            <div class="stat-value"><?= number_format($counts["faqs"]) ?></div>
            <div class="stat-label">FAQ položek</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('branches', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="bi bi-geo-alt"></i>
            </div>
            <div class="stat-value"><?= number_format($counts["branches"]) ?></div>
            <div class="stat-label">Poboček</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('event_calendar', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="bi bi-calendar-event"></i>
            </div>
            <div class="stat-value"><?= number_format($counts["upcoming_events"]) ?></div>
            <div class="stat-label">Nadch. akcí</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Poslední importy -->
    <div class="col-12 col-xl-7">
        <div class="card border-0 h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-file-earmark-arrow-down me-2 text-muted"></i>Poslední XML importy</h6>
                <?php if (in_array('xml_import', $activeModules)): ?>
                <a href="<?= APP_URL ?>/xml" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>Nový import
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentImports)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Žádné importy zatím
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Stav</th>
                                <th>Importováno</th>
                                <th>Aktualizováno</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentImports as $import): ?>
                            <tr>
                                <td>
                                    <?php
                                    $badges = [
                                        'completed'  => 'success',
                                        'failed'     => 'danger',
                                        'processing' => 'warning',
                                        'pending'    => 'secondary',
                                    ];
                                    $badge = $badges[$import['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>">
                                        <?= $e($import['status']) ?>
                                    </span>
                                </td>
                                <td><?= number_format($import['products_imported']) ?></td>
                                <td><?= number_format($import['products_updated']) ?></td>
                                <td class="text-muted small">
                                    <?= date('d.m.Y H:i', strtotime($import['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aktivní moduly -->
    <div class="col-12 col-xl-5">
        <div class="card border-0 h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-puzzle me-2 text-muted"></i>Aktivní moduly</h6>
            </div>
            <div class="card-body">
                <?php
                $allModules = [
                    'xml_import'     => ['icon' => 'file-earmark-arrow-down', 'label' => 'XML Import'],
                    'faq'            => ['icon' => 'question-circle',          'label' => 'FAQ'],
                    'branches'       => ['icon' => 'geo-alt',                  'label' => 'Pobočky'],
                    'event_calendar' => ['icon' => 'calendar-event',           'label' => 'Kalendář akcí'],
                    'product_tabs'   => ['icon' => 'layout-text-window',       'label' => 'Záložky produktů'],
                    'product_videos' => ['icon' => 'play-circle',              'label' => 'Videa'],
                    'api_access'     => ['icon' => 'key',                      'label' => 'API přístup'],
                    'webhooks'       => ['icon' => 'broadcast',                'label' => 'Webhooky'],
                    'statistics'     => ['icon' => 'bar-chart-line',           'label' => 'Statistiky'],
                ];
                foreach ($allModules as $name => $info):
                    $isActive = in_array($name, $activeModules) || $currentUser['role'] === 'superadmin';
                ?>
                <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-<?= $info['icon'] ?> text-<?= $isActive ? 'primary' : 'secondary' ?>"></i>
                        <span class="<?= $isActive ? '' : 'text-muted' ?> small"><?= $info['label'] ?></span>
                    </div>
                    <span class="badge bg-<?= $isActive ? 'success' : 'secondary' ?> bg-opacity-<?= $isActive ? '100' : '25' ?>">
                        <?= $isActive ? 'aktivní' : 'neaktivní' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
