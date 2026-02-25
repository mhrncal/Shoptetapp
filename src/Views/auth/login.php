<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<p class="auth-title">Shoptet Admin</p>
<p class="auth-subtitle mb-4">Přihlaste se do administrace vašeho e-shopu</p>

<form method="POST" action="<?= APP_URL ?>/login" class="space-y-4">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               placeholder="vas@email.cz" required autocomplete="username"
               value="<?= $e($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-4">
        <label class="form-label d-flex justify-content-between align-items-center">
            <span>Heslo</span>
            <a href="<?= APP_URL ?>/password/reset"
               style="font-size:.8rem; color:hsl(var(--primary)); text-decoration:none; font-weight:400;">
                Zapomenuté heslo?
            </a>
        </label>
        <input type="password" name="password" class="form-control"
               placeholder="••••••••" required autocomplete="current-password">
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Přihlásit se
    </button>

    <p class="text-center mt-4 mb-0" style="font-size:.875rem; color:hsl(var(--muted-foreground));">
        Nemáte účet?
        <a href="<?= APP_URL ?>/register"
           style="color:hsl(var(--primary)); text-decoration:none; font-weight:500;">
            Registrovat se
        </a>
    </p>
</form>
