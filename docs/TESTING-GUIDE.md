# 🧪 Testovací průvodce CSV importem v ShopCode

## ✅ Status implementace

**CSV parsování je PLNĚ IMPLEMENTOVÁNO a připraveno k testování!**

### Co je hotovo:
- ✅ `CsvParser.php` - Core parser s field mappingem
- ✅ `XmlController.php` - UI s formulářem pro CSV
- ✅ `QueueWorker.php` - Zpracování CSV frontou
- ✅ UI formulář s radio buttony (XML/CSV)
- ✅ Tabulka pro mapování CSV sloupců
- ✅ Automatická detekce kódování
- ✅ Grupování variant podle pairCode

---

## 📋 Předpoklady pro testování

### 1. Databáze
Ujisti se, že máš vytvořené tabulky:
```sql
-- Zkontroluj:
SHOW TABLES LIKE 'products';
SHOW TABLES LIKE 'product_variants';
SHOW TABLES LIKE 'xml_processing_queue';
SHOW TABLES LIKE 'xml_imports';
```

### 2. Config
Zkontroluj `config/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'shopcode');
define('DB_USER', 'root');
define('DB_PASS', '...');
```

### 3. Cron worker
Ujisti se, že cron worker je spustitelný:
```bash
php /path/to/Shoptetapp/cron/process-xml.php
```

---

## 🎯 Testovací scénáře

### Scénář 1: Standardní Shoptet CSV

**Soubor:** `test-shoptet.csv`
```csv
code;pairCode;name;defaultCategory;price
SKU-001;;Jednoduchý produkt A;Kategorie 1;299.50
SKU-002;;Jednoduchý produkt B;Kategorie 2;499
SKU-003;PAIR-100;Tričko;Oblečení;399
SKU-004;PAIR-100;Tričko M;Oblečení;399
SKU-005;PAIR-100;Tričko L;Oblečení;399
```

**Field mapping (výchozí):**
- code → `code`
- pairCode → `pairCode`
- name → `name`
- category → `defaultCategory`
- price → `price`

**Očekávaný výsledek:**
- 4 produkty vytvořeny:
  - SKU-001 (jednoduchý)
  - SKU-002 (jednoduchý)
  - PAIR-100 (s 3 variantami: SKU-003, SKU-004, SKU-005)
  - Další produkty...

**SQL kontrola:**
```sql
SELECT COUNT(*) FROM products;  -- Mělo by být 4
SELECT COUNT(*) FROM product_variants WHERE product_id IN (
    SELECT id FROM products WHERE shoptet_id = 'PAIR-100'
);  -- Mělo by být 3
```

---

### Scénář 2: Vlastní názvy sloupců

**Soubor:** `test-custom-columns.csv`
```csv
SKU;Název;Cena s DPH;Značka;Kategorie
ABC-001;Produkt X;1299.00;Nike;Sport
ABC-002;Produkt Y;899.50;Adidas;Sport
```

**Field mapping (vlastní):**
- code → `SKU`
- name → `Název`
- price → `Cena s DPH`
- brand → `Značka`
- category → `Kategorie`
- pairCode → (nechat prázdné)

**Očekávaný výsledek:**
- 2 produkty vytvořeny
- Brand správně namapován (Nike, Adidas)
- Cena správně parsována (1299.00, 899.50)

**SQL kontrola:**
```sql
SELECT code, name, brand, price FROM products WHERE code LIKE 'ABC-%';
-- Mělo by vrátit 2 řádky s správnými hodnotami
```

---

### Scénář 3: České znaky (CP1250)

**Test kódování:**
1. Ulož CSV v CP1250 (Excel Save As → CSV)
2. Obsahuje: `Produkt s českou diakřitikou ěščřžýá`
3. Import

**Očekávaný výsledek:**
- ✅ České znaky správně zobrazeny
- ✅ Žádná korupce (�, ???)

