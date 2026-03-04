<?php $pageTitle = 'Recenze #' . $review['id']; ?>
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

<div class="row g-4">

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
                        <a href="<?= $e(APP_URL . '/public/uploads/' . $photo['path']) ?>"
                           class="photo-lightbox d-block position-relative" data-lightbox="review-photos">
                            <img src="<?= $e(APP_URL . '/public/uploads/' . ($photo['thumb'] ?? $photo['path'])) ?>"
                                 class="img-fluid rounded"
                                 style="aspect-ratio:1;object-fit:cover;width:100%;"
                                 alt="Foto <?= $i+1 ?>"
                                 onerror="this.src='<?= APP_URL ?>/public/uploads/<?= $e($photo['path']) ?>'">
                            <div class="position-absolute top-0 end-0 m-1">
                                <span class="badge bg-dark bg-opacity-75"><?= $i+1 ?></span>
                            </div>
                        </a>
                        <div class="mt-2 d-flex gap-1">
                            <?php $isLegacy = is_string($photo['id']) && str_starts_with($photo['id'], 'legacy_'); ?>
                            
                            <?php if ($isLegacy): ?>
                                <small class="text-muted flex-fill text-center">
                                    <i class="bi bi-info-circle"></i> Stará fotka - použijte CSV/XML export
                                </small>
                            <?php else: ?>
                                <a href="<?= APP_URL ?>/photo/download?id=<?= $photo['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary flex-fill" title="Stáhnout originál">
                                    <i class="bi bi-download"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" 
                                        onclick="openReuploadModal(<?= $photo['id'] ?>)" title="Nahradit fotku">
                                    <i class="bi bi-upload"></i>
                                </button>
                                <form method="POST" action="<?= APP_URL ?>/photo/delete" 
                                      onsubmit="return confirm('Opravdu smazat tuto fotku?')" 
                                      class="flex-fill">
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
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted ps-3" style="width:110px;">Autor</td>
                        <td><strong><?= $e($review['author_name']) ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">E-mail</td>
                        <td><a href="mailto:<?= $e($review['author_email']) ?>"><?= $e($review['author_email']) ?></a></td>
                    </tr>
                    <?php if ($review['rating']): ?>
                    <tr>
                        <td class="text-muted ps-3">Hodnocení</td>
                        <td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill text-warning' : ' text-muted' ?>"></i>
                            <?php endfor; ?>
                            (<?= $review['rating'] ?>/5)
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (($review['product_name'] ?? null)): ?>
                    <tr>
                        <td class="text-muted ps-3">Produkt</td>
                        <td>
                            <a href="<?= APP_URL ?>/products/<?= $review['product_id'] ?>">
                                <?= $e(($review['product_name'] ?? null)) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($review['sku']): ?>
                    <tr>
                        <td class="text-muted ps-3">SKU</td>
                        <td><code><?= $e($review['sku']) ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted ps-3">Datum</td>
                        <td><?= date('d.m.Y H:i', strtotime($review['created_at'])) ?></td>
                    </tr>
                    <?php if ($review['reviewed_at']): ?>
                    <tr>
                        <td class="text-muted ps-3">Schváleno</td>
                        <td><?= date('d.m.Y H:i', strtotime($review['reviewed_at'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
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
                <div class="d-flex gap-2">
                    <?php if ($review['status'] !== 'approved'): ?>
                    <form method="POST" action="<?= APP_URL ?>/reviews/change-status" class="flex-fill">
                        <input type="hidden" name="_csrf" value="<?= $_SESSION['_csrf'] ?? '' ?>">
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
                        <input type="hidden" name="_csrf" value="<?= $_SESSION['_csrf'] ?? '' ?>">
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
        <div class="card border-0 border-secondary border-opacity-25 mb-4">
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
        <div class="modal-content bg-dark">
            <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="lightboxTitle">Fotka 1</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="lightboxImg" src="" class="img-fluid" style="max-height:80vh;">
            </div>
            <div class="modal-footer border-0 justify-content-between">
                <button type="button" class="btn btn-outline-light" id="prevPhoto">
                    <i class="bi bi-chevron-left"></i> Předchozí
                </button>
                <button type="button" class="btn btn-outline-light" id="nextPhoto">
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
</script>
