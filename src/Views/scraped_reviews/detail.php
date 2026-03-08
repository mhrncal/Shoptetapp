<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
<?php
$platformLabels = ['heureka' => 'Heureka', 'trustedshops' => 'Trusted Shops', 'shoptet' => 'Shoptet'];
$platformColors = ['heureka' => 'warning', 'trustedshops' => 'success', 'shoptet' => 'primary'];
?>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="/scraped-reviews" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold mb-0">Recenze #<?= $review['id'] ?></h4>
    <span class="badge bg-<?= $platformColors[$review['platform']] ?? 'secondary' ?>"><?= $platformLabels[$review['platform']] ?? $review['platform'] ?></span>
</div>

<div class="row g-3">
    <div class="col-12 col-md-8">

        <!-- Originální text -->
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Originál</h6></div>
            <div class="card-body">
                <?php if ($review['rating']): ?>
                <div class="text-warning mb-2">
                    <?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?>
                    <span class="text-muted small ms-1"><?= $review['rating'] ?>/5</span>
                </div>
                <?php endif; ?>
                <p class="mb-0"><?= nl2br($e($review['content'])) ?></p>
            </div>
        </div>

        <!-- Překlady -->
        <?php if (!empty($review['translations'])): ?>
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold"><i class="bi bi-translate me-1"></i>Překlady</h6></div>
            <div class="card-body p-0">
                <div class="accordion accordion-flush" id="translationsAccordion">
                    <?php foreach ($review['translations'] as $lang => $text): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#lang_<?= $e($lang) ?>">
                                <?= $e($allLangs[$lang] ?? $lang) ?>
                                <span class="badge bg-secondary ms-2" style="font-size:.7rem;"><?= $e($lang) ?></span>
                            </button>
                        </h2>
                        <div id="lang_<?= $e($lang) ?>" class="accordion-collapse collapse" data-bs-parent="#translationsAccordion">
                            <div class="accordion-body py-2">
                                <p class="mb-0 small"><?= nl2br($e($text)) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Zatím žádné překlady. Spusťte překlad ze seznamu recenzí.</div>
        <?php endif; ?>

    </div>
    <div class="col-12 col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Informace</h6></div>
            <div class="card-body p-0">
                <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                    <span class="text-muted small">Autor</span>
                    <strong class="small"><?= $e($review['author']) ?></strong>
                </div>
                <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                    <span class="text-muted small">Zdroj</span>
                    <span class="small"><?= $e($review['source_name']) ?></span>
                </div>
                <?php if ($review['reviewed_at']): ?>
                <div class="d-flex justify-content-between px-3 py-2 border-bottom">
                    <span class="text-muted small">Datum</span>
                    <span class="small"><?= date('d.m.Y', strtotime($review['reviewed_at'])) ?></span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between px-3 py-2">
                    <span class="text-muted small">Nascrapováno</span>
                    <span class="small"><?= date('d.m.Y H:i', strtotime($review['scraped_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
