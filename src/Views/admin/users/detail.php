<?php $pageTitle = 'Detail uživatele'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= APP_URL ?>/admin/users" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h4 class="fw-bold mb-0 d-inline">
            <?= $e($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?>
        </h4>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/edit" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Editovat
        </a>
        <?php if ($targetUser['role'] !== 'superadmin'): ?>
        <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/impersonate">
            <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
            <button class="btn btn-outline-info btn-sm">
                <i class="bi bi-person-fill-gear me-1"></i>Impersonovat
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Info -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Informace o uživateli</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted w-40">ID</td><td><?= $e($targetUser['id']) ?></td></tr>
                    <tr><td class="text-muted">E-mail</td><td><?= $e($targetUser['email']) ?></td></tr>
                    <tr><td class="text-muted">Jméno</td><td><?= $e($targetUser['first_name'] . ' ' . $targetUser['last_name']) ?></td></tr>
                    <tr><td class="text-muted">Shop</td><td><?= $e($targetUser['shop_name'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">URL</td><td><?= $e($targetUser['shop_url'] ?? '—') ?></td></tr>
                    <tr>
                        <td class="text-muted">Role</td>
                        <td>
                            <?php if ($targetUser['role'] === 'superadmin'): ?>
                            <span class="badge bg-warning text-dark">Superadmin</span>
                            <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-25 text-body">User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Stav</td>
                        <td>
                            <?php
                            $bs = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'];
                            $labels = ['approved' => 'Schválen', 'pending' => 'Čeká na schválení', 'rejected' => 'Zamítnut'];
                            ?>
                            <span class="badge bg-<?= $bs[$targetUser['status']] ?>">
                                <?= $labels[$targetUser['status']] ?>
                            </span>
                        </td>
                    </tr>
                    <tr><td class="text-muted">Registrace</td><td><?= date('d.m.Y H:i', strtotime($targetUser['created_at'])) ?></td></tr>
                    <tr><td class="text-muted">Poslední login</td>
                        <td><?= $targetUser['last_login_at'] ? date('d.m.Y H:i', strtotime($targetUser['last_login_at'])) : '—' ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Akce -->
        <?php if ($targetUser['status'] === 'pending'): ?>
        <div class="card border-0 border-warning mb-4">
            <div class="card-body d-flex gap-2">
                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/approve" class="flex-fill">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <button class="btn btn-success w-100"><i class="bi bi-check-lg me-1"></i>Schválit účet</button>
                </form>
                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/reject" class="flex-fill">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <button class="btn btn-warning w-100"><i class="bi bi-x-lg me-1"></i>Zamítnout</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($targetUser['role'] !== 'superadmin'): ?>
        <div class="card border-0 border-danger">
            <div class="card-body">
                <h6 class="text-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Nebezpečná zóna</h6>
                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $targetUser['id'] ?>/delete"
                      onsubmit="return confirm('Opravdu smazat uživatele a VŠECHNA jeho data?');">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <button class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Smazat uživatele
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Moduly -->
    <div class="col-12 col-lg-7">
        <div class="card border-0">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-puzzle me-2 text-muted"></i>Přiřazené moduly</h6>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Modul</th>
                            <th>Stav</th>
                            <th class="text-end">Přepínač</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $m): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold small"><?= $e($m['label']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= $e($m['description'] ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($m['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ($m['status'] ?? 'inactive') === 'active' ? 'Aktivní' : 'Neaktivní' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="form-check form-switch d-inline-block mb-0">
                                    <input class="form-check-input module-toggle" type="checkbox"
                                           data-user="<?= $targetUser['id'] ?>"
                                           data-module="<?= $m['id'] ?>"
                                           <?= ($m['status'] ?? 'inactive') === 'active' ? 'checked' : '' ?>>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).on('change', '.module-toggle', function() {
    const $this    = $(this);
    const userId   = $this.data('user');
    const moduleId = $this.data('module');
    const status   = $this.is(':checked') ? 'active' : 'inactive';

    $.post('<?= APP_URL ?>/admin/modules/assign', {
        _csrf:     '<?= $e($csrfToken) ?>',
        user_id:   userId,
        module_id: moduleId,
        status:    status
    })
    .done(function(resp) {
        const badge = $this.closest('tr').find('.badge');
        if (status === 'active') {
            badge.removeClass('bg-secondary').addClass('bg-success').text('Aktivní');
        } else {
            badge.removeClass('bg-success').addClass('bg-secondary').text('Neaktivní');
        }
    })
    .fail(function() {
        $this.prop('checked', !$this.is(':checked')); // rollback
        alert('Chyba při ukládání.');
    });
});
</script>
