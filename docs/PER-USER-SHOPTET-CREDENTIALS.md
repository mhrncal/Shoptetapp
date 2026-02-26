# 🔐 Per-User Shoptet Credentials - Dokumentace

## ✅ Co bylo implementováno

**Každý klient (user) může mít své vlastní Shoptet přihlašovací údaje!**

### Klíčové vlastnosti:

- ✅ **Per-user credentials** - každý uživatel má své vlastní Shoptet login
- ✅ **Šifrované hesla** - AES-256-CBC encryption
- ✅ **UI pro nastavení** - jednoduchý formulář v settings
- ✅ **Automatický import** - zapíná/vypíná se per-user
- ✅ **CRON worker** - používá credentials konkrétního uživatele
- ✅ **Bezpečné** - hesla nikdy v plaintext

---

## 📊 Databázová změna

### SQL migrace:

```sql
ALTER TABLE `users`
ADD COLUMN `shoptet_email` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `shoptet_password_encrypted` TEXT DEFAULT NULL,
ADD COLUMN `shoptet_url` VARCHAR(500) DEFAULT 'https://admin.shoptet.cz',
ADD COLUMN `shoptet_auto_import` TINYINT(1) DEFAULT 1;
```

**Spusť:**
```bash
mysql shopcode < database/migrations/001_add_shoptet_credentials.sql
```

---

## 🔒 Šifrování hesel

### Nová služba: `Encryption`

**Lokace:** `src/Services/Encryption.php`

**Použití:**
```php
use ShopCode\Services\Encryption;

$enc = new Encryption();

// Šifrování
$encrypted = $enc->encrypt('moje-heslo');
// Výsledek: "abc123...:def456..." (iv:ciphertext)

// Dešifrování
$plaintext = $enc->decrypt($encrypted);
// Výsledek: "moje-heslo"
```

### Konfigurace:

**config/config.php:**
```php
// Vygeneruj nový klíč:
// php -r "echo base64_encode(random_bytes(32));"

define('ENCRYPTION_KEY', 'tvůj-base64-klíč-zde');
```