**Kontrola:**
```sql
SELECT name FROM products WHERE code = 'SKU-006';
-- Mělo by vrátit: "Produkt s českou diakřitikou ěščřžýá"
```

---

## 🖥️ Kroky testování v UI

### Krok 1: Nahraj CSV soubor někam dostupný

**Možnosti:**
```bash
# A) Lokální web server
cp test-shoptet.csv /var/www/html/test.csv
# URL: http://localhost/test.csv

# B) Shoptet hosting
# Nahraj přes FTP na cdn.myshoptet.com

# C) GitHub Gist (veřejný)
# Vytvoř gist s CSV obsahem
# URL: https://gist.githubusercontent.com/...
```

### Krok 2: Přihlaš se do ShopCode

```
URL: http://localhost/shopcode/
nebo: http://tvoje-doména.cz/
```

### Krok 3: Navigace na Import

```
Dashboard → Import produktů (XML/CSV)
nebo přímo: /xml
```

### Krok 4: Vyplň formulář

**A) Pro standardní Shoptet CSV:**
1. Vyber **CSV** radio button
2. Zadej URL: `http://localhost/test.csv`
3. Nech výchozí mapování:
   - code → `code`
   - pairCode → `pairCode`
   - name → `name`
   - category → `defaultCategory`
   - price → `price`
4. Klikni **Spustit import**

**B) Pro vlastní sloupce:**
1. Vyber **CSV** radio button
2. Zadej URL: `http://localhost/test-custom-columns.csv`
3. **UPRAV mapování:**
   - code → `SKU`
   - name → `Název`
   - price → `Cena s DPH`
   - brand → `Značka`
   - category → `Kategorie`
4. Klikni **Spustit import**

### Krok 5: Sleduj frontu

**V UI:**
- Měl bys vidět položku ve frontě se stavem `pending`
- Refresh stránku každých 5 sekund

**Nebo v terminálu:**
```bash
# Manuální spuštění cron workeru
php cron/process-xml.php

# Sleduj logy
tail -f /var/log/shopcode-xml.log
```

### Krok 6: Ověř výsledek

**V UI:**
- Stav by měl být `completed`
- Počet produktů: X

**V databázi:**
```sql
-- Poslední import
SELECT * FROM xml_processing_queue ORDER BY id DESC LIMIT 1;

-- Produkty z importu
SELECT id, shoptet_id, code, name, price, brand, category 
FROM products 
ORDER BY id DESC 
LIMIT 10;

-- Varianty
SELECT pv.*, p.shoptet_id as parent_id
FROM product_variants pv
JOIN products p ON pv.product_id = p.id
ORDER BY pv.id DESC
LIMIT 10;
```

---

## 🐛 Debugging

### Problem: "CSV nemá hlavičku"

**Řešení:**
```bash
# Zkontroluj první řádek CSV
head -1 test-shoptet.csv
# Mělo by být: code;pairCode;name;defaultCategory;price
```

### Problem: "CSV nemá sloupec 'code'"

**Řešení:**
Buď:
1. CSV má jiný název sloupce → uprav field mapping
2. CSV skutečně nemá code sloupec → přidej ho

### Problem: "Nepodařilo se dekódovat CSV"

**Řešení:**
```bash
# Zkontroluj kódování
file -i test-shoptet.csv
# Mělo by být: charset=utf-8 nebo charset=iso-8859-1

# Převeď na UTF-8
iconv -f CP1250 -t UTF-8 test-shoptet.csv > test-utf8.csv
```

### Problem: České znaky jsou rozbité

**Řešení:**
```php
// CsvParser by měl automaticky detekovat
// Zkontroluj funkci decode() v CsvParser.php

// Manuální fix:
$decoded = iconv('CP1250', 'UTF-8//TRANSLIT', file_get_contents('test.csv'));
file_put_contents('test-utf8.csv', $decoded);
```

### Problem: Varianty se nevytvořily

