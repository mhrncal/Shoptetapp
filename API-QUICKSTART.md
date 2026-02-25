# ⚡ API Quick Start - Za 2 minuty

## ✅ API je JIŽ AKTIVNÍ!

**Nepotřebuješ nic nastavovat!**
- ✅ CORS je nastaven na `*` (všechny domény)
- ✅ Není potřeba whitelistovat URL adresy
- ✅ Funguje okamžitě

---

## 🔑 Krok 1: Vytvoř API token (30 sekund)

1. Přihlaš se do ShopCode
2. Jdi na **Profil** → **API tokeny**
3. Klikni **Vytvořit nový token**
4. Vyber oprávnění (např. `products:read`)
5. Klikni **Vytvořit**
6. **ZKOPÍRUJ TOKEN** (zobrazí se jen jednou!)

```
sc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
```

---

## 🧪 Krok 2: Otestuj API (30 sekund)

### Browser Console:

Otevři DevTools (F12) a spusť:

```javascript
fetch('https://tvoje-domena.cz/api/v1/products?page=1&per_page=5', {
  headers: {
    'Authorization': 'Bearer sc_...'  // Vlož svůj token
  }
})
.then(r => r.json())
.then(data => console.log(data));
```

### cURL:

```bash
curl -H "Authorization: Bearer sc_..." \
     https://tvoje-domena.cz/api/v1/products
```

---

## 📊 Dostupné endpointy

```
GET /api/v1/products          - Seznam produktů
GET /api/v1/products/{id}     - Detail produktu
GET /api/v1/faq               - FAQ
GET /api/v1/branches          - Pobočky
GET /api/v1/events            - Akce
```

---

## 💻 Použití v kódu

### JavaScript:

```javascript
const token = 'sc_...';

async function getProducts() {
  const response = await fetch('https://tvoje-domena.cz/api/v1/products', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return await response.json();
}
```

### PHP:

```php
$token = 'sc_...';

$ch = curl_init('https://tvoje-domena.cz/api/v1/products');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
```

---

## 🎯 Response příklad:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "SKU-001",
      "name": "Produkt ABC",
      "price": 299.00,
      "category": "Kategorie",
      "brand": "Značka",
      "images": ["https://..."],
      "parameters": {"Barva": "černá"}
    }
  ],
  "pagination": {
    "total": 156,
    "page": 1,
    "per_page": 50,
    "pages": 4
  }
}
```

---

## 🔒 Bezpečnost

- ✅ Token je bezpečný (SHA-256 hash)
- ✅ Můžeš ho kdykoli revokovat
- ✅ Nastav expiraci (volitelné)
- ✅ Sleduj last_used_at

---

## 📚 Kompletní dokumentace

**Pro více info viz:**
- `docs/API-DOCUMENTATION.md` - Kompletní dokumentace
- `tests/ShopCode-API.postman_collection.json` - Postman kolekce

---

## ❓ FAQ

**Q: Musím whitelistovat domény?**  
A: NE! CORS je nastaven na `*` (všechny domény).

**Q: Funguje to z JavaScriptu na webu?**  
A: ANO! CORS headers jsou správně nastavené.

**Q: Kde najdu token?**  
A: Profil → API tokeny → Vytvořit nový

**Q: Co když token ztratím?**  
A: Vytvoř nový, starý revokuj.

**Q: Mohu použít více tokenů?**  
A: ANO! Můžeš mít neomezený počet.

---

**To je vše!** 🎉

API je **hotové** a **funguje** ze všech domén!
