# 🔄 Photo Reviews - Kompletní workflow po schválení

## ✅ Současný stav

**AUTOMATICKÝ IMPORT DO SHOPTETU JE PLNĚ IMPLEMENTOVÁN!**

Po schválení recenze v admin UI se automaticky spustí proces importu fotek do Shoptetu pomocí Selenium robota.

---

## 📊 Kompletní workflow

```
┌─────────────────────────────────┐
│ 1. Uživatel odešle formulář    │
│    s fotkami přes API           │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 2. Uložení do DB                │
│    status: 'pending'            │
│    photos: JSON pole            │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 3. Email adminovi               │
│    "Nová recenze ke schválení"  │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 4. Admin přijde do ShopCode     │
│    /reviews                     │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 5. Admin klikne "Schválit"      │
│    status: 'approved'           │
│    imported: 0                  │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 6. CRON worker (každých 30 min) │
│    cron/import-reviews.php      │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 7. Najde schválené recenze      │
│    status='approved' + imported=0│
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 8. CsvGenerator                 │
│    Vygeneruje CSV soubor        │
│    Formát: SKU;URL1;URL2;...    │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 9. ShoptetBot (Selenium)        │
│    - Přihlásí se do Shoptetu    │
│    - Naviguje na import fotek   │
│    - Nahraje CSV                │
│    - Potvrdí import             │
│    - Počká na výsledek          │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 10. Shoptet zpracuje CSV        │
│     Stáhne fotky z URL          │
│     Přidá k produktům           │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 11. Označení jako importované   │
│     imported: 1                 │
│     imported_at: NOW()          │
└──────────────┬──────────────────┘
               │
               ▼
┌─────────────────────────────────┐
│ 12. Fotky viditelné na e-shopu  │
│     ✅ HOTOVO                    │
└─────────────────────────────────┘
```

---

## 🤖 Automatický import - Selenium Robot

### Co dělá ShoptetBot:

1. **Spustí Chrome browser** (headless mode)
2. **Přihlásí se do Shoptet adminu**
   - URL: `SHOPTET_URL/admin/login/`
   - Credentials: `SHOPTET_EMAIL`, `SHOPTET_PASSWORD`
3. **Naviguje na stránku importu fotek**
   - URL: `SHOPTET_URL/admin/products/import-photos/`
4. **Nahraje CSV soubor**
   - Najde `<input type="file">`
   - Uploadne CSV
5. **Potvrdí import**
   - Klikne na submit button
6. **Čeká na výsledek**
   - Hledá success/error zprávu
   - Timeout: 60 sekund
7. **Ukončí browser**

### CSV formát pro Shoptet:

```csv
Kód;Fotka 1;Fotka 2;Fotka 3;Fotka 4;Fotka 5
SKU-001;https://tvoje-domena.cz/uploads/reviews/1/abc123/original_1.jpg;https://...;https://...;;
SKU-002;https://tvoje-domena.cz/uploads/reviews/1/def456/original_1.jpg;;;;
```

**Pravidla:**
- Delimiter: středník (`;`)
- UTF-8 BOM (pro správné zobrazení diakritiky)
- Max 5 fotek na produkt
- Prázdné sloupce pokud méně než 5 fotek

### Příklad vygenerovaného CSV:

```csv
Kód;Fotka 1;Fotka 2;Fotka 3;Fotka 4;Fotka 5
SKU-TRICKO-001;https://shopcode.cz/uploads/reviews/1/a1b2c3/original_1.jpg;https://shopcode.cz/uploads/reviews/1/a1b2c3/original_2.jpg;;;
SKU-BOTY-005;https://shopcode.cz/uploads/reviews/1/d4e5f6/original_1.jpg;;;;
```

---

## ⏱️ CRON nastavení

### Současný stav:

**Script:** `cron/import-reviews.php`  
**Doporučená frekvence:** Každých 30 minut

### Nastavení crontabu:

```bash
# Otevři crontab
sudo crontab -u www-data -e

# Přidej řádek:
30 * * * * php /var/www/shopcode/cron/import-reviews.php >> /var/log/shopcode-reviews.log 2>&1
```

