# 🛒 ShopCode - E-commerce Platform

**Multitenantní platforma pro správu Shoptet e-shopů s pokročilými funkcemi pro fotorecenze, import produktů a API.**

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql)](https://www.mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap)](https://getbootstrap.com)

---

## ✨ Klíčové funkce

### 📸 Fotorecenze
- **API endpoint** pro příjem fotorecenzí z formulářů
- **Admin UI** pro schvalování/zamítání recenzí
- **Automatické zpracování** fotek (resize, thumbnail)
- **CSV/XML export** pro import do Shoptetu
- **Automatické XML feedy** (denně v 18:00)
- **Email notifikace** při nové recenzi

### 📦 Import produktů
- **XML/CSV parsing** z různých zdrojů
- **Automatické mapování** polí
- **Varianty produktů** (velikosti, barvy, atd.)
- **CRON worker** pro pravidelný import
- **Queue system** pro zpracování na pozadí

### 🔌 REST API
- **Bearer token** autentizace
- **CORS** podpora pro všechny domény
- **Rate limiting** ochrana
- **Endpointy:** produkty, FAQ, pobočky, události
- **Pagination** a filtrování
- **Postman kolekce** pro testování

### 👥 Multi-tenant
- **Izolovaná data** pro každého klienta
- **Vlastní XML feedy** per uživatel
- **Permission systém**
- **Admin oversight**

### 🛡️ Bezpečnost
- **CSRF ochrana**
- **Rate limiting** na API i formuláře
- **Honeypot anti-spam**
- **Bezpečné nahrávání** souborů
- **Password hashing** (bcrypt)

---

## 🚀 Quick Start

### Požadavky
- PHP 8.2+
- MySQL 8.0+
- Apache/Nginx
- Composer (volitelné)

### Instalace (5 minut)

```bash
# 1. Naklonuj repo
git clone https://github.com/mhrncal/Shoptetapp.git
cd Shoptetapp

# 2. Nakopíruj .env
cp .env.example .env

# 3. Uprav .env (database credentials, app URL, atd.)
nano .env

# 4. Vytvoř databázi
mysql -u root -p -e "CREATE DATABASE shopcode CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Importuj schéma
mysql -u root -p shopcode < database/schema.sql
mysql -u root -p shopcode < database/seed.sql

# 6. Nastav oprávnění
chmod 755 public/uploads tmp
chown -R www-data:www-data public/uploads tmp

# 7. Nastav document root na /public
# (viz docs/DEPLOYMENT.md)

# 8. Přihlaš se
# URL: http://localhost
# Email: admin@shopcode.local
# Heslo: admin123
```

**Hotovo!** 🎉

---

## 📚 Dokumentace

### Začínáme
- [🚀 Production Deployment](docs/DEPLOYMENT.md) - Kompletní deployment guide
- [🔧 Environment Variables](.env.example) - Všechny .env proměnné

### Features
- [📸 Photo Reviews API](docs/API-PHOTO-REVIEWS.md) - API pro fotorecenze
- [📊 CSV/XML Export](docs/CSV-XML-EXPORT.md) - Export recenzí do Shoptetu
- [🔌 REST API](docs/API-DOCUMENTATION.md) - Kompletní API dokumentace
- [📋 CRON Jobs](docs/CRON-SAFETY.md) - Bezpečnostní mechanismy

### Testování
- [🧪 Testing Guide](docs/TESTING-GUIDE.md) - Jak testovat CSV import
- [📮 Postman](tests/ShopCode-API.postman_collection.json) - API kolekce

---

## 🏗️ Architektura

```
┌─────────────────────────────────────────┐
│ Frontend: Bootstrap 5.3 + jQuery        │
├─────────────────────────────────────────┤
│ Backend: PHP 8.2+ (Pure OOP MVC)        │
│  ├─ Router (custom)                     │
│  ├─ Controllers                         │
│  ├─ Models (Active Record pattern)     │
│  ├─ Services (business logic)          │
│  └─ Views (PHP templates)              │
├─────────────────────────────────────────┤
│ Database: MySQL 8.0+ (utf8mb4)         │
└─────────────────────────────────────────┘
```

---

## 🔌 API Quick Start

### 1. Vytvoř API token
```
Přihlaš se → Profil → API tokeny → Vytvořit nový token
```

### 2. Test request
```bash
curl -H "Authorization: Bearer sc_your_token_here" \
     https://tvoje-domena.cz/api/v1/products
```

### 3. Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "SKU-001",
      "name": "Tričko černé",
      "price": 399.00
    }
  ]
}
```

**Více:** [API Documentation](docs/API-DOCUMENTATION.md)

---

## 📸 Photo Reviews Quick Start

### 1. HTML formulář
```html
<form id="review-form" enctype="multipart/form-data">
  <input type="text" name="name" required>
  <input type="email" name="email" required>
  <input type="hidden" name="product_id" value="SKU-001">
  <input type="file" name="photos[]" accept="image/*" multiple required>
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

**Více:** [Photo Reviews API](docs/API-PHOTO-REVIEWS.md)

---

## ⚙️ CRON Jobs

```bash
# XML Feed Generator (denně v 18:00)
0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1

# Health Monitor (každých 15 min)
*/15 * * * * bash /var/www/shopcode/scripts/cron-health-check.sh >> /var/log/shopcode-monitor.log 2>&1
```

**Bezpečnost:**
- ✅ Mutex lock - nepustí 2 instance
- ✅ Hung process detection - auto-recovery do 30 min
- ✅ Timeout protection - max 10 min běhu
- ✅ Error isolation - chyba u jednoho ≠ pád všech

**Více:** [CRON Safety](docs/CRON-SAFETY.md)

---

## 🛡️ Bezpečnost

- ✅ CSRF tokens
- ✅ Rate limiting (API + forms)
- ✅ SQL injection prevence
- ✅ XSS protection
- ✅ File upload validace
- ✅ Honeypot anti-spam
- ✅ Password hashing (bcrypt)

---

## 📊 Monitoring

```bash
# CRON logy
tail -f /var/log/shopcode-xml-feeds.log

# Health check
bash scripts/cron-health-check.sh
```

---

## 🎯 Roadmap

### ✅ Hotovo
- [x] Multi-tenant architecture
- [x] Photo reviews API
- [x] CSV/XML export
- [x] REST API
- [x] CRON automation
- [x] Health monitoring

### 🚧 V plánu
- [ ] Dashboard s grafy
- [ ] Email templates
- [ ] Webhooks
- [ ] Analytics

---

**Made with ❤️ for Shoptet e-shops**

**Version:** 1.0.0  
**Last updated:** 25. února 2026
