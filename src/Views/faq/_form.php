<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $faq = $faq ?? null; ?>

<div class="mb-3">
    <label class="form-label">Vazba na produkt <span class="text-muted small">(nepovinné)</span></label>
    <select name="product_id" class="form-select">
        <option value="">— Obecná FAQ (bez produktu) —</option>
        <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>"
                <?= ($faq && $faq['product_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= $e($p['name']) ?>
            <small class="text-muted">(<?= $e($p['shoptet_id']) ?>)</small>
        </option>
        <?php endforeach; ?>
    </select>
</div>

<div class="mb-3">
    <label class="form-label">Otázka <span class="text-danger">*</span></label>
    <input type="text" name="question" class="form-control"
           placeholder="Jak dlouho trvá dodání?" required
           value="<?= $e($faq['question'] ?? '') ?>">
</div>

<div class="mb-3">
    <label class="form-label">Odpověď <span class="text-danger">*</span></label>
    <textarea name="answer" class="form-control" rows="5" required
              placeholder="Standardní dodací lhůta je 2–3 pracovní dny..."><?= $e($faq['answer'] ?? '') ?></textarea>
</div>

<div class="row g-3">
    <div class="col-6">
        <label class="form-label">Pořadí</label>
        <input type="number" name="sort_order" class="form-control" min="0"
               value="<?= $e($faq['sort_order'] ?? 0) ?>">
    </div>
    <div class="col-6 d-flex align-items-end">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="is_public" id="isPublic_<?= uniqid() ?>"
                   value="1" <?= (!$faq || $faq['is_public']) ? 'checked' : '' ?>>
            <label class="form-check-label">Veřejná FAQ</label>
        </div>
    </div>
</div>
