<?php $pageTitle = 'Editace uživatele'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">Editace: <?= $e($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?></h4>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <div class="card border-0">
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/edit">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Jméno</label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?= $e($targetUser['first_name']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Příjmení</label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?= $e($targetUser['last_name']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control bg-secondary bg-opacity-10"
                               value="<?= $e($targetUser['email']) ?>" readonly>
                        <div class="form-text">E-mail nelze změnit přes toto rozhraní.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Název shopu</label>
                        <input type="text" name="shop_name" class="form-control"
                               value="<?= $e($targetUser['shop_name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL shopu</label>
                        <input type="url" name="shop_url" class="form-control"
                               value="<?= $e($targetUser['shop_url']) ?>">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <label class="form-label">Stav účtu</label>
                            <select name="status" class="form-select">
                                <option value="pending"  <?= $targetUser['status'] === 'pending'  ? 'selected' : '' ?>>Čeká na schválení</option>
                                <option value="approved" <?= $targetUser['status'] === 'approved' ? 'selected' : '' ?>>Schválen</option>
                                <option value="rejected" <?= $targetUser['status'] === 'rejected' ? 'selected' : '' ?>>Zamítnut</option>
                            </select>
                        </div>
                        <?php if ($targetUser['role'] !== 'superadmin'): ?>
                        <div class="col-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="user"       <?= $targetUser['role'] === 'user'       ? 'selected' : '' ?>>User</option>
                                <option value="superadmin" <?= $targetUser['role'] === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Uložit změny
                        </button>
                        <a href="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>" class="btn btn-outline-secondary">
                            Zrušit
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
