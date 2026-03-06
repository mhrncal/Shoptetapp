<?php
$e       = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$jsUrl   = rtrim(APP_URL, '/');
$jsCsrf  = addslashes($csrfToken ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-play-circle me-2"></i>Videa k produktům</h4>
        <p class="text-muted small mb-0"><?= count($groups ?? []) ?> skupin produktů</p>
    </div>
    <button class="btn btn-primary btn-sm" id="btnAddNew">
        <i class="bi bi-plus me-1"></i>Přidat video
    </button>
</div>

<?php if (empty($groups)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-play-circle fs-1 d-block mb-3"></i>
        <p>Žádná videa. Klikněte + Přidat video a vyhledejte produkt.</p>
    </div>
</div>
<?php else: ?>

<div class="d-flex flex-column gap-3">
<?php foreach ($groups as $pairKey => $products):
    $isPair      = !str_starts_with((string)$pairKey, '__single_');
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
        <div class="min-w-0">
            <span class="fw-semibold"><?= $e($mainProduct['name']) ?></span>
            <?php if ($isPair): ?>
                <span class="badge bg-info ms-2"><i class="bi bi-link-45deg me-1"></i><?= count($products) ?> var.</span>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($products as $p): ?>
                <code class="small text-muted"><?= $e($p['code']) ?></code>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="btn btn-sm btn-outline-primary flex-shrink-0 btn-add-video"
                data-id="<?= (int)$mainProduct['id'] ?>"
                data-name="<?= $e($mainProduct['name']) ?>">
            <i class="bi bi-plus"></i>
        </button>
    </div>

    <?php if (empty($groupVideos)): ?>
    <div class="card-body py-2 text-muted small">Žádná videa</div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($groupVideos as $v):
            $isFile = !empty($v['file_path']);
            $thumb  = !$isFile ? \ShopCode\Models\ProductVideo::thumbnail($v['url'] ?? '') : null;
        ?>
        <div class="list-group-item px-3 py-2">
            <div class="d-flex align-items-center gap-3">
                <div class="flex-shrink-0 rounded overflow-hidden bg-dark d-flex align-items-center justify-content-center"
                     style="width:56px;height:40px;">
                    <?php if ($thumb): ?>
                    <img src="<?= $e($thumb) ?>" style="width:56px;height:40px;object-fit:cover;" loading="lazy" alt="">
                    <?php else: ?>
                    <i class="bi bi-<?= $isFile ? 'file-earmark-play' : 'play-fill' ?> text-white"></i>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-medium small text-truncate"><?= $e($v['title'] ?? 'Video') ?></div>
                    <?php if ($isFile): ?>
                    <span class="badge bg-secondary" style="font-size:.65rem;"><i class="bi bi-hdd me-1"></i>Lokální soubor</span>
                    <?php else: ?>
                    <div class="text-muted text-truncate" style="font-size:.72rem;"><?= $e($v['url'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
                <form method="POST" action="<?= APP_URL ?>/products/videos/<?= (int)$v['id'] ?>"
                      onsubmit="return confirm('Smazat video?')" class="flex-shrink-0">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="_method" value="DELETE">
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

<?php if (($total ?? 0) > ($perPage ?? 20)): ?>
<?php $pages = (int)ceil($total / $perPage); ?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-muted"><?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage, $total) ?> z <?= $total ?> skupin</small>
    <div class="d-flex gap-2">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Zpět</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
        <a href="?page=<?= $page+1 ?>" class="btn btn-sm btn-outline-secondary">Další <i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Modal: Vyhledat produkt -->
<div class="modal fade" id="searchProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vybrat produkt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="productSearch" class="form-control mb-3"
                       placeholder="Hledat produkt..." autocomplete="off">
                <div id="searchResults">
                    <div class="text-muted small text-center py-3">Začněte psát pro vyhledání...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Přidat video -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Přidat video</h5>
                    <small class="text-muted" id="addVideoProductName"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pb-0">
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <button class="nav-link active" id="tab-url-btn" onclick="switchTab('url')">
                            <i class="bi bi-link-45deg me-1"></i>YouTube / Vimeo
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tab-upload-btn" onclick="switchTab('upload')">
                            <i class="bi bi-upload me-1"></i>Nahrát soubor
                        </button>
                    </li>
                </ul>

                <!-- URL -->
                <div id="panel-url">
                    <form method="POST" id="addVideoForm">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="_referer" value="videos">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL <span class="text-danger">*</span></label>
                            <input type="url" name="url" id="urlInput" class="form-control" required
                                   placeholder="https://www.youtube.com/watch?v=...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Název</label>
                            <input type="text" name="title" id="urlTitle" class="form-control" placeholder="Ukázka produktu">
                        </div>
                        <div class="modal-footer px-0">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i>Přidat</button>
                        </div>
                    </form>
                </div>

                <!-- Upload -->
                <div id="panel-upload" style="display:none;">
                    <div class="mb-3">
                        <label class="form-label">Název</label>
                        <input type="text" id="uploadTitle" class="form-control" placeholder="Ukázka produktu">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Soubor <span class="text-danger">*</span></label>
                        <input type="file" id="videoFileInput" class="form-control"
                               accept="video/mp4,video/webm,video/quicktime,video/x-msvideo">
                        <div class="form-text">MP4, WebM, MOV — max 50 MB</div>
                    </div>
                    <div id="uploadProgress" style="display:none;" class="mb-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Nahrávám...</span><span id="uploadPercent">0%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                 id="uploadBar" style="width:0%"></div>
                        </div>
                    </div>
                    <div id="uploadError" class="alert alert-danger py-2 small" style="display:none;"></div>
                    <div class="modal-footer px-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                        <button type="button" class="btn btn-primary" id="uploadBtn" onclick="startUpload()">
                            <i class="bi bi-upload me-1"></i>Nahrát
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var _currentProductId = null;
var _csrf = '<?= $jsCsrf ?>';
var _baseUrl = '<?= $jsUrl ?>';

// Tlačítka přidat video (u karet produktů)
document.querySelectorAll('.btn-add-video').forEach(function(btn) {
    btn.addEventListener('click', function() {
        openAddVideo(this.dataset.id, this.dataset.name);
    });
});

// Tlačítko přidat nový (header)
document.getElementById('btnAddNew').addEventListener('click', function() {
    openSearchModal();
});

function openSearchModal() {
    document.getElementById('productSearch').value = '';
    document.getElementById('searchResults').innerHTML = '<div class="text-muted small text-center py-3">Začněte psát...</div>';
    new bootstrap.Modal(document.getElementById('searchProductModal')).show();
}

function openAddVideo(productId, productName) {
    _currentProductId = productId;
    document.getElementById('addVideoProductName').textContent = productName;
    document.getElementById('addVideoForm').action = _baseUrl + '/products/' + productId + '/videos';
    document.getElementById('urlInput').value = '';
    document.getElementById('urlTitle').value = '';
    document.getElementById('videoFileInput').value = '';
    document.getElementById('uploadTitle').value = '';
    document.getElementById('uploadProgress').style.display = 'none';
    document.getElementById('uploadError').style.display = 'none';
    switchTab('url');
    new bootstrap.Modal(document.getElementById('addVideoModal')).show();
}

function switchTab(tab) {
    document.getElementById('panel-url').style.display    = tab === 'url'    ? '' : 'none';
    document.getElementById('panel-upload').style.display = tab === 'upload' ? '' : 'none';
    document.getElementById('tab-url-btn').classList.toggle('active',    tab === 'url');
    document.getElementById('tab-upload-btn').classList.toggle('active', tab === 'upload');
}

function startUpload() {
    var file = document.getElementById('videoFileInput').files[0];
    if (!file) { showUploadError('Vyberte soubor.'); return; }
    if (file.size > 50 * 1024 * 1024) { showUploadError('Soubor je větší než 50 MB.'); return; }

    var fd = new FormData();
    fd.append('_csrf', _csrf);
    fd.append('video_file', file);
    fd.append('title', document.getElementById('uploadTitle').value);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', _baseUrl + '/products/' + _currentProductId + '/videos/upload');

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var pct = Math.round(e.loaded / e.total * 100);
            document.getElementById('uploadBar').style.width = pct + '%';
            document.getElementById('uploadPercent').textContent = pct + '%';
        }
    };
    xhr.onloadstart = function() {
        document.getElementById('uploadProgress').style.display = '';
        document.getElementById('uploadError').style.display = 'none';
        document.getElementById('uploadBtn').disabled = true;
    };
    xhr.onload = function() {
        document.getElementById('uploadBtn').disabled = false;
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.ok) {
                bootstrap.Modal.getInstance(document.getElementById('addVideoModal')).hide();
                location.reload();
            } else {
                showUploadError(resp.error || 'Chyba při nahrávání.');
            }
        } catch(ex) {
            showUploadError('Neočekávaná chyba.');
        }
    };
    xhr.onerror = function() {
        document.getElementById('uploadBtn').disabled = false;
        showUploadError('Chyba sítě.');
    };
    xhr.send(fd);
}

