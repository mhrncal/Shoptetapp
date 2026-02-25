# Analýza parsování produktů v ShopCode

## 📊 Přehled současného stavu

ShopCode má plně funkční **dual-format import systém** podporující XML i CSV feedy s pokročilými možnostmi mapování polí.

---

## 🔧 Architektura parsování

### 1. XML Parsování

**Soubor:** `src/Services/XmlParser.php`

#### Klíčové vlastnosti:
- ✅ **Streamovací parser** — efektivní pro velké soubory
- ✅ **XMLReader** — nízká spotřeba paměti
- ✅ **Shoptet Marketing XML feed** — plná podpora struktury
- ✅ **Varianty** — kompletní zpracování včetně parametrů

#### Podporovaná XML struktura:
```xml
<SHOP>
  <SHOPITEM id="251441">
    <n>Název produktu</n>              <!-- název v <n>, NE <PRODUCTNAME> -->
    <CODE>SKU</CODE>
    <PRICE_VAT>1299</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
    <DESCRIPTION><![CDATA[...]]></DESCRIPTION>
    <CATEGORIES>
      <DEFAULT_CATEGORY id="22918">Kategorie</DEFAULT_CATEGORY>
    </CATEGORIES>
    <IMAGES>
      <IMAGE>https://cdn.myshoptet.com/...</IMAGE>
    </IMAGES>
    <PARAMETERS>
      <PARAMETER>
        <n>Barva</n>
        <VALUE>černá</VALUE>
      </PARAMETER>
    </PARAMETERS>
    <TEXT_PROPERTIES>
      <TEXT_PROPERTY>
        <n>Značka</n>
        <VALUE>Nike</VALUE>
      </TEXT_PROPERTY>
    </TEXT_PROPERTIES>
    <STOCK><AMOUNT>10</AMOUNT></STOCK>
    <VARIANTS>
      <VARIANT id="252560">
        <n>Varianta M</n>
        <CODE>SKU-M</CODE>
        <PRICE_VAT>1299</PRICE_VAT>
        <STOCK><AMOUNT>5</AMOUNT></STOCK>
      </VARIANT>
    </VARIANTS>
  </SHOPITEM>
</SHOP>
```

#### Parsovaná pole:
| Interní pole | XML tag | Poznámka |
|--------------|---------|----------|
| `shoptet_id` | `@id` atribut | Povinné |
| `name` | `<n>` | Fallback na `<PRODUCTNAME>` |
| `code` | `<CODE>` | SKU produktu |
| `price` | `<PRICE_VAT>` | Decimal, nullable |
| `currency` | `<CURRENCY>` | Default: CZK |
| `description` | `<DESCRIPTION>` | CDATA support |
| `category` | `<DEFAULT_CATEGORY>` | Fallback na první `<CATEGORY>` |
| `brand` | TEXT_PROPERTIES | Hledá: Výrobce, Značka, Brand |
| `availability` | `<AVAILABILITY_OUT_OF_STOCK>` | |
| `images` | `<IMAGES><IMAGE>` | JSON pole URL |
| `parameters` | `<PARAMETERS>` + `<TEXT_PROPERTIES>` | JSON objekt |
| `stock` | `<STOCK><AMOUNT>` | Integer |

#### Fallback logika:
```php
// Kategorie - preferuje DEFAULT_CATEGORY
if (isset($node->CATEGORIES->DEFAULT_CATEGORY)) → použij
else → vezmi první CATEGORY

// Brand - hledá v TEXT_PROPERTIES
parseTextProperty($node, 'Výrobce') 
  ?: parseTextProperty($node, 'Značka') 
  ?: parseTextProperty($node, 'Brand')

// Obrázky - fallback na staré tagy
<IMAGES><IMAGE> → preferováno
fallback → <IMGURL>, <IMGURL_ALTERNATIVE>
```

### 2. CSV Parsování

