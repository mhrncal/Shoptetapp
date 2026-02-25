# 🚀 ShopCode API - Kompletní dokumentace

## ✅ Současný stav

**API je JIŽ PLNĚ FUNKČNÍ a dostupné ze všech domén!**

- ✅ REST API v1
- ✅ Bearer token autentizace
- ✅ CORS `Access-Control-Allow-Origin: *` (přístup ze všech domén)
- ✅ Permissions systém
- ✅ Rate limiting není aktivní (volný přístup)

**Nepotřebuješ manuálně zadávat povolené URL adresy - API funguje odnikud!**

---

## 🔑 Autentizace

### 1. Vytvoř API token

**Přes UI:**
1. Přihlaš se do ShopCode
2. Jdi na **Profil** → **API tokeny**
3. Klikni **Vytvořit nový token**
4. Zadej název (např. "Můj web")
5. Vyber oprávnění
6. Klikni **Vytvořit**
7. **ZKOPÍRUJ TOKEN** (zobrazí se jen jednou!)

**Výsledek:**
```
sc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
```

### 2. Použij token v API requestech

**Header:**
```
Authorization: Bearer sc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
```

---

## 📚 API Endpoints

**Base URL:** `https://tvoje-domena.cz/api/v1`

### Products

#### GET `/api/v1/products`

Vrátí seznam produktů s filtrováním a paginací.

**Query parametry:**
- `page` (int, default: 1) - Číslo stránky
- `per_page` (int, default: 50, max: 200) - Počet položek na stránku
- `search` (string) - Hledání v názvu a popisu
- `category` (string) - Filtr podle kategorie
- `brand` (string) - Filtr podle značky
- `sort` (string) - Řazení (např. `price_asc`, `name_desc`)

**Příklad requestu:**
```bash
curl -X GET "https://tvoje-domena.cz/api/v1/products?page=1&per_page=10&search=tricko" \
  -H "Authorization: Bearer sc_..."
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "shoptet_id": "12345",
      "code": "SKU-001",
      "name": "Tričko černé",
      "description": "Bavlněné tričko...",
      "price": 399.00,
      "currency": "CZK",
      "category": "Oblečení",
      "brand": "Nike",
      "availability": "skladem",
      "images": [
        "https://cdn.myshoptet.com/image1.jpg",
        "https://cdn.myshoptet.com/image2.jpg"
      ],
      "parameters": {
        "Barva": "černá",
        "Materiál": "bavlna"
      },
      "created_at": "2026-02-25 10:30:00",
      "updated_at": "2026-02-25 10:30:00"
    }
  ],
  "pagination": {
    "total": 156,
    "page": 1,
    "per_page": 10,
    "pages": 16
  }
}
```

---

#### GET `/api/v1/products/{id}`

Vrátí detail jednoho produktu včetně variant.

**Příklad requestu:**
```bash
curl -X GET "https://tvoje-domena.cz/api/v1/products/1" \
  -H "Authorization: Bearer sc_..."
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "shoptet_id": "12345",
    "code": "SKU-001",
    "name": "Tričko černé",
    "description": "Bavlněné tričko...",
    "price": 399.00,
    "currency": "CZK",
    "category": "Oblečení",
    "brand": "Nike",
    "availability": "skladem",
    "images": [
      "https://cdn.myshoptet.com/image1.jpg"
    ],
    "parameters": {
      "Barva": "černá",
      "Materiál": "bavlna"
    },
    "variants": [
      {
        "id": 10,
        "shoptet_variant_id": "12346",
        "code": "SKU-001-M",
        "name": "Tričko černé M",
        "price": 399.00,
        "stock": 5,
        "parameters": {
          "Velikost": "M"
        }
      },
      {
        "id": 11,
        "shoptet_variant_id": "12347",
        "code": "SKU-001-L",
        "name": "Tričko černé L",
        "price": 399.00,
        "stock": 3,
        "parameters": {
          "Velikost": "L"
        }
      }
    ],
    "created_at": "2026-02-25 10:30:00",
    "updated_at": "2026-02-25 10:30:00"
  }
}
```

---

### FAQ

#### GET `/api/v1/faq`

Vrátí seznam FAQ (pouze veřejné).

**Query parametry:**
- `product_id` (int) - Filtr podle produktu
- `search` (string) - Hledání v otázce a odpovědi

**Příklad requestu:**
```bash
curl -X GET "https://tvoje-domena.cz/api/v1/faq?product_id=1" \
  -H "Authorization: Bearer sc_..."
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "product_id": 1,
      "question": "Jak prat toto tričko?",
      "answer": "Perte na 30°C...",
      "is_public": true,
      "sort_order": 0,
      "created_at": "2026-02-25 10:30:00"
    }
  ],
  "total": 1
}
```

---

### Branches (Pobočky)

#### GET `/api/v1/branches`

Vrátí seznam poboček s otevírací dobou.

