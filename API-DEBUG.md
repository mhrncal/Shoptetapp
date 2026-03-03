# 🔍 API Endpoint - Diagnostika 404

## Problém:
```
POST https://aplikace.shopcode.cz/api/submit-review
→ 404 Not Found
```

## Na serveru udělej:

### 1. Zkontroluj že soubory existují:
```bash
cd /srv/app
ls -la api/submit-review.php
ls -la public/api/submit-review.php
```

**Měly by existovat OBA soubory!**

### 2. Zkontroluj document root:
```bash
pwd
# Mělo by být: /srv/app
```

### 3. Test přímého přístupu:
```bash
# Test 1: PHP syntax
php -l api/submit-review.php

# Test 2: Spusť přímo
php api/submit-review.php
# (Vrátí chybu že není POST, ale to je OK)
```

### 4. Zkontroluj Nginx/Apache config:

**Roští používá Nginx!** .htaccess nefunguje.

Musíš nastavit v **Roští admin** nebo vytvořit `nginx.conf`:

```nginx
location /api/ {
    try_files $uri $uri/ =404;
}
```

### 5. Dočasné řešení - Symlink:

```bash
cd /srv/app

# Vytvoř symlink v rootu
ln -s api/submit-review.php submit-review.php

# Pak testuj:
# https://aplikace.shopcode.cz/submit-review
```

### 6. Nebo změň cestu v testu:

V `public/test-fotorecenze.html` změň:
```javascript
// Z:
const API_URL = '/api/submit-review';

// Na:
const API_URL = '/submit-review.php';
// nebo
const API_URL = 'api/submit-review.php';
```

---

## Řešení podle situace:

### A) Pokud `/srv/app/api/submit-review.php` NEEXISTUJE:
```bash
git pull
chmod 644 api/submit-review.php
```

### B) Pokud EXISTUJE ale vrací 404:
Roští nevidí složku `/api/` - přesuň do rootu:
```bash
mv api/submit-review.php submit-review.php
```

A změň v testu:
```javascript
const API_URL = '/submit-review.php';
```

### C) Pokud nefunguje ani to:
Použij public endpoint:
```javascript
const API_URL = '/public/api/submit-review.php';
```

---

## Quick fix TEST:

Změň cestu v testu na:
```javascript
const API_URL = '/public/api/submit-review.php';
```

Tento endpoint **URČITĚ EXISTUJE** a měl by fungovat!
