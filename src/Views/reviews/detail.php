<?php $pageTitle = 'Recenze #' . $review['id']; ?>
<?php $isPreview = !empty($review['xml_exported_at']); ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
<?php $st = \ShopCode\Models\Review::STATUSES[$review['status']] ?? ['label'=>$review['status'],'color'=>'secondary']; ?>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="<?= APP_URL ?>/reviews" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Recenze #<?= $review['id'] ?></h4>
    <span class="badge bg-<?= $st['color'] ?> ms-1"><?= $st['label'] ?></span>
    <?php if ($review['imported']): ?>
    <span class="badge bg-success bg-opacity-20 text-success">
        <i class="bi bi-check-circle me-1"></i>Importováno <?= date('d.m.Y', strtotime($review['imported_at'])) ?>
    </span>
    <?php endif; ?>
</div>

<?php if ($isPreview): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
    <div class="small">
        <strong>Fotky jsou náhledy</strong> — originály byly exportovány do Shoptetu <?= date('d.m.Y', strtotime($review['xml_exported_at'])) ?>.
        Pro nový export nahrajte originální fotky ze zálohy znovu.
    </div>
</div>
<?php endif; ?>

<div class="row g-4" style="overflow-x:hidden;margin-left:0;margin-right:0;">

    <!-- Levý sloupec: fotky -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Fotografie (<?= count($review['photos']) ?>)</h6></div>
            <div class="card-body">
                <?php if (empty($review['photos'])): ?>
                <p class="text-muted small">Žádné fotky.</p>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($review['photos'] as $i => $photo): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <?php
                        $cacheBust = !empty($photo['updated_at']) ? '?t=' . strtotime($photo['updated_at']) : '';
                        $photoUrl  = !empty($photo['shoptet_url'])
                            ? $photo['shoptet_url']
                            : APP_URL . '/public/uploads/' . $photo['path'] . $cacheBust;
                        $thumbUrl  = !empty($photo['shoptet_url'])
                            ? $photo['shoptet_url']
                            : APP_URL . '/public/uploads/' . ($photo['thumb'] ?? $photo['path']) . $cacheBust;
                        ?>
                        <a href="<?= $e($photoUrl) ?>"
                           class="photo-lightbox d-block position-relative" data-lightbox="review-photos">
                            <img src="<?= $e($thumbUrl) ?>"
                                 class="img-fluid rounded"
                                 style="width:100%;height:auto;"
                                 alt="Foto <?= $i+1 ?>"
                                 onerror="this.parentElement.style.display='none';">
                            <div class="position-absolute top-0 end-0 m-1">
                                <span class="badge bg-secondary"><?= $i+1 ?></span>
                            </div>
                        </a>
                        <div class="mt-2 d-flex gap-1">
                            <?php $isLegacy = is_string($photo['id']) && str_starts_with($photo['id'], 'legacy_'); ?>
                            
                            <?php if ($isLegacy): ?>
                                <small class="text-muted flex-fill text-center">
                                    <i class="bi bi-info-circle"></i> Stará fotka - použijte CSV/XML export
                                </small>
                            <?php else: ?>
                                <?php if ($review['status'] !== 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="rotatePhoto(<?= $photo['id'] ?>, 270, this)" title="Otočit doleva">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        onclick="rotatePhoto(<?= $photo['id'] ?>, 90, this)" title="Otočit doprava">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <?php endif; ?>
                                <a href="<?= APP_URL ?>/photo/download?id=<?= $photo['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary flex-fill" title="Stáhnout originál (před úpravami)">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" 
                                        onclick="openReuploadModal(<?= $photo['id'] ?>)" title="Nahradit fotku">
                                    <i class="bi bi-upload"></i>
                                </button>
                                <form method="POST" action="<?= APP_URL ?>/photo/delete"
                                      onsubmit="return confirm('Opravdu smazat tuto fotku?')"
                                      class="flex-fill">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $photo['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" title="Smazat">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: metadata + akce -->
    <div class="col-12 col-lg-5">

        <!-- Metadata -->
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Informace</h6></div>
            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Autor</span>
                    <strong><?= $e($review['author_name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">E-mail</span>
                    <a href="mailto:<?= $e($review['author_email']) ?>" class="text-truncate small"><?= $e($review['author_email']) ?></a>
                </div>
                <?php if ($review['rating']): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Hodnocení</span>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                        <?php endfor; ?>
                        <span class="small ms-1">(<?= $review['rating'] ?>/5)</span>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($review['sku']): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">SKU</span>
                    <code class="small"><?= $e($review['sku']) ?></code>
                </div>
                <?php endif; ?>
                <?php if (!empty($review['product_name'])): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Produkt</span>
                    <span class="small fw-semibold text-end" style="max-width:60%"><?= $e($review['product_name']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($review['source_url'])): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Zdrojová URL</span>
                    <a href="<?= $e($review['source_url']) ?>" target="_blank" class="small text-truncate" style="max-width:60%">
                        <?= $e($review['source_url']) ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Datum</span>
                    <span class="small"><?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></span>
                </div>
                <?php if ($review['reviewed_at']): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Schváleno</span>
                    <span class="small"><?= date('d.m.Y H:i', strtotime($review['reviewed_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($review['xml_exported_at'])): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">XML export</span>
                    <span class="badge bg-info"><?= date('d.m.Y H:i', strtotime($review['xml_exported_at'])) ?></span>
                </div>
                <?php endif; ?>
                <?php foreach ($review['photos'] as $pi => $photo):
                    if (is_string($photo['id'] ?? '') && str_starts_with($photo['id'] ?? '', 'legacy_')) continue;
                    $ext2        = pathinfo($photo['path'] ?? '', PATHINFO_EXTENSION);
                    $displayAbs2 = ROOT . '/public/uploads/' . ltrim($photo['path'] ?? '', '/');
                    $origAbs2    = substr($displayAbs2, 0, -strlen('.' . $ext2)) . '_original.' . $ext2;
                    $srcAbs2     = file_exists($origAbs2) ? $origAbs2 : $displayAbs2;
                    $imgSize2    = file_exists($srcAbs2) ? @getimagesize($srcAbs2) : null;
                    $fileSize2   = file_exists($srcAbs2) ? filesize($srcAbs2) : null;
                    if (!$imgSize2 && !$fileSize2) continue;
                ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="text-muted small">Foto <?= $pi + 1 ?></span>
                    <div class="text-end small">
                        <?php if ($imgSize2): ?>
                        <div><?= $imgSize2[0] ?>×<?= $imgSize2[1] ?> px</div>
                        <?php endif; ?>
                        <?php if ($fileSize2): ?>
                        <div class="text-muted"><?= round($fileSize2 / 1024) ?> KB</div>
                        <?php endif; ?>
                        <a href="<?= APP_URL ?>/photo/download?id=<?= $photo['id'] ?>" class="small text-primary">
                            <i class="bi bi-download me-1"></i>Stáhnout originál
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($review['comment']): ?>
                <div class="px-3 pb-3">
                    <div class="text-muted small mb-1">Komentář zákazníka:</div>
                    <blockquote class="border-start border-primary ps-3 text-secondary small">
                        <?= nl2br($e($review['comment'])) ?>
                    </blockquote>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Akce: Schválit / Zamítnout -->
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Moderace</h6></div>
            <div class="card-body">
                <?php if ($review['admin_note']): ?>
                <div class="alert alert-secondary small mb-3">
                    <strong>Interní poznámka:</strong><br>
                    <?= nl2br($e($review['admin_note'])) ?>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="form-label small text-muted">Interní poznámka (nepovinná)</label>
                    <textarea id="adminNote" class="form-control form-control-sm" rows="2"
                              placeholder="Důvod zamítnutí, poznámka..."><?= $e($review['admin_note'] ?? '') ?></textarea>
                </div>
                
                <!-- Uložit poznámku -->
                <div class="mb-3">
                    <form method="POST" action="/reviews/update-note">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= $review['id'] ?>">
                        <input type="hidden" name="admin_note" id="saveNoteInput">
                        <button type="submit" class="btn btn-sm btn-secondary w-100" 
                                onclick="this.form.querySelector('#saveNoteInput').value = document.getElementById('adminNote').value">
                            <i class="bi bi-save me-1"></i>Uložit poznámku
                        </button>
                    </form>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if ($review['status'] !== 'approved'): ?>
                    <form method="POST" action="<?= APP_URL ?>/reviews/change-status" class="flex-fill">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $review['id'] ?>">
                        <input type="hidden" name="status" value="approved">
                        <input type="hidden" name="admin_note" class="note-input">
                        <button type="submit" class="btn btn-success w-100" onclick="this.form.querySelector('.note-input').value = document.getElementById('adminNote').value">
                            <i class="bi bi-check-circle me-1"></i>Schválit
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($review['status'] !== 'rejected'): ?>
                    <form method="POST" action="<?= APP_URL ?>/reviews/change-status" class="flex-fill">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= $review['id'] ?>">
                        <input type="hidden" name="status" value="rejected">
                        <input type="hidden" name="admin_note" class="note-input">
                        <button type="submit" class="btn btn-danger w-100" onclick="this.form.querySelector('.note-input').value = document.getElementById('adminNote').value">
                            <i class="bi bi-x-circle me-1"></i>Zamítnout
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (false && $review['admin_note']): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="text-muted small mb-1">Interní poznámka:</div>
                <p class="small mb-0"><?= nl2br($e($review['admin_note'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Smazat -->
        <div class="card border-0 border-danger border-opacity-25">
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/reviews/<?= $review['id'] ?>"
                      onsubmit="return confirm('Smazat recenzi a všechny fotky?')">
                    <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-trash me-1"></i>Smazat recenzi a fotky
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Re-upload Modal -->
<div class="modal fade" id="reuploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= APP_URL ?>/photo/reupload" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nahradit fotku</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="photo_id" id="reupload-photo-id">
                    <div class="mb-3">
                        <label class="form-label">Vyberte novou fotku</label>
                        <input type="file" class="form-control" name="photo" 
                               accept="image/jpeg,image/png,image/webp" required>
                        <small class="text-muted">
                            JPG, PNG nebo WEBP, max 10MB<br>
                            Fotka bude zpracována s watermarkem podle vašeho nastavení
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Nahrát a nahradit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReuploadModal(photoId) {
    document.getElementById('reupload-photo-id').value = photoId;
    new bootstrap.Modal(document.getElementById('reuploadModal')).show();
}
</script>

<!-- Lightbox pro fotky -->
<div id="photoLightbox" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title" id="lightboxTitle">Fotka 1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="lightboxImg" src="" class="img-fluid" style="max-height:80vh;">
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn btn-outline-secondary" id="prevPhoto">
                    <i class="bi bi-chevron-left"></i> Předchozí
                </button>
                <button type="button" class="btn btn-outline-secondary" id="nextPhoto">
                    Další <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const photos = document.querySelectorAll('.photo-lightbox');
    const lightbox = new bootstrap.Modal(document.getElementById('photoLightbox'));
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxTitle = document.getElementById('lightboxTitle');
    const prevBtn = document.getElementById('prevPhoto');
    const nextBtn = document.getElementById('nextPhoto');
    
    let currentIndex = 0;
    const photoUrls = Array.from(photos).map(a => a.href);
    
    function showPhoto(index) {
        currentIndex = index;
        lightboxImg.src = photoUrls[index];
        lightboxTitle.textContent = `Fotka ${index + 1} z ${photoUrls.length}`;
        
        prevBtn.disabled = index === 0;
        nextBtn.disabled = index === photoUrls.length - 1;
    }
    
    photos.forEach((photo, index) => {
        photo.addEventListener('click', (e) => {
            e.preventDefault();
            showPhoto(index);
            lightbox.show();
        });
    });
    
    prevBtn.addEventListener('click', () => {
        if (currentIndex > 0) showPhoto(currentIndex - 1);
    });
    
    nextBtn.addEventListener('click', () => {
        if (currentIndex < photoUrls.length - 1) showPhoto(currentIndex + 1);
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
        if (!document.getElementById('photoLightbox').classList.contains('show')) return;
        
        if (e.key === 'ArrowLeft') prevBtn.click();
        if (e.key === 'ArrowRight') nextBtn.click();
        if (e.key === 'Escape') lightbox.hide();
    });
});

function rotatePhoto(photoId, degrees, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch('/photo/rotate', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            id: photoId,
            degrees: degrees,
            _csrf: document.querySelector('input[name="_csrf"]').value
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Reload stránky s cache-busting parametrem
            const url = new URL(window.location.href);
            url.searchParams.set('t', data.ts || Date.now());
            window.location.href = url.toString();
        } else {
            alert('Chyba: ' + (data.error || 'Neznámá chyba'));
        }
    })
    .catch(() => alert('Chyba při rotaci.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = degrees === 90
            ? '<i class="bi bi-arrow-clockwise"></i>'
            : '<i class="bi bi-arrow-counterclockwise"></i>';
    });
}
</script>
