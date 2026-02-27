# 📤 CSV/XML Export - Bez Selenium Robota

## ✅ Nové řešení - JEDNODUŠŠÍ!

**Žádný Selenium robot, žádná hesla!**

Místo automatického uploadu do Shoptetu pomocí robota:
- ✅ **Tlačítka v admin UI** - okamžitý export CSV/XML
- ✅ **CRON denně v 18:00** - automatické generování XML feedů
- ✅ **Uživatel si sám nahraje** - do Shoptetu manuálně

---

## 🎯 Jak to funguje

### 1. Okamžitý export (tlačítka v UI)

**Uživatel jde na `/reviews`**

**Vidí tlačítka:**
- **"Stáhnout CSV"** → okamžitý export všech schválených recenzí
- **"Stáhnout XML"** → okamžitý export všech schválených recenzí
- **"XML Feed" (info)** → URL k automaticky generovanému feedu

**Klikne na tlačítko → stáhne soubor → nahraje do Shoptetu manuálně**

### 2. Automatický XML feed (CRON)

**Denně v 18:00:**
- CRON projde všechny uživatele se schválenými recenzemi
- Vygeneruje pro každého permanentní XML feed
- Uloží do `/public/feeds/user_{id}_reviews.xml`
- Feed je přístupný na URL: `https://tvoje-domena.cz/feeds/user_1_reviews.xml`

**Uživatel pak:**
- Zkopíruje URL feedu
- Přidá do Shoptetu jako automatický import
- Shoptet denně stáhne feed a aktualizuje fotky

---

## 📊 Workflow

### Varianta A: Manuální export

```
1. Zákazník odešle fotky
   ↓
2. Recenze se uloží (status: pending)
   ↓
3. Admin schválí (status: approved)
   ↓
4. Admin jde na /reviews
   ↓
5. Klikne "Stáhnout CSV" nebo "Stáhnout XML"
   ↓
6. Stáhne soubor na počítač
   ↓
7. Přihlásí se do Shoptet adminu
   ↓
8. Katalog → Import fotek → Nahraje CSV/XML
   ↓
9. Fotky se zobrazí na e-shopu ✅
```

### Varianta B: Automatický XML feed

```
1. Zákazník odešle fotky
   ↓
2. Recenze se uloží (status: pending)
   ↓
3. Admin schválí (status: approved)
   ↓
4. CRON denně v 18:00 vygeneruje XML feed
   ↓
5. Feed dostupný na: https://domena.cz/feeds/user_1_reviews.xml
   ↓
6. Shoptet automaticky stahuje feed (nastaveno jednou)
   ↓
7. Fotky se aktualizují na e-shopu ✅
```

---

## 🗂️ Nové soubory

### 1. **ReviewExportController.php**

**Lokace:** `src/Controllers/ReviewExportController.php`

**Metody:**
- `exportCsv()` - Export CSV (okamžitě)
- `exportXml()` - Export XML (okamžitě)
- `markAsImported()` - Označit jako importované

**Použití:**
```php
// Routes:
GET /reviews/export/csv  → stáhne CSV
GET /reviews/export/xml  → stáhne XML
POST /reviews/mark-imported → označí jako importované
```

### 2. **XmlFeedGenerator.php**

**Lokace:** `src/Services/XmlFeedGenerator.php`

**Metody:**
- `generate($userId, $reviews)` - Dočasný export (do tmp/)
- `generatePermanentFeed($userId, $reviews)` - Permanentní feed (do public/feeds/)
- `cleanup($path)` - Smazání dočasného souboru

**XML formát:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <code>SKU-001</code>
        <images>
            <image>https://domena.cz/uploads/reviews/1/abc/photo1.jpg</image>
            <image>https://domena.cz/uploads/reviews/1/abc/photo2.jpg</image>
        </images>
    </product>
    <product>
        <code>SKU-002</code>
        <images>
            <image>https://domena.cz/uploads/reviews/2/def/photo1.jpg</image>
        </images>
    </product>
</products>
```

### 3. **generate-xml-feeds.php** (CRON)

**Lokace:** `cron/generate-xml-feeds.php`

**Spouštění:**
```bash
# Denně v 18:00
0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1
```

**Co dělá:**
1. Najde všechny uživatele se schválenými recenzemi
2. Pro každého vygeneruje XML feed
3. Uloží do `/public/feeds/user_{id}_reviews.xml`
4. Feed je dostupný na URL

---

## 🔧 Nastavení

### 1. Přidej routes

V `public/index.php` nebo `config/routes.php`:

```php
// CSV/XML export
$router->get('/reviews/export/csv', [ReviewExportController::class, 'exportCsv']);
$router->get('/reviews/export/xml', [ReviewExportController::class, 'exportXml']);
$router->post('/reviews/mark-imported', [ReviewExportController::class, 'markAsImported']);
```

### 2. Vytvoř feeds adresář

```bash
mkdir -p /var/www/shopcode/public/feeds
chmod 755 /var/www/shopcode/public/feeds
chown www-data:www-data /var/www/shopcode/public/feeds
```

### 3. Přidaj CRON

```bash
# Otevři crontab
sudo crontab -u www-data -e