**Generování klíče:**
```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

---

## 🎨 UI pro nastavení

### Nová stránka: `/settings/shoptet`

**View:** `src/Views/settings/shoptet.php`  
**Controller:** `src/Controllers/ShoptetSettingsController.php`

**Funkce:**
- Formulář pro zadání Shoptet credentials
- Zapnutí/vypnutí automatického importu
- Status integrace (nastaveno/nenastaveno)
- Smazání credentials
- Nápověda a požadavky na server

### Routes (přidej do routes.php):

```php
// Shoptet settings
$router->get('/settings/shoptet', [ShoptetSettingsController::class, 'index']);
$router->post('/settings/shoptet', [ShoptetSettingsController::class, 'update']);
$router->post('/settings/shoptet/delete', [ShoptetSettingsController::class, 'delete']);
```

---

## 🤖 Upravený CRON worker

### Změny v `cron/import-reviews.php`:

**Před:**
```php
// Používal globální credentials z config.php
$bot = new ShoptetBot();
```

**Po:**
```php
// Načte credentials z databáze pro každého uživatele
$users = $db->query("
    SELECT DISTINCT u.id, u.shoptet_url, u.shoptet_email, u.shoptet_password_encrypted
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.status = 'approved' 
      AND r.imported = 0
      AND u.shoptet_auto_import = 1
      AND u.shoptet_email IS NOT NULL
      AND u.shoptet_password_encrypted IS NOT NULL
");

foreach ($users as $user) {
    // Dešifruj heslo
    $encryption = new Encryption();
    $shoptetPassword = $encryption->decrypt($user['shoptet_password_encrypted']);
    
    // Vytvoř robota s uživatelovými credentials
    $bot = new ShoptetBot(
        $user['shoptet_url'],
        $user['shoptet_email'],
        $shoptetPassword
    );
    
    // Import...
}
```

---

## 🔧 Upravený ShoptetBot

### Změny v `src/Services/ShoptetBot.php`:

**Před:**
```php
public function __construct()
{
    // Používal credentials z config.php
}
```

**Po:**
```php
public function __construct(
    string $shoptetUrl,
    string $shoptetEmail,
    string $shoptetPassword
)
{
    $this->shoptetUrl = $shoptetUrl;
    $this->shoptetEmail = $shoptetEmail;
    $this->shoptetPassword = $shoptetPassword;
    // ...
}
```

---

## 📝 User Model - Nové metody

### `User::updateShoptetCredentials()`

```php
User::updateShoptetCredentials(
    userId: 1,
    shoptetEmail: 'klient@example.com',
    shoptetPassword: 'heslo123',
    shoptetUrl: 'https://admin.shoptet.cz',
    autoImport: true
);
```

### `User::getShoptetPassword()`

```php
$password = User::getShoptetPassword(1);
// Vrátí dešifrované heslo nebo null
```

### `User::hasShoptetCredentials()`

```php
if (User::hasShoptetCredentials(1)) {
    // Uživatel má nastavené credentials
}
```

### `User::deleteShoptetCredentials()`

```php
User::deleteShoptetCredentials(1);
// Smaže credentials a vypne auto-import
```

---

## 🧪 Testování

### Test 1: Nastavení credentials přes UI

1. Přihlaš se do ShopCode
2. Jdi na `/settings/shoptet`
3. Vyplň formulář:
   - Shoptet Email: `tvuj@shoptet-email.cz`
   - Shoptet Heslo: `tvoje-heslo`
   - Automatický import: ✓
4. Klikni "Uložit nastavení"
5. Měl bys vidět: "Shoptet integrace byla úspěšně nastavena!"

### Test 2: Kontrola v databázi

```sql
SELECT 
    id, 
    email, 
    shoptet_email,
    shoptet_password_encrypted,
    shoptet_auto_import
FROM users 
WHERE id = 1;

-- shoptet_email by mělo být vyplněné
-- shoptet_password_encrypted by mělo obsahovat: "abc:def" (iv:ciphertext)
-- shoptet_auto_import by mělo být 1
```

### Test 3: Dešifrování hesla

```php
<?php
require 'config/config.php';
spl_autoload_register(/* ... */);

use ShopCode\Models\User;

$password = User::getShoptetPassword(1);
echo "Dešifrované heslo: " . $password . "\n";
```

### Test 4: CRON worker s credentials

```bash
# Schval recenzi v UI
# Pak spusť worker manuálně:
php cron/import-reviews.php

# Měl bys vidět v logu:
# [16:30:01] Uživatel #1: 3 recenzí ke zpracování.
# [16:30:03]   [Selenium] Přihlášení úspěšné.
# Přihlášení by mělo proběhnout s credentials uživatele #1
```

---

## 🚀 Deployment checklist

### 1. Databáze

```bash
# Spusť migraci
mysql shopcode < database/migrations/001_add_shoptet_credentials.sql

# Ověř změny
mysql shopcode -e "DESCRIBE users;" | grep shoptet
```

### 2. Config

```php
// config/config.php - přidej:
define('ENCRYPTION_KEY', 'vygenerovaný-base64-klíč');
```

**Generování klíče:**
```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

### 3. Routes

Přidej do `public/index.php` nebo `config/routes.php`:
```php
$router->get('/settings/shoptet', [ShoptetSettingsController::class, 'index']);
$router->post('/settings/shoptet', [ShoptetSettingsController::class, 'update']);
$router->post('/settings/shoptet/delete', [ShoptetSettingsController::class, 'delete']);
```

### 4. Menu položka (volitelné)

Přidej link do navigace:
```html
<a href="/settings/shoptet" class="nav-link">
    <i class="bi bi-shop me-2"></i>Shoptet Integrace
</a>
```

### 5. Test

```bash
# Test encryption služby
php -r "
require 'config/config.php';
spl_autoload_register(/* ... */);
use ShopCode\Services\Encryption;
var_dump(Encryption::test()); // Mělo by vrátit bool(true)
"
```

---

## 🔐 Bezpečnost

### Co je chráněno:

✅ **Hesla v databázi:**
- Šifrována AES-256-CBC
- Každé heslo má unikátní IV (initialization vector)
- Formát: `base64(iv):base64(ciphertext)`

✅ **Encryption key:**
- Uložen v `config/config.php` (mimo git)
- 32 bytů (256 bitů)
- Generován kryptograficky bezpečným RNG

✅ **Transport:**
- Hesla nikdy neodesílána v plaintext
- HTTPS doporučeno

### Co NENÍ chráněno:

⚠️ **Shoptet email:**
- Uložen v plaintext
- Není citlivý údaj

⚠️ **Encryption key v config.php:**
- Musí být chráněn file permissions
- `chmod 600 config/config.php`
- Přidej do `.gitignore`

### Best practices:

1. **Nikdy necommituj config.php s real ENCRYPTION_KEY**
2. **Používej různé klíče pro dev/staging/production**
3. **Backup encryption key někam bezpečně**
4. **Rotuj encryption key pravidelně** (1x ročně)

---

## 🎯 Workflow po implementaci

### Pro každého uživatele (klienta):

1. **Admin schválí uživatele** v ShopCode admin UI
2. **Uživatel se přihlásí**
3. **Uživatel jde na `/settings/shoptet`**
4. **Vyplní své Shoptet credentials:**
   - Email pro Shoptet admin
   - Heslo
   - Zapne automatický import
5. **Uloží nastavení**
6. **Od teď:**
   - Zákazníci odesílají fotorecenze
   - Uživatel schvaluje v UI
   - CRON automaticky uploaduje do JEHO Shoptet účtu
   - Fotky se zobrazují na JEHO e-shopu

### Multi-tenant workflow:

```
Klient A (user_id: 1)
├─ Shoptet credentials: klientA@example.com
├─ Auto-import: zapnuto
├─ Schválené recenze: 5
└─ CRON → uploadne do Shoptet účtu klienta A

Klient B (user_id: 2)
├─ Shoptet credentials: klientB@example.com
├─ Auto-import: zapnuto
├─ Schválené recenze: 3
└─ CRON → uploadne do Shoptet účtu klienta B

Klient C (user_id: 3)
├─ Shoptet credentials: NENASTAVENO
├─ Auto-import: vypnuto
├─ Schválené recenze: 10
└─ CRON → PŘESKOČÍ (nemá credentials)
```

---

## ⚠️ Důležité poznámky

### CRON worker nyní vyžaduje credentials v DB

**Nebude fungovat, pokud:**
- ❌ Uživatel nemá `shoptet_email`
- ❌ Uživatel nemá `shoptet_password_encrypted`
- ❌ Uživatel má `shoptet_auto_import = 0`

**Worker automaticky přeskočí uživatele bez credentials.**

### Globální credentials v config.php již nejsou použité

**Před implementací:**
```php
// config/config.php
define('SHOPTET_EMAIL', 'global@email.cz');
define('SHOPTET_PASSWORD', 'global-heslo');
```

**Po implementaci:**
```php
// Tyto konstanty už nejsou potřeba!
// Můžeš je odstranit nebo nechat pro backward compatibility
```

---

## 📚 Soubory

### Nové soubory:

```
database/migrations/
└── 001_add_shoptet_credentials.sql

src/Services/
└── Encryption.php

src/Controllers/
└── ShoptetSettingsController.php

src/Views/settings/
└── shoptet.php
```

### Upravené soubory:

```
cron/import-reviews.php              (per-user credentials)
src/Services/ShoptetBot.php          (credentials jako parametry)
src/Models/User.php                  (nové metody)
```

---

## ✅ Výhody per-user credentials

1. **Multi-tenant ready** - každý klient má svůj Shoptet účet
2. **Bezpečnější** - klient A nevidí data klienta B
3. **Samostatnost** - každý klient spravuje své credentials
4. **Flexibilnější** - zapínání/vypínání auto-importu per-user
5. **Škálovatelné** - podpora neomezeného počtu klientů

---

**Datum:** 25. února 2026  
**Status:** ✅ Implementováno  
**Migration:** `001_add_shoptet_credentials.sql`  
**Encryption:** AES-256-CBC
