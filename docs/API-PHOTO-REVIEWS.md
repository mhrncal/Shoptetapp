# 📸 Photo Review API - Kompletní dokumentace

## ✅ Status

**API JE JIŽ PLNĚ FUNKČNÍ!**

Endpoint `/api/submit-review` je připraven k použití bez jakékoli konfigurace.

---

## 📍 Endpoint

```
POST https://tvoje-domena.cz/api/submit-review
```

**Typ:** Standalone endpoint (mimo REST API v1)  
**Auth:** Není potřeba (veřejný formulář)  
**CORS:** `Access-Control-Allow-Origin: *` (všechny domény)  
**Rate limit:** 5 requestů za 10 minut z jedné IP

---

## 🔧 Request

### Content-Type

```
multipart/form-data
```

### Povinné pole

| Pole | Typ | Popis | Validace |
|------|-----|-------|----------|
| `name` | string | Jméno autora | Max 100 znaků |
| `email` | string | Email autora | Validní email |
| `photos[]` | file[] | Fotky produktu | 1-5 fotek, JPG/PNG/WEBP |

### Volitelné pole

| Pole | Typ | Popis | Validace |
|------|-----|-------|----------|
| `product_id` | string | Shoptet ID produktu | - |
| `sku` | string | SKU produktu | - |
| `rating` | int | Hodnocení 1-5 hvězdiček | 1-5 |
| `comment` | string | Komentář k recenzi | Max 500 znaků |

### Anti-spam pole

| Pole | Typ | Popis |
|------|-----|-------|
| `website` | string | Honeypot pole (boti vyplní, lidé ne) |

---

## 📤 Příklady requestů

### JavaScript (Fetch API)

```javascript
async function submitReview(formData) {
  // formData už obsahuje všechna pole včetně photos[]
  
  const response = await fetch('https://tvoje-domena.cz/api/submit-review', {
    method: 'POST',
    body: formData  // Pozor: NEPOSÍLAT Content-Type header!
  });
  
  const data = await response.json();
  
  if (data.success) {
    alert(data.message); // "Recenze byla odeslána ke schválení. Děkujeme!"
  } else {
    alert('Chyba: ' + data.error);
  }
}

// Použití:
const form = document.getElementById('review-form');
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(form);
  await submitReview(formData);
});
```

### JavaScript (XHR)

```javascript
function submitReview(formData) {
  const xhr = new XMLHttpRequest();
  
  xhr.open('POST', 'https://tvoje-domena.cz/api/submit-review');
  
  xhr.onload = function() {
    const data = JSON.parse(xhr.responseText);
    
    if (data.success) {
      alert(data.message);
    } else {
      alert('Chyba: ' + data.error);
    }
  };
  
  xhr.send(formData);
}
```

### jQuery

```javascript
$('#review-form').on('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  $.ajax({
    url: 'https://tvoje-domena.cz/api/submit-review',
    type: 'POST',
    data: formData,
    processData: false,  // Důležité!
    contentType: false,  // Důležité!
    success: function(data) {
      alert(data.message);
    },
    error: function(xhr) {
      const data = JSON.parse(xhr.responseText);
      alert('Chyba: ' + data.error);
    }
  });
});
```

### cURL (testování)

```bash
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Jan Novák" \
  -F "email=jan@example.com" \
  -F "product_id=12345" \
  -F "sku=SKU-001" \
  -F "rating=5" \
  -F "comment=Skvělý produkt!" \
  -F "photos[]=@/path/to/photo1.jpg" \
  -F "photos[]=@/path/to/photo2.jpg"
```

### PHP

```php
$ch = curl_init('https://tvoje-domena.cz/api/submit-review');

$data = [
    'name'       => 'Jan Novák',
    'email'      => 'jan@example.com',
    'product_id' => '12345',
    'rating'     => 5,
    'comment'    => 'Skvělý produkt!',
    'photos[]'   => new CURLFile('/path/to/photo1.jpg'),
];

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo $result['message'];
} else {
    echo 'Chyba: ' . $result['error'];
}

curl_close($ch);
```

---

## 📨 Response

### Success (200 OK)

```json
{
  "success": true,
  "message": "Recenze byla odeslána ke schválení. Děkujeme!"
}
```

### Error (4xx/5xx)

```json
{
  "success": false,
  "error": "Zadejte platný e-mail."
}
```

---

## 🚨 Error Codes

| HTTP Code | Popis | Příklad error message |
|-----------|-------|----------------------|
| 400 | Špatný request | "Zadejte jméno (max 100 znaků)." |
| 405 | Špatná metoda | "Metoda není povolena." |
| 422 | Validační chyba | "Hodnocení musí být 1–5." |
| 429 | Rate limit | "Příliš mnoho požadavků. Zkuste to za chvíli." |
| 500 | Server error | "Nelze přiřadit recenzi k e-shopu." |

