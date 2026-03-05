<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-bookmark me-2"></i>Vlastní záložky</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= count($tabs) ?></strong> záložek</p>
    </div>
</div>

<?php if (empty($tabs)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bookmark fs-1 d-block mb-3"></i>
        <p>Žádné záložky. Přidejte záložku v detailu produktu.</p>
        <a href="<?= APP_URL ?>/products" class="btn btn-primary btn-sm">
            <i class="bi bi-box me-1"></i>Přejít na produkty
        </a>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-2">
    <?php foreach ($tabs as $t): ?>
    <div class="card">
        <div class="card-body py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="fw-medium text-truncate"><?= $e($t['title']) ?></div>
                        <?php if (!($t['is_active'] ?? true)): ?>
                        <span class="badge bg-secondary" style="font-size:.7rem;">Neaktivní</span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted text-truncate"><?= $e($t['product_name']) ?></div>
                    <?php if ($t['content'] ?? null): ?>
                    <div class="small text-muted mt-1" style="overflow:hidden;max-height:2.5rem;">
                        <?= $e(mb_substr(strip_tags($t['content']), 0, 100)) ?>...
                    </div>
                    <?php endif; ?>
                </div>
                <a href="<?= APP_URL ?>/products/<?= $t['product_id'] ?>"
                   class="btn btn-sm btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
