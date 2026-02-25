<?php $pageTitle = 'Správa modulů'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-puzzle me-2"></i>Správa modulů</h4>
        <p class="text-muted small mb-0">Aktivujte nebo deaktivujte moduly pro jednotlivé uživatele</p>
    </div>
</div>

<?php if (empty($users)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-people fs-1 d-block mb-3"></i>
        <p>Žádní schválení uživatelé.</p>
    </div>
</div>
<?php else: ?>

<!-- Legenda modulů -->
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Dostupné moduly</h6></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($modules as $m): ?>
            <span class="badge bg-secondary">
                <?= $e($m['label'] ?? $m['name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Uživatelé a jejich moduly -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="min-width:200px;">Uživatel</th>
                        <?php foreach ($modules as $m): ?>
                        <th class="text-center" style="min-width:90px; font-size:.75rem; font-weight:500;">
                            <?= $e($m['label'] ?? $m['name']) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $active = $userModules[$u['id']] ?? [];
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle avatar-sm">
                                    <?= strtoupper(substr($u['first_name'] ?? $u['email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="small fw-medium"><?= $e($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                    <div style="font-size:.7rem;" class="text-muted"><?= $e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($modules as $m):
                            $isActive = in_array($m['name'], $active);
                            $newStatus = $isActive ? 'inactive' : 'active';
                        ?>
                        <td class="text-center">
                            <form method="POST" action="<?= APP_URL ?>/admin/modules/assign" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                                <input type="hidden" name="status" value="<?= $newStatus ?>">
                                <button type="submit"
                                    class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>"
                                    title="<?= $isActive ? 'Deaktivovat' : 'Aktivovat' ?>"
                                    style="width:36px; height:30px; padding:0;">
                                    <i class="bi bi-<?= $isActive ? 'check-lg' : 'x-lg' ?>"
                                       style="font-size:.8rem;"></i>
                                </button>
                            </form>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