---

## 🎨 HTML Formulář (příklad)

### Základní formulář

```html
<form id="review-form" enctype="multipart/form-data">
  <!-- Jméno -->
  <label for="name">Vaše jméno *</label>
  <input type="text" id="name" name="name" required maxlength="100">
  
  <!-- Email -->
  <label for="email">Email *</label>
  <input type="email" id="email" name="email" required>
  
  <!-- Shoptet ID produktu (skryté pole, předvyplněné z Shoptet) -->
  <input type="hidden" name="product_id" value="12345">
  
  <!-- SKU produktu (skryté pole, předvyplněné z Shoptet) -->
  <input type="hidden" name="sku" value="SKU-001">
  
  <!-- Hodnocení (volitelné) -->
  <label for="rating">Hodnocení (1-5 hvězdiček)</label>
  <select id="rating" name="rating">
    <option value="">-- Vyberte --</option>
    <option value="1">⭐ (1)</option>
    <option value="2">⭐⭐ (2)</option>
    <option value="3">⭐⭐⭐ (3)</option>
    <option value="4">⭐⭐⭐⭐ (4)</option>
    <option value="5">⭐⭐⭐⭐⭐ (5)</option>
  </select>
  
  <!-- Komentář (volitelné) -->
  <label for="comment">Komentář</label>
  <textarea id="comment" name="comment" maxlength="500"></textarea>
  
  <!-- Fotky (povinné, 1-5 fotek) -->
  <label for="photos">Nahrajte fotky produktu * (1-5 fotek)</label>
  <input type="file" id="photos" name="photos[]" 
         accept="image/jpeg,image/png,image/webp" 
         multiple required>
  
  <!-- Honeypot anti-spam (schovej pomocí CSS) -->
  <input type="text" name="website" style="display:none;" tabindex="-1">
  
  <!-- Submit -->
  <button type="submit">Odeslat recenzi</button>
</form>

<script>
document.getElementById('review-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const button = e.target.querySelector('button[type="submit"]');
  
  button.disabled = true;
  button.textContent = 'Odesílám...';
  
  try {
    const response = await fetch('https://tvoje-domena.cz/api/submit-review', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert(data.message);
      e.target.reset(); // Vyčisti formulář
    } else {
      alert('Chyba: ' + data.error);
    }
  } catch (error) {
    alert('Chyba při odesílání: ' + error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Odeslat recenzi';
  }
});
</script>
```

### Stylovaný formulář s preview fotek

```html
<form id="review-form" enctype="multipart/form-data">
  <div class="form-group">
    <label>Vaše jméno *</label>
    <input type="text" name="name" required maxlength="100" class="form-control">
  </div>
  
  <div class="form-group">
    <label>Email *</label>
    <input type="email" name="email" required class="form-control">
  </div>
  
  <input type="hidden" name="product_id" value="12345">
  <input type="hidden" name="sku" value="SKU-001">
  
  <div class="form-group">
    <label>Hodnocení</label>
    <div class="rating-stars" id="rating-stars">
      <span data-value="1">☆</span>
      <span data-value="2">☆</span>
      <span data-value="3">☆</span>
      <span data-value="4">☆</span>
      <span data-value="5">☆</span>
    </div>
    <input type="hidden" name="rating" id="rating-value">
  </div>
  
  <div class="form-group">
    <label>Komentář</label>
    <textarea name="comment" maxlength="500" class="form-control" rows="4"></textarea>
    <small class="text-muted"><span id="char-count">0</span> / 500 znaků</small>
  </div>
  
  <div class="form-group">
    <label>Fotky produktu * (1-5 fotek)</label>
    <input type="file" name="photos[]" id="photos-input" 
           accept="image/jpeg,image/png,image/webp" 
           multiple required class="form-control">
    <div id="photo-preview" class="photo-preview"></div>
  </div>
  
  <input type="text" name="website" style="display:none;" tabindex="-1">
  
  <button type="submit" class="btn btn-primary">Odeslat recenzi</button>
</form>

<style>
.form-group { margin-bottom: 1.5rem; }
.form-control {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.rating-stars span {
  font-size: 2rem;
  cursor: pointer;
  color: #ccc;
}
.rating-stars span:hover,
.rating-stars span.active {
  color: #ffc107;
}

.photo-preview {
  display: flex;
  gap: 10px;
  margin-top: 10px;
  flex-wrap: wrap;
}
.photo-preview img {
  width: 100px;
  height: 100px;
  object-fit: cover;
  border-radius: 4px;
  border: 2px solid #ddd;
}
</style>

<script>
// Hodnocení hvězdičkami
const stars = document.querySelectorAll('#rating-stars span');
const ratingInput = document.getElementById('rating-value');

stars.forEach(star => {
  star.addEventListener('click', () => {
    const value = star.getAttribute('data-value');
    ratingInput.value = value;
    
    stars.forEach((s, i) => {
      s.textContent = i < value ? '★' : '☆';
      s.classList.toggle('active', i < value);
    });
  });
});

// Počítadlo znaků
const commentField = document.querySelector('textarea[name="comment"]');
const charCount = document.getElementById('char-count');

commentField.addEventListener('input', () => {
  charCount.textContent = commentField.value.length;
});

// Preview fotek
const photosInput = document.getElementById('photos-input');
const photoPreview = document.getElementById('photo-preview');

photosInput.addEventListener('change', (e) => {
  photoPreview.innerHTML = '';
  
  const files = Array.from(e.target.files);
  
  if (files.length > 5) {
    alert('Maximálně 5 fotek!');
    e.target.value = '';
    return;
  }
  
  files.forEach(file => {
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = document.createElement('img');
      img.src = e.target.result;
      photoPreview.appendChild(img);
    };
    reader.readAsDataURL(file);
  });
});

// Submit
document.getElementById('review-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const button = e.target.querySelector('button[type="submit"]');
  
  button.disabled = true;
  button.textContent = 'Odesílám...';
  
  try {
    const response = await fetch('https://tvoje-domena.cz/api/submit-review', {
      method: 'POST',
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert(data.message);
      e.target.reset();
      photoPreview.innerHTML = '';
      stars.forEach(s => s.textContent = '☆');
      charCount.textContent = '0';
    } else {
      alert('Chyba: ' + data.error);
    }
  } catch (error) {
    alert('Chyba: ' + error.message);
  } finally {
    button.disabled = false;
    button.textContent = 'Odeslat recenzi';
  }
});
</script>
```