**Co to znamená:**
- Spustí se každou půlhodinu (00:30, 01:30, 02:30, ...)
- Loguje do `/var/log/shopcode-reviews.log`

### Alternativní frekvence:

**Každých 15 minut:**
```bash
*/15 * * * * php /var/www/shopcode/cron/import-reviews.php >> /var/log/shopcode-reviews.log 2>&1
```

**Každou hodinu:**
```bash
0 * * * * php /var/www/shopcode/cron/import-reviews.php >> /var/log/shopcode-reviews.log 2>&1
```

**Denně v 2:00:**
```bash
0 2 * * * php /var/www/shopcode/cron/import-reviews.php >> /var/log/shopcode-reviews.log 2>&1
```

---

## 🔧 Požadavky na server

### 1. Composer balíčky

```bash
composer require facebook/webdriver
```

### 2. Chrome & ChromeDriver

**Ubuntu/Debian:**
```bash
apt-get update
apt-get install -y chromium-browser chromium-chromedriver
```

**CentOS/RHEL:**
```bash
yum install -y chromium chromium-chromedriver
```

### 3. Spuštění ChromeDriver

**Varianta A: Manuální spuštění**
```bash
# Spusť na pozadí
chromedriver --port=9515 &

# Nebo jako systemd service
sudo nano /etc/systemd/system/chromedriver.service
```

**Varianta B: Auto-start (systemd service)**

`/etc/systemd/system/chromedriver.service`:
```ini
[Unit]
Description=ChromeDriver for Selenium
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/chromedriver --port=9515
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable chromedriver
sudo systemctl start chromedriver
sudo systemctl status chromedriver
```

### 4. Konfigurace v config.php

```php
// Shoptet přihlašovací údaje
define('SHOPTET_URL', 'https://admin.shoptet.cz');
define('SHOPTET_EMAIL', 'vas@email.cz');
define('SHOPTET_PASSWORD', 'vase-heslo');

// ChromeDriver (volitelné, default: http://localhost:9515)
define('CHROMEDRIVER_URL', 'http://localhost:9515');

// URL vaší aplikace (pro sestavení URL fotek)
define('APP_URL', 'https://tvoje-domena.cz');
```

---

## 🧪 Testování

### Test 1: Manuální spuštění CRON workeru

```bash
# Spusť worker manuálně
php /var/www/shopcode/cron/import-reviews.php

# Očekávaný výstup (pokud jsou schválené recenze):
# [2026-02-25 16:30:00] Nalezeno 1 uživatelů se schválenými recenzemi.
# [2026-02-25 16:30:01] Uživatel #1: 3 recenzí ke zpracování.
# [2026-02-25 16:30:02] CSV vygenerován: shoptet_import_20260225163002_abc123.csv (3 recenzí)
# [2026-02-25 16:30:03] Spouštím Selenium robot...
# [2026-02-25 16:30:05] Přihlášení úspěšné.
# [2026-02-25 16:30:07] Navigace na stránku importu.
# [2026-02-25 16:30:08] CSV soubor nahrán, čekám na potvrzení importu...
# [2026-02-25 16:30:09] Import potvrzen.
# [2026-02-25 16:30:15] Import dokončen: Import byl úspěšně dokončen
# [2026-02-25 16:30:15] ✅ Import úspěšný — označeno 3 recenzí.
# [2026-02-25 16:30:15] Hotovo. Celkem importováno: 3 recenzí.
```

### Test 2: Kontrola ChromeDriver

```bash
# Je ChromeDriver spuštěný?
ps aux | grep chromedriver

# Testuj endpoint
curl http://localhost:9515/status
# Mělo by vrátit JSON s "ready": true
```

### Test 3: Kontrola databáze

```sql
-- Kolik recenzí čeká na import?
SELECT COUNT(*) FROM reviews 
WHERE status = 'approved' AND imported = 0;

-- Detail recenzí
SELECT id, sku, shoptet_id, author_name, created_at, status, imported
FROM reviews 
WHERE status = 'approved' AND imported = 0;
```

