<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<div class="auth-title">Vytvoření účtu</div>
<div class="auth-subtitle">Zaregistrujte se do systému ShopCode</div>

<form method="POST" action="<?= APP_URL ?>/register">
    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

    <div class="row g-3 mb-3">
        <div class="col-6">
            <label class="form-label">Jméno</label>
            <input type="text" name="first_name" class="form-control"
                   placeholder="Jan" required value="<?= $e($_POST['first_name'] ?? '') ?>">
        </div>
        <div class="col-6">
            <label class="form-label">Příjmení</label>
            <input type="text" name="last_name" class="form-control"
                   placeholder="Novák" required value="<?= $e($_POST['last_name'] ?? '') ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               placeholder="vas@email.cz" required value="<?= $e($_POST['email'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Název e-shopu</label>
        <input type="text" name="shop_name" class="form-control"
               placeholder="Můj e-shop" required value="<?= $e($_POST['shop_name'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">URL e-shopu</label>
        <input type="url" name="shop_url" class="form-control"
               placeholder="https://mujeshop.cz" value="<?= $e($_POST['shop_url'] ?? '') ?>">
    </div>

    <div class="mb-3">
        <label class="form-label">Heslo</label>
        <input type="password" name="password" class="form-control"
               placeholder="Minimálně 8 znaků" required autocomplete="new-password">
    </div>

    <div class="mb-4">
        <label class="form-label">Heslo znovu</label>
        <input type="password" name="password_confirm" class="form-control"
               placeholder="Zopakujte heslo" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary w-100">
        Vytvořit účet
    </button>

    <p class="text-center mt-4 mb-0" style="font-size:.875rem;color:var(--sc-muted-fg);">
        Máte již účet?
        <a href="<?= APP_URL ?>/login"
           style="color:var(--sc-primary);text-decoration:none;font-weight:500;">
            Přihlásit se
        </a>
    </p>
</form>