---

## 🔐 Bezpečnost

### Rate Limiting

**Ochrana:** Max 5 requestů z jedné IP za 10 minut

```php
// Implementováno v Review::checkRateLimit()
Review::checkRateLimit($ip, 'submit-review', 5, 600)
```

### Honeypot Anti-Spam

**Skryté pole `website`:**
- Lidé ho nevidí (display:none)
- Boti ho vyplní
- Pokud je vyplněné → předstíráme úspěch, ale neuložíme

```html
<input type="text" name="website" style="display:none;" tabindex="-1">
```

### Validace fotek

```php
// ImageHandler provádí validaci:
- Povolené typy: JPG, PNG, WEBP
- Max velikost: nastavitelné
- Resize na max rozměry
- Vytvoření thumbnailu
- Bezpečné uložení s UUID
```

### SQL Injection

✅ Použití prepared statements ve všech queries

### XSS Protection

✅ Všechny výstupy jsou escapované v admin UI

---

## 📊 Co se děje po odeslání

### 1. Validace (server-side)

```php
✓ Jméno: povinné, max 100 znaků
✓ Email: validní formát
✓ Fotky: 1-5 fotek, povolené formáty
✓ Rating: 1-5 nebo null
✓ Komentář: max 500 znaků
✓ Rate limit: max 5 requestů/10 min
✓ Honeypot: field 'website' musí být prázdný
```

### 2. Zpracování fotek

```php
✓ Resize na max rozměry (např. 1920x1920)
✓ Vytvoření thumbnailu (např. 300x300)
✓ Uložení do /public/uploads/reviews/{user_id}/{uuid}/
✓ Názvy: original_1.jpg, thumb_1.jpg, atd.
```

### 3. Uložení do databáze

```sql
INSERT INTO reviews (
    user_id,        -- Automaticky přiřazeno podle product_id/sku
    product_id,     -- Pokud nalezen v products tabulce
    shoptet_id,     -- Z formuláře
    sku,            -- Z formuláře
    author_name,    -- Z formuláře
    author_email,   -- Z formuláře
    rating,         -- Z formuláře (1-5 nebo NULL)
    comment,        -- Z formuláře
    photos,         -- JSON pole: [{"path":"...", "thumb":"..."}]
    status,         -- 'pending'
    created_at      -- NOW()
)
```

### 4. Email notifikace

**Odesláno na:** Superadmin email (definovaný v config)

**Obsahuje:**
- Jméno a email autora
- SKU/Shoptet ID produktu
- Počet fotek
- Hodnocení (hvězdičky)
- Komentář
- Link na schválení v admin UI

### 5. Response

```json
{
  "success": true,
  "message": "Recenze byla odeslána ke schválení. Děkujeme!"
}
```

---

## 🎯 Workflow po odeslání

