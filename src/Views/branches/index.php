<?php $pageTitle = 'Pobočky'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2"></i>Pobočky</h4>
    <a href="<?= APP_URL ?>/branches/new" class="btn btn-primary btn-sm">
        <i class="bi bi-plus me-1"></i>Přidat pobočku
    </a>
</div>

<?php if (empty($branches)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-geo-alt fs-1 d-block mb-3"></i>
        <p>Žádné pobočky. Přidejte první pobočku.</p>
        <a href="<?= APP_URL ?>/branches/new" class="btn btn-primary btn-sm">
            <i class="bi bi-plus me-1"></i>Přidat pobočku
        </a>
    </div>
</div>
<?php else: ?>

<div class="row g-4">
    <?php foreach ($branches as $b): ?>
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="fw-bold mb-0"><?= $e($b['name']) ?></h5>
                    <div class="btn-group btn-group-sm">
                        <a href="<?= APP_URL ?>/branches/<?= $b['id'] ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="POST" action="<?= APP_URL ?>/branches/<?= $b['id'] ?>"
                              onsubmit="return confirm('Smazat tuto pobočku?')">
                            <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($b['description']): ?>
                <p class="text-muted small mb-3"><?= $e($b['description']) ?></p>
                <?php endif; ?>

                <!-- Adresa -->
                <?php if ($b['street_address'] || $b['city']): ?>
                <div class="d-flex gap-2 align-items-start mb-3">
                    <i class="bi bi-house text-muted mt-1"></i>
                    <span class="small">
                        <?= $e($b['street_address']) ?>
                        <?php if ($b['city']): ?>, <?= $e($b['city']) ?><?php endif; ?>
                        <?php if ($b['postal_code']): ?> <?= $e($b['postal_code']) ?><?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($b['google_maps_url']): ?>
                <a href="<?= $e($b['google_maps_url']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-map me-1"></i>Google Maps
                </a>
                <?php endif; ?>

                <!-- Otevírací doby -->
                <div class="border-top border-secondary border-opacity-25 pt-3">
                    <h6 class="small fw-semibold text-muted mb-2">
                        <i class="bi bi-clock me-1"></i>Otevírací doby
                    </h6>
                    <div class="row g-1" style="font-size:.8rem;">
                        <?php foreach ($days as $d => $dayName):
                            $h = $b['hours'][$d] ?? null;
                        ?>
                        <div class="col-6 d-flex justify-content-between">
                            <span class="text-muted"><?= $dayName ?></span>
                            <span class="<?= ($h && $h['is_closed']) ? 'text-danger' : 'text-success' ?>">
                                <?php if (!$h || $h['is_closed']): ?>
                                    Zavřeno
                                <?php else: ?>
                                    <?= substr($h['open_from'],0,5) ?>–<?= substr($h['open_to'],0,5) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