**Kontrola:**
```sql
-- Zkontroluj pairCode v products
SELECT shoptet_id, code FROM products WHERE shoptet_id LIKE 'PAIR-%';

-- Zkontroluj varianty
SELECT * FROM product_variants WHERE product_id IN (
    SELECT id FROM products WHERE shoptet_id LIKE 'PAIR-%'
);
```

**Řešení:**
- pairCode sloupec musí mít stejnou hodnotu pro všechny varianty
- První řádek skupiny určuje název produktu
- Ostatní řádky jsou varianty

### Problem: Import se zasekl na "processing"

**Řešení:**
```sql
-- Uvolni zaseknutou položku
UPDATE xml_processing_queue 
SET status = 'pending' 
WHERE status = 'processing' 
  AND id = XXX;  -- ID tvého importu

-- Nebo spusť worker manuálně
php cron/process-xml.php
```

---

## 📊 Monitorování importu

### Real-time sledování

**V terminálu:**
```bash
# Spusť worker v foreground
php cron/process-xml.php

# Výstup:
# [2026-02-25 15:30:00] ===== XML Worker START (PID: 12345) =====
# [2026-02-25 15:30:01] [Queue#5] 🚀 Zahájení zpracování | Formát: CSV | URL: http://...
# [2026-02-25 15:30:02] [Queue#5] ⬇️  Stahuji feed...
# [2026-02-25 15:30:03] [Queue#5] ✅ Staženo 0.01 MB
# [2026-02-25 15:30:03] [Queue#5]   ↻ Zpracováno: 100
# [2026-02-25 15:30:04] [Queue#5] ✅ Hotovo | Produktů: 156 | Nových: 156 | Akt.: 0 | Chyb parseru: 0
# [2026-02-25 15:30:04] ===== XML Worker END | Zpracováno: 1 =====
```

### Database monitoring

```sql
-- Aktivní importy
SELECT id, feed_format, status, progress_percentage, products_processed
FROM xml_processing_queue 
WHERE status IN ('pending', 'processing')
ORDER BY created_at DESC;

-- Historie importů
SELECT id, feed_format, status, products_processed, error_message, created_at
FROM xml_processing_queue 
ORDER BY created_at DESC 
LIMIT 20;

-- Počet produktů
SELECT COUNT(*) as total_products FROM products;
SELECT COUNT(*) as total_variants FROM product_variants;
```

---

## ✅ Checklist úspěšného importu

Po úspěšném importu by mělo platit:

- [ ] Fronta: status = `completed`
- [ ] Fronta: products_processed = očekávaný počet
- [ ] Fronta: error_message = NULL
- [ ] Products: řádky vytvořeny s správnými daty
- [ ] Products: shoptet_id je vyplněno
- [ ] Products: code, name, price jsou správně
- [ ] Product_variants: varianty vytvořeny pro produkty s pairCode
- [ ] Product_variants: shoptet_variant_id = code varianty
- [ ] České znaky: správně zobrazeny
- [ ] Duplicity: ON DUPLICATE KEY UPDATE funguje (re-import)

---

## 🔄 Re-import test

**Test update logiky:**

1. **První import:**
```bash
# Import test-shoptet.csv
# Zkontroluj: 4 produkty vytvořeny
```

2. **Uprav CSV:**
```csv
code;pairCode;name;defaultCategory;price
SKU-001;;UPRAVENÝ produkt A;Kategorie 1;399.00  # ← změna ceny a názvu
SKU-002;;Jednoduchý produkt B;Kategorie 2;499
```

3. **Druhý import stejného CSV:**
```bash
# Import znovu
# Očekáváno: updated = 2, inserted = 0 (pro první 2)
```

4. **SQL kontrola:**
```sql
SELECT name, price FROM products WHERE code = 'SKU-001';
-- Mělo by vrátit: "UPRAVENÝ produkt A", 399.00
```

---

## 📝 Testovací data - Generování