### Test 4: Simulace celého workflow

```bash
# 1. Schval recenzi v admin UI
# /reviews → klikni "Schválit" u některé pending recenze

# 2. Počkej max 30 minut (nebo spusť manuálně)
php /var/www/shopcode/cron/import-reviews.php

# 3. Zkontroluj DB
mysql shopcode -e "SELECT id, imported FROM reviews WHERE id = XXX;"
# imported by mělo být 1

# 4. Zkontroluj Shoptet admin
# Přihlaš se do Shoptetu
# Katalog → Produkty → najdi produkt podle SKU
# Měl bys vidět nové fotky
```

---

## 🔒 Bezpečnost & Retry logika

### Retry mechanismus

**Max 3 pokusy:**
```php
const MAX_RETRIES = 3;
```

**Po 3 selháních:**
- Import se automaticky pozastaví
- Email adminovi
- Nutný manuální zásah

**Obnovení po selhání:**
```bash
# Smaž retry lock soubor
rm /var/www/shopcode/tmp/import-reviews-retries.json

# Worker se znovu spustí při dalším cronu
```

### Mutex lock

**Zabraňuje souběžnému běhu:**
```php
$lockFile = ROOT . '/tmp/import-reviews.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Jiná instance běží, přeskakuji.";
    exit(0);
}
```

### Screenshot při chybě

**Pro debugging:**
```php
$screenshotPath = ROOT . '/tmp/selenium_error_20260225163045.png';
$this->driver->takeScreenshot($screenshotPath);
```

**Zkontroluj:**
```bash
ls -la /var/www/shopcode/tmp/selenium_error_*.png
```

---

## 📊 Monitoring

### Log soubory

**Hlavní log:**
```bash
tail -f /var/log/shopcode-reviews.log
```

**Struktura logu:**
```
[2026-02-25 16:30:00] Nalezeno 1 uživatelů se schválenými recenzemi.
[2026-02-25 16:30:01] Uživatel #1: 3 recenzí ke zpracování.
[2026-02-25 16:30:02] CSV vygenerován: shoptet_import_20260225163002_abc123.csv (3 recenzí)
[2026-02-25 16:30:03]   [Selenium] [16:30:03] Spouštím Selenium robot...
[2026-02-25 16:30:05]   [Selenium] [16:30:05] Přihlášení úspěšné.
[2026-02-25 16:30:07]   [Selenium] [16:30:07] Navigace na stránku importu.
[2026-02-25 16:30:15] ✅ Import úspěšný — označeno 3 recenzí.
[2026-02-25 16:30:15] Hotovo. Celkem importováno: 3 recenzí.
```

### SQL dotazy pro monitoring

```sql
-- Kolik recenzí čeká?
SELECT COUNT(*) as pending_import 
FROM reviews 
WHERE status = 'approved' AND imported = 0;

-- Poslední importované
SELECT id, sku, author_name, imported_at 
FROM reviews 
WHERE imported = 1 
ORDER BY imported_at DESC 
LIMIT 10;

-- Statistiky
SELECT 
    status,
    imported,
    COUNT(*) as count
FROM reviews 
GROUP BY status, imported;
```

### Email notifikace

**Při selhání:**
- Subject: `[ShopCode] ❌ Selenium import recenzí selhal`
- Obsahuje: user_id, chyba, počet pokusů

**Konfigurace:**
```php
// AdminNotifier::notifySuperadmin() pošle email na:
defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@example.com'
```

---

## 🐛 Troubleshooting

### Problem: "ChromeDriver není spuštěný"

**Chyba:**
```
Failed to connect to localhost port 9515: Connection refused
```

**Řešení:**
```bash
# Spusť ChromeDriver
chromedriver --port=9515 &

# Nebo restartuj service
sudo systemctl restart chromedriver
```

### Problem: "Přihlášení do Shoptetu selhalo"

**Chyba:**
```
Přihlášení do Shoptetu selhalo — zkontrolujte přihlašovací údaje.
```

