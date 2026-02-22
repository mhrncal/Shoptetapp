<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $wh = $webhook ?? null; ?>

<div class="mb-3">
    <label class="form-label">Název <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" required
           value="<?= $e($wh['name'] ?? '') ?>" placeholder="Můj server, Záloha...">
</div>

<div class="mb-3">
    <label class="form-label">URL <span class="text-danger">*</span></label>
    <input type="url" name="url" class="form-control" required
           value="<?= $e($wh['url'] ?? '') ?>" placeholder="https://mujserver.cz/webhook">
</div>

<div class="mb-3">
    <label class="form-label">Eventy <span class="text-danger">*</span></label>
    <div class="row g-2">
        <?php foreach ($allEvents as $key => $label): ?>
        <div class="col-6">
            <div class="form-check">
                <input class="form-check-input" type="checkbox"
                       name="events[]" value="<?= $e($key) ?>"
                       id="ev_<?= $e(str_replace('.','_',$key)) ?>"
                       <?= (!$wh || in_array($key, $wh['events'] ?? [])) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="ev_<?= $e(str_replace('.','_',$key)) ?>">
                    <span class="font-monospace"><?= $e($key) ?></span><br>
                    <span class="text-muted" style="font-size:.75rem;"><?= $e($label) ?></span>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-6">
        <label class="form-label">Počet pokusů</label>
        <select name="retry_count" class="form-select">
            <?php foreach ([1,2,3,5] as $n): ?>
            <option value="<?= $n ?>" <?= ($wh['retry_count'] ?? 3) == $n ? 'selected' : '' ?>>
                <?= $n ?>×
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 d-flex align-items-end pb-2">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   <?= (!$wh || $wh['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label">Aktivní</label>
        </div>
    </div>
</div>
