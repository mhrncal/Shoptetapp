<?php
/** @var array|null $config */
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>

<div class="container-fluid px-4 py-4">

    <div class="d-flex align-items-center mb-4 gap-2">
        <a href="/reviews" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0">Import fotek ze Shoptetu</h1>
    </div>

    <?php if ($flash['success'] ?? null): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $e($flash['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash['error'] ?? null): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $e($flash['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Nastavení URL -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-link-45deg me-2"></i>URL exportu fotek</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        V Shoptetu přejdi na <strong>Produkty → Export → Fotky produktů</strong>,
                        vygeneruj odkaz a vlož ho sem. CSV bude obsahovat SKU a URL fotek na CDN.
                    </p>
                    <form method="post" action="/reviews/photo-import/save-url">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">URL CSV exportu</label>
                            <input type="url" name="csv_url" class="form-control font-monospace"
                                   placeholder="https://www.vas-eshop.cz/export/products.csv?patternId=...&hash=..."
                                   value="<?= $e($config['csv_url'] ?? '') ?>" required>
                            <div class="form-text">Formát: SKU;pairCode;name;defaultImage;image;image2;…image28</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Uložit URL
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stav importu + spuštění -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-cloud-download me-2"></i>Spustit import</h5>
                </div>
                <div class="card-body">
                    <?php if ($config): ?>
                        <dl class="row small mb-3">
                            <dt class="col-6 text-muted">Poslední import</dt>
                            <dd class="col-6">
                                <?= $config['last_imported_at']
                                    ? $e(date('d.m.Y H:i', strtotime($config['last_imported_at'])))
                                    : '<span class="text-muted">—</span>' ?>
                            </dd>
                            <dt class="col-6 text-muted">Produktů</dt>
                            <dd class="col-6"><?= $config['last_row_count'] ? number_format((int)$config['last_row_count'], 0, ',', ' ') : '—' ?></dd>
                            <dt class="col-6 text-muted">Fotek celkem</dt>
                            <dd class="col-6"><?= $config['last_image_count'] ? number_format((int)$config['last_image_count'], 0, ',', ' ') : '—' ?></dd>
                        </dl>

                        <form method="post" action="/reviews/photo-import/run" id="importForm">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <button type="submit" class="btn btn-success w-100" id="importBtn"
                                    onclick="return confirmImport()">
                                <i class="bi bi-arrow-repeat me-1"></i> Spustit import teď
                            </button>
                        </form>
                        <div class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            Import stahuje CSV streamově – při velkých katalozích může trvat 1–2 minuty.
                            Stávající záznamy budou přepsány.
                        </div>
                    <?php else: ?>
                        <div class="text-muted small">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            Nejprve nastavte URL exportu vlevo.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function confirmImport() {
    const btn = document.getElementById('importBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Importuji…';
    return true;
}
</script>
