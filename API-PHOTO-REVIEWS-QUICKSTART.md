# ⚡ Photo Review API - Quick Start

## ✅ API je JIŽ FUNKČNÍ!

Endpoint `/api/submit-review` je připraven k použití.

---

## 🎯 Základní použití (30 sekund)

### HTML formulář:

```html
<form id="review-form" enctype="multipart/form-data">
  <input type="text" name="name" placeholder="Vaše jméno" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="hidden" name="product_id" value="12345">
  <input type="file" name="photos[]" accept="image/*" multiple required>
  <button type="submit">Odeslat recenzi</button>
</form>
```

### JavaScript:

```javascript
document.getElementById('review-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  
  const response = await fetch('https://tvoje-domena.cz/api/submit-review', {
    method: 'POST',
    body: formData
  });
  
  const data = await response.json();
  
  if (data.success) {
    alert(data.message);
    e.target.reset();
  } else {
    alert('Chyba: ' + data.error);
  }
});
```

✅ **Hotovo!**

---

## 📋 Povinná pole

- `name` - Jméno autora
- `email` - Email autora  
- `photos[]` - 1-5 fotek (JPG/PNG/WEBP)

## 📋 Volitelná pole

- `product_id` - Shoptet ID produktu
- `sku` - SKU produktu
- `rating` - Hodnocení 1-5
- `comment` - Komentář (max 500 znaků)

---

## 🧪 Test (cURL)

```bash
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Jan Novák" \
  -F "email=jan@example.com" \
  -F "photos[]=@test.jpg"
```

**Response:**
```json
{
  "success": true,
  "message": "Recenze byla odeslána ke schválení. Děkujeme!"
}
```

---

## 📊 Co se stane po odeslání?

1. ✅ Validace dat
2. ✅ Zpracování fotek (resize + thumbnail)
3. ✅ Uložení do databáze (status: pending)
4. ✅ Email adminovi
5. ✅ Admin schválí/zamítne v UI

---

## 🔒 Bezpečnost

- ✅ Rate limit: 5 requestů/10 min z IP
- ✅ Honeypot anti-spam
- ✅ Validace všech polí
- ✅ Safe file upload

---

## 🌐 CORS

```
Access-Control-Allow-Origin: *
```

Funguje ze všech domén!

---

## 📚 Kompletní dokumentace

Pro detaily viz: `docs/API-PHOTO-REVIEWS.md`

---

**Status:** ✅ Ready  
**Endpoint:** `POST /api/submit-review`  
**Auth:** Není potřeba
