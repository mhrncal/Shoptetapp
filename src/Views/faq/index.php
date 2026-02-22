<?php $pageTitle = 'FAQ'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-question-circle me-2"></i>FAQ</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong> položek</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFaqModal">
        <i class="bi bi-plus me-1"></i>Přidat FAQ
    </button>
</div>

<!-- Filtry -->
<div class="card border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Hledat v otázkách..."
                           value="<?= $e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="filter" class="form-select form-select-sm">
                    <option value="">Vše</option>
                    <option value="general" <?= $filter === 'general' ? 'selected' : '' ?>>Pouze obecné</option>
                </select>
            </div>
            <div class="col-3 col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtr</button>
            </div>
            <div class="col-3 col-md-1">
                <a href="<?= APP_URL ?>/faq" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Seznam FAQ -->
<?php if (empty($faqs)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-question-circle fs-1 d-block mb-3"></i>
        <p>Žádné FAQ položky. Přidejte první otázku.</p>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFaqModal">
            <i class="bi bi-plus me-1"></i>Přidat FAQ
        </button>
    </div>
</div>
<?php else: ?>

<?php
// Rozdělíme na obecné a produktové
$general  = array_filter($faqs, fn($f) => $f['product_id'] === null);
$prodFaqs = array_filter($faqs, fn($f) => $f['product_id'] !== null);
?>

<?php foreach ([['general' => true, 'label' => 'Obecné FAQ', 'icon' => 'question-circle', 'items' => $general], ['general' => false, 'label' => 'FAQ k produktům', 'icon' => 'box', 'items' => $prodFaqs]] as $section):
    if (empty($section['items'])) continue;
?>
<div class="card border-0 mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-<?= $section['icon'] ?> me-2 text-muted"></i>
            <?= $section['label'] ?>
            <span class="badge bg-secondary ms-2"><?= count($section['items']) ?></span>
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="faqAccordion<?= $section['general'] ? 'G' : 'P' ?>">
            <?php foreach ($section['items'] as $faq): ?>
            <div class="accordion-item bg-transparent border-0 border-bottom border-secondary border-opacity-25">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed bg-transparent text-white shadow-none py-3"
                            type="button" data-bs-toggle="collapse"
                            data-bs-target="#faq<?= $faq['id'] ?>">
                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-2 w-100 me-3">
                            <span class="fw-semibold"><?= $e($faq['question']) ?></span>
                            <div class="d-flex gap-2 ms-md-auto flex-shrink-0">
                                <?php if ($faq['product_name']): ?>
                                <span class="badge bg-primary bg-opacity-20 text-primary small">
                                    <i class="bi bi-box me-1"></i><?= $e($faq['product_name']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!$faq['is_public']): ?>
                                <span class="badge bg-warning text-dark small">Neveřejné</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="faq<?= $faq['id'] ?>" class="accordion-collapse collapse">
                    <div class="accordion-body pt-0">
                        <div class="text-secondary mb-3" style="line-height:1.7;">
                            <?= nl2br($e($faq['answer'])) ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="editFaq(<?= htmlspecialchars(json_encode($faq), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil me-1"></i>Upravit
                            </button>
                            <form method="POST" action="<?= APP_URL ?>/faq/<?= $faq['id'] ?>"
                                  onsubmit="return confirm('Smazat tuto FAQ položku?')">
                                <input type="hidden" name="_csrf"    value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i>Smazat
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Modal: Přidat FAQ -->
<div class="modal fade" id="addFaqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Přidat FAQ položku</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/faq">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">
                    <?= \ShopCode\Core\View::partial('faq/_form', ['products' => $products, 'faq' => null, 'csrfToken' => $csrfToken]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Upravit FAQ -->
<div class="modal fade" id="editFaqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Upravit FAQ položku</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editFaqForm" action="">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body" id="editFaqBody">
                    <?= \ShopCode\Core\View::partial('faq/_form', ['products' => $products, 'faq' => null, 'csrfToken' => $csrfToken]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Uložit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editFaq(faq) {
    var form = document.getElementById('editFaqForm');
    form.action = '<?= APP_URL ?>/faq/' + faq.id;

    var body = document.getElementById('editFaqBody');
    body.querySelector('[name="question"]').value    = faq.question    || '';
    body.querySelector('[name="answer"]').value      = faq.answer      || '';
    body.querySelector('[name="sort_order"]').value  = faq.sort_order  || 0;
    body.querySelector('[name="is_public"]').checked = faq.is_public == 1;

    var productSel = body.querySelector('[name="product_id"]');
    if (productSel) productSel.value = faq.product_id || '';

    var modal = new bootstrap.Modal(document.getElementById('editFaqModal'));
    modal.show();
}
</script>
