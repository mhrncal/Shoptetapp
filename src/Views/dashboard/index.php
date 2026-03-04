<?php $pageTitle = 'Dashboard'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="mb-4">
    <h1 class="fw-bold gradient-text mb-1" style="font-size:clamp(1.25rem,5vw,1.75rem);">Vítejte zpět!</h1>
    <p class="text-muted mb-0" style="font-size:.9rem;">
        Přehled e-shopu <strong><?= $e($currentUser['shop_name'] ?? $currentUser['email']) ?></strong>
    </p>
</div>

<?php if ($activeImport): ?>
<div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <span class="spinner-border spinner-border-sm flex-shrink-0 mt-1"></span>
    <div>
        <strong>Probíhá XML import</strong> —
        <?= number_format($activeImport['products_processed']) ?> produktů
        (<?= $activeImport['progress_percentage'] ?>%)
        <a href="<?= APP_URL ?>/xml" class="ms-1 alert-link">Detail →</a>
    </div>
</div>
<?php endif; ?>

<!-- Stat karty — 2 sloupce na mobilu, 4 na desktopu -->
<div class="row g-2 g-md-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box"></i></div>
            <div class="stat-value"><?= number_format($counts['products']) ?></div>
            <div class="stat-label">Produktů</div>
        </div>
    </div>
    <?php if (in_array('faq', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-question-circle"></i></div>
            <div class="stat-value"><?= number_format($counts['faqs']) ?></div>
            <div class="stat-label">FAQ</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('branches', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-geo-alt"></i></div>
            <div class="stat-value"><?= number_format($counts['branches']) ?></div>
            <div class="stat-label">Pobočky</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('event_calendar', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-event"></i></div>
            <div class="stat-value"><?= number_format($counts['upcoming_events']) ?></div>
            <div class="stat-label">Nadch. akcí</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (in_array('reviews', $activeModules)): ?>
    <div class="col-6 col-lg-3">
        <a href="<?= APP_URL ?>/reviews?status=pending" class="text-decoration-none d-block h-100">
            <div class="stat-card h-100 <?= ($counts['reviews_pending'] ?? 0) > 0 ? 'border-warning' : '' ?>" style="<?= ($counts['reviews_pending'] ?? 0) > 0 ? 'border-color:hsl(var(--bs-warning-rgb)) !important;' : '' ?>">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-camera"></i></div>
                <div class="stat-value"><?= number_format($counts['reviews_pending'] ?? 0) ?></div>
                <div class="stat-label">Čeká ke schválení</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <!-- Poslední importy -->
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-muted"></i>Poslední importy
                </h6>
                <?php if (in_array('xml_import', $activeModules)): ?>
                <a href="<?= APP_URL ?>/xml" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus me-1"></i>Import
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentImports)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Žádné importy zatím
                </div>
                <?php else: ?>

                <!-- DESKTOP tabulka -->
                <div class="d-none d-sm-block">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Stav</th>
                                    <th>Nové</th>
                                    <th>Aktualizováno</th>
                                    <th>Datum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentImports as $import):
                                    $badges = ['completed'=>'success','failed'=>'danger','processing'=>'warning','pending'=>'secondary'];
                                    $badge = $badges[$import['status']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $badge ?>"><?= $e($import['status']) ?></span></td>
                                    <td><?= number_format($import['products_imported']) ?></td>
                                    <td><?= number_format($import['products_updated']) ?></td>
                                    <td class="text-muted small"><?= date('d.m.Y H:i', strtotime($import['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MOBIL: řádkový seznam -->
                <div class="d-sm-none">
                    <?php foreach ($recentImports as $import):
                        $badges = ['completed'=>'success','failed'=>'danger','processing'=>'warning','pending'=>'secondary'];
                        $badge = $badges[$import['status']] ?? 'secondary';
                    ?>
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $badge ?>"><?= $e($import['status']) ?></span>
                            <span class="small text-muted"><?= date('d.m. H:i', strtotime($import['created_at'])) ?></span>
                        </div>
                        <div class="text-end small">
                            <span class="text-success">+<?= number_format($import['products_imported']) ?></span>
                            <span class="text-muted mx-1">·</span>
                            <span class="text-info">↻<?= number_format($import['products_updated']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Aktivní moduly -->
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-puzzle me-2 text-muted"></i>Aktivní moduly</h6>
            </div>
            <div class="card-body p-0">
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
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-<?= $info['icon'] ?> text-<?= $isActive ? 'primary' : 'secondary' ?>"></i>
                        <span class="small <?= $isActive ? '' : 'text-muted' ?>"><?= $info['label'] ?></span>
                    </div>
                    <span class="badge bg-<?= $isActive ? 'success' : 'secondary bg-opacity-25 text-body' ?>">
                        <?= $isActive ? 'aktivní' : 'neakt.' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
