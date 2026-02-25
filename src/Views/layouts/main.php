<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \ShopCode\Core\View::e($pageTitle ?? 'Dashboard') ?> — ShopCode</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/assets/css/app.css">
</head>
<body>

<?php
$e           = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$active      = fn($path) => str_starts_with($currentPath, $path) ? 'active' : '';
$hasModule   = fn($name) => in_array($name, $activeModules ?? []) || ($currentUser['role'] === 'superadmin');
$impersonating = \ShopCode\Core\Session::get('impersonating_as');
?>

<?php if ($impersonating): ?>
<div class="impersonation-bar d-flex align-items-center justify-content-center px-3 py-2 gap-3">
    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
    <span><strong>IMPERSONATION MÓD AKTIVNÍ</strong> — Přihlášen jako:
        <strong><?= $e($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></strong>
        (<?= $e($currentUser['email']) ?>)
    </span>
    <form method="POST" action="<?= APP_URL ?>/admin/impersonate/stop" class="d-inline">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button type="submit" class="btn btn-sm btn-danger">
            <i class="bi bi-box-arrow-left me-1"></i>Vrátit se zpět
        </button>
    </form>
</div>
<?php endif; ?>

<div id="wrapper">

    <!-- ── Sidebar ──────────────────────────────────────── -->
    <nav id="sidebar">

        <!-- Logo -->
        <div class="sidebar-brand">
            <a href="<?= APP_URL ?>/dashboard" class="text-decoration-none d-flex align-items-center gap-2">
                <img src="<?= ASSETS_URL ?>/assets/shopcode-logo.png" alt="ShopCode">
                <div class="sidebar-brand-text">
                    <h2>Admin</h2>
                    <p>Shoptet systém</p>
                </div>
            </a>
        </div>

        <!-- Uživatel -->
        <div class="sidebar-user">
            <div class="avatar-circle">
                <?= strtoupper(substr($currentUser['first_name'] ?? $currentUser['email'], 0, 1)) ?>
            </div>
            <div class="overflow-hidden">
                <div class="fw-medium text-truncate" style="font-size:.875rem;color:var(--sc-fg)">
                    <?= $e(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])) ?>
                </div>
                <div style="font-size:.7rem;color:var(--sc-muted-fg);" class="text-truncate">
                    <?= $e($currentUser['email']) ?>
                </div>
            </div>
        </div>

        <!-- Navigace -->
        <ul class="sidebar-nav">
            <li>
                <a href="<?= APP_URL ?>/dashboard"
                   class="nav-link <?= $active('/dashboard') ?: ($currentPath === '/' ? 'active' : '') ?>">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/products" class="nav-link <?= $active('/products') ?>">
                    <i class="bi bi-box"></i> Produkty
                </a>
            </li>

            <?php if ($hasModule('faq')): ?>
            <li>
                <a href="<?= APP_URL ?>/faq" class="nav-link <?= $active('/faq') ?>">
                    <i class="bi bi-question-circle"></i> FAQ
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('reviews')): ?>
            <li>
                <a href="<?= APP_URL ?>/reviews" class="nav-link <?= $active('/reviews') ?>">
                    <i class="bi bi-star"></i> Fotorecenze
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('branches')): ?>
            <li>
                <a href="<?= APP_URL ?>/branches" class="nav-link <?= $active('/branches') ?>">
                    <i class="bi bi-geo-alt"></i> Pobočky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('product_videos')): ?>
            <li>
                <a href="<?= APP_URL ?>/products" class="nav-link">
                    <i class="bi bi-play-circle"></i> Videa k produktům
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('product_tabs')): ?>
            <li>
                <a href="<?= APP_URL ?>/products" class="nav-link">
                    <i class="bi bi-bookmark"></i> Vlastní záložky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('event_calendar')): ?>
            <li>
                <a href="<?= APP_URL ?>/events" class="nav-link <?= $active('/events') ?>">
                    <i class="bi bi-calendar-event"></i> Kalendář akcí
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('xml_import')): ?>
            <li>
                <a href="<?= APP_URL ?>/xml" class="nav-link <?= $active('/xml') ?>">
                    <i class="bi bi-file-earmark-arrow-down"></i> XML Import
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('api_access')): ?>
            <li>
                <a href="<?= APP_URL ?>/api-tokens" class="nav-link <?= $active('/api-tokens') ?>">
                    <i class="bi bi-key"></i> API tokeny
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('webhooks')): ?>
            <li>
                <a href="<?= APP_URL ?>/webhooks" class="nav-link <?= $active('/webhooks') ?>">
                    <i class="bi bi-broadcast"></i> Webhooky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('statistics')): ?>
            <li>
                <a href="<?= APP_URL ?>/statistics" class="nav-link <?= $active('/statistics') ?>">
                    <i class="bi bi-bar-chart-line"></i> Statistiky
                </a>
            </li>
            <?php endif; ?>

            <?php if ($currentUser['role'] === 'superadmin'): ?>
            <li class="sidebar-section-title">Administrace</li>
            <li>
                <a href="<?= APP_URL ?>/admin" class="nav-link <?= $active('/admin') ?>">
                    <i class="bi bi-shield-check"></i> Admin panel
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Spodní část -->
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
                <i class="bi bi-box-arrow-right"></i> Odhlásit se
            </a>
        </div>
    </nav>

    <!-- ── Hlavní obsah ──────────────────────────────────── -->
    <div id="page-content">

        <!-- Topbar -->
        <header class="topbar">
            <!-- Mobile toggle -->
            <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>

            <!-- Search box -->
            <div class="search-box d-none d-md-flex">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Hledat v systému...">
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if ($currentUser['role'] === 'superadmin'): ?>
                <span class="badge" style="background:var(--sc-primary-light);color:var(--sc-primary);font-size:.75rem;">
                    <i class="bi bi-shield-fill me-1"></i>Superadmin
                </span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Flash zprávy -->
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

        <!-- Obsah stránky -->
        <main class="p-4 animate-fade-in">
            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= ASSETS_URL ?>/assets/js/app.js"></script>
</body>
</html>
