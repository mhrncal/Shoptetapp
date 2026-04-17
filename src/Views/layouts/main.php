<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
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
                <div class="fw-medium text-truncate" style="font-size:.875rem;color:hsl(var(--foreground))">
                    <?= $e(trim($currentUser['first_name'] . ' ' . $currentUser['last_name'])) ?>
                </div>
                <div style="font-size:.7rem;color:hsl(var(--muted-foreground));" class="text-truncate">
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
            <?php if ($hasModule('xml_import')): ?>
            <li>
                <a href="<?= APP_URL ?>/products" class="nav-link <?= $active('/products') ?>">
                    <i class="bi bi-box"></i> Produkty
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/feeds" class="nav-link <?= $active('/feeds') ?>">
                    <i class="bi bi-cloud-download"></i> Importy produktů
                </a>
            </li>
            <?php endif; ?>

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
            <li>
                <a href="<?= APP_URL ?>/watermark/settings" class="nav-link <?= $active('/watermark') ?>">
                    <i class="bi bi-droplet"></i> Watermark
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('scraped_reviews')): ?>
            <li>
                <a href="<?= APP_URL ?>/scraped-reviews" class="nav-link <?= $active('/scraped-reviews') ?>">
                    <i class="bi bi-search"></i> Scrapované recenze
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
                <a href="<?= APP_URL ?>/product-videos" class="nav-link <?= $active('/product-videos') ?>">
                    <i class="bi bi-play-circle"></i> Videa k produktům
                </a>
            </li>
            <?php endif; ?>

            <?php if ($hasModule('product_tabs')): ?>
            <li>
                <a href="<?= APP_URL ?>/product-tabs" class="nav-link <?= $active('/product-tabs') ?>">
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
                    <i class="bi bi-shield-check"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/users" class="nav-link <?= $active('/admin/users') ?>">
                    <i class="bi bi-people"></i> Uživatelé
                </a>
            </li>
            <li>
                <a href="<?= APP_URL ?>/admin/users/create" class="nav-link <?= $active('/admin/users/create') ?>">
                    <i class="bi bi-person-plus"></i> Přidat uživatele
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
    <div id="page-content" style="overflow-x:hidden;max-width:100%;">

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
                <span class="badge" style="background:hsl(var(--primary)/.1);color:hsl(var(--primary));font-size:.75rem;">
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
        <main class="p-3 p-md-4 animate-fade-in">
            <?= $content ?>
        </main>
    </div>
</div>

<!-- Sidebar overlay (mobile) -->
<div id="sidebar-overlay"></div>

<!-- Mobile Bottom Navigation -->
<nav id="mobile-nav">
    <a href="<?= APP_URL ?>/dashboard"
       class="mobile-nav-item <?= (str_starts_with($currentPath, '/dashboard') || $currentPath === '/') ? 'active' : '' ?>">
        <i class="bi bi-grid-1x2<?= (str_starts_with($currentPath, '/dashboard') || $currentPath === '/') ? '-fill' : '' ?>"></i>
        <span>Přehled</span>
    </a>
    <?php if ($hasModule('xml_import')): ?>
    <a href="<?= APP_URL ?>/products"
       class="mobile-nav-item <?= str_starts_with($currentPath, '/products') ? 'active' : '' ?>">
        <i class="bi bi-box<?= str_starts_with($currentPath, '/products') ? '-fill' : '' ?>"></i>
        <span>Produkty</span>
    </a>
    <?php endif; ?>
    <?php if (in_array('reviews', $activeModules ?? []) || ($currentUser['role'] === 'superadmin')): ?>
    <a href="<?= APP_URL ?>/reviews"
       class="mobile-nav-item <?= str_starts_with($currentPath, '/reviews') ? 'active' : '' ?>">
        <i class="bi bi-camera<?= str_starts_with($currentPath, '/reviews') ? '-fill' : '' ?>"></i>
        <span>Recenze</span>
        <?php if (!empty($counts['reviews_pending']) && $counts['reviews_pending'] > 0): ?>
        <span class="mobile-nav-badge"><?= $counts['reviews_pending'] > 9 ? '9+' : $counts['reviews_pending'] ?></span>
        <?php endif; ?>
    </a>
    <?php else: ?>
    <a href="<?= APP_URL ?>/settings"
       class="mobile-nav-item <?= str_starts_with($currentPath, '/settings') ? 'active' : '' ?>">
        <i class="bi bi-gear<?= str_starts_with($currentPath, '/settings') ? '-fill' : '' ?>"></i>
        <span>Nastavení</span>
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/profile"
       class="mobile-nav-item <?= str_starts_with($currentPath, '/profile') ? 'active' : '' ?>">
        <i class="bi bi-person<?= str_starts_with($currentPath, '/profile') ? '-fill' : '' ?>"></i>
        <span>Profil</span>
    </a>
    <button type="button" class="mobile-nav-item" id="mobileMenuToggle" style="background:none;border:none;cursor:pointer;">
        <i class="bi bi-list"></i>
        <span>Více</span>
    </button>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= ASSETS_URL ?>/assets/js/app.js"></script>
</body>
</html>
