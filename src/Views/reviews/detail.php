<?php $pageTitle = 'Recenze #' . $review['id']; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
<?php $st = Review::STATUSES[$review['status']] ?? ['label'=>$review['status'],'color'=>'secondary']; ?>

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
                    <div class="col-6 col-md-4">
                        <a href="<?= $e(APP_URL . '/uploads/' . $photo['path']) ?>"
                           target="_blank" class="d-block position-relative">
                            <img src="<?= $e(APP_URL . '/uploads/' . ($photo['thumb'] ?? $photo['path'])) ?>"
                                 class="img-fluid rounded"
                                 style="aspect-ratio:1;object-fit:cover;width:100%;"
                                 alt="Foto <?= $i+1 ?>"
                                 onerror="this.src='<?= APP_URL ?>/uploads/<?= $e($photo['path']) ?>'">
                            <div class="position-absolute top-0 end-0 m-1">
                                <span class="badge bg-dark bg-opacity-75"><?= $i+1 ?></span>
                            </div>
                        </a>
                        <div class="mt-1">
                            <a href="<?= $e(APP_URL . '/uploads/' . $photo['path']) ?>"
                               target="_blank" class="text-muted small">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Plná velikost
                            </a>
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
                    <?php if ($review['product_name']): ?>
                    <tr>
                        <td class="text-muted ps-3">Produkt</td>
                        <td>
                            <a href="<?= APP_URL ?>/products/<?= $review['product_id'] ?>">
                                <?= $e($review['product_name']) ?>
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
        <?php if ($review['status'] === 'pending'): ?>
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Moderace</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small text-muted">Interní poznámka (nepovinná)</label>
                    <textarea id="adminNote" class="form-control form-control-sm" rows="2"
                              placeholder="Důvod zamítnutí, poznámka..."></textarea>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= APP_URL ?>/reviews/<?= $review['id'] ?>/approve">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="admin_note" id="noteApprove">
                        <button type="submit" class="btn btn-success btn-sm"
                                onclick="document.getElementById('noteApprove').value=document.getElementById('adminNote').value">
                            <i class="bi bi-check-circle me-1"></i>Schválit
                        </button>
                    </form>
                    <form method="POST" action="<?= APP_URL ?>/reviews/<?= $review['id'] ?>/reject">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="admin_note" id="noteReject">
                        <button type="submit" class="btn btn-danger btn-sm"
                                onclick="document.getElementById('noteReject').value=document.getElementById('adminNote').value">
                            <i class="bi bi-x-circle me-1"></i>Zamítnout
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php elseif ($review['admin_note']): ?>
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
