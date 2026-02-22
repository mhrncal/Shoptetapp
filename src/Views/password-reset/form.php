<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<div class="text-center mb-4">
    <h4 class="fw-bold">Nové heslo</h4>
    <p class="text-muted small">Nastavte nové heslo pro <strong><?= $e($email) ?></strong></p>
</div>

<form method="POST" action="<?= APP_URL ?>/password/reset/<?= $e($token) ?>">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="mb-3">
        <label class="form-label">Nové heslo</label>
        <div class="input-group">
            <input type="password" name="password" id="pwd" class="form-control"
                   required minlength="8" autofocus placeholder="Minimálně 8 znaků">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="var i=document.getElementById('pwd');i.type=i.type==='password'?'text':'password'">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label">Potvrzení hesla</label>
        <input type="password" name="password_confirm" class="form-control"
               required minlength="8" placeholder="Zopakujte heslo">
    </div>

    <button type="submit" class="btn btn-primary w-100 mb-3">
        <i class="bi bi-lock me-2"></i>Nastavit nové heslo
    </button>

    <div class="text-center">
        <a href="<?= APP_URL ?>/login" class="text-muted small">
            <i class="bi bi-arrow-left me-1"></i>Zpět na přihlášení
        </a>
    </div>
</form>
