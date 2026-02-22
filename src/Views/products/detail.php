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

<?php if (!empty($tabs) || !empty($videos)): ?>
<hr class="border-secondary my-4">
<div class="row g-4">

    <!-- Product Tabs -->
    <?php if (!empty($tabs) || true): ?>
    <div class="col-12" id="tabs">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-layout-tabs me-2 text-muted"></i>Záložky produktu</h6>
                <?php if (in_array('product_tabs', $activeModules ?? [])): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTabModal">
                    <i class="bi bi-plus me-1"></i>Přidat záložku
                </button>
                <?php endif; ?>
            </div>
            <?php if (empty($tabs)): ?>
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-layout-tabs fs-2 d-block mb-2"></i>
                <p class="small mb-0">Žádné záložky. Přidejte první záložku k produktu.</p>
            </div>
            <?php else: ?>
            <div class="card-body p-0">
                <div class="nav nav-tabs border-secondary px-3 pt-2 gap-1" id="productTabNav">
                    <?php foreach ($tabs as $i => $tab): ?>
                    <button class="nav-link <?= $i === 0 ? 'active' : '' ?> <?= !$tab['is_active'] ? 'text-muted' : '' ?>"
                            data-bs-toggle="tab" data-bs-target="#ptab<?= $tab['id'] ?>">
                        <?= $e($tab['title']) ?>
                        <?php if (!$tab['is_active']): ?>
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem;">skrytá</span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="tab-content p-4">
                    <?php foreach ($tabs as $i => $tab): ?>
                    <div class="tab-pane fade <?= $i === 0 ? 'show active' : '' ?>" id="ptab<?= $tab['id'] ?>">
                        <div class="product-tab-content mb-3" style="line-height:1.7;">
                            <?= nl2br($e($tab['content'])) ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="editTab(<?= htmlspecialchars(json_encode($tab), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil me-1"></i>Upravit
                            </button>
                            <form method="POST" action="<?= APP_URL ?>/products/tabs/<?= $tab['id'] ?>"
                                  onsubmit="return confirm('Smazat záložku?')">
                                <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Product Videos -->
    <div class="col-12" id="videos">
        <div class="card border-0">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-play-circle me-2 text-muted"></i>Videa</h6>
                <?php if (in_array('product_videos', $activeModules ?? [])): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVideoModal">
                    <i class="bi bi-plus me-1"></i>Přidat video
                </button>
                <?php endif; ?>
            </div>
            <?php if (empty($videos)): ?>
            <div class="card-body text-center py-4 text-muted">
                <i class="bi bi-play-circle fs-2 d-block mb-2"></i>
                <p class="small mb-0">Žádná videa. Přidejte YouTube nebo Vimeo odkaz.</p>
            </div>
            <?php else: ?>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($videos as $vid):
                        $embedUrl = \ShopCode\Models\ProductVideo::embedUrl($vid['url']);
                        $thumb    = \ShopCode\Models\ProductVideo::thumbnail($vid['url']);
                    ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card border-secondary bg-transparent">
                            <?php if ($embedUrl): ?>
                            <div class="ratio ratio-16x9">
                                <iframe src="<?= $e($embedUrl) ?>?rel=0"
                                        allowfullscreen loading="lazy"
                                        style="border-radius:8px 8px 0 0;border:0;"></iframe>
                            </div>
                            <?php elseif ($thumb): ?>
                            <img src="<?= $e($thumb) ?>" class="card-img-top" style="border-radius:8px 8px 0 0;" alt="">
                            <?php endif; ?>
                            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                                <span class="small fw-semibold text-truncate me-2">
                                    <?= $e($vid['title'] ?: 'Video') ?>
                                </span>
                                <form method="POST" action="<?= APP_URL ?>/products/videos/<?= $vid['id'] ?>"
                                      onsubmit="return confirm('Smazat video?')">
                                    <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php endif; ?>

<!-- Modal: Přidat záložku -->
<div class="modal fade" id="addTabModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Přidat záložku</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/products/<?= $product['id'] ?>/tabs">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Název záložky <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="Technické parametry, Doprava...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Obsah <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="8" required
                                  placeholder="Obsah záložky (podporuje HTML)..."></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Pořadí</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-6 d-flex align-items-end pb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                                <label class="form-check-label">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat záložku</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Upravit záložku -->
<div class="modal fade" id="editTabModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Upravit záložku</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editTabForm" action="">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body" id="editTabBody">
                    <div class="mb-3">
                        <label class="form-label">Název záložky</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Obsah</label>
                        <textarea name="content" class="form-control" rows="8"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Pořadí</label>
                            <input type="number" name="sort_order" class="form-control" min="0">
                        </div>
                        <div class="col-6 d-flex align-items-end pb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1">
                                <label class="form-check-label">Aktivní</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Uložit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Přidat video -->
<div class="modal fade" id="addVideoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Přidat video</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/products/<?= $product['id'] ?>/videos">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">YouTube nebo Vimeo URL <span class="text-danger">*</span></label>
                        <input type="url" name="url" class="form-control" required
                               placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Název videa <span class="text-muted small">(nepovinné)</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Ukázka produktu">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pořadí</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat video</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTab(tab) {
    var form = document.getElementById('editTabForm');
    form.action = '<?= APP_URL ?>/products/tabs/' + tab.id;
    var body = document.getElementById('editTabBody');
    body.querySelector('[name="title"]').value      = tab.title      || '';
    body.querySelector('[name="content"]').value    = tab.content    || '';
    body.querySelector('[name="sort_order"]').value = tab.sort_order || 0;
    body.querySelector('[name="is_active"]').checked = tab.is_active == 1;
    new bootstrap.Modal(document.getElementById('editTabModal')).show();
}
</script>
