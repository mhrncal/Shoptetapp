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
        <div class="input-group">
            <input type="password" name="password" id="passwordInput" class="form-control"
                   placeholder="••••••••" required autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" id="togglePassword" tabindex="-1">
                <i class="bi bi-eye" id="toggleIcon"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Přihlásit se
    </button>

</form>

<script>
document.getElementById('togglePassword').addEventListener('click', function() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