```
Uživatel odešle formulář
         ↓
API validuje + zpracuje fotky
         ↓
Uloží do DB se statusem 'pending'
         ↓
Pošle email adminovi
         ↓
Admin přijde do ShopCode
         ↓
/reviews → vidí nové recenze
         ↓
Schválí/zamítne
         ↓
Status = 'approved' nebo 'rejected'
         ↓
Schválené recenze → export do Shoptet CSV
         ↓
Import do Shoptet
         ↓
Recenze se zobrazí na e-shopu
```

---

## 🧪 Testování

### Test 1: Minimální request

```bash
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Test User" \
  -F "email=test@example.com" \
  -F "photos[]=@test.jpg"
```

**Očekávaný response:**
```json
{
  "success": true,
  "message": "Recenze byla odeslána ke schválení. Děkujeme!"
}
```

### Test 2: Kompletní request

```bash
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Jan Novák" \
  -F "email=jan@example.com" \
  -F "product_id=12345" \
  -F "sku=SKU-001" \
  -F "rating=5" \
  -F "comment=Skvělý produkt, doporučuji!" \
  -F "photos[]=@photo1.jpg" \
  -F "photos[]=@photo2.jpg"
```

### Test 3: Validační chyby

```bash
# Chybný email
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Test" \
  -F "email=invalid-email" \
  -F "photos[]=@test.jpg"

# Response:
{
  "success": false,
  "error": "Zadejte platný e-mail."
}
```

### Test 4: Rate limiting

```bash
# Odešli 6 requestů rychle za sebou
for i in {1..6}; do
  curl -X POST https://tvoje-domena.cz/api/submit-review \
    -F "name=Test$i" \
    -F "email=test$i@example.com" \
    -F "photos[]=@test.jpg"
done

# 6. request vrátí:
{
  "success": false,
  "error": "Příliš mnoho požadavků. Zkuste to za chvíli."
}
```

---

## 🔧 Konfigurace

### CORS (současný stav)

```php
// public/api/submit-review.php
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = defined('SHOPTET_DOMAINS') ? SHOPTET_DOMAINS : [];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} elseif (empty($allowed)) {
    // Dev mode — povolíme vše
    header('Access-Control-Allow-Origin: *');
}
```

**Produkční nastavení (config/config.php):**

```php
define('SHOPTET_DOMAINS', [
    'https://muj-eshop.myshoptet.com',
    'https://www.muj-eshop.cz',
]);
```

### Rate Limiting

```php
// Max 5 requestů za 10 minut (600 sekund)
Review::checkRateLimit($ip, 'submit-review', 5, 600);

// Upravit v submit-review.php řádek 49:
if (!Review::checkRateLimit($ip, 'submit-review', 10, 3600)) {
    // Max 10 requestů za hodinu
}
```

---

## 📝 Databázová struktura

### Tabulka `reviews`

```sql
CREATE TABLE reviews (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    product_id    INT UNSIGNED,
    shoptet_id    VARCHAR(255),
    sku           VARCHAR(255),
    author_name   VARCHAR(100) NOT NULL,
    author_email  VARCHAR(255) NOT NULL,
    rating        TINYINT UNSIGNED,           -- 1-5 nebo NULL
    comment       TEXT,
    photos        JSON,                        -- [{"path":"...", "thumb":"..."}]
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_note    TEXT,
    imported      TINYINT(1) DEFAULT 0,
    imported_at   DATETIME,
    reviewed_at   DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    KEY idx_user (user_id),
    KEY idx_product (product_id),
    KEY idx_status (status),
    KEY idx_imported (imported)
);
```

---

## ✅ Checklist implementace

- [x] API endpoint `/api/submit-review` existuje
- [x] CORS je nakonfigurován
- [x] Rate limiting je aktivní
- [x] Honeypot anti-spam funguje
- [x] Validace všech polí
- [x] Zpracování a resize fotek
- [x] Uložení do databáze
- [x] Email notifikace
- [x] Error handling
- [x] Documentation

---

## 🚀 Quick Start

### 1. HTML formulář

```html
<form id="review-form" enctype="multipart/form-data">
  <input type="text" name="name" required>
  <input type="email" name="email" required>
  <input type="hidden" name="product_id" value="12345">
  <input type="file" name="photos[]" multiple required>
  <button type="submit">Odeslat</button>
</form>
```

### 2. JavaScript

```javascript
document.getElementById('review-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const response = await fetch('https://tvoje-domena.cz/api/submit-review', {
    method: 'POST',
    body: formData
  });
  
  const data = await response.json();
  alert(data.success ? data.message : data.error);
});
```

### 3. Hotovo! 🎉

---

**Datum:** 25. února 2026  
**Status:** ✅ Production Ready  
**Endpoint:** `/api/submit-review`  
**Auth:** Není potřeba (veřejný)  
**CORS:** `*` (všechny domény)
