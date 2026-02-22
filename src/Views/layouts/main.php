<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \ShopCode\Core\View::e($pageTitle ?? 'Dashboard') ?> — ShopCode</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body>

<?php
$e           = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$active      = fn($path) => str_starts_with($currentPath, $path) ? 'active' : '';
$hasModule   = fn($name) => in_array($name, $activeModules ?? []) || ($currentUser['role'] === 'superadmin');
$impersonating = \ShopCode\Core\Session::get('impersonating_as');
?>

<?php if ($impersonating): ?>
<div class="impersonation-bar text-center py-2 px-3">
    <i class="bi bi-person-fill-gear me-2"></i>
    Zobrazujete aplikaci jako <strong><?= $e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></strong>
    (<?= $e($currentUser['email']) ?>)
    <form method="POST" action="<?= APP_URL ?>/admin/impersonate/stop" class="d-inline ms-3">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button type="submit" class="btn btn-sm btn-warning">
            <i class="bi bi-box-arrow-left me-1"></i>Ukončit impersonaci
        </button>
    </form>
</div>
<?php endif; ?>

<div class="d-flex" id="wrapper">

    <!-- Sidebar -->
    <nav id="sidebar" class="d-flex flex-column">
        <div class="sidebar-brand">
            <a href="<?= APP_URL ?>/dashboard" class="text-decoration-none">
                <i class="bi bi-box-seam text-primary me-2"></i>
                <span class="fw-bold text-white fs-5">ShopCode</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle">
                    <?= strtoupper(substr($currentUser['first_name'] ?? $currentUser['email'], 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <div class="text-white fw-semibold text-truncate small">
                        <?= $e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                    </div>
                    <div class="text-secondary" style="font-size:.7rem;">
                        <?= $e($currentUser['shop_name'] ?? $currentUser['email']) ?>
                    </div>
                </div>
            </div>
        </div>

        <ul class="sidebar-nav nav flex-column flex-grow-1">
            <li class="nav-item">
                <a href="<?= APP_URL ?>/dashboard" class="nav-link <?= $active('/dashboard') ?: ($currentPath === '/' ? 'active' : '') ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/products" class="nav-link <?= $active('/products') ?>">
                    <i class="bi bi-box"></i> Produkty
                </a>
            </li>

            <?php if ($hasModule('xml_import')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/xml" class="nav-link <?= $active('/xml') ?>">
                    <i class="bi bi-file-earmark-arrow-down"></i> XML Import
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('faq')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/faq" class="nav-link <?= $active('/faq') ?>">
                    <i class="bi bi-question-circle"></i> FAQ
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('branches')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/branches" class="nav-link <?= $active('/branches') ?>">
                    <i class="bi bi-geo-alt"></i> Pobočky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('event_calendar')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/events" class="nav-link <?= $active('/events') ?>">
                    <i class="bi bi-calendar-event"></i> Kalendář akcí
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('reviews')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/reviews" class="nav-link <?= $active('/reviews') ?>">
                    <i class="bi bi-camera"></i> Fotorecenze
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('api_access')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/api-tokens" class="nav-link <?= $active('/api-tokens') ?>">
                    <i class="bi bi-key"></i> API tokeny
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('webhooks')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/webhooks" class="nav-link <?= $active('/webhooks') ?>">
                    <i class="bi bi-broadcast"></i> Webhooky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('statistics')): ?>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/statistics" class="nav-link <?= $active('/statistics') ?>">
                    <i class="bi bi-bar-chart-line"></i> Statistiky
                </a>
            </li>
            <?php endif; ?>

            <!-- Admin sekce -->
            <?php if ($currentUser['role'] === 'superadmin'): ?>
            <li class="sidebar-section-title">Administrace</li>
            <li class="nav-item">
                <a href="<?= APP_URL ?>/admin" class="nav-link <?= $active('/admin') ?>">
                    <i class="bi bi-shield-check"></i> Admin panel
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-bottom">
            <a href="<?= APP_URL ?>/profile" class="nav-link <?= $active('/profile') ?>">
                <i class="bi bi-person-circle"></i> Profil
            </a>
            <?php if ($hasModule('settings')): ?>
            <a href="<?= APP_URL ?>/settings" class="nav-link <?= $active('/settings') ?>">
                <i class="bi bi-gear"></i> Nastavení
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/logout" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i> Odhlásit
            </a>
        </div>
    </nav>

    <!-- Hlavní obsah -->
    <div id="page-content">
        <!-- Topbar -->
        <div class="topbar d-flex align-items-center justify-content-between px-4">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if ($currentUser['role'] === 'superadmin'): ?>
                <span class="badge bg-warning text-dark">
                    <i class="bi bi-shield-fill me-1"></i>Superadmin
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Flash zprávy -->
        <div class="px-4 pt-3">
            <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show" role="alert">
                <?= $f['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Obsah stránky -->
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