### Malý dataset (10 produktů)
```bash
cat > test-small.csv << 'EOF'
code;pairCode;name;defaultCategory;price
P001;;Produkt 1;Kat A;100
P002;;Produkt 2;Kat A;200
P003;VAR1;Varianta Produkt;Kat B;300
P004;VAR1;Varianta M;Kat B;300
P005;VAR1;Varianta L;Kat B;300
P006;;Produkt 6;Kat C;400
P007;;Produkt 7;Kat C;500
P008;VAR2;Další varianta;Kat D;600
P009;VAR2;Další M;Kat D;600
P010;VAR2;Další L;Kat D;600
EOF
```

### Střední dataset (100 produktů)
```bash
# Generuj pomocí PHP
php -r '
echo "code;pairCode;name;defaultCategory;price\n";
for ($i = 1; $i <= 100; $i++) {
    $code = "SKU-" . str_pad($i, 4, "0", STR_PAD_LEFT);
    $name = "Produkt " . $i;
    $cat = "Kategorie " . (($i % 5) + 1);
    $price = rand(100, 9999) / 10;
    echo "$code;;$name;$cat;$price\n";
}
' > test-medium.csv
```

### Velký dataset (10,000 produktů)
```bash
php -r '
echo "code;pairCode;name;defaultCategory;price\n";
for ($i = 1; $i <= 10000; $i++) {
    $code = "BIG-" . str_pad($i, 5, "0", STR_PAD_LEFT);
    $name = "Velký produkt " . $i;
    $cat = "Kategorie " . (($i % 20) + 1);
    $price = rand(100, 99999) / 10;
    echo "$code;;$name;$cat;$price\n";
}
' > test-large.csv
```

---

## 🎓 Co testovat

### Funkční testy
- [ ] Import standardního CSV
- [ ] Import s vlastními sloupci
- [ ] Grupování variant (pairCode)
- [ ] Re-import (update)
- [ ] České znaky (CP1250, UTF-8)
- [ ] Prázdná pole (null handling)
- [ ] Neplatné hodnoty (price = "abc")

### Performance testy
- [ ] 100 produktů → čas?
- [ ] 1,000 produktů → čas?
- [ ] 10,000 produktů → čas?
- [ ] Memory usage (sleduj PHP process)

### Edge cases
- [ ] CSV bez pairCode sloupce
- [ ] CSV s prázdnými řádky
- [ ] CSV s BOM (Byte Order Mark)
- [ ] Duplicitní code v CSV
- [ ] Velmi dlouhé hodnoty (>500 znaků)

---

## 🚀 Produkční deployment

**Před nasazením:**

1. **Cron setup:**
```bash
# /etc/crontab
*/5 * * * * www-data php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode-xml.log 2>&1
```

2. **PHP limity:**
```ini
; /etc/php/8.x/cli/php.ini
memory_limit = 512M
max_execution_time = 0
```

3. **Log rotation:**
```bash
# /etc/logrotate.d/shopcode
/var/log/shopcode-xml.log {
    daily
    rotate 7
    compress
    missingok
    notifempty
}
```

4. **Monitoring:**
```bash
# Sleduj zaseknuté importy
*/15 * * * * root mysql shopcode -e "SELECT COUNT(*) FROM xml_processing_queue WHERE status='processing' AND started_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)" | mail -s "ShopCode stuck imports" admin@example.com
```

---

## 📞 Support

**Pokud něco nefunguje:**

1. Zkontroluj logy: `tail -f /var/log/shopcode-xml.log`
2. Zkontroluj databázi: `SELECT * FROM xml_processing_queue ORDER BY id DESC LIMIT 1`
3. Zkontroluj error_message ve frontě
4. Spusť worker manuálně: `php cron/process-xml.php`
5. Přečti error log v response
6. Kontaktuj support s log výpisem

---

**Datum:** 25. února 2026  
**Verze:** Production Ready  
**Status:** ✅ Připraveno k testování
