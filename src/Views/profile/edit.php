<?php $pageTitle = 'Profil'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-circle me-2"></i>Profil</h4>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <form method="POST" action="<?= APP_URL ?>/profile">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

            <div class="card border-0 mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Osobní údaje</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Jméno <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control"
                                   value="<?= $e($user['first_name']) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Příjmení <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control"
                                   value="<?= $e($user['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control bg-secondary bg-opacity-10"
                               value="<?= $e($user['email']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Název e-shopu</label>
                        <input type="text" name="shop_name" class="form-control"
                               value="<?= $e($user['shop_name']) ?>" placeholder="Můj obchod s.r.o.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL e-shopu</label>
                        <input type="url" name="shop_url" class="form-control"
                               value="<?= $e($user['shop_url']) ?>" placeholder="https://mujshop.cz">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">URL XML feedu</label>
                        <input type="url" name="xml_feed_url" class="form-control"
                               value="<?= $e($user['xml_feed_url']) ?>" placeholder="https://mujshop.cz/feed.xml">
                        <div class="form-text">Odkaz na váš Shoptet XML produktový feed.</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 mb-4">
                <div class="card-header">
                    <h6 class="mb-0 fw-semibold">Změna hesla <span class="text-muted fw-normal small">(nepovinné)</span></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Aktuální heslo</label>
                        <input type="password" name="current_password" class="form-control"
                               autocomplete="current-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Nové heslo</label>
                        <input type="password" name="new_password" class="form-control"
                               placeholder="min. 8 znaků" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Uložit profil
            </button>
        </form>
    </div>
</div>
