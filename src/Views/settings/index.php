<?php $pageTitle = 'Nastavení'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Nastavení</h4>
</div>

<!-- Taby -->
<ul class="nav nav-tabs border-secondary mb-4" id="settingsTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-profile">Profil</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-password">Heslo</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-modules">Moduly</a></li>
    <li class="nav-item"><a class="nav-link text-danger" data-bs-toggle="tab" href="#tab-danger">Nebezpečná zóna</a></li>
</ul>

<div class="tab-content">

    <!-- TAB: Profil -->
    <div class="tab-pane fade show active" id="tab-profile">
        <div class="card border-0" style="max-width:600px;">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Osobní údaje</h6></div>
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/settings/profile">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Jméno <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required
                                   value="<?= $e($user['first_name']) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Příjmení <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required
                                   value="<?= $e($user['last_name']) ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">E-mail</label>
                        <input type="email" class="form-control" value="<?= $e($user['email']) ?>" disabled>
                        <div class="form-text">E-mail nelze změnit. Kontaktujte administrátora.</div>
                    </div>

                    <hr class="border-secondary">

                    <div class="mb-3">
                        <label class="form-label">Název e-shopu</label>
                        <input type="text" name="shop_name" class="form-control"
                               value="<?= $e($user['shop_name'] ?? '') ?>" placeholder="Můj e-shop s.r.o.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL e-shopu</label>
                        <input type="url" name="shop_url" class="form-control"
                               value="<?= $e($user['shop_url'] ?? '') ?>" placeholder="https://mujeshop.cz">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">URL XML feedu</label>
                        <input type="url" name="xml_feed_url" class="form-control"
                               value="<?= $e($user['xml_feed_url'] ?? '') ?>"
                               placeholder="https://mujeshop.cz/export/feed.xml">
                        <div class="form-text">Shoptet produktový feed pro XML import.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Uložit profil
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: Heslo -->
    <div class="tab-pane fade" id="tab-password">
        <div class="card border-0" style="max-width:480px;">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Změna hesla</h6></div>
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/settings/password">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label">Aktuální heslo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="current_password" class="form-control" required id="curPwd">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePwd('curPwd', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nové heslo <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="new_password" class="form-control" required
                                   minlength="8" id="newPwd">
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="togglePwd('newPwd', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimálně 8 znaků.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Potvrzení nového hesla <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-lock me-1"></i>Změnit heslo
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB: Moduly -->
    <div class="tab-pane fade" id="tab-modules">
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Aktivní moduly</h6>
                <p class="text-muted small mb-0 mt-1">Přehled modulů přiřazených k vašemu účtu. Správu provádí administrátor.</p>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th class="ps-3">Modul</th><th>Popis</th><th>Verze</th><th>Stav</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($modules as $mod):
                        $isActive = $activeMap[$mod['id']] ?? false;
                    ?>
                    <tr class="<?= !$isActive ? 'opacity-60' : '' ?>">
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-<?= $e($mod['icon'] ?? 'puzzle') ?> text-muted"></i>
                                <strong><?= $e($mod['label']) ?></strong>
                            </div>
                        </td>
                        <td class="text-muted small"><?= $e($mod['description'] ?? '') ?></td>
                        <td class="text-muted small font-monospace">v<?= $e($mod['version'] ?? '1.0') ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge bg-success">Aktivní</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Neaktivní</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: Nebezpečná zóna -->
    <div class="tab-pane fade" id="tab-danger">
        <div class="card border-0 border-danger border-opacity-25">
            <div class="card-header border-danger border-opacity-25">
                <h6 class="mb-0 fw-semibold text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>Nebezpečná zóna
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-1">Smazání účtu je nevratná akce. Všechna vaše data budou trvale odstraněna.</p>
                <p class="text-muted small mb-4">Smaže se: profil, produkty, FAQ, pobočky, události, API tokeny, webhooky, XML importy.</p>

                <button class="btn btn-danger" data-bs-toggle="collapse" data-bs-target="#deleteForm">
                    <i class="bi bi-trash me-1"></i>Smazat účet
                </button>

                <div class="collapse mt-3" id="deleteForm">
                    <div class="card border-danger border-opacity-25 p-3" style="max-width:400px;">
                        <p class="small mb-3">Pro potvrzení zadejte své heslo:</p>
                        <form method="POST" action="<?= APP_URL ?>/settings/delete"
                              onsubmit="return confirm('Opravdu smazat celý účet? Tuto akci nelze vrátit!')">
                            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                            <div class="mb-3">
                                <input type="password" name="confirm_password" class="form-control border-danger"
                                       placeholder="Vaše heslo" required>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">
                                Ano, smazat můj účet
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function togglePwd(id, btn) {
    var inp = document.getElementById(id);
    var icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// Otevři správný tab podle hash v URL
var hash = window.location.hash;
if (hash) {
    var tabMap = { '#password': 'tab-password', '#modules': 'tab-modules', '#danger': 'tab-danger' };
    if (tabMap[hash]) {
        var tab = document.querySelector('[href="#' + tabMap[hash] + '"]');
        if (tab) new bootstrap.Tab(tab).show();
    }
}
</script>
