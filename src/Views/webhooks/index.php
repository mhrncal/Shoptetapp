<?php $pageTitle = 'Webhooky'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-broadcast me-2"></i>Webhooky</h4>
        <p class="text-muted small mb-0">HTTP notifikace při změnách dat</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWebhookModal">
        <i class="bi bi-plus me-1"></i>Přidat webhook
    </button>
</div>

<?php if (empty($webhooks)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-broadcast fs-1 d-block mb-3"></i>
        <p>Žádné webhooky. Přidejte první.</p>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWebhookModal">
            <i class="bi bi-plus me-1"></i>Přidat webhook
        </button>
    </div>
</div>
<?php else: ?>

<?php foreach ($webhooks as $wh): ?>
<div class="card border-0 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <div class="status-dot <?= $wh['is_active'] ? 'bg-success' : 'bg-secondary' ?>"></div>
            <div>
                <span class="fw-semibold"><?= $e($wh['name']) ?></span>
                <span class="text-muted small ms-2"><?= $e($wh['url']) ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary"
                    onclick="editWebhook(<?= htmlspecialchars(json_encode($wh), ENT_QUOTES) ?>)">
                <i class="bi bi-pencil"></i>
            </button>
            <form method="POST" action="<?= APP_URL ?>/webhooks/<?= $wh['id'] ?>"
                  onsubmit="return confirm('Smazat webhook?')">
                <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <!-- Info -->
            <div class="col-12 col-md-5">
                <div class="mb-3">
                    <div class="small text-muted mb-1">Eventy</div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($wh['events'] as $ev): ?>
                        <span class="badge bg-primary bg-opacity-20 text-primary small font-monospace">
                            <?= $e($ev) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="d-flex gap-4 small text-muted">
                    <div>
                        <span class="d-block">Retries</span>
                        <strong class="text-body"><?= $wh['retry_count'] ?>×</strong>
                    </div>
                    <div>
                        <span class="d-block">Secret</span>
                        <code class="text-info small"><?= $e(substr($wh['secret'], 0, 16)) ?>…</code>
                    </div>
                </div>
            </div>

            <!-- Poslední doručení -->
            <div class="col-12 col-md-7">
                <div class="small text-muted mb-2">Poslední doručení</div>
                <?php if (empty($wh['recent_logs'])): ?>
                <p class="text-muted small">Žádné záznamy</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <tbody>
                        <?php foreach ($wh['recent_logs'] as $log):
                            $ok = $log['response_status'] >= 200 && $log['response_status'] < 300;
                        ?>
                        <tr>
                            <td>
                                <span class="badge bg-<?= $ok ? 'success' : 'danger' ?> bg-opacity-20 text-<?= $ok ? 'success' : 'danger' ?>">
                                    <?= $log['response_status'] ?? 'ERR' ?>
                                </span>
                            </td>
                            <td class="font-monospace text-muted"><?= $e($log['event_type']) ?></td>
                            <td class="text-muted"><?= $log['attempt_number'] ?>. pokus</td>
                            <td class="text-muted"><?= date('d.m. H:i', strtotime($log['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Info: ověření podpisu -->
<div class="card border-0 border-secondary border-opacity-25 mt-2">
    <div class="card-body py-3 small text-muted">
        <p class="fw-semibold text-body mb-2"><i class="bi bi-shield-check me-1"></i>Ověření podpisu</p>
        Každý request obsahuje hlavičku <code>X-ShopCode-Signature</code> ve formátu
        <code>t={timestamp},v1={hmac}</code>. HMAC je SHA-256 hash těla requestu podepsaný vaším secret klíčem.
    </div>
</div>

<!-- Modal: Přidat webhook -->
<div class="modal fade" id="addWebhookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Přidat webhook</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/webhooks">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">
                    <?= \ShopCode\Core\View::partial('webhooks/_form', ['webhook' => null, 'allEvents' => $allEvents]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Přidat webhook</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Upravit webhook -->
<div class="modal fade" id="editWebhookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Upravit webhook</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editWebhookForm" action="">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body" id="editWebhookBody">
                    <?= \ShopCode\Core\View::partial('webhooks/_form', ['webhook' => null, 'allEvents' => $allEvents]) ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">Uložit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.status-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
</style>

<script>
function editWebhook(wh) {
    var form = document.getElementById('editWebhookForm');
    form.action = '<?= APP_URL ?>/webhooks/' + wh.id;
    var body = document.getElementById('editWebhookBody');

    body.querySelector('[name="name"]').value         = wh.name        || '';
    body.querySelector('[name="url"]').value          = wh.url         || '';
    body.querySelector('[name="retry_count"]').value  = wh.retry_count || 3;
    body.querySelector('[name="is_active"]').checked  = wh.is_active == 1;

    // Checkboxy eventů
    body.querySelectorAll('[name="events[]"]').forEach(function(cb) {
        cb.checked = wh.events && wh.events.includes(cb.value);
    });

    new bootstrap.Modal(document.getElementById('editWebhookModal')).show();
}
</script>
