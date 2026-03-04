# 🔧 OPRAVA URL PROBLÉMU

## Problém:
URL se renderuje jako: `https://aplikace.shopcode.czhttps//aplikace.shopcode.cz/reviews`

## Možné příčiny:

### 1. Špatný APP_URL v config.php (NEJPRAVDĚPODOBNĚJŠÍ)

**Zkontroluj soubor:**
```bash
cat /srv/app/config/config.php | grep APP_URL
```

**Možné špatné hodnoty:**
```php
// ❌ ŠPATNĚ - má https://
define('APP_URL', 'https://aplikace.shopcode.cz');

// ❌ ŠPATNĚ - má trailing slash
define('APP_URL', 'https://aplikace.shopcode.cz/');
```

**SPRÁVNĚ by mělo být:**
```php
// ✅ SPRÁVNĚ - PRÁZDNÝ STRING nebo bez https://
define('APP_URL', '');

// NEBO
define('APP_URL', 'http://aplikace.shopcode.cz');  // bez https://
```

**OPRAVA:**
```bash
cd /srv/app
nano config/config.php

# Změň na:
define('APP_URL', '');
```

---

### 2. Relativní URL v kódu (JIŽ OPRAVENO)

✅ Všechny formuláře už používají relativní URL (`/reviews/delete`)
✅ Odstraněny všechny `<?= APP_URL ?>` z views

---

### 3. Browser cache

**VYMAŽ CACHE:**
1. CTRL+SHIFT+DEL
2. Vyber "Cached images and files"
3. Klikni "Clear data"
4. Hard refresh: CTRL+F5

---

### 4. Nginx/Apache konfigurace

Možná server dělá double redirect. Zkontroluj nginx/apache config.

---

## RYCHLÁ OPRAVA:

**Prostě nastav APP_URL na prázdný string:**

```bash
cd /srv/app
sed -i "s|define('APP_URL'.*|define('APP_URL', '');|" config/config.php
```

**Pak otevři:**
```
https://aplikace.shopcode.cz/reviews
```

Hard refresh (CTRL+F5) a mělo by fungovat!
