<?php $e = fn($v) => htmlspecialchars($v, ENT_QUOTES); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Importy produktů</h1>
    <a href="/feeds/create" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nový import
    </a>
</div>

<?php if (!empty($feeds)): ?>
<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Název</th>
                <th>Typ</th>
                <th>Poslední stažení</th>
                <th>Status</th>
                <th>Akce</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($feeds as $feed): ?>
            <tr>
                <td>
                    <strong><?= $e($feed['name']) ?></strong><br>
                    <small class="text-muted"><?= $e(substr($feed['url'], 0, 60)) ?>...</small>
                </td>
                <td>
                    <?php if ($feed['type'] === 'csv_with_images'): ?>
                        <span class="badge bg-info">S obrázky</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Základní</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($feed['last_fetch_at']): ?>
                        <?= date('d.m.Y H:i', strtotime($feed['last_fetch_at'])) ?>
                    <?php else: ?>
                        <span class="text-muted">Nikdy</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($feed['last_fetch_status'] === 'success'): ?>
                        <span class="badge bg-success">OK</span>
                    <?php elseif ($feed['last_fetch_status'] === 'error'): ?>
                        <span class="badge bg-danger" title="<?= $e($feed['last_error']) ?>">Chyba</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" action="/feeds/sync">
                            <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Synchronizovat teď">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </form>
                        
                        <form method="POST" action="/feeds/delete" onsubmit="return confirm('Smazat feed?')">
                            <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                            <input type="hidden" name="id" value="<?= $feed['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Smazat">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="alert alert-info">
    Zatím nemáte žádné importy. <a href="/feeds/create">Vytvořte první import</a>
</div>
<?php endif; ?>