**Soubor:** `src/Services/CsvParser.php`

#### Klíčové vlastnosti:
- ✅ **Flexibilní field mapping** — uživatelsky konfigurovatelné
- ✅ **Automatická detekce kódování** — UTF-8, UTF-16, CP1250, ISO-8859-2
- ✅ **BOM handling** — správné zpracování Byte Order Mark
- ✅ **Grupování variant** — podle `pairCode`
- ✅ **Delimiter** — středník (`;`)

#### Detekce kódování (priorita):
```php
1. UTF-8 BOM (EF BB BF)          → odstranění BOM
2. UTF-16 LE BOM (FF FE)         → konverze na UTF-8
3. UTF-16 BE BOM (FE FF)         → konverze na UTF-8
4. Platné UTF-8 bez BOM          → žádná konverze
5. CP1250 (Windows-1250)         → iconv + detekce českých znaků
6. ISO-8859-2                    → iconv konverze
7. Poslední záchrana: CP1250     → bez validace
```

#### Výchozí mapování:
```php
private const DEFAULT_MAP = [
    'code'         => 'code',           // POVINNÉ
    'pairCode'     => 'pairCode',       // Pro varianty
    'name'         => 'name',
    'category'     => 'defaultCategory',
    'price'        => '',               // Prázdné = ignorovat
    'brand'        => '',
    'description'  => '',
    'availability' => '',
    'images'       => '',
    'ean'          => '',
    'stock'        => '',
];
```

#### Logika grupování variant:
```php
// Prázdný pairCode → jednoduchý produkt
if ($pairCode === '') {
    singles[] = produkt
}

// Vyplněný pairCode → variantní produkt
else {
    grouped[$pairCode][] = produkt
}

// Výsledek:
product = data z PRVNÍHO řádku skupiny
variantCodes = [code1, code2, code3, ...]
```

#### Dostupná CSV pole:
| Interní | Shoptet CSV sloupec | Popis |
|---------|---------------------|-------|
| `code` | `code` | **Povinné** - SKU produktu |
| `pairCode` | `pairCode` | Grupování variant |
| `name` | `name` | Název produktu |
| `category` | `defaultCategory` | Výchozí kategorie |
| `price` | `price` | Cena s DPH |
| `originalPrice` | `originalPrice` | Původní cena |
| `vat` | `vat` | DPH % |
| `stock` | `stock` | Skladová zásoba |
| `brand` | `brand` | Značka |
| `ean` | `ean` | EAN kód |
| `weight` | `weight` | Hmotnost |
| `description` | `description` | Popis produktu |
| `url` | `url` | URL produktu |
| `image` | `image` | URL obrázku |
| `availability` | `availability` | Dostupnost |

---

## 🔄 Workflow zpracování

### Tok dat:

```
┌─────────────────┐
│ Uživatel        │
│ /xml/start      │ → Formulář s field_map
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────┐
│ XmlController::start()          │
│ - Validace URL                  │
│ - Sestavení field_map z POST    │
│ - XmlDownloader::probe()        │
│ - XmlImport::addToQueue()       │
└────────┬────────────────────────┘
         │
         ▼ (uloženo do DB)
┌─────────────────────────────────┐
│ xml_processing_queue            │
│ - feed_format: xml|csv          │
│ - field_map: JSON               │
│ - status: pending               │
└────────┬────────────────────────┘
         │
         ▼ (cron každých 5 min)
┌─────────────────────────────────┐
│ cron/process-xml.php            │
│ - Lock file (prevent duplicates)│
│ - QueueWorker::processNext()    │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ QueueWorker                     │
│ - Download feed                 │
│ - Detekce formátu              │
│ ├─ XML → XmlParser::stream()   │
│ └─ CSV → CsvParser::stream()   │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ XmlImporter                     │
│ - Batch upsert (500 produktů)  │
│ - ON DUPLICATE KEY UPDATE       │
│ - Varianty linking              │
│ - Progress tracking             │
└────────┬────────────────────────┘
         │
         ▼
┌─────────────────────────────────┐
│ Database                        │
│ - products                      │
│ - product_variants              │
│ - xml_imports (history)         │
└─────────────────────────────────┘
```