# Přidej řádek:
0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1
```

### 4. Hotovo!

---

## 🧪 Testování

### Test 1: Export CSV

1. Schval nějaké recenze v UI
2. Jdi na `/reviews`
3. Klikni "Stáhnout CSV"
4. Měl bys stáhnout soubor `shoptet-fotky-2026-02-25-160000.csv`
5. Otevři v Excelu/TextEditoru
6. Měl bys vidět:
   ```csv
   Kód;Fotka 1;Fotka 2;Fotka 3;Fotka 4;Fotka 5
   SKU-001;https://...;https://...;;;
   ```

### Test 2: Export XML

1. Klikni "Stáhnout XML"
2. Měl bys stáhnout soubor `shoptet-fotky-2026-02-25-160000.xml`
3. Otevři v textovém editoru
4. Měl bys vidět XML formát

### Test 3: CRON generování XML feedů

```bash
# Spusť manuálně:
php cron/generate-xml-feeds.php

# Měl bys vidět:
# [2026-02-25 18:00:00] ===== XML Feed Generator START =====
# [2026-02-25 18:00:01] Nalezeno 2 uživatelů se schválenými recenzemi.
# [2026-02-25 18:00:02] Uživatel #1 (Můj e-shop): 5 schválených recenzí.
# [2026-02-25 18:00:03]   ✅ XML feed vygenerován: https://domena.cz/feeds/user_1_reviews.xml
# [2026-02-25 18:00:03] ===== XML Feed Generator END | Vygenerováno: 2 feedů =====
```

### Test 4: Ověř XML feed

```bash
# Zkontroluj že soubor existuje
ls -la /var/www/shopcode/public/feeds/

# Měl bys vidět:
# user_1_reviews.xml
# user_2_reviews.xml

# Otevři v prohlížeči:
# https://tvoje-domena.cz/feeds/user_1_reviews.xml

# Měl bys vidět XML feed
```

---

## 📥 Jak nahrát do Shoptetu

### Varianta A: Manuální CSV upload

1. **Stáhni CSV** z ShopCode admin
2. **Přihlaš se** do Shoptet adminu
3. **Katalog** → **Import a export** → **Import fotek**
4. **Vyber CSV soubor**
5. **Klikni "Importovat"**
6. **Shoptet stáhne fotky** z URL
7. **Fotky se zobrazí** na produktech ✅

### Varianta B: Automatický XML feed

**Nastavení (jednorázově):**

1. **Zkopíruj URL feedu:**
   ```
   https://tvoje-domena.cz/feeds/user_1_reviews.xml
   ```

2. **Přihlaš se** do Shoptet adminu

3. **Katalog** → **Import a export** → **Automatický import**

4. **Přidej nový import:**
   - Název: "Fotorecenze"
   - URL: `https://tvoje-domena.cz/feeds/user_1_reviews.xml`
   - Frekvence: Denně
   - Čas: 19:00 (hodinu po generování)

5. **Ulož**

**Od teď:**
- CRON generuje feed každý den v 18:00
- Shoptet stahuje feed každý den v 19:00
- Fotky se automaticky aktualizují ✅

---

## 📊 CSV formát

**Shoptet kompatibilní formát:**

```csv
Kód;Fotka 1;Fotka 2;Fotka 3;Fotka 4;Fotka 5
SKU-001;https://domena.cz/uploads/reviews/1/abc/photo1.jpg;https://domena.cz/uploads/reviews/1/abc/photo2.jpg;;;
SKU-002;https://domena.cz/uploads/reviews/2/def/photo1.jpg;;;;
```

**Pravidla:**
- Delimiter: středník (`;`)
- Encoding: UTF-8 BOM
- Max 5 fotek na produkt
- Prázdné sloupce pokud méně fotek

---

## 📊 XML formát

**Shoptet kompatibilní formát:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <code>SKU-001</code>
        <images>
            <image>https://domena.cz/uploads/reviews/1/abc/photo1.jpg</image>
            <image>https://domena.cz/uploads/reviews/1/abc/photo2.jpg</image>
        </images>
    </product>
