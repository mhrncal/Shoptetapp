<?php $pageTitle = 'Přihlášení'; ?>

<div class="card border-0 shadow-lg bg-dark-subtle">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold mb-4">Přihlášení</h4>

        <form method="POST" action="<?= APP_URL ?>/login">
            <input type="hidden" name="_csrf" value="<?= \ShopCode\Core\View::e($csrfToken) ?>">

            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary">
                        <i class="bi bi-envelope text-secondary"></i>
                    </span>
                    <input type="email" name="email" class="form-control bg-dark border-secondary text-white"
                           placeholder="vas@email.cz" required autocomplete="email">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Heslo</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary">
                        <i class="bi bi-lock text-secondary"></i>
                    </span>
                    <input type="password" name="password" id="passwordInput"
                           class="form-control bg-dark border-secondary text-white"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4 d-flex align-items-center justify-content-between">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label text-secondary" for="remember">Zapamatovat mě</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-box-arrow-in-right me-2"></i>Přihlásit se
            </button>
        </form>

        <hr class="border-secondary my-4">
        <p class="text-center text-secondary mb-0">
            Nemáte účet?
            <a href="<?= APP_URL ?>/register" class="text-primary">Zaregistrujte se</a>
        </p>
    </div>
</div>

<script>
$('#togglePassword').on('click', function() {
    const input = $('#passwordInput');
    const icon  = $(this).find('i');
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
        input.attr('type', 'password');
        icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
});
</script>
