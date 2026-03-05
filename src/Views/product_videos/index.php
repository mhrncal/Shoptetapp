<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-play-circle me-2"></i>Videa k produktům</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= count($videos) ?></strong> videí</p>
    </div>
</div>

<?php if (empty($videos)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-play-circle fs-1 d-block mb-3"></i>
        <p>Žádná videa. Přidejte video v detailu produktu.</p>
        <a href="<?= APP_URL ?>/products" class="btn btn-primary btn-sm">
            <i class="bi bi-box me-1"></i>Přejít na produkty
        </a>
    </div>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-2">
    <?php foreach ($videos as $v): ?>
    <div class="card">
        <div class="card-body py-3">
            <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded bg-dark"
                     style="width:48px;height:48px;">
                    <i class="bi bi-play-fill text-white"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-medium text-truncate"><?= $e($v['title'] ?? 'Video') ?></div>
                    <div class="small text-muted text-truncate"><?= $e($v['product_name']) ?></div>
                    <?php if ($v['video_url'] ?? null): ?>
                    <a href="<?= $e($v['video_url']) ?>" target="_blank"
                       class="small text-primary text-truncate d-block"><?= $e($v['video_url']) ?></a>
                    <?php endif; ?>
                </div>
                <a href="<?= APP_URL ?>/products/<?= $v['product_id'] ?>"
                   class="btn btn-sm btn-outline-secondary flex-shrink-0">
                    <i class="bi bi-pencil"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
