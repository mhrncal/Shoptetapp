<?php $pageTitle = 'API tokeny'; ?>
<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-key me-2"></i>API tokeny</h4>
        <p class="text-muted small mb-0">Tokeny pro přístup k REST API</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTokenModal">
        <i class="bi bi-plus me-1"></i>Vytvořit token
    </button>
</div>

<!-- Nový token — zobraz jednou -->
<?php if (!empty($newToken)): ?>
<div class="alert alert-success border-success d-flex align-items-start gap-3 mb-4">
    <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div class="flex-grow-1">
        <strong>Token vytvořen! Zkopírujte ho nyní — nebude znovu zobrazen.</strong>
        <div class="input-group mt-2">
            <input type="text" class="form-control font-monospace bg-dark border-success text-white"
                   id="newTokenValue" value="<?= $e($newToken) ?>" readonly>
            <button class="btn btn-success" onclick="copyToken()">
                <i class="bi bi-clipboard me-1"></i>Kopírovat
            </button>
        </div>
    </div>
</div>
<script>
function copyToken() {
    var input = document.getElementById('newTokenValue');
    navigator.clipboard.writeText(input.value).then(function() {
        showToast('Token zkopírován do schránky', 'success');
    });
}
</script>
<?php endif; ?>

<!-- Seznam tokenů -->
<?php if (empty($tokens)): ?>
<div class="card border-0">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-key fs-1 d-block mb-3"></i>
        <p>Zatím žádné API tokeny. Vytvořte první token.</p>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTokenModal">
            <i class="bi bi-plus me-1"></i>Vytvořit token
        </button>
    </div>
</div>
<?php else: ?>
<div class="card border-0">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Název</th>
                    <th>Prefix tokenu</th>
                    <th>Oprávnění</th>
                    <th>Platnost</th>
                    <th>Naposledy použit</th>
                    <th>Stav</th>
                    <th class="text-end">Akce</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $t):
                $perms    = json_decode($t['permissions'], true) ?? [];
                $expired  = $t['expires_at'] && strtotime($t['expires_at']) < time();
            ?>
            <tr class="<?= !$t['is_active'] || $expired ? 'opacity-60' : '' ?>">
                <td class="fw-semibold"><?= $e($t['name']) ?></td>
                <td>
                    <code class="text-info"><?= $e($t['token_prefix']) ?>…</code>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <?php foreach ($perms as $p): ?>
                        <span class="badge bg-secondary bg-opacity-30 text-body small font-monospace">
                            <?= $e($p) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td class="small text-muted">
                    <?php if ($t['expires_at']): ?>
                        <span class="<?= $expired ? 'text-danger' : '' ?>">
                            <?= date('d.m.Y', strtotime($t['expires_at'])) ?>
                            <?= $expired ? '(vypršel)' : '' ?>
                        </span>
                    <?php else: ?>
                        <span class="text-success">Bez expirace</span>
                    <?php endif; ?>
                </td>
                <td class="small text-muted">
                    <?= $t['last_used_at'] ? date('d.m.Y H:i', strtotime($t['last_used_at'])) : '—' ?>
                </td>
                <td>
                    <?php if ($expired): ?>
                        <span class="badge bg-danger">Vypršel</span>
                    <?php elseif ($t['is_active']): ?>
                        <span class="badge bg-success">Aktivní</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Revokován</span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <form method="POST" action="<?= APP_URL ?>/api-tokens/<?= $t['id'] ?>"
                          onsubmit="return confirm('Smazat token? Tuto akci nelze vrátit.')">
                        <input type="hidden" name="_csrf"   value="<?= $e($csrfToken) ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Info box -->
<div class="card border-0 border-secondary border-opacity-25 mt-4">
    <div class="card-body py-3 small text-muted">
        <p class="fw-semibold text-body mb-2"><i class="bi bi-info-circle me-1"></i>Jak používat API tokeny</p>
        Přidejte token do HTTP hlavičky každého requestu:
        <code class="d-block mt-2 p-2 bg-dark border border-secondary rounded">
            Authorization: Bearer sc_váš_token_zde
        </code>
    </div>
</div>

<!-- Modal: Vytvořit token -->
<div class="modal fade" id="addTokenModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Vytvořit API token</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= APP_URL ?>/api-tokens">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Název tokenu <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               placeholder="Produkční server, Testování...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Oprávnění <span class="text-danger">*</span></label>
                        <?php foreach ($permissions as $perm): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="permissions[]" value="<?= $e($perm) ?>"
                                   id="perm_<?= $e($perm) ?>" checked>
                            <label class="form-check-label font-monospace small" for="perm_<?= $e($perm) ?>">
                                <?= $e($perm) ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Platnost do <span class="text-muted small">(nepovinné)</span></label>
                        <input type="date" name="expires_at" class="form-control"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        <div class="form-text">Ponechte prázdné pro token bez expirace.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Zrušit</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i>Vytvořit token
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