---

## 📦 Databázová struktura

### Tabulka: `products`
```sql
CREATE TABLE products (
    id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id       INT UNSIGNED NOT NULL,
    shoptet_id    VARCHAR(255) NOT NULL,      -- Z XML: @id, z CSV: pairCode nebo code
    code          VARCHAR(255),                -- SKU
    name          VARCHAR(500) NOT NULL,
    description   LONGTEXT,
    price         DECIMAL(12,2),
    currency      VARCHAR(10) DEFAULT 'CZK',
    category      VARCHAR(255),
    brand         VARCHAR(255),
    availability  VARCHAR(100),
    images        JSON,                        -- ["url1", "url2"]
    parameters    JSON,                        -- {"Barva": "černá", "Velikost": "M"}
    xml_data      JSON,                        -- Rezerva pro raw data
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_product_user_shoptet (user_id, shoptet_id),
    KEY idx_products_user (user_id)
);
```

### Tabulka: `product_variants`
```sql
CREATE TABLE product_variants (
    id                  INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    product_id          INT UNSIGNED NOT NULL,
    shoptet_variant_id  VARCHAR(255) NOT NULL,  -- Z XML: VARIANT/@id, z CSV: code
    code                VARCHAR(255),            -- SKU varianty
    name                VARCHAR(500),
    price               DECIMAL(12,2),
    stock               INT DEFAULT 0,
    parameters          JSON,                    -- Parametry specifické pro variantu
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_variant_user_shoptet (user_id, shoptet_variant_id),
    KEY idx_variants_product (product_id)
);
```

### Tabulka: `xml_processing_queue`
```sql
CREATE TABLE xml_processing_queue (
    id                  INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    xml_feed_url        TEXT NOT NULL,
    feed_format         ENUM('xml','csv') DEFAULT 'xml',
    field_map           JSON,                    -- {"code": "SKU", "name": "Název"}
    status              ENUM('pending','processing','completed','failed'),
    priority            TINYINT UNSIGNED DEFAULT 5,
    progress_percentage TINYINT UNSIGNED DEFAULT 0,
    products_processed  INT DEFAULT 0,
    error_message       TEXT,
    retry_count         TINYINT UNSIGNED DEFAULT 0,
    max_retries         TINYINT UNSIGNED DEFAULT 3,
    started_at          DATETIME,
    completed_at        DATETIME,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_queue_status_priority (status, priority, created_at)
);
```

---

## 🎯 Field Mapping - Aktuální implementace

### CSV Mapping (plně funkční)
**UI:** `src/Views/xml/index.php` - formulář s tabulkou mapování

**Příklad POST dat:**
```php
$_POST['field_map'] = [
    'code'     => 'SKU',              // Vlastní název sloupce
    'name'     => 'Název produktu',
    'category' => 'Kategorie',
    'price'    => 'Cena s DPH',
    'brand'    => 'Značka',
    // ... ostatní pole
];
```

**Zpracování:**
1. `XmlController::start()` → sestaví field_map z POST
2. Uloží do `xml_processing_queue.field_map` jako JSON
3. `QueueWorker` → předá field_map do `CsvParser::stream()`
4. Parser používá resolver pro mapování sloupců

### XML Mapping (částečně implementováno)

**Současný stav:**
- ✅ UI accordion s formulářem pro XML tagy
- ✅ Ukládání do databáze (`field_map` JSON)
- ⚠️ **CHYBÍ:** Použití field_map v `XmlParser`

