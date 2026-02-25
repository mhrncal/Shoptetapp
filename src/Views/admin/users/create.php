<?php $pageTitle = 'Nový uživatel'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= APP_URL ?>/admin/users" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Nový uživatel</h4>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-header"><h6 class="mb-0">Údaje uživatele</h6></div>
    <div class="card-body">
        <form method="POST" action="<?= APP_URL ?>/admin/users/create">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label">Jméno <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?= $e($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Příjmení</label>
                    <input type="text" name="last_name" class="form-control"
                           value="<?= $e($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">E-mail <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" required
                       value="<?= $e($_POST['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Název shopu</label>
                <input type="text" name="shop_name" class="form-control"
                       value="<?= $e($_POST['shop_name'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Heslo <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required
                       placeholder="Min. 8 znaků">
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="user">User</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Stav</label>
                    <select name="status" class="form-select">
                        <option value="approved" selected>Schválen</option>
                        <option value="pending">Čeká na schválení</option>
                        <option value="rejected">Zamítnut</option>
                    </select>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Vytvořit uživatele
                </button>
                <a href="<?= APP_URL ?>/admin/users" class="btn btn-outline-secondary">Zrušit</a>
            </div>
        </form>
    </div>
</div>