**Příklad requestu:**
```bash
curl -X GET "https://tvoje-domena.cz/api/v1/branches" \
  -H "Authorization: Bearer sc_..."
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Pobočka Praha",
      "address": "Václavské náměstí 1",
      "city": "Praha",
      "zip": "11000",
      "phone": "+420 123 456 789",
      "email": "praha@example.com",
      "opening_hours": [
        {
          "day": "Pondělí",
          "day_index": 1,
          "closed": false,
          "open_from": "08:00",
          "open_to": "18:00",
          "note": null
        },
        {
          "day": "Úterý",
          "day_index": 2,
          "closed": false,
          "open_from": "08:00",
          "open_to": "18:00",
          "note": null
        },
        {
          "day": "Sobota",
          "day_index": 6,
          "closed": true,
          "open_from": null,
          "open_to": null,
          "note": "Zavřeno"
        }
      ]
    }
  ],
  "total": 1
}
```

---

### Events (Akce)

#### GET `/api/v1/events`

Vrátí seznam aktivních akcí.

**Query parametry:**
- `upcoming` (bool) - Pouze nadcházející akce
- `past` (bool) - Pouze minulé akce

**Příklad requestu:**
```bash
curl -X GET "https://tvoje-domena.cz/api/v1/events?upcoming=1" \
  -H "Authorization: Bearer sc_..."
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "title": "Výprodej zimní kolekce",
      "description": "Slevy až 50%",
      "start_date": "2026-03-01",
      "end_date": "2026-03-31",
      "is_active": true,
      "created_at": "2026-02-20 10:00:00"
    }
  ],
  "total": 1
}
```

---

## 🔒 Permissions (Oprávnění)

Každý token má specifická oprávnění:

| Permission | Popis | Endpointy |
|------------|-------|-----------|
| `products:read` | Čtení produktů | `/api/v1/products`, `/api/v1/products/{id}` |
| `faq:read` | Čtení FAQ | `/api/v1/faq` |
| `branches:read` | Čtení poboček | `/api/v1/branches` |
| `events:read` | Čtení akcí | `/api/v1/events` |

**Při chybějícím oprávnění:**
```json
{
  "error": "Chybí oprávnění: products:read",
  "code": 403
}
```

---

## 🌐 CORS (Cross-Origin Resource Sharing)

**Současné nastavení:**
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
```

**To znamená:**
- ✅ API je přístupné **ze všech domén**
- ✅ Není potřeba whitelistovat URL adresy
- ✅ Funguje z frontendu (JavaScript/React/Vue/Angular)
- ✅ Funguje z backendu (PHP/Node/Python)

---

## 💻 Příklady použití

### JavaScript (Fetch)

```javascript
async function getProducts() {
  const response = await fetch('https://tvoje-domena.cz/api/v1/products?page=1&per_page=10', {
    method: 'GET',
    headers: {
      'Authorization': 'Bearer sc_a1b2c3d4e5f6...',
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  console.log(data);
}
```

### jQuery

```javascript
$.ajax({
  url: 'https://tvoje-domena.cz/api/v1/products',
  method: 'GET',
  headers: {
    'Authorization': 'Bearer sc_a1b2c3d4e5f6...'
  },
  success: function(data) {
    console.log(data);
  }
});
```

### cURL (Bash)

```bash
curl -X GET "https://tvoje-domena.cz/api/v1/products?search=tricko" \
  -H "Authorization: Bearer sc_a1b2c3d4e5f6..." \
  -H "Content-Type: application/json"
```

### PHP

```php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://tvoje-domena.cz/api/v1/products');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer sc_a1b2c3d4e5f6...',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

print_r($data);
```

### Python

```python
import requests

headers = {
    'Authorization': 'Bearer sc_a1b2c3d4e5f6...',
    'Content-Type': 'application/json'
}

response = requests.get(
    'https://tvoje-domena.cz/api/v1/products',
    headers=headers
)

data = response.json()
print(data)
```

### Node.js (axios)

```javascript
const axios = require('axios');

const response = await axios.get('https://tvoje-domena.cz/api/v1/products', {
  headers: {
    'Authorization': 'Bearer sc_a1b2c3d4e5f6...'
  }
});

console.log(response.data);
```

---

## 🧪 Testování API

### 1. Postman

**Import kolekce:**
```json
{
  "info": {
    "name": "ShopCode API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get Products",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "Authorization",
            "value": "Bearer {{api_token}}",
            "type": "text"
          }
        ],
        "url": {
          "raw": "{{base_url}}/api/v1/products?page=1&per_page=10",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "products"],
          "query": [
            {"key": "page", "value": "1"},
            {"key": "per_page", "value": "10"}
          ]
        }
      }
    }
  ],
  "variable": [
    {
      "key": "base_url",
      "value": "https://tvoje-domena.cz"
    },
    {
      "key": "api_token",
      "value": "sc_..."
    }
  ]
}
```

### 2. Browser DevTools

Otevři konzoli v prohlížeči a spusť:

```javascript
fetch('https://tvoje-domena.cz/api/v1/products?page=1', {
  headers: {
    'Authorization': 'Bearer sc_...'
  }
})
.then(r => r.json())
.then(data => console.log(data));
```

---

## 🚨 Error Handling

### 401 Unauthorized

```json
{
  "error": "Chybí Authorization: Bearer token",
  "code": 401
}
```

**Příčiny:**
- Token není v headeru
- Token je neplatný
- Token vypršel
- Token byl revokován

### 403 Forbidden

```json
{
  "error": "Chybí oprávnění: products:read",
  "code": 403
}
```

**Příčiny:**
- Token nemá potřebné permission
- Uživatelský účet není schválený

### 404 Not Found

```json
{
  "success": false,
  "error": "Produkt nenalezen",
  "code": 404
}
```

**Příčiny:**
- Produkt s daným ID neexistuje
- Produkt patří jinému uživateli

---

## ⚙️ Konfigurace CORS (pokud chceš omezit domény)

**Současný stav (všechny domény):**
```php
header('Access-Control-Allow-Origin: *');
```

**Pokud chceš omezit na konkrétní domény:**

Uprav `src/Controllers/ApiController.php` (řádek 31):

```php
// Povolené domény
$allowedOrigins = [
    'https://muj-web.cz',
    'https://www.muj-web.cz',
    'https://app.muj-web.cz',
    'http://localhost:3000', // pro development
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Zakázat ostatní domény
    header('Access-Control-Allow-Origin: https://muj-web.cz');
}
```

**Ale doporučuji nechat `*` (všechny domény), protože:**
- ✅ Bearer token už zajišťuje bezpečnost
- ✅ Flexibilnější (funguje ze všech domén)
- ✅ Jednodušší na údržbu

---

## 📊 Rate Limiting (volitelné)

**Současný stav:** Není implementován rate limiting.

**Pokud chceš přidat:**

Vytvoř `src/Middleware/RateLimitMiddleware.php`:

```php
<?php
namespace ShopCode\Middleware;

