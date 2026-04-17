<?php
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$isLogo = ($settings['watermark_type'] ?? 'text') === 'logo';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-droplet me-2"></i>Nastavení Watermarku</h4>
</div>

<div class="row">
    <!-- Formulář -->
    <div class="col-lg-6">
        <div class="card border-0 mb-4">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Konfigurace</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/watermark/update" id="watermark-form" enctype="multipart/form-data">
                    <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">

                    <!-- Povolit watermark -->
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enabled"
                                   name="enabled" <?= $settings['enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enabled">
                                Povolit watermark na všech fotkách
                            </label>
                        </div>
                    </div>

                    <!-- Typ watermarku -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Typ watermarku</label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="watermark_type"
                                       id="wm-type-text" value="text" <?= !$isLogo ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wm-type-text">
                                    <i class="bi bi-fonts me-1"></i>Text
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="watermark_type"
                                       id="wm-type-logo" value="logo" <?= $isLogo ? 'checked' : '' ?>>
                                <label class="form-check-label" for="wm-type-logo">
                                    <i class="bi bi-image me-1"></i>Logo / Obrázek
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SEKCE TEXT -->
                    <div id="section-text" <?= $isLogo ? 'style="display:none"' : '' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Text watermarku</label>
                            <input type="text" class="form-control" name="text" id="wm-text"
                                   value="<?= $e($settings['text']) ?>" maxlength="255">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Font</label>
                            <select class="form-select" name="font" id="wm-font">
                                <?php foreach ($fonts as $key => $label): ?>
                                <option value="<?= $e($key) ?>" <?= $settings['font'] === $key ? 'selected' : '' ?>>
                                    <?= $e($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Velikost</label>
                            <div class="btn-group w-100" role="group">
                                <?php foreach ($sizes as $key => $info): ?>
                                <input type="radio" class="btn-check" name="size"
                                       id="size-<?= $key ?>" value="<?= $key ?>"
                                       <?= $settings['size'] === $key ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary" for="size-<?= $key ?>">
                                    <?= $e($info['label']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Barva textu</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color"
                                       id="wm-color" name="color" value="<?= $e($settings['color']) ?>">
                                <input type="text" class="form-control" id="wm-color-text"
                                       value="<?= $e($settings['color']) ?>" maxlength="7">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="shadow_enabled"
                                       name="shadow_enabled" <?= ($settings['shadow_enabled'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="shadow_enabled">
                                    Přidat stín (lepší čitelnost na světlých fotkách)
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SEKCE LOGO -->
                    <div id="section-logo" <?= !$isLogo ? 'style="display:none"' : '' ?>>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Logo soubor <small class="text-muted">(PNG, JPG, JPEG, SVG)</small></label>
                            <?php if (!empty($settings['logo_path'])): ?>
                            <div class="mb-2 p-2 bg-light rounded d-flex align-items-center gap-3">
                                <img src="<?= APP_URL ?>/public/<?= $e($settings['logo_path']) ?>"
                                     style="max-height:48px;max-width:160px;object-fit:contain;" alt="Logo">
                                <span class="text-muted small">Aktuální logo</span>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" name="logo" id="logo-input"
                                   accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml">
                            <div class="form-text">Doporučeno PNG s průhledným pozadím. Logo se škáluje na max 25% šířky fotky.</div>
                        </div>
                    </div>

                    <!-- SPOLEČNÉ: Pozice, průhlednost, padding -->
                    <hr class="my-3">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pozice</label>
                        <div class="position-grid">
                            <?php foreach ($positions as $code => $info): ?>
                            <label class="position-btn <?= $settings['position'] === $code ? 'active' : '' ?>">
                                <input type="radio" name="position" value="<?= $code ?>"
                                       <?= $settings['position'] === $code ? 'checked' : '' ?>>
                                <span><?= $code ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted">TL=vlevo nahoře · TC=nahoře střed · BR=vpravo dole</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Průhlednost: <span id="opacity-value"><?= $settings['opacity'] ?>%</span>
                        </label>
                        <input type="range" class="form-range" id="wm-opacity" name="opacity"
                               min="0" max="100" value="<?= $settings['opacity'] ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            Odstup od okraje: <span id="padding-value"><?= $settings['padding'] ?>px</span>
                        </label>
                        <input type="range" class="form-range" id="wm-padding" name="padding"
                               min="5" max="100" value="<?= $settings['padding'] ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Uložit nastavení
                    </button>
                </form>

                <div class="mt-3 border-top pt-3">
                    <h6 class="text-muted mb-2">Přegenerovat watermark</h6>
                    <p class="small text-muted mb-3">Aplikuj nové nastavení na všechny existující fotky. Originály zůstanou zachovány.</p>
                    <form method="POST" action="<?= APP_URL ?>/watermark/regenerate"
                          onsubmit="return confirm('Přegenerovat watermark na všech fotkách?')">
                        <input type="hidden" name="_csrf" value="<?= $csrfToken ?>">
                        <button type="submit" class="btn btn-outline-warning w-100">
                            <i class="bi bi-arrow-repeat me-1"></i>Přegenerovat všechny fotky
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-6">
        <div class="card sticky-top" style="top:1rem;">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-eye me-1"></i>Náhled</h6>
            </div>
            <div class="card-body">
                <div class="preview-container">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='500'%3E%3Crect fill='%23d1fae5' width='800' height='500'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='22' fill='%236ee7b7'%3ENáhled fotky%3C/text%3E%3C/svg%3E"
                         alt="Preview" class="preview-image">
                    <div class="watermark-preview" id="wm-prev-text"><?= $e($settings['text']) ?></div>
                    <div class="watermark-preview" id="wm-prev-logo" style="display:none;padding:0;">
                        <?php if (!empty($settings['logo_path'])): ?>
                        <img id="logo-prev-img" src="<?= APP_URL ?>/public/<?= $e($settings['logo_path']) ?>"
                             style="max-height:60px;max-width:160px;object-fit:contain;">
                        <?php else: ?>
                        <img id="logo-prev-img" src="" style="display:none;max-height:60px;max-width:160px;">
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>Náhled je orientační. Výsledek závisí na velikosti fotky.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.position-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:8px; }
.position-btn { border:2px solid #dee2e6; border-radius:8px; padding:10px; text-align:center; cursor:pointer; transition:all .2s; font-weight:600; font-size:13px; }
.position-btn:hover { border-color:hsl(var(--primary)); background:hsl(var(--muted)); }
.position-btn.active { border-color:hsl(var(--primary)); background:hsl(var(--primary)); color:hsl(var(--primary-foreground)); }
.position-btn input { display:none; }
.preview-container { position:relative; width:100%; aspect-ratio:16/10; background:#e5e7eb; border-radius:8px; overflow:hidden; }
.preview-image { width:100%; height:100%; object-fit:cover; }
.watermark-preview { position:absolute; font-family:Arial,sans-serif; font-size:24px; color:#fff; padding:20px; pointer-events:none; transition:all .2s; line-height:1; }
</style>

<script>
const opacitySlider = document.getElementById('wm-opacity');
const paddingSlider = document.getElementById('wm-padding');
const colorInput    = document.getElementById('wm-color');
const colorText     = document.getElementById('wm-color-text');
const prevText      = document.getElementById('wm-prev-text');
const prevLogo      = document.getElementById('wm-prev-logo');
const logoPrevImg   = document.getElementById('logo-prev-img');

function isLogoMode() { return document.getElementById('wm-type-logo').checked; }

function applyPosition(el, pos, pad) {
    el.style.top = el.style.bottom = el.style.left = el.style.right = 'auto';
    el.style.transform = 'none';
    const p = pad + 'px';
    const m = {
        TL:{top:p,left:p}, TC:{top:p,left:'50%',transform:'translateX(-50%)'}, TR:{top:p,right:p},
        ML:{top:'50%',left:p,transform:'translateY(-50%)'}, MC:{top:'50%',left:'50%',transform:'translate(-50%,-50%)'}, MR:{top:'50%',right:p,transform:'translateY(-50%)'},
        BL:{bottom:p,left:p}, BC:{bottom:p,left:'50%',transform:'translateX(-50%)'}, BR:{bottom:p,right:p}
    };
    Object.assign(el.style, m[pos] || m.BR);
}

function updatePreview() {
    const pos  = (document.querySelector('input[name="position"]:checked') || {value:'BR'}).value;
    const pad  = parseInt(paddingSlider.value);
    const opac = opacitySlider.value / 100;

    if (isLogoMode()) {
        prevLogo.style.opacity = opac;
        applyPosition(prevLogo, pos, pad);
    } else {
        const size = (document.querySelector('input[name="size"]:checked') || {value:'medium'}).value;
        const sizeMap = {small:'16px', medium:'24px', large:'36px'};
        const shadow = document.getElementById('shadow_enabled').checked;
        prevText.textContent     = document.getElementById('wm-text').value;
        prevText.style.fontFamily = document.getElementById('wm-font').value;
        prevText.style.fontSize   = sizeMap[size];
        prevText.style.color      = colorInput.value;
        prevText.style.opacity    = opac;
        prevText.style.textShadow = shadow ? '2px 2px 4px rgba(0,0,0,0.8)' : 'none';
        applyPosition(prevText, pos, pad);
    }
}

function toggleSections() {
    const logo = isLogoMode();
    document.getElementById('section-text').style.display = logo ? 'none' : '';
    document.getElementById('section-logo').style.display = logo ? '' : 'none';
    prevText.style.display = logo ? 'none' : '';
    prevLogo.style.display = logo ? '' : 'none';
    updatePreview();
}

// Logo file preview
document.getElementById('logo-input')?.addEventListener('change', function() {
    if (this.files[0]) {
        const r = new FileReader();
        r.onload = e => { logoPrevImg.src = e.target.result; logoPrevImg.style.display = ''; };
        r.readAsDataURL(this.files[0]);
    }
});

// Events
document.getElementById('wm-type-text').addEventListener('change', toggleSections);
document.getElementById('wm-type-logo').addEventListener('change', toggleSections);
document.getElementById('watermark-form').addEventListener('input', updatePreview);
document.getElementById('watermark-form').addEventListener('change', updatePreview);
opacitySlider.addEventListener('input', e => { document.getElementById('opacity-value').textContent = e.target.value + '%'; });
paddingSlider.addEventListener('input', e => { document.getElementById('padding-value').textContent = e.target.value + 'px'; });
colorInput.addEventListener('input', e => { colorText.value = e.target.value; });
colorText.addEventListener('input', e => { if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) { colorInput.value = e.target.value; updatePreview(); }});
document.querySelectorAll('.position-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.position-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        setTimeout(updatePreview, 10);
    });
});

toggleSections();
</script>