**Řešení:**
```bash
# Zkontroluj config
cat /var/www/shopcode/config/config.php | grep SHOPTET

# Ujisti se, že:
# - SHOPTET_EMAIL je správný
# - SHOPTET_PASSWORD je správný
# - SHOPTET_URL je správná (https://admin.shoptet.cz)
```

### Problem: "CSV soubor neexistuje"

**Chyba:**
```
CSV soubor neexistuje: /var/www/shopcode/tmp/shoptet_import_xxx.csv
```

**Řešení:**
```bash
# Zkontroluj tmp adresář
ls -la /var/www/shopcode/tmp/

# Zkontroluj oprávnění
sudo chown -R www-data:www-data /var/www/shopcode/tmp/
sudo chmod 755 /var/www/shopcode/tmp/
```

### Problem: "Timeout při čekání na element"

**Chyba:**
```
Timeout waiting for element: input[type=file]
```

**Řešení:**
- Shoptet změnil strukturu stránky
- Zkontroluj screenshot: `/tmp/selenium_error_*.png`
- Uprav selektory v `ShoptetBot.php`

### Problem: Import se pozastavil po 3 selháních

**Řešení:**
```bash
# 1. Zkontroluj příčinu selhání v logu
tail -50 /var/log/shopcode-reviews.log

# 2. Oprav problém (ChromeDriver, credentials, atd.)

# 3. Smaž retry lock
rm /var/www/shopcode/tmp/import-reviews-retries.json

# 4. Spusť manuálně nebo počkej na další cron
php /var/www/shopcode/cron/import-reviews.php
```

---

## ✅ Checklist instalace

- [ ] Nainstalován Chromium browser
- [ ] Nainstalován ChromeDriver
- [ ] ChromeDriver běží na portu 9515
- [ ] Composer balíček `facebook/webdriver` nainstalován
- [ ] Config obsahuje `SHOPTET_URL`, `SHOPTET_EMAIL`, `SHOPTET_PASSWORD`
- [ ] Config obsahuje `APP_URL` (pro URL fotek)
- [ ] Crontab nastaven (každých 30 minut)
- [ ] Log soubor `/var/log/shopcode-reviews.log` existuje a má správná oprávnění
- [ ] Tmp adresář `/tmp/` má správná oprávnění
- [ ] Otestován manuální běh workeru
- [ ] Ověřen import v Shoptet adminu

---

## 📚 Dokumentace souborů

### cron/import-reviews.php
- Hlavní cron worker
- Najde schválené recenze
- Spustí CsvGenerator
- Spustí ShoptetBot
- Označí jako importované
- Email při selhání

### src/Services/CsvGenerator.php
- Generuje CSV soubor
- Formát: `Kód;Fotka 1;Fotka 2;...`
- UTF-8 BOM
- Max 5 fotek na produkt

### src/Services/ShoptetBot.php
- Selenium robot
- Přihlášení do Shoptetu
- Upload CSV
- Potvrzení importu
- Screenshot při chybě

### src/Models/Review.php
- `getPendingImport()` - Najde schválené neimportované
- `markImported()` - Označí jako importované

---

## 🎯 Best Practices

1. **Pravidelně monitoruj logy**
   ```bash
   tail -f /var/log/shopcode-reviews.log
   ```

2. **Zkontroluj ChromeDriver je up**
   ```bash
   systemctl status chromedriver
   ```

3. **Otestuj credentials před nasazením**
   ```bash
   # Přihlaš se manuálně do Shoptet adminu
   # s credentials z config.php
   ```

4. **Backup before deployment**
   ```bash
   # Zálohuj databázi reviews tabulku
   mysqldump shopcode reviews > reviews_backup.sql
   ```

5. **Sleduj retry rate**
   ```bash
   # Pokud často selháváš, něco je špatně
   cat /var/www/shopcode/tmp/import-reviews-retries.json
   ```

---

**Datum:** 25. února 2026  
**Status:** ✅ Plně implementováno  
**CRON:** `cron/import-reviews.php`  
**Frekvence:** Každých 30 minut  
**Selenium:** ShoptetBot s ChromeDriver