class RateLimitMiddleware
{
    private const LIMIT = 100; // requestů
    private const WINDOW = 60;  // sekund
    
    public static function check(int $userId): void
    {
        $key = "rate_limit:user:$userId";
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        
        $count = $redis->incr($key);
        
        if ($count === 1) {
            $redis->expire($key, self::WINDOW);
        }
        
        if ($count > self::LIMIT) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Rate limit exceeded. Try again later.',
                'code' => 429
            ]);
            exit;
        }
        
        header('X-RateLimit-Limit: ' . self::LIMIT);
        header('X-RateLimit-Remaining: ' . max(0, self::LIMIT - $count));
    }
}
```

Pak přidej do `ApiAuthMiddleware::handle()`:
```php
RateLimitMiddleware::check($token['user_id']);
```

---

## 🔐 Bezpečnostní best practices

### 1. HTTPSOnly (produkce)

Ujisti se, že API běží pouze přes HTTPS:

```php
// src/Controllers/ApiController.php - přidej do __construct()
if ($_SERVER['HTTPS'] !== 'on' && $_ENV['APP_ENV'] === 'production') {
    http_response_code(403);
    echo json_encode(['error' => 'HTTPS required', 'code' => 403]);
    exit;
}
```

### 2. Token rotation

- ✅ Tokeny mají expiraci (volitelné)
- ✅ Lze revokovat přes UI
- ✅ Last used tracking

### 3. Monitoring

Sleduj:
- Počet API requestů
- Failed authentication attempts
- Token usage

```sql
-- Nejpoužívanější tokeny
SELECT 
    t.name,
    t.token_prefix,
    t.last_used_at,
    u.email
FROM api_tokens t
JOIN users u ON u.id = t.user_id
WHERE t.is_active = 1
ORDER BY t.last_used_at DESC
LIMIT 10;
```

---

## 📝 Changelog

### v1.0 (Současná verze)
- ✅ REST API endpointy (products, faq, branches, events)
- ✅ Bearer token autentizace
- ✅ Permissions systém
- ✅ CORS `*` (všechny domény)
- ✅ JSON responses
- ✅ Error handling
- ✅ Pagination support

### Plánované featury
- [ ] Rate limiting
- [ ] Webhooks
- [ ] POST/PUT/DELETE operace
- [ ] API verze v2
- [ ] GraphQL endpoint

---

## 🎯 Quick Start

**3 kroky k použití API:**

1. **Vytvoř token:**
   - UI → Profil → API tokeny → Vytvořit

2. **Zkopíruj token:**
   ```
   sc_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
   ```

3. **Použij v requestu:**
   ```bash
   curl -H "Authorization: Bearer sc_..." \
        https://tvoje-domena.cz/api/v1/products
   ```

✅ **Hotovo!** API funguje ze všech domén.

---

## 📞 Support

**Pokud něco nefunguje:**

1. Zkontroluj token (není vypršelý, není revokovaný)
2. Zkontroluj permissions
3. Zkontroluj CORS headers v response
4. Zkontroluj DB (tabulka `api_tokens`)
5. Zkontroluj logy serveru

---

**Datum:** 25. února 2026  
**Verze:** v1.0  
**Status:** ✅ Production Ready
