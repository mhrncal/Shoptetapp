<?php $pageTitle = 'Správa uživatelů'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Správa uživatelů</h4>
    <a href="<?= APP_URL ?>/admin/users/create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Přidat uživatele
    </a>
</div>

<!-- Filtry -->
<div class="card border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label small text-muted mb-1">Hledat</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Jméno, e-mail, shop..."
                           value="<?= $e($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Stav</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Všechny</option>
                    <option value="pending"  <?= $statusFilter === 'pending'  ? 'selected' : '' ?>>Čeká na schválení</option>
                    <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Schváleni</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Zamítnuti</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filtrovat</button>
            </div>
            <div class="col-6 col-md-2">
                <a href="<?= APP_URL ?>/admin/users" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabulka -->
<div class="card border-0">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="small text-muted">Celkem: <strong><?= $total ?></strong></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Uživatel</th>
                        <th>Shop</th>
                        <th>Role</th>
                        <th>Stav</th>
                        <th>Registrace</th>
                        <th>Poslední login</th>
                        <th class="text-end">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle avatar-sm">
                                    <?= strtoupper(substr($u['first_name'] ?? $u['email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold small">
                                        <?= $e($u['first_name'] . ' ' . $u['last_name']) ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.75rem;"><?= $e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted"><?= $e($u['shop_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($u['role'] === 'superadmin'): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-shield-fill me-1"></i>Superadmin</span>
                            <?php else: ?>
                            <span class="badge bg-secondary bg-opacity-25 text-body">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $bs     = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'];
                            $labels = ['approved' => 'Schválen', 'pending' => 'Čeká', 'rejected' => 'Zamítnut'];
                            ?>
                            <span class="badge bg-<?= $bs[$u['status']] ?? 'secondary' ?>">
                                <?= $labels[$u['status']] ?? $e($u['status']) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                        <td class="small text-muted">
                            <?= $u['last_login_at'] ? date('d.m.Y H:i', strtotime($u['last_login_at'])) : '—' ?>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= APP_URL ?>/admin/users/<?= $u['id'] ?>" class="btn btn-outline-secondary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($u['status'] === 'pending'): ?>
                                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $u['id'] ?>/approve" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <button type="submit" class="btn btn-success" title="Schválit">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $u['id'] ?>/reject" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <button type="submit" class="btn btn-warning" title="Zamítnout">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($u['role'] !== 'superadmin'): ?>
                                <form method="POST" action="<?= APP_URL ?>/admin/users/<?= $u['id'] ?>/impersonate" class="d-inline">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <button type="submit" class="btn btn-outline-info" title="Impersonovat">
                                        <i class="bi bi-person-fill-gear"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people fs-2 d-block mb-2"></i>
                            Žádní uživatelé nenalezeni
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stránkování -->
    <?php if ($total > $perPage): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Zobrazeno <?= min($page * $perPage, $total) ?> z <?= $total ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php $pages = ceil($total / $perPage); ?>
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                        <?= $p ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
