<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= \ShopCode\Core\View::e($pageTitle ?? 'Panel') ?> — ShopCode</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $active = fn($path) => str_starts_with($currentPath, $path) ? 'active' : ''; ?>

<div class="d-flex" id="wrapper">

    <!-- Admin Sidebar -->
    <nav id="sidebar" class="d-flex flex-column admin-sidebar">
        <div class="sidebar-brand">
            <a href="<?= APP_URL ?>/admin" class="text-decoration-none">
                <i class="bi bi-shield-check text-warning me-2"></i>
                <span class="fw-bold text-white fs-5">Admin</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle bg-warning text-dark">
                    <?= strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div>
                    <div class="text-white fw-semibold small"><?= $e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
                    <div class="text-secondary" style="font-size:.7rem;">Superadmin</div>
                </div>
            </div>
        </div>

        <ul class="sidebar-nav nav flex-column flex-grow-1">
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin" class="nav-link <?= $currentPath === '/admin' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-section-title">Uživatelé</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin/users" class="nav-link <?= $active('/admin/users') ?>">
                    <i class="bi bi-people"></i> Správa uživatelů
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin/modules" class="nav-link <?= $active('/admin/modules') ?>">
                    <i class="bi bi-puzzle"></i> Správa modulů
                </a>
            </li>
            <li class="sidebar-section-title">Systém</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin/xml-queue" class="nav-link <?= $active('/admin/xml-queue') ?>">
                    <i class="bi bi-list-task"></i> XML fronta
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin/system" class="nav-link <?= $active('/admin/system') ?>">
                    <i class="bi bi-cpu"></i> Systém
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin/audit-log" class="nav-link <?= $active('/admin/audit-log') ?>">
                    <i class="bi bi-journal-text"></i> Audit log
                </a>
            </li>
        </ul>

        <div class="sidebar-bottom">
            <a href="<?= APP_URL ?>/dashboard" class="nav-link">
                <i class="bi bi-arrow-left-circle"></i> Zpět na app
            </a>
            <a href="<?= APP_URL ?>/logout" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Odhlásit
            </a>
        </div>
    </nav>

    <!-- Obsah -->
    <div id="page-content">
        <div class="topbar d-flex align-items-center px-4">
            <span class="badge bg-warning text-dark me-3">
                <i class="bi bi-shield-fill me-1"></i>Admin panel
            </span>
            <?php if (isset($pageTitle)): ?>
            <h6 class="mb-0 text-muted"><?= $e($pageTitle) ?></h6>
            <?php endif; ?>
        </div>

        <div class="px-4 pt-3">
            <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show" role="alert">
                <?= $f['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
        </div>

        <main class="p-4">
            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
