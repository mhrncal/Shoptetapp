<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $ev = $event ?? null; ?>
<?php $toLocal = fn($dt) => $dt ? substr(str_replace(' ', 'T', $dt), 0, 16) : ''; ?>

<div class="mb-3">
    <label class="form-label">Název akce <span class="text-danger">*</span></label>
    <input type="text" name="title" class="form-control" required
           value="<?= $e($ev['title'] ?? '') ?>" placeholder="Výprodej jaro 2025">
</div>

<div class="row g-3 mb-3">
    <div class="col-6">
        <label class="form-label">Začátek <span class="text-danger">*</span></label>
        <input type="datetime-local" name="start_date" class="form-control" required
               value="<?= $e($toLocal($ev['start_date'] ?? '')) ?>">
    </div>
    <div class="col-6">
        <label class="form-label">Konec <span class="text-danger">*</span></label>
        <input type="datetime-local" name="end_date" class="form-control" required
               value="<?= $e($toLocal($ev['end_date'] ?? '')) ?>">
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Popis</label>
    <textarea name="description" class="form-control" rows="4"
              placeholder="Popis akce..."><?= $e($ev['description'] ?? '') ?></textarea>
</div>

<div class="mb-3">
    <label class="form-label">Místo konání</label>
    <input type="text" name="address" class="form-control"
           value="<?= $e($ev['address'] ?? '') ?>" placeholder="Václavské náměstí 1, Praha">
</div>

<div class="row g-3 mb-3">
    <div class="col-6">
        <label class="form-label">URL akce</label>
        <input type="url" name="event_url" class="form-control"
               value="<?= $e($ev['event_url'] ?? '') ?>" placeholder="https://...">
    </div>
    <div class="col-6">
        <label class="form-label">URL obrázku</label>
        <input type="url" name="image_url" class="form-control"
               value="<?= $e($ev['image_url'] ?? '') ?>" placeholder="https://...">
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Odkaz Google Maps</label>
    <input type="url" name="google_maps_url" class="form-control"
           value="<?= $e($ev['google_maps_url'] ?? '') ?>" placeholder="https://maps.google.com/...">
</div>

<div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_active" value="1"
           <?= (!$ev || $ev['is_active']) ? 'checked' : '' ?>>
    <label class="form-check-label">Akce je aktivní (viditelná)</label>
</div>
