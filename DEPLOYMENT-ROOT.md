# 🚀 Deployment - Document Root v ROOTU projektu

## ✅ Aktuální konfigurace

**Document root:** `/srv/app/` (root projektu)

**Struktura:**
```
/srv/app/                    ← WEBROOT (document root zde)
├── index.php                ← Entry point ✅
├── .htaccess                ← Routing ✅
├── config/                  ← Konfigurace
├── src/                     ← Aplikační kód
├── public/                  ← Statické soubory
│   ├── assets/              → /assets/ (přes .htaccess)
│   ├── uploads/             → /uploads/
│   ├── feeds/               → /feeds/
│   └── api/                 → /api/
│       └── submit-review.php
├── cron/                    ← CRON skripty
├── database/                ← DB schémata
└── tmp/                     ← Temp soubory
```

---

## 🔧 Jak funguje routing:

### `.htaccess` v rootu:

1. **Statické soubory** `/assets/`, `/uploads/`, `/feeds/`
   → přesměrovává na `public/assets/`, `public/uploads/`, atd.

2. **API endpoint** `/api/*`
   → přesměrovává na `public/api/*`

3. **Vše ostatní** (`/products`, `/login`, atd.)
   → `index.php` v rootu

---

## 📋 Deployment na Roští:

### 1. Nastav document root na `/srv/app/`

To je **již výchozí** na Roští! Nemusíš nic nastavovat.

### 2. Vytvoř config

```bash
cd /srv/app
cp config/config.example.php config/config.php
nano config/config.php
```

Uprav:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_URL=https://aplikace.shopcode.cz`
- `APP_ENV=production`
- `APP_DEBUG=false`
- SMTP údaje (viz ROSTI_SMTP.md)

### 3. Importuj databázi

```bash
mysql -u user -p database < database/schema.sql
mysql -u user -p database < database/seed.sql
```

### 4. Nastav oprávnění

```bash
chmod 755 public/uploads public/feeds tmp
chmod 600 config/config.php
```

### 5. Nastav CRON

V Roští admin:
```
0 18 * * * php /srv/app/cron/generate-xml-feeds.php
```

---

## 🧪 Test

```bash
# Homepage
curl -I https://aplikace.shopcode.cz/

# API
curl -X POST https://aplikace.shopcode.cz/api/submit-review \
  -F "user_id=1" \
  -F "name=Test" \
  -F "email=test@example.com" \
  -F "photos[]=@test.jpg"
```

---

## ✅ URL mapování:

| URL | Fyzický soubor |
|-----|----------------|
| `/` | `index.php` |
| `/login` | `index.php` → router |
| `/products` | `index.php` → router |
| `/assets/css/style.css` | `public/assets/css/style.css` |
| `/uploads/foto.jpg` | `public/uploads/foto.jpg` |
| `/feeds/user_1.xml` | `public/feeds/user_1.xml` |
| `/api/submit-review` | `public/api/submit-review.php` |

---

## 🔒 Bezpečnost:

`.htaccess` blokuje přístup k:
- `/config/` - 403 Forbidden
- `/src/` - 403 Forbidden
- `/database/` - 403 Forbidden
- `/cron/` - 403 Forbidden
- `/tmp/` - 403 Forbidden

---

**Datum:** 3. března 2026  
**Status:** ✅ Production Ready
