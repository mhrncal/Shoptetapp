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

<?php
$e      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$active = fn($path) => str_starts_with($currentPath, $path) ? 'active' : '';
?>

<div id="wrapper">

    <!-- ── Admin Sidebar ────────────────────────────────── -->
    <nav id="sidebar" class="admin-sidebar">

        <!-- Logo -->
        <div class="sidebar-brand">
            <a href="<?= APP_URL ?>/admin" class="text-decoration-none d-flex align-items-center gap-2">
                <img src="<?= APP_URL ?>/assets/shopcode-logo.png" alt="ShopCode">
                <div class="sidebar-brand-text">
                    <h2>Superadmin</h2>
                    <p>ShopCode systém</p>
                </div>
            </a>
        </div>

        <!-- User info -->
        <div class="sidebar-user">
            <div class="avatar-circle">
                <?= strtoupper(substr($currentUser['first_name'] ?? $currentUser['email'], 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-medium text-truncate" style="font-size:.875rem;color:var(--sc-fg)">
                    <?= $e(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])) ?>
                </div>
                <div style="font-size:.7rem;" class="text-truncate">
                    <span class="badge" style="background:var(--sc-primary);font-size:.65rem;">superadmin</span>
                </div>
            </div>
        </div>

        <!-- Navigace -->
        <ul class="sidebar-nav">
            <li class="sidebar-section-title">Uživatelé</li>
            <li>
                <a href="<?= APP_URL ?>/admin" class="nav-link <?= $currentPath === '/admin' ? 'active' : '' ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/users" class="nav-link <?= $active('/admin/users') ?>">
                    <i class="bi bi-people"></i> Uživatelé
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/modules" class="nav-link <?= $active('/admin/modules') ?>">
                    <i class="bi bi-puzzle"></i> Moduly
                </a>
            </li>

            <li class="sidebar-section-title">Systém</li>
            <li>
                <a href="<?= APP_URL ?>/admin/xml-queue" class="nav-link <?= $active('/admin/xml-queue') ?>">
                    <i class="bi bi-list-task"></i> XML fronta
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/system" class="nav-link <?= $active('/admin/system') ?>">
                    <i class="bi bi-server"></i> Správa systému
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/audit-log" class="nav-link <?= $active('/admin/audit-log') ?>">
                    <i class="bi bi-journal-text"></i> Audit log
                </a>
            </li>
        </ul>

        <div class="sidebar-bottom">
            <a href="<?= APP_URL ?>/dashboard" class="nav-link">
                <i class="bi bi-arrow-left-circle"></i> Zpět do aplikace
            </a>
            <a href="<?= APP_URL ?>/logout" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Odhlásit se
            </a>
        </div>
    </nav>

    <!-- ── Hlavní obsah ──────────────────────────────────── -->
    <div id="page-content">

        <header class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="search-box d-none d-md-flex">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Hledat v adminu...">
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="badge" style="background:var(--sc-primary);font-size:.75rem;">
                    <i class="bi bi-shield-fill me-1"></i>Admin panel
                </span>
            </div>
        </header>

        <?php if (!empty($flash)): ?>
        <div class="px-4 pt-3">
            <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show animate-fade-in" role="alert">
                <?= $f['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <main class="p-4 animate-fade-in">
            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
