<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-play-circle me-2"></i>Videa k produktům</h4>
        <p class="text-muted small mb-0"><?= count($groups) ?> skupin produktů</p>
    </div>
</div>

<?php if (empty($groups)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-play-circle fs-1 d-block mb-3"></i>
        <p>Nejdřív naimportujte produkty přes feeds.</p>
        <a href="<?= APP_URL ?>/feeds" class="btn btn-primary btn-sm">
            <i class="bi bi-cloud-download me-1"></i>Importy
        </a>
    </div>
</div>
<?php else: ?>

<div class="d-flex flex-column gap-3">
<?php foreach ($groups as $pairKey => $products):
    $isPair     = !str_starts_with($pairKey, '__single_');
    $mainProduct = $products[0];
    // Všechna videa pro tuto skupinu
    $groupVideos = [];
    foreach ($products as $p) {
        foreach ($videosByProduct[$p['id']] ?? [] as $v) {
            $groupVideos[] = $v;
        }
    }
?>
<div class="card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <div class="min-w-0">
            <span class="fw-semibold"><?= $e($mainProduct['name']) ?></span>
            <?php if ($isPair): ?>
                <span class="badge bg-info ms-2" title="Spárované produkty">
                    <i class="bi bi-link-45deg me-1"></i><?= count($products) ?> var.
                </span>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($products as $p): ?>
                <code class="small text-muted"><?= $e($p['code']) ?></code>
                <?php endforeach; ?>
                <?php if ($isPair): ?>
                <span class="text-muted small">pair: <code><?= $e($mainProduct['pair_code']) ?></code></span>
                <?php endif; ?>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-primary flex-shrink-0"
                onclick="openAddVideo(<?= $mainProduct['id'] ?>, '<?= $e(addslashes($mainProduct['name'])) ?>')"
                title="Přidat video k tomuto produktu">
            <i class="bi bi-plus"></i><span class="d-none d-sm-inline ms-1">Video</span>
        </button>
    </div>

    <?php if (empty($groupVideos)): ?>
    <div class="card-body py-2 text-muted small">Žádná videa — klikněte + Video</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($groupVideos as $v):
            $thumb   = \ShopCode\Models\ProductVideo::thumbnail($v['url'] ?? '');
            $embedOk = \ShopCode\Models\ProductVideo::embedUrl($v['url'] ?? '');
        ?>
        <div class="list-group-item px-3 py-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Thumbnail -->
                <div class="flex-shrink-0 rounded overflow-hidden bg-dark"
                     style="width:56px;height:40px;display:flex;align-items:center;justify-content:center;">
                    <?php if ($thumb): ?>
                    <img src="<?= $e($thumb) ?>" style="width:56px;height:40px;object-fit:cover;" alt="">
                    <?php else: ?>
                    <i class="bi bi-play-fill text-white"></i>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-medium small text-truncate"><?= $e($v['title'] ?? 'Video') ?></div>
                    <div class="text-muted" style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $e($v['url']) ?></div>
                    <?php if (!$embedOk): ?>
                    <span class="badge bg-warning text-dark" style="font-size:.65rem;">Neplatná URL</span>
                    <?php endif; ?>
                </div>

                <!-- Autoplay toggle -->
                <div class="flex-shrink-0 d-flex align-items-center gap-1" title="Autoplay">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input autoplay-toggle" type="checkbox"
                               role="switch"
                               data-id="<?= $v['id'] ?>"
                               data-csrf="<?= $e($csrfToken) ?>"
                               <?= $v['autoplay'] ? 'checked' : '' ?>
                               title="Autoplay">
                    </div>
                    <span class="text-muted d-none d-sm-inline" style="font-size:.72rem;">Auto</span>
                </div>

                <!-- Smazat -->
                <form method="POST" action="<?= APP_URL ?>/products/videos/<?= $v['id'] ?>/delete"
                      onsubmit="return confirm('Smazat video?')" class="flex-shrink-0">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal: Přidat video -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Přidat video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addVideoForm">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="_referer" value="videos">
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="addVideoProductName"></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">YouTube nebo Vimeo URL <span class="text-danger">*</span></label>
                        <input type="url" name="url" class="form-control" required
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Název videa <span class="text-muted small">(nepovinné)</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Ukázka produktu">
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="autoplay" value="1" id="modalAutoplay">
                            <label class="form-check-label" for="modalAutoplay">Autoplay</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Přidat video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddVideo(productId, productName) {
    document.getElementById('addVideoProductName').textContent = 'Produkt: ' + productName;
    document.getElementById('addVideoForm').action = '<?= APP_URL ?>/products/' + productId + '/videos';
    document.getElementById('addVideoForm').querySelector('input[name="url"]').value = '';
    document.getElementById('addVideoForm').querySelector('input[name="title"]').value = '';
    document.getElementById('modalAutoplay').checked = false;
    new bootstrap.Modal(document.getElementById('addVideoModal')).show();
}

// Autoplay toggle — AJAX
document.querySelectorAll('.autoplay-toggle').forEach(function(el) {
    el.addEventListener('change', function() {
        const id   = this.dataset.id;
        const csrf = this.dataset.csrf;
        const fd   = new FormData();
        fd.append('_csrf', csrf);
        fetch('<?= APP_URL ?>/products/videos/' + id + '/autoplay', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { this.checked = !!data.autoplay; })
            .catch(() => { this.checked = !this.checked; }); // rollback při chybě
    });
});
</script>
