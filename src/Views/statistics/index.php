<?php $pageTitle = 'Statistiky'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $fmt = fn($n) => number_format((float)$n, 0, ',', ' '); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2"></i>Statistiky</h4>
</div>

<!-- Přehledové karty -->
<div class="row g-3 mb-4">
    <?php $cards = [
        ['icon'=>'box',         'label'=>'Produkty',      'value'=>$counts['products'],        'color'=>'primary'],
        ['icon'=>'question-circle','label'=>'FAQ',         'value'=>$counts['faqs'],            'color'=>'info'],
        ['icon'=>'geo-alt',     'label'=>'Pobočky',       'value'=>$counts['branches'],        'color'=>'success'],
        ['icon'=>'calendar-event','label'=>'Akce',        'value'=>$counts['events'],          'color'=>'warning'],
        ['icon'=>'camera',      'label'=>'Recenze',       'value'=>$counts['reviews'] ?? 0,    'color'=>'danger',
         'sub' => ($counts['reviews_pending'] ?? 0) > 0 ? ($counts['reviews_pending'] . ' čeká') : null],
        ['icon'=>'broadcast',   'label'=>'Webhooky',      'value'=>$counts['webhooks'],        'color'=>'secondary'],
        ['icon'=>'key',         'label'=>'API tokeny',    'value'=>$counts['api_tokens'],      'color'=>'secondary'],
    ]; ?>
    <?php foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card border-0 text-center h-100">
            <div class="card-body py-3">
                <i class="bi bi-<?= $c['icon'] ?> fs-3 text-<?= $c['color'] ?> mb-2 d-block"></i>
                <div class="fs-4 fw-bold"><?= $fmt($c['value']) ?></div>
                <div class="text-muted small"><?= $c['label'] ?></div>
                <?php if (!empty($c['sub'])): ?>
                <div class="badge bg-warning text-dark mt-1" style="font-size:.65rem;"><?= htmlspecialchars($c['sub']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">

    <!-- Produkty po kategorii -->
    <?php if (!empty($byCategory)): ?>
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Produkty dle kategorie (top 10)</h6></div>
            <div class="card-body">
                <?php
                $maxCat = max(array_column($byCategory, 'cnt'));
                foreach ($byCategory as $row):
                    $pct = $maxCat > 0 ? round($row['cnt'] / $maxCat * 100) : 0;
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-truncate me-2" style="max-width:200px;"
                              title="<?= $e($row['category']) ?>"><?= $e($row['category']) ?></span>
                        <strong><?= $fmt($row['cnt']) ?></strong>
                    </div>
                    <div class="progress" style="height:5px;">
                        <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Produkty po značce -->
    <?php if (!empty($byBrand)): ?>
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Produkty dle značky (top 10)</h6></div>
            <div class="card-body">
                <?php
                $maxBr = max(array_column($byBrand, 'cnt'));
                foreach ($byBrand as $row):
                    $pct = $maxBr > 0 ? round($row['cnt'] / $maxBr * 100) : 0;
                ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= $e($row['brand']) ?></span>
                        <strong><?= $fmt($row['cnt']) ?></strong>
                    </div>
                    <div class="progress" style="height:5px;">
                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Cenové statistiky + importy -->
<div class="row g-4">

    <?php if ($priceStats && $priceStats['with_price'] > 0): ?>
    <div class="col-12 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Cenové statistiky</h6></div>
            <div class="card-body p-0">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted ps-3">Nejnižší cena</td>
                        <td class="fw-semibold text-success"><?= $fmt($priceStats['price_min']) ?> Kč</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Průměrná cena</td>
                        <td class="fw-semibold"><?= $fmt($priceStats['price_avg']) ?> Kč</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">Nejvyšší cena</td>
                        <td class="fw-semibold text-danger"><?= $fmt($priceStats['price_max']) ?> Kč</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-3">S cenou</td>
                        <td><?= $fmt($priceStats['with_price']) ?> ks</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Poslední import -->
    <div class="col-12 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Poslední XML import</h6></div>
            <div class="card-body">
                <?php if (!$lastImport): ?>
                <p class="text-muted small">Zatím žádný import.</p>
                <a href="<?= APP_URL ?>/xml" class="btn btn-sm btn-primary">Spustit import</a>
                <?php else:
                    $colors = ['completed'=>'success','failed'=>'danger','processing'=>'info','pending'=>'warning'];
                ?>
                <div class="mb-3">
                    <span class="badge bg-<?= $colors[$lastImport['status']] ?? 'secondary' ?> mb-2">
                        <?= $e($lastImport['status']) ?>
                    </span>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted ps-0">Nové</td>
                            <td><?= $fmt($lastImport['products_imported']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Aktualizované</td>
                            <td><?= $fmt($lastImport['products_updated']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted ps-0">Datum</td>
                            <td class="small"><?= date('d.m.Y H:i', strtotime($lastImport['created_at'])) ?></td>
                        </tr>
                    </table>
                </div>
                <a href="<?= APP_URL ?>/xml" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i>Nový import
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Historie importů 30 dní -->
    <?php if (!empty($importHistory)): ?>
    <div class="col-12 col-md-4">
        <div class="card border-0 h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Importy (posledních 30 dní)</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr>
                        <th class="ps-3">Den</th><th>Stav</th><th class="text-end pe-3">Počet</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($importHistory as $row):
                        $colors = ['completed'=>'success','failed'=>'danger','processing'=>'info'];
                    ?>
                    <tr>
                        <td class="ps-3 small text-muted"><?= date('d.m.', strtotime($row['day'])) ?></td>
                        <td>
                            <span class="badge bg-<?= $colors[$row['status']] ?? 'secondary' ?> bg-opacity-20 text-<?= $colors[$row['status']] ?? 'secondary' ?> small">
                                <?= $e($row['status']) ?>
                            </span>
                        </td>
                        <td class="text-end pe-3"><?= $row['cnt'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if ($counts['products'] === 0): ?>
<div class="card border-0 border-primary border-opacity-25 mt-4">
    <div class="card-body text-center py-4">
        <i class="bi bi-cloud-download fs-2 text-primary mb-3 d-block"></i>
        <p class="mb-2">Zatím žádná data. Začněte importem XML feedu.</p>
        <a href="<?= APP_URL ?>/xml" class="btn btn-primary btn-sm">
            <i class="bi bi-file-earmark-arrow-down me-1"></i>Spustit XML import
        </a>
    </div>
</div>
<?php endif; ?>
