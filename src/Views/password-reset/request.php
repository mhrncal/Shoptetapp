<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<div class="text-center mb-4">
    <h4 class="fw-bold">Zapomenuté heslo</h4>
    <p class="text-muted small">Zadejte svůj e-mail a pošleme vám odkaz pro reset hesla.</p>
</div>

<form method="POST" action="<?= APP_URL ?>/password/reset">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required autofocus
               placeholder="vas@email.cz">
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-3">
        <i class="bi bi-envelope me-2"></i>Odeslat odkaz pro reset
    </button>

    <div class="text-center">
        <a href="<?= APP_URL ?>/login" class="text-muted small">
            <i class="bi bi-arrow-left me-1"></i>Zpět na přihlášení
        </a>
    </div>
</form>
