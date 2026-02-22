<?php $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
<?php $branch = $branch ?? null; ?>
<?php $h = $branch['hours'] ?? []; ?>

<input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">

<div class="row g-4">
    <!-- Levý sloupec: základní info -->
    <div class="col-12 col-lg-6">
        <div class="card border-0">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Základní informace</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Název pobočky <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= $e($branch['name'] ?? '') ?>" placeholder="Prodejna Praha 1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Popis</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Krátký popis pobočky..."><?= $e($branch['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ulice a číslo</label>
                    <input type="text" name="street_address" class="form-control"
                           value="<?= $e($branch['street_address'] ?? '') ?>" placeholder="Václavské náměstí 1">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label">Město</label>
                        <input type="text" name="city" class="form-control"
                               value="<?= $e($branch['city'] ?? '') ?>" placeholder="Praha">
                    </div>
                    <div class="col-4">
                        <label class="form-label">PSČ</label>
                        <input type="text" name="postal_code" class="form-control"
                               value="<?= $e($branch['postal_code'] ?? '') ?>" placeholder="110 00">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">URL pobočky</label>
                    <input type="url" name="branch_url" class="form-control"
                           value="<?= $e($branch['branch_url'] ?? '') ?>" placeholder="https://...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Odkaz Google Maps</label>
                    <input type="url" name="google_maps_url" class="form-control"
                           value="<?= $e($branch['google_maps_url'] ?? '') ?>"
                           placeholder="https://maps.google.com/...">
                </div>
                <div class="mb-3">
                    <label class="form-label">URL obrázku</label>
                    <input type="url" name="image_url" class="form-control"
                           value="<?= $e($branch['image_url'] ?? '') ?>" placeholder="https://...">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Zeměpisná šířka</label>
                        <input type="number" name="latitude" step="any" class="form-control"
                               value="<?= $e($branch['latitude'] ?? '') ?>" placeholder="50.0755">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Zeměpisná délka</label>
                        <input type="number" name="longitude" step="any" class="form-control"
                               value="<?= $e($branch['longitude'] ?? '') ?>" placeholder="14.4378">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pravý sloupec: otevírací doby -->
    <div class="col-12 col-lg-6">
        <div class="card border-0">
            <div class="card-header"><h6 class="mb-0 fw-semibold"><i class="bi bi-clock me-2 text-muted"></i>Otevírací doby</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px;">Den</th>
                            <th style="width:70px;" class="text-center">Zavřeno</th>
                            <th>Od</th>
                            <th>Do</th>
                            <th>Poznámka</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($days as $d => $dayName):
                        $dayH = $h[$d] ?? [];
                        $closed = !empty($dayH['is_closed']);
                    ?>
                    <tr class="hour-row" data-day="<?= $d ?>">
                        <td class="fw-semibold small"><?= $dayName ?></td>
                        <td class="text-center">
                            <div class="form-check d-flex justify-content-center mb-0">
                                <input class="form-check-input closed-toggle" type="checkbox"
                                       name="hours[<?= $d ?>][is_closed]" value="1"
                                       <?= $closed ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td>
                            <input type="time" name="hours[<?= $d ?>][open_from]"
                                   class="form-control form-control-sm time-input"
                                   value="<?= $e(substr($dayH['open_from'] ?? '09:00',0,5)) ?>"
                                   <?= $closed ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <input type="time" name="hours[<?= $d ?>][open_to]"
                                   class="form-control form-control-sm time-input"
                                   value="<?= $e(substr($dayH['open_to'] ?? '17:00',0,5)) ?>"
                                   <?= $closed ? 'disabled' : '' ?>>
                        </td>
                        <td>
                            <input type="text" name="hours[<?= $d ?>][note]"
                                   class="form-control form-control-sm"
                                   placeholder="Polední pauza..."
                                   value="<?= $e($dayH['note'] ?? '') ?>"
                                   <?= $closed ? 'disabled' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Rychlé akce -->
                <div class="p-3 border-top border-secondary border-opacity-25">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setWeekdays()">
                            Po–Pá 9–17
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary" onclick="setAllOpen()">
                            Celý týden
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="setAllClosed()">
                            Vše zavřeno
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle zavřeno → disable vstupů
$(document).on('change', '.closed-toggle', function() {
    var row = $(this).closest('tr');
    var closed = $(this).is(':checked');
    row.find('.time-input, input[type=text]').prop('disabled', closed);
    row.toggleClass('text-muted', closed);
});

function setWeekdays() {
    for (var d = 0; d <= 6; d++) {
        var row = $('[data-day="'+d+'"]');
        var isClosed = d >= 5;
        row.find('.closed-toggle').prop('checked', isClosed).trigger('change');
        if (!isClosed) {
            row.find('input[name*="open_from"]').val('09:00');
            row.find('input[name*="open_to"]').val('17:00');
        }
    }
}

function setAllOpen() {
    for (var d = 0; d <= 6; d++) {
        var row = $('[data-day="'+d+'"]');
        row.find('.closed-toggle').prop('checked', false).trigger('change');
        row.find('input[name*="open_from"]').val('09:00');
        row.find('input[name*="open_to"]').val('17:00');
    }
}

function setAllClosed() {
    $('.closed-toggle').prop('checked', true).trigger('change');
}
</script>
