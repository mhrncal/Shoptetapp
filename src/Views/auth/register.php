<?php $pageTitle = 'Registrace'; ?>

<div class="card border-0 shadow-lg bg-dark-subtle">
    <div class="card-body p-4">
        <h4 class="card-title fw-bold mb-4">Registrace</h4>

        <form method="POST" action="<?= APP_URL ?>/register">
            <input type="hidden" name="_csrf" value="<?= \ShopCode\Core\View::e($csrfToken) ?>">

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Jméno <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control bg-dark border-secondary text-white"
                           placeholder="Jan" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Příjmení <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control bg-dark border-secondary text-white"
                           placeholder="Novák" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Název e-shopu</label>
                <input type="text" name="shop_name" class="form-control bg-dark border-secondary text-white"
                       placeholder="Můj obchod s.r.o.">
            </div>

            <div class="mb-3">
                <label class="form-label">E-mail <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control bg-dark border-secondary text-white"
                       placeholder="vas@email.cz" required autocomplete="email">
            </div>

            <div class="mb-3">
                <label class="form-label">Heslo <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control bg-dark border-secondary text-white"
                       placeholder="min. 8 znaků" required minlength="8" autocomplete="new-password">
            </div>

            <div class="mb-4">
                <label class="form-label">Heslo znovu <span class="text-danger">*</span></label>
                <input type="password" name="password2" class="form-control bg-dark border-secondary text-white"
                       placeholder="••••••••" required autocomplete="new-password">
            </div>

            <div class="alert alert-info d-flex gap-2 py-2 mb-4" style="font-size:.85rem;">
                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                <span>Po registraci bude váš účet čekat na schválení administrátorem.</span>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                <i class="bi bi-person-plus me-2"></i>Zaregistrovat se
            </button>
        </form>

        <hr class="border-secondary my-4">
        <p class="text-center text-secondary mb-0">
            Máte účet? <a href="<?= APP_URL ?>/login" class="text-primary">Přihlaste se</a>
        </p>
    </div>
</div>
