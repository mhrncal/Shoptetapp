<?php $pageTitle = $product['name']; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex align-items-center mb-4">
    <a href="<?= APP_URL ?>/products" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0"><?= $e($product['name']) ?></h4>
</div>

<?php
$images     = $product['images']     ? json_decode($product['images'],     true) : [];
$parameters = $product['parameters'] ? json_decode($product['parameters'], true) : [];
?>

<div class="row g-4">
    <!-- Levý sloupec -->
    <div class="col-12 col-lg-4">

        <!-- Obrázky -->
        <?php if (!empty($images)): ?>
        <div class="card border-0 mb-4">
            <div class="card-body p-2">
                <img src="<?= $e($images[0]) ?>" class="img-fluid rounded w-100 mb-2"
                     style="max-height:300px;object-fit:contain;background:rgba(255,255,255,.03);"
                     id="mainImage" alt="<?= $e($product['name']) ?>">
                <?php if (count($images) > 1): ?>
                <div class="d-flex gap-1 flex-wrap">
                    <?php foreach ($images as $i => $img): ?>
                    <img src="<?= $e($img) ?>" alt=""
                         style="width:56px;height:56px;object-fit:cover;border-radius:4px;cursor:pointer;opacity:<?= $i===0?'1':'.6' ?>;"
                         class="thumb-img" onclick="$('#mainImage').attr('src','<?= $e($img) ?>');$('.thumb-img').css('opacity','.6');$(this).css('opacity','1');">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Základní info -->
        <div class="card border-0">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Základní informace</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted w-40 ps-3">Shoptet ID</td>
                        <td class="font-monospace"><?= $e($product['shoptet_id']) ?></td></tr>
                    <tr><td class="text-muted ps-3">Cena</td>
                        <td class="fw-bold">
                            <?= $product['price'] !== null
                                ? number_format((float)$product['price'],2,',',' ') . ' ' . $e($product['currency'])
                                : '—' ?>
                        </td></tr>
                    <tr><td class="text-muted ps-3">Kategorie</td>
                        <td><?= $e($product['category'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted ps-3">Značka</td>
                        <td><?= $e($product['brand'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted ps-3">Dostupnost</td>
                        <td><?= $e($product['availability'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted ps-3">Importováno</td>
                        <td class="small"><?= date('d.m.Y H:i', strtotime($product['created_at'])) ?></td></tr>
                    <tr><td class="text-muted ps-3">Aktualizováno</td>
                        <td class="small"><?= date('d.m.Y H:i', strtotime($product['updated_at'])) ?></td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec -->
    <div class="col-12 col-lg-8">

        <!-- Popis -->
        <?php if ($product['description']): ?>
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Popis</h6></div>
            <div class="card-body">
                <div class="product-description" style="max-height:300px;overflow-y:auto;font-size:.9rem;line-height:1.6;">
                    <?= nl2br($e($product['description'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Parametry -->
        <?php if (!empty($parameters)): ?>
        <div class="card border-0 mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Parametry</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <tbody>
                    <?php foreach ($parameters as $key => $val): ?>
                    <tr>
                        <td class="text-muted small ps-3" style="width:40%;"><?= $e($key) ?></td>
                        <td class="small"><?= $e($val) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Varianty -->
        <?php if (!empty($variants)): ?>
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0 fw-semibold">Varianty</h6>
                <span class="badge bg-secondary"><?= count($variants) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:.85rem;">
                        <thead><tr>
                            <th>Varianta ID</th>
                            <th>Název</th>
                            <th class="text-end">Cena</th>
                            <th class="text-center">Sklad</th>
                            <th>Parametry</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($variants as $v):
                            $vParams = $v['parameters'] ? json_decode($v['parameters'], true) : [];
                        ?>
                        <tr>
                            <td class="font-monospace text-muted small"><?= $e($v['shoptet_variant_id']) ?></td>
                            <td><?= $e($v['name'] ?? '—') ?></td>
                            <td class="text-end">
                                <?= $v['price'] !== null
                                    ? number_format((float)$v['price'],2,',',' ')
                                    : '—' ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $v['stock'] > 0 ? 'success' : 'secondary' ?>">
                                    <?= (int)$v['stock'] ?>
                                </span>
                            </td>
                            <td class="small text-muted">
                                <?= $e(implode(', ', array_map(fn($k,$v) => "{$k}: {$v}", array_keys($vParams), $vParams))) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
