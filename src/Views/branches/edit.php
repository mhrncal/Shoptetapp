<?php $pageTitle = isset($branch['id']) ? 'Upravit pobočku' : 'Nová pobočka'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/branches" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
</div>

<form method="POST"
      action="<?= isset($branch['id']) ? APP_URL . '/branches/' . $branch['id'] : APP_URL . '/branches' ?>">

    <?= \ShopCode\Core\View::partial('branches/_form', ['branch' => $branch ?? null, 'days' => $days, 'csrfToken' => $csrfToken]) ?>

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save me-1"></i><?= isset($branch['id']) ? 'Uložit změny' : 'Přidat pobočku' ?>
        </button>
        <a href="<?= APP_URL ?>/branches" class="btn btn-outline-secondary">Zrušit</a>
    </div>
</form>
