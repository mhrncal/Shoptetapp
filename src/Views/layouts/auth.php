<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \ShopCode\Core\View::e($pageTitle ?? 'ShopCode') ?> — ShopCode</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/assets/css/app.css">
</head>
<body>

<div class="auth-bg">
    <div class="auth-card animate-fade-in">

        <!-- CardHeader — logo, title, description -->
        <div class="auth-card-header">
            <div class="auth-logo">
                <img src="<?= ASSETS_URL ?>/assets/shopcode-logo.png" alt="ShopCode">
            </div>
        </div>

        <!-- CardContent -->
        <div class="auth-card-body">
            <?php foreach ($flash as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show mb-4" role="alert">
                <?= $f['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>

            <?= $content ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
