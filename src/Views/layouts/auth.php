<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \ShopCode\Core\View::e($pageTitle ?? 'ShopCode') ?> — ShopCode</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-dark min-vh-100 d-flex align-items-center justify-content-center">

<div class="container" style="max-width: 460px;">
    <div class="text-center mb-4">
        <h1 class="fw-bold text-white fs-2">
            <i class="bi bi-box-seam text-primary me-2"></i>ShopCode
        </h1>
        <p class="text-secondary small">Správa Shoptet e-shopů</p>
    </div>

    <?php foreach ($flash as $f): ?>
        <div class="alert alert-<?= $f['type'] === 'error' ? 'danger' : $f['type'] ?> alert-dismissible fade show" role="alert">
            <?= $f['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <?= $content ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</body>
</html>
