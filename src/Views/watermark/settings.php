<?php
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-droplet"></i> Nastavení Watermarku
            </h1>
        </div>
    </div>

    <div class="row">
        <!-- Formulář -->
        <div class="col-lg-6">
            <div class="card border-0 mb-4">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">Konfigurace</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= APP_URL ?>/watermark/update" id="watermark-form">
                        
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

                        <!-- Text -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Text watermarku</label>
                            <input type="text" class="form-control" name="text" id="wm-text"
                                   value="<?= $e($settings['text']) ?>" maxlength="255" required>
                        </div>

                        <!-- Font -->
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

                        <!-- Pozice -->
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
                            <small class="text-muted">TL=top-left, TC=top-center, atd.</small>
                        </div>

                        <!-- Velikost -->
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

                        <!-- Barva -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Barva textu</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" 
                                       id="wm-color" name="color" value="<?= $e($settings['color']) ?>">
                                <input type="text" class="form-control" id="wm-color-text" 
                                       value="<?= $e($settings['color']) ?>" maxlength="7">
                            </div>
                        </div>

                        <!-- Opacity -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Průhlednost: <span id="opacity-value"><?= $settings['opacity'] ?>%</span>
                            </label>
                            <input type="range" class="form-range" id="wm-opacity" name="opacity" 
                                   min="0" max="100" value="<?= $settings['opacity'] ?>">
                        </div>

                        <!-- Padding -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Padding od okraje: <span id="padding-value"><?= $settings['padding'] ?>px</span>
                            </label>
                            <input type="range" class="form-range" id="wm-padding" name="padding" 
                                   min="5" max="100" value="<?= $settings['padding'] ?>">
                        </div>

                        <!-- Stín -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="shadow_enabled" 
                                       name="shadow_enabled" <?= ($settings['shadow_enabled'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="shadow_enabled">
                                    Přidat černý stín (lepší čitelnost)
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Uložit nastavení
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Preview -->
        <div class="col-lg-6">
            <div class="card border-0 sticky-top" style="top: 20px;">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-eye"></i> Náhled
                    </h6>
                </div>
                <div class="card-body">
                    <div class="preview-container">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='450'%3E%3Crect fill='%23e5e7eb' width='800' height='450'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='sans-serif' font-size='24' fill='%239ca3af'%3ENáhled fotky%3C/text%3E%3C/svg%3E" 
                             alt="Preview" class="preview-image">
                        <div class="watermark-preview" id="watermark-preview">
                            <?= $e($settings['text']) ?>
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-info-circle"></i>
                        Watermark se automaticky aplikuje na všechny nově nahrané fotky.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.position-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 8px;
}
.position-btn {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 14px;
}
.position-btn:hover {
    border-color: #0d6efd;
    background: #f8f9fa;
}
.position-btn.active {
    border-color: #0d6efd;
    background: #0d6efd;
    color: white;
}
.position-btn input {
    display: none;
}

.preview-container {
    position: relative;
    width: 100%;
    aspect-ratio: 16/9;
    background: #f8f9fa;
    border-radius: 8px;
    overflow: hidden;
}
.preview-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.watermark-preview {
    position: absolute;
    font-family: Arial, sans-serif;
    font-size: 24px;
    color: #FFFFFF;
    padding: 20px;
    pointer-events: none;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
    transition: all 0.3s;
}
</style>

<script>
// Live preview updater
const form = document.getElementById('watermark-form');
const preview = document.getElementById('watermark-preview');
const opacitySlider = document.getElementById('wm-opacity');
const paddingSlider = document.getElementById('wm-padding');
const colorInput = document.getElementById('wm-color');
const colorText = document.getElementById('wm-color-text');

function updatePreview() {
    const text = document.getElementById('wm-text').value;
    const font = document.getElementById('wm-font').value;
    const position = document.querySelector('input[name="position"]:checked').value;
    const size = document.querySelector('input[name="size"]:checked').value;
    const color = colorInput.value;
    const opacity = opacitySlider.value / 100;
    const padding = paddingSlider.value + 'px';
    const shadow = document.getElementById('shadow_enabled').checked;
    
    const sizeMap = {small: '16px', medium: '24px', large: '36px'};
    
    preview.textContent = text;
    preview.style.fontFamily = font;
    preview.style.fontSize = sizeMap[size];
    preview.style.color = color;
    preview.style.opacity = opacity;
    preview.style.textShadow = shadow ? '2px 2px 4px rgba(0,0,0,0.8)' : 'none';
    
    // Position - reset všechny pozice
    preview.style.top = 'auto';
    preview.style.bottom = 'auto';
    preview.style.left = 'auto';
    preview.style.right = 'auto';
    preview.style.transform = 'none';
    
    const positions = {
        'TL': {top: padding, left: padding, transform: 'none'},
        'TC': {top: padding, left: '50%', transform: 'translateX(-50%)'},
        'TR': {top: padding, right: padding, transform: 'none'},
        'ML': {top: '50%', left: padding, transform: 'translateY(-50%)'},
        'MC': {top: '50%', left: '50%', transform: 'translate(-50%, -50%)'},
        'MR': {top: '50%', right: padding, transform: 'translateY(-50%)'},
        'BL': {bottom: padding, left: padding, transform: 'none'},
        'BC': {bottom: padding, left: '50%', transform: 'translateX(-50%)'},
        'BR': {bottom: padding, right: padding, transform: 'none'}
    };
    
    // Aplikuj vybranou pozici
    Object.assign(preview.style, positions[position]);
}

// Event listeners
form.addEventListener('input', updatePreview);
form.addEventListener('change', updatePreview);

opacitySlider.addEventListener('input', (e) => {
    document.getElementById('opacity-value').textContent = e.target.value + '%';
});

paddingSlider.addEventListener('input', (e) => {
    document.getElementById('padding-value').textContent = e.target.value + 'px';
});

colorInput.addEventListener('input', (e) => {
    colorText.value = e.target.value;
});

colorText.addEventListener('input', (e) => {
    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
        colorInput.value = e.target.value;
        updatePreview();
    }
});

// Position button toggle
document.querySelectorAll('.position-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.position-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});

// Initial preview
updatePreview();
</script>