</products>
```

**Pravidla:**
- Encoding: UTF-8
- Pretty print: zapnuto (odsazení)
- Bez limitu počtu fotek
- Validní XML 1.0

---

## 🔄 Srovnání: Před vs. Po

### PŘED (Selenium robot):

```
❌ Složité - Selenium, ChromeDriver, hesla
❌ Bezpečnostní riziko - hesla v DB
❌ Křehké - závislé na Shoptet UI
❌ Pomalé - 30-60 sekund na import
❌ Náročné - server requirements
```

### PO (CSV/XML export):

```
✅ Jednoduché - tlačítka v UI
✅ Bezpečné - žádná hesla
✅ Robustní - standard CSV/XML
✅ Rychlé - okamžitý download
✅ Nenáročné - žádné dependencies
✅ Flexibilní - manuální i automatický
```

---

## 🎯 Výhody nového řešení

### Pro uživatele:

- ✅ **Okamžitý export** - klikni a stáhni
- ✅ **Bez čekání** - není potřeba čekat na CRON
- ✅ **Kontrola** - sám si nahraje do Shoptetu
- ✅ **Volba** - CSV (jednorázově) nebo XML (automaticky)

### Pro administrátora:

- ✅ **Jednodušší** - žádný Selenium
- ✅ **Bezpečnější** - žádná hesla
- ✅ **Spolehlivější** - méně co se může pokazit
- ✅ **Levnější** - žádné server requirements

### Technické výhody:

- ✅ **Žádné dependencies** - Selenium, ChromeDriver
- ✅ **Žádná hesla** - v DB ani config.php
- ✅ **Standard formáty** - CSV, XML
- ✅ **Škálovatelné** - neomezený počet uživatelů
- ✅ **Debugovatelné** - stáhni soubor a zkontroluj

---

## 🗑️ Co odstranit

### Nepotřebné soubory (Selenium):

```bash
# Můžeš smazat:
rm src/Services/ShoptetBot.php
rm src/Services/Encryption.php
rm cron/import-reviews.php
rm database/migrations/001_add_shoptet_credentials.sql
rm src/Controllers/ShoptetSettingsController.php
rm src/Views/settings/shoptet.php
```

### Nepotřebné DB sloupce:

```sql
-- Můžeš odstranit (volitelné):
ALTER TABLE users
DROP COLUMN shoptet_email,
DROP COLUMN shoptet_password_encrypted,
DROP COLUMN shoptet_url,
DROP COLUMN shoptet_auto_import;
```

### Nepotřebné dependencies:

```bash
# Už nepotřebuješ:
composer remove facebook/webdriver

# Už nepotřebuješ:
apt-get remove chromium-browser chromium-chromedriver
```

---

## ✅ Deployment checklist

- [ ] Přidej routes pro export (`/reviews/export/csv`, `/reviews/export/xml`)
- [ ] Vytvoř feeds adresář (`mkdir public/feeds`)
- [ ] Nastav oprávnění (`chmod 755 public/feeds`)
- [ ] Přidej CRON (denně v 18:00)
- [ ] Otestuj CSV export
- [ ] Otestuj XML export
- [ ] Otestuj CRON generování feedů
- [ ] Ověř že feed je přístupný přes URL
- [ ] (Volitelné) Odstraň Selenium soubory
- [ ] (Volitelné) Odstraň DB sloupce pro Shoptet credentials

---

## 📚 Soubory

### Nové soubory:

```
src/Controllers/
└── ReviewExportController.php

src/Services/
└── XmlFeedGenerator.php

cron/
└── generate-xml-feeds.php

public/feeds/
└── user_{id}_reviews.xml (generováno automaticky)
```

### Upravené soubory:

```
src/Views/reviews/index.php  (přidána tlačítka)
src/Controllers/ReviewController.php  (xmlFeedUrl)
```

---

## 🎊 SHRNUTÍ

### ✅ Co bylo vytvořeno:

1. **CSV export** - okamžitý download
2. **XML export** - okamžitý download
3. **XML feed generátor** - CRON denně v 18:00
4. **Tlačítka v UI** - Stáhnout CSV/XML
5. **Info o feedu** - URL k automatickému feedu

### ✅ Výhody:

- 🎯 **Jednodušší** - bez Selenium
- 🔒 **Bezpečnější** - bez hesel
- ⚡ **Rychlejší** - okamžitý export
- 🛠️ **Flexibilnější** - manuální i automatický
- 💰 **Levnější** - žádné dependencies

### ✅ Workflow:

**Manuální:** Schval → Klikni "Stáhnout CSV" → Nahrај do Shoptetu  
**Automatický:** Schval → CRON v 18:00 → Shoptet stahuje feed → Hotovo

---

**Datum:** 25. února 2026  
**Status:** ✅ Implementováno  
**Complexity:** Jednoduchý  
**Dependencies:** Žádné