**Problém:**
```php
// QueueWorker.php - řádek 163-171
private function parseXml(array $item, string $tmpFile, XmlImporter $importer, array $fieldMap): array
{
    // fieldMap pro XML (zatím ignorujeme — XmlParser má svou vlastní logiku)
    return XmlParser::stream(
        $tmpFile,
        fn($product, $variants) => $importer->addProduct($product, $variants),
        fn($count) => $this->log($item['id'], "  ↻ Zpracováno: {$count}")
    );
}
```

**XmlParser má hardcoded mapování:**
```php
// XmlParser.php - řádky 120-164
'name'         => self::text($node->n),              // Vždy <n>
'price'        => self::decimal((string)($node->PRICE_VAT ?? '')),  // Vždy PRICE_VAT
'code'         => self::text($node->CODE),           // Vždy CODE
// atd...
```

---

## ⚠️ Co NEFUNGUJE / Chybí

### 1. XML Field Mapping není aktivní

**Popis problému:**
- Uživatel může v UI nastavit vlastní XML tagy
- Formulář ukládá data do `field_map`
- **ALE:** `XmlParser` ignoruje `field_map` a používá pevné názvy tagů

**Řešení:**
Potřebujeme upravit `XmlParser::parseProductNode()` aby přijímal a používal field_map:

```php
// Současný stav:
'name' => self::text($node->n),

// Potřebujeme:
'name' => self::text($node->{$fieldMap['name'] ?? 'n'}),
```

### 2. Chybějící XML pole v parseru

Podle CSV dostupných polí (15 polí) vs XML parser (12 polí):

**Chybí v XML parseru:**
- ❌ `ean` — není parsováno
- ❌ `url` — parsováno ale ukládá se do `url` místo interního použití
- ❌ `weight` — není parsováno
- ❌ `originalPrice` — není parsováno
- ❌ `vat` — není parsováno

### 3. Nekonzistentní pole mezi XML a CSV

| Pole | XML Parser | CSV Parser | DB Schema | Poznámka |
|------|------------|------------|-----------|----------|
| `code` | ✅ CODE | ✅ code | ✅ code | OK |
| `name` | ✅ n | ✅ name | ✅ name | OK |
| `price` | ✅ PRICE_VAT | ✅ price | ✅ price | OK |
| `stock` | ✅ STOCK/AMOUNT | ✅ stock | ❌ | **CHYBÍ v products!** |
| `ean` | ❌ | ✅ ean | ❌ | Chybí oboje |
| `weight` | ❌ | ✅ weight | ❌ | Chybí oboje |
| `vat` | ❌ | ✅ vat | ❌ | Chybí oboje |
| `url` | ✅ ORIG_URL | ✅ url | ❌ | Parsuje se, neukládá |

---

## ✅ Co FUNGUJE správně

### 1. CSV Parsování - Kompletní
- ✅ Flexibilní field mapping
- ✅ Automatická detekce kódování
- ✅ Grupování variant podle pairCode
- ✅ Error handling a progress tracking
- ✅ Batch processing (500 produktů)

### 2. XML Parsování - Shoptet Marketing Feed
- ✅ Streamovací parser pro velké soubory
- ✅ Správné zpracování CDATA
- ✅ Varianty s parametry
- ✅ Fallback logika pro kategorie/brand
- ✅ JSON encoding pro images a parameters

### 3. Import Pipeline
- ✅ Queue system s prioritami
- ✅ Lock mechanismus (prevent duplicates)
- ✅ Retry logic (max 3 pokusy)
- ✅ Progress tracking (% a počet)
- ✅ Webhook notifikace
- ✅ Email notifikace při selhání

### 4. Database Operations
- ✅ Batch upsert (ON DUPLICATE KEY UPDATE)
- ✅ Foreign key constraints
- ✅ Transaction handling
- ✅ Indexy pro rychlost

---

## 🔍 Specifické nálezy

