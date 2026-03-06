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
    $isPair      = !str_starts_with($pairKey, '__single_');
    $mainProduct = $products[0];
    $groupVideos = [];
    foreach ($products as $p) {
        foreach ($videosByProduct[$p['id']] ?? [] as $v) {
            $groupVideos[] = $v;
        }
    }
?>
<div class="card">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold text-truncate"><?= $e($mainProduct['name']) ?></div>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($products as $p): ?>
                <code class="small text-muted"><?= $e($p['code']) ?></code>
                <?php endforeach; ?>
                <?php if ($isPair): ?>
                <span class="badge bg-info bg-opacity-75">pair: <?= $e($mainProduct['pair_code']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-primary flex-shrink-0"
                onclick="openAddVideo(<?= $mainProduct['id'] ?>, '<?= $e(addslashes($mainProduct['name'])) ?>')">
            <i class="bi bi-plus"></i><span class="d-none d-sm-inline ms-1">Video</span>
        </button>
    </div>

    <?php if (empty($groupVideos)): ?>
    <div class="card-body py-2 text-muted small">Žádná videa</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($groupVideos as $v):
            $isLocal = !empty($v['file_path']);
            $thumb   = !$isLocal && $v['url'] ? \ShopCode\Models\ProductVideo::thumbnail($v['url']) : null;
        ?>
        <div class="list-group-item px-3 py-2">
            <div class="d-flex align-items-center gap-3">
                <!-- Thumbnail / ikona -->
                <div class="flex-shrink-0 rounded overflow-hidden bg-dark d-flex align-items-center justify-content-center"
                     style="width:56px;height:40px;">
                    <?php if ($thumb): ?>
                    <img src="<?= $e($thumb) ?>" style="width:56px;height:40px;object-fit:cover;" alt="">
                    <?php else: ?>
                    <i class="bi bi-<?= $isLocal ? 'file-play' : 'youtube' ?> text-white"></i>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="flex-grow-1 min-w-0">
                    <div class="small fw-medium text-truncate"><?= $e($v['title'] ?? ($isLocal ? basename($v['file_path']) : 'Video')) ?></div>
                    <?php if ($isLocal): ?>
                    <span class="badge bg-secondary" style="font-size:.65rem;">Lokální soubor</span>
                    <?php else: ?>
                    <div class="text-muted" style="font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $e($v['url']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Autoplay toggle -->
                <div class="flex-shrink-0">
                    <div class="form-check form-switch mb-0" title="Autoplay">
                        <input class="form-check-input autoplay-toggle" type="checkbox"
                               role="switch"
                               data-id="<?= $v['id'] ?>"
                               data-csrf="<?= $e($csrfToken) ?>"
                               <?= $v['autoplay'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-muted d-none d-sm-inline" style="font-size:.72rem;">Auto</label>
                    </div>
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
            <form method="POST" id="addVideoForm" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="_referer" value="videos">
                <div class="modal-body">
                    <p class="fw-semibold small mb-3" id="addVideoProductName"></p>

                    <!-- Tabs: upload vs URL -->
                    <ul class="nav nav-pills nav-fill mb-3" id="videoTypeTabs">
                        <li class="nav-item">
                            <button class="nav-link active" type="button" onclick="switchTab('upload')">
                                <i class="bi bi-upload me-1"></i>Nahrát soubor
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" type="button" onclick="switchTab('url')">
                                <i class="bi bi-youtube me-1"></i>YouTube / Vimeo
                            </button>
                        </li>
                    </ul>

                    <!-- Upload -->
                    <div id="tab-upload">
                        <div class="mb-3">
                            <label class="form-label">Video soubor <span class="text-danger">*</span>
                                <span class="text-muted small">(MP4, WebM, MOV — max 5 MB)</span>
                            </label>
                            <input type="file" name="video_file" id="videoFileInput"
                                   class="form-control" accept="video/mp4,video/webm,video/ogg,video/quicktime">
                            <div id="fileSizeWarning" class="text-danger small mt-1" style="display:none;">
                                Soubor je příliš velký! Maximum je 5 MB.
                            </div>
                        </div>
                    </div>

                    <!-- URL -->
                    <div id="tab-url" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">YouTube nebo Vimeo URL <span class="text-danger">*</span></label>
                            <input type="url" name="url" class="form-control"
                                   placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Název <span class="text-muted small">(nepovinné)</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Ukázka produktu">
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="autoplay" value="1" id="modalAutoplay">
                        <label class="form-check-label" for="modalAutoplay">Autoplay</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary" id="addVideoSubmit">
                        <i class="bi bi-plus me-1"></i>Přidat video
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var activeTab = 'upload';

function switchTab(tab) {
    activeTab = tab;
    document.getElementById('tab-upload').style.display = tab === 'upload' ? '' : 'none';
    document.getElementById('tab-url').style.display    = tab === 'url'    ? '' : 'none';
    document.querySelectorAll('#videoTypeTabs .nav-link').forEach(function(btn, i) {
        btn.classList.toggle('active', (i === 0 && tab === 'upload') || (i === 1 && tab === 'url'));
    });
    // Reset nepotřebného pole
    if (tab === 'upload') document.querySelector('input[name="url"]').value = '';
    else document.getElementById('videoFileInput').value = '';
    document.getElementById('fileSizeWarning').style.display = 'none';
}

// Kontrola velikosti souboru před submitem
document.getElementById('videoFileInput').addEventListener('change', function() {
    var maxSize = 5 * 1024 * 1024;
    var warn = document.getElementById('fileSizeWarning');
    var btn  = document.getElementById('addVideoSubmit');
    if (this.files[0] && this.files[0].size > maxSize) {
        warn.style.display = '';
        btn.disabled = true;
    } else {
        warn.style.display = 'none';
        btn.disabled = false;
    }
});

function openAddVideo(productId, productName) {
    document.getElementById('addVideoProductName').textContent = productName;
    document.getElementById('addVideoForm').action = '<?= APP_URL ?>/products/' + productId + '/videos';
    document.querySelector('input[name="url"]').value = '';
    document.querySelector('input[name="title"]').value = '';
    document.getElementById('videoFileInput').value = '';
    document.getElementById('modalAutoplay').checked = false;
    document.getElementById('addVideoSubmit').disabled = false;
    switchTab('upload');
    new bootstrap.Modal(document.getElementById('addVideoModal')).show();
}

// Autoplay toggle AJAX
document.querySelectorAll('.autoplay-toggle').forEach(function(el) {
    el.addEventListener('change', function() {
        var id   = this.dataset.id;
        var csrf = this.dataset.csrf;
        var self = this;
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fetch('<?= APP_URL ?>/products/videos/' + id + '/autoplay', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) { self.checked = !!data.autoplay; })
            .catch(function() { self.checked = !self.checked; });
    });
});
</script>
