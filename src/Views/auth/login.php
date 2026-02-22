<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<div class="auth-title">Shoptet Admin</div>
<div class="auth-subtitle">Přihlaste se do administrace vašeho e-shopu</div>

<form method="POST" action="<?= APP_URL ?>/login">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               placeholder="vas@email.cz" required autocomplete="username"
               value="<?= $e($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label">Heslo</label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required autocomplete="current-password">
        <div class="text-end mt-1">
            <a href="<?= APP_URL ?>/password/reset"
               style="font-size:.8rem;color:var(--sc-primary);text-decoration:none;">
                Zapomenuté heslo?
            </a>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Přihlásit se
    </button>

    <p class="text-center mt-4 mb-0" style="font-size:.875rem;color:var(--sc-muted-fg);">
        Nemáte účet?
        <a href="<?= APP_URL ?>/register"
           style="color:var(--sc-primary);text-decoration:none;font-weight:500;">
            Registrovat se
        </a>
    </p>
</form>