### Kódování CSV
```php
// CsvParser má robustní detekci:
if (BOM UTF-8)     → odstranění BOM
if (BOM UTF-16 LE) → mb_convert_encoding
if (BOM UTF-16 BE) → mb_convert_encoding
if (UTF-8 valid)   → použít přímo
if (CP1250 + české znaky) → iconv
if (ISO-8859-2)    → iconv
else → CP1250 fallback
```

### XML Varianty
```php
// XmlParser správně zpracovává:
<VARIANTS>
  <VARIANT id="252560">
    <n>Varianta M</n>
    <CODE>SKU-M</CODE>
    <PRICE_VAT>1299</PRICE_VAT>
    <STOCK><AMOUNT>5</AMOUNT></STOCK>
    <PARAMETERS>...</PARAMETERS>
  </VARIANT>
</VARIANTS>

// → Ukládá jako product_variants s vazbou na products
```

### Performance optimalizace
```php
// XmlImporter.php - batch processing
private const BATCH_SIZE = 500;

// Produkty:
INSERT INTO products (...) VALUES (...), (...), (...)  // 500x
ON DUPLICATE KEY UPDATE ...

// Varianty:
foreach (array_chunk($variantBatch, 500) as $chunk)    // Po 500
```

---

## 🎯 Doporučení pro XML Field Mapping

### Priorita 1: Implementovat field_map v XmlParser

**Současný problém:**
```php
// XmlController.php ukládá field_map:
$fieldMap = [
    'code'     => 'PRODUCT_CODE',    // uživatel chce jiný tag
    'name'     => 'PRODUCT_NAME',
    'price'    => 'PRICE',
    // ...
];

// ALE XmlParser ignoruje a používá:
'code' => self::text($node->CODE),    // pevný CODE
'name' => self::text($node->n),       // pevný n
```

**Řešení - 3 přístupy:**

#### A) Hardcoded mapování (tvoje preferovaný přístup)
```php
// XmlParser.php - přidat jako konstantu
private const FIELD_MAP = [
    'code'         => 'CODE',
    'name'         => 'n',
    'category'     => 'defaultCategory',
    'price'        => 'PRICE_VAT',
    'currency'     => 'CURRENCY',
    'description'  => 'DESCRIPTION',
    'availability' => 'AVAILABILITY_OUT_OF_STOCK',
    'brand'        => null,  // Speciální logika z TEXT_PROPERTIES
    'images'       => null,  // Speciální logika z IMAGES
    'parameters'   => null,  // Speciální logika
    'stock'        => null,  // Speciální logika ze STOCK/AMOUNT
];

// Pak v parseProductNode():
private static function parseProductNode(\SimpleXMLElement $node): array
{
    $map = self::FIELD_MAP;
    
    $product = [
        'shoptet_id'   => (string)($node->attributes()['id'] ?? ''),
        'code'         => self::text($node->{$map['code']}),
        'name'         => self::text($node->{$map['name']}),
        'price'        => self::decimal((string)($node->{$map['price']} ?? '')),
        'currency'     => self::text($node->{$map['currency']}) ?: 'CZK',
        'description'  => self::text($node->{$map['description']}),
        'category'     => self::parsePrimaryCategory($node),
        'availability' => self::text($node->{$map['availability']}),
        // ... speciální logika pro brand, images, atd.
    ];
    // ...
}
```

**Výhody:**
- ✅ Jednoduché na údržbu
- ✅ Jasné a přehledné
- ✅ Konzistentní chování

**Nevýhody:**
- ❌ Uživatel nemůže změnit mapování
- ❌ UI formulář pro XML mapping je zbytečný

