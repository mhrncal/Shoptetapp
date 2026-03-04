# 📦 Průvodce importy produktů a automatickým párováním recenzí

## 🎯 Co to dělá

Systém automaticky:
1. Stahuje CSV feed s produkty ze Shoptetu
2. Ukládá produkty do databáze (včetně obrázků)
3. Páruje fotorecenze s produkty podle SKU
4. Generuje XML/CSV export s recenzemi + produktovými fotkami

---

## 🚀 Jak to nastavit

### 1. Získej URL k CSV exportu ze Shoptetu

V Shoptetu:
- Exporty → Vytvořit nový export
- Vyber sloupce: `code`, `pairCode`, `name`, `defaultImage`, `image`, `image2`, atd.
- Zkopíruj URL s hash parametrem

Př: `https://755166.myshoptet.com/export/products.csv?patternId=204&partnerId=9&hash=xxx`

### 2. Vytvoř import v ShopCode

Navigace: **Importy produktů** → **Nový import**

Vyplň:
- **Název**: např. "Shoptet produkty"
- **URL**: URL k CSV feedu
- **Typ CSV**: 
  - "Základní" = jen code, pairCode, name
  - **"S obrázky"** = + sloupce defaultImage, image, image2, ...
- **Oddělovač**: `;` (středník pro Shoptet)
- **Kódování**: `windows-1250` (výchozí Shoptet)
- **Povolit automatickou synchronizaci**: ✅ ANO

Klikni **"Vytvořit import"**

### 3. Spusť první synchronizaci

Na stránce "Importy produktů" klikni na ikonu **🔄** u feedu.

Systém:
- Stáhne CSV (~1-5 MB)
- Naparsuje produkty (~500-5000 řádků)
- Uloží do databáze
- Spáruje s recenzemi
- Vygeneruje exporty

---

## ⚙️ Automatická synchronizace (CRON)

### Nastavení

```bash
# Přidej do crontab
crontab -e

# Přidej řádek (spustí se každý den ve 3:00)
5 3 * * * /usr/bin/php /srv/app/cron/feed_sync.php >> /srv/app/tmp/logs/feed_sync.log 2>&1
```

### Co CRON dělá

1. **03:05** - Spustí se feed_sync.php
2. Pro každý aktivní feed:
   - Stáhne CSV ze Shoptetu
   - Parsuje řádky (batch po 500)
   - INSERT nové produkty
   - UPDATE existující produkty
   - Spáruje recenze podle SKU
   - Vygeneruje XML + CSV exporty

### Výstupy

Soubory se uloží do: `/public/feeds/`

- `user_1_reviews_with_products.xml`
- `user_1_reviews_with_products.csv`

---

## 📊 Formát XML exportu

```xml
<SHOP>
    <SHOPITEM>
        <CODE>PC858/SED</CODE>
        <IMAGES>
            <!-- Produktové fotky (ze Shoptetu) -->
            <IMAGE description="">https://cdn.myshoptet.com/.../product.jpg</IMAGE>
            <IMAGE description="">https://cdn.myshoptet.com/.../product2.jpg</IMAGE>
            
            <!-- Zákaznické fotky (fotorecenze) -->
            <IMAGE description="Zákaznická fotka">https://aplikace.shopcode.cz/uploads/1/xxx/photo.jpg</IMAGE>
        </IMAGES>
        <IMAGE_REF>https://cdn.myshoptet.com/.../product.jpg</IMAGE_REF>
    </SHOPITEM>
</SHOP>
```

---

## 📁 Formát CSV exportu

Sloupce (oddělené středníkem):
```
code;pairCode;name;author_name;author_email;rating;comment;review_photos;product_images;created_at
```

**review_photos** a **product_images** = URL oddělené `|`

---

## 🔍 Párování recenzí s produkty

### Logika

Recenze se spáruje s produktem pokud:
```sql
review.sku = product.code 
  NEBO 
review.sku = product.pair_code
```

### Příklad

**CSV řádek:**
```
code=0521;pairCode=1;name=Acáena Buchananova;...
```

**Recenze s SKU:**
- `sku='0521'` ✅ Spáruje se (code match)
- `sku='1'` ✅ Spáruje se (pairCode match)
- `sku='xxx'` ❌ Nespáruje se

---

## 🛠️ Typy CSV

### Základní CSV (csv_simple)

Povinné sloupce:
- `code`
- `pairCode` (optional)
- `name`

**Použití:** Jen párování bez produktových fotek

### CSV s obrázky (csv_with_images)

Povinné sloupce:
- `code`
- `pairCode` (optional)
- `name`
- `defaultImage`
- `image`, `image2`, `image3`, ... `image28` (optional)

**Použití:** Párování + export s produktovými fotkami

---

## 📈 Monitoring

### Logy

```bash
# Sleduj CRON logy
tail -f /srv/app/tmp/logs/feed_sync.log
```

### Admin UI

V "Importy produktů" vidíš:
- ✅ Status: OK / ❌ Chyba
- 🕒 Poslední stažení
- Chybové hlášky (tooltip na "Chyba")

---

## ⚡ Minimální nároky na server

### Optimalizace

✅ **Stream download** - CSV se stahuje po částech (ne celý do RAM)  
✅ **Batch insert** - 500 produktů najednou  
✅ **Cleanup** - Staré cache CSV se mažou po 7 dnech  
✅ **Indexy** - Rychlé vyhledávání podle code/pairCode  

### Spotřeba

- **RAM**: ~50 MB (i pro 10k produktů)
- **Disk**: ~2-10 MB CSV cache
- **DB**: ~1-5 MB pro 5000 produktů
- **Čas**: ~30-120 sekund (záleží na velikosti CSV)

---

## 🧪 Testování

### Manuální test

1. Vytvoř feed
2. Klikni 🔄 "Synchronizovat teď"
3. Zkontroluj výsledek:
   - Produkty v DB: `SELECT COUNT(*) FROM products WHERE user_id=1`
   - Spárované recenze: `SELECT COUNT(*) FROM reviews WHERE product_id IS NOT NULL`
   - Export: `/public/feeds/user_1_reviews_with_products.xml`

### CRON test

```bash
# Spusť ručně
/usr/bin/php /srv/app/cron/feed_sync.php

# Zkontroluj log
cat /srv/app/tmp/logs/feed_sync.log
```

---

## 🐛 Troubleshooting

### CSV se nestahuje

- Zkontroluj URL (musí být veřejně přístupný)
- Zkontroluj hash parametr (platnost)

### Produkty se neukládají

- Zkontroluj encoding (windows-1250 vs UTF-8)
- Zkontroluj delimiter (`;` vs `,`)
- Zkontroluj sloupce (code, name musí existovat)

### Recenze se nepárují

- Zkontroluj SKU v recenzích (`SELECT DISTINCT sku FROM reviews`)
- Zkontroluj code v produktech (`SELECT DISTINCT code FROM products`)
- Zkontroluj case-sensitivity (ABC vs abc)

---

## 📝 Migrace

```bash
# Spusť migrace v tomto pořadí
mysql < database/migrations/007_create_product_feeds.sql
mysql < database/migrations/008_add_paircode_to_products.sql
```

---

## ✅ Hotovo!

Systém je připravený. Každý den:
1. Stáhne aktuální produkty ze Shoptetu
2. Spáruje s recenzemi
3. Vygeneruje XML/CSV s fotkami

**URL k exportům:**
- `https://aplikace.shopcode.cz/feeds/user_1_reviews_with_products.xml`
- `https://aplikace.shopcode.cz/feeds/user_1_reviews_with_products.csv`
