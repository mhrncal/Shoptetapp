<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>

<?php
$platformLabels = ['heureka' => 'Heureka', 'trustedshops' => 'Trusted Shops', 'shoptet' => 'Shoptet', 'google' => 'Google'];
$platformColors = ['heureka' => 'warning', 'trustedshops' => 'success', 'shoptet' => 'primary', 'google' => 'danger'];
?>

<!-- Hlavička -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-star me-2"></i>Scrapované recenze</h4>
        <p class="text-muted small mb-0">Celkem: <strong><?= $total ?></strong></p>
    </div>
    <?php if (!empty($userLangs) && $hasDeepL): ?>
    <form method="POST" action="/scraped-reviews/translate">
        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
        <button class="btn btn-sm btn-outline-primary">
            <i class="bi bi-translate me-1"></i>Přeložit nepřeložené
        </button>
    </form>
    <?php endif; ?>
</div>



<div class="row g-3 mb-4">

    <!-- Zdroje -->
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-link-45deg me-1"></i>Zdroje</h6>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addSourceForm">
                    <i class="bi bi-plus"></i> Přidat
                </button>
            </div>

            <!-- Formulář přidání zdroje -->
            <div class="collapse" id="addSourceForm">
                <div class="card-body border-bottom">
                    <form method="POST" action="/scraped-reviews/add-source">
                        <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                        <div class="mb-2">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Název (např. Heureka CZ)" required>
                        </div>
                        <div class="mb-2">
                            <input type="url" name="url" class="form-control form-control-sm" placeholder="URL stránky s recenzemi" required>
                        </div>
                        <div class="mb-2">
                            <select name="platform" class="form-select form-select-sm" required>
                                <option value="">— Platforma —</option>
                                <option value="heureka">Heureka</option>
                                <option value="trustedshops">Trusted Shops</option>
                                <option value="shoptet">Shoptet</option>
                                <option value="google">Google</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100">Přidat zdroj</button>
                    </form>
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (empty($sources)): ?>
                <div class="text-center text-muted py-4 small">Zatím žádné zdroje.</div>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($sources as $s): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $platformColors[$s['platform']] ?? 'secondary' ?> flex-shrink-0"><?= $platformLabels[$s['platform']] ?? $s['platform'] ?></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate"><?= $e($s['name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $e($s['url']) ?></div>
                                <?php if ($s['last_scraped_at']): ?>
                                <div class="text-muted" style="font-size:.7rem;">Naposledy: <?= date('d.m.Y H:i', strtotime($s['last_scraped_at'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <form method="POST" action="/scraped-reviews/scrape">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="source_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Scrape nyní"><i class="bi bi-arrow-clockwise"></i></button>
                                </form>
                                <form method="POST" action="/scraped-reviews/delete-source" onsubmit="return confirm('Smazat zdroj i recenze?')">
                                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: DeepL klíč + jazyky -->
    <div class="col-12 col-lg-7 d-flex flex-column gap-3">

        <!-- DeepL API klíč -->
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-key me-1"></i>DeepL API klíč</h6>
                <?php if ($hasDeepL): ?>
                <span class="badge bg-success ms-auto">Aktivní</span>
                <?php else: ?>
                <span class="badge bg-warning text-dark ms-auto">Nenastaveno</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="/scraped-reviews/save-api-key">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <div class="input-group input-group-sm">
                        <input type="password" name="deepl_api_key" class="form-control font-monospace"
                               placeholder="váš-deepl-api-klíč:fx"
                               value="<?= $hasDeepL ? '••••••••••••••••' : '' ?>">
                        <button type="submit" class="btn btn-primary">Uložit</button>
                        <?php if ($hasDeepL): ?>
                        <button type="submit" name="deepl_api_key" value="" class="btn btn-outline-danger" onclick="return confirm('Odstranit klíč?')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted mt-1" style="font-size:.75rem;">
                        Free klíč z <a href="https://www.deepl.com/pro-api" target="_blank">deepl.com/pro-api</a> — 500 000 znaků/měsíc zdarma. Klíč končí <code>:fx</code>.
                    </div>
                </form>
            </div>
        </div>

        <!-- Jazyky překladů -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-translate me-1"></i>Jazyky překladů</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="/scraped-reviews/save-langs">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <?php foreach ($allLangs as $code => $label): ?>
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="checkbox" name="langs[]"
                                   value="<?= $e($code) ?>" id="lang_<?= $e($code) ?>"
                                   <?= in_array($code, $userLangs) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="lang_<?= $e($code) ?>"><?= $e($label) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary <?= !$hasDeepL ? 'disabled' : '' ?>">Uložit jazyky</button>
                    <?php if (!$hasDeepL): ?>
                    <span class="text-muted small ms-2">Nejprve zadejte DeepL klíč.</span>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>

<!-- Filtr zdrojů -->
<?php if (count($sources) > 1): ?>
<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -0.75rem;padding:0 0.75rem 0.5rem;">
    <div class="d-flex gap-2 mb-3" style="flex-wrap:nowrap;min-width:max-content;">
        <a href="/scraped-reviews" class="btn btn-sm <?= !$sourceFilter ? 'btn-secondary' : 'btn-outline-secondary' ?>">Vše</a>
        <?php foreach ($sources as $s): ?>
        <a href="?source=<?= $s['id'] ?>" class="btn btn-sm <?= $sourceFilter == $s['id'] ? 'btn-'.$platformColors[$s['platform']] : 'btn-outline-'.$platformColors[$s['platform']] ?>">
            <?= $e($s['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Seznam recenzí -->
<?php if (empty($reviews)): ?>
<div class="card"><div class="card-body text-center py-5 text-muted">
    <i class="bi bi-star fs-1 d-block mb-3"></i>
    <p>Žádné recenze. Přidejte zdroj a spusťte scraping.</p>
</div></div>
<?php else: ?>
<div class="d-flex flex-column gap-2 mb-3">
    <?php foreach ($reviews as $r): ?>
    <a href="/scraped-reviews/<?= $r['id'] ?>" class="text-decoration-none">
    <div class="card card-hover">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-<?= $platformColors[$r['platform']] ?? 'secondary' ?> flex-shrink-0"><?= $platformLabels[$r['platform']] ?? $r['platform'] ?></span>
                <strong class="small flex-grow-1 text-dark text-truncate"><?= $e($r['author']) ?></strong>
                <?php if ($r['rating']): ?>
                <span class="text-warning small flex-shrink-0">
                    <?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?>
                </span>
                <?php endif; ?>
                <span class="text-muted small flex-shrink-0"><?= $r['reviewed_at'] ? date('d.m.Y', strtotime($r['reviewed_at'])) : '' ?></span>
            </div>
            <p class="small text-muted mb-0" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;"><?= $e($r['content']) ?></p>
        </div>
    </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($total > $perPage): ?>
<?php $pages = ceil($total / $perPage); ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&source=<?= $sourceFilter ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php endif; ?>

<style>.card-hover:hover{background:#f8f9fa;transition:background .15s;}</style>