#### B) Konfigurovatelné mapování (komplexnější)
```php
// QueueWorker předá field_map do parseru
private function parseXml(array $item, string $tmpFile, XmlImporter $importer, array $fieldMap): array
{
    return XmlParser::stream(
        $tmpFile,
        fn($product, $variants) => $importer->addProduct($product, $variants),
        fn($count) => $this->log($item['id'], "  ↻ Zpracováno: {$count}"),
        $fieldMap  // NOVÝ parametr
    );
}

// XmlParser.php
public static function stream(
    string   $filePath,
    callable $callback,
    ?callable $progress = null,
    array    $fieldMap = []  // NOVÝ parametr
): array {
    // Merge s výchozím mapováním
    $map = array_merge(self::DEFAULT_MAP, $fieldMap);
    // ...
}
```

**Výhody:**
- ✅ Maximální flexibilita
- ✅ UI formulář dává smysl

**Nevýhody:**
- ❌ Složitější implementace
- ❌ Potřeba validace uživatelského vstupu
- ❌ Možné chyby při nesprávném mapování

#### C) Hybrid (doporučené)
```php
// Hardcoded pro standardní Shoptet XML
// Ale s možností override přes constants/config

// config/xml-mapping.php
return [
    'default' => [
        'code'  => 'CODE',
        'name'  => 'n',
        'price' => 'PRICE_VAT',
        // ...
    ],
    
    // Alternativní presets pro jiné systémy
    'heureka' => [
        'code'  => 'PRODUCT_ID',
        'name'  => 'PRODUCT_NAME',
        'price' => 'PRICE',
    ],
];
```

---

## 📋 Checklist implementace

### Minimální scope (tvé zadání - hardcoded):

- [ ] Přidat `private const FIELD_MAP` do `XmlParser.php`
- [ ] Upravit `parseProductNode()` použít konstantu
- [ ] Odstranit nebo deaktivovat XML mapping UI v `xml/index.php`
- [ ] Odstranit zpracování XML field_map v `XmlController::start()`
- [ ] Otestovat import s různými XML feedy

### Rozšířený scope (pokud chceš flexibilitu):

- [ ] Přidat parametr `$fieldMap` do `XmlParser::stream()`
- [ ] Merge uživatelského mapování s výchozím
- [ ] Validace field_map v `XmlController`
- [ ] Error handling pro neexistující XML tagy
- [ ] Dokumentace pro uživatele

### Databázové rozšíření (volitelné):

- [ ] Přidat sloupec `stock` do `products` tabulky
- [ ] Přidat sloupec `ean` do `products`
- [ ] Přidat sloupec `weight` do `products`
- [ ] Přidat sloupec `url` do `products`
- [ ] Přidat sloupec `original_price` do `products`
- [ ] Přidat sloupec `vat` do `products`
- [ ] Migrace pro existující data

---

## 🚀 Závěr

### Co funguje výborně:
1. ✅ **CSV import** — kompletní, robustní, flexibilní
2. ✅ **XML parsing** — efektivní streamování, správná logika
3. ✅ **Queue system** — spolehlivý, škálovatelný
4. ✅ **Batch operations** — optimalizované pro výkon

### Co potřebuje doladit:
1. ⚠️ **XML field mapping** — implementovat podle zvoleného přístupu (hardcoded doporučeno)
2. ⚠️ **Databázové schema** — zvážit přidání stock, ean, weight, url
3. ⚠️ **Konzistence** — sjednotit dostupná pole mezi XML a CSV

### Doporučení:
**Pro rychlé dokončení:** Jdi s hardcoded přístupem (přístup A)
- Definuj konstantu `FIELD_MAP` v `XmlParser`
- Uprav parsing logiku
- Odstraň/deaktivuj XML mapping UI
- **Výsledek:** Jednoduchý, stabilní, snadno udržovatelný

**Pro budoucí flexibilitu:** Hybrid přístup (přístup C)
- Config soubor s presets
- Možnost override v admin sekci
- Validace a error handling
- **Výsledek:** Flexibilní, ale víc práce

---

**Datum analýzy:** 25. února 2026
**Verze ShopCode:** Production-ready (dle memory)
**Status:** ✅ Připraven k finální implementaci XML field mappingu