function showUploadError(msg) {
    var el = document.getElementById('uploadError');
    el.textContent = msg;
    el.style.display = '';
    document.getElementById('uploadProgress').style.display = 'none';
}

// Search produktů
var _searchTimer = null;
document.getElementById('productSearch').addEventListener('input', function() {
    clearTimeout(_searchTimer);
    var q = this.value.trim();
    if (q.length < 2) {
        document.getElementById('searchResults').innerHTML = '<div class="text-muted small text-center py-3">Zadejte alespoň 2 znaky...</div>';
        return;
    }
    _searchTimer = setTimeout(function() {
        fetch(_baseUrl + '/products/search?search=' + encodeURIComponent(q) + '&limit=10')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var el = document.getElementById('searchResults');
                if (!data.products || !data.products.length) {
                    el.innerHTML = '<div class="text-muted small text-center py-3">Žádné výsledky.</div>';
                    return;
                }
                el.innerHTML = data.products.map(function(p) {
                    var codes = p.code ? ' &ndash; <code style="font-size:.72rem;">' + p.code + '</code>' : '';
                    return '<button class="btn btn-outline-secondary text-start w-100 py-2 mb-1" data-id="' + p.id + '" data-name="' + (p.name || '').replace(/"/g, '&quot;') + '">' +
                        '<span class="small">' + (p.name || '') + codes + '</span>' +
                        '</button>';
                }).join('');
                el.querySelectorAll('button[data-id]').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var pid  = this.dataset.id;
                        var name = this.dataset.name;
                        bootstrap.Modal.getInstance(document.getElementById('searchProductModal')).hide();
                        setTimeout(function() { openAddVideo(pid, name); }, 300);
                    });
                });
            });
    }, 300);
});
</script>
