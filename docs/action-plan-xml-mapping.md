# Action Plan: XML Field Mapping - Hardcoded přístup

## 🎯 Cíl
Implementovat pevné (hardcoded) mapování XML polí v `XmlParser.php` podle tvého zadání.

---

## 📋 Checklist implementace

### Fáze 1: Úprava XmlParser.php ⏱️ 15 minut

- [ ] **1.1** Přidat konstantu `FIELD_MAP` do `XmlParser.php`
```php
private const FIELD_MAP = [
    'code'         => 'CODE',
    'name'         => 'n',
    'category'     => 'defaultCategory',
    'price'        => 'PRICE_VAT',
    'currency'     => 'CURRENCY',
    'description'  => 'DESCRIPTION',
    'availability' => 'AVAILABILITY_OUT_OF_STOCK',
    'url'          => 'ORIG_URL',
];
```

- [ ] **1.2** Upravit `parseProductNode()` pro použití konstanty
```php
// Místo hardcoded:
'name' => self::text($node->n),

// Použít:
'name' => self::text($node->{self::FIELD_MAP['name']}),
```

- [ ] **1.3** Speciální logika zůstává (brand, images, parameters, stock)
```php
// Tyto funkce zůstávají beze změny:
'brand'      => self::parseTextProperty($node, 'Výrobce') ?: ...,
'images'     => self::parseImages($node),
'parameters' => self::parseParameters($node),
'stock'      => self::parseStock($node),
```

### Fáze 2: Odstranění XML mapping z UI ⏱️ 10 minut

- [ ] **2.1** Upravit `src/Views/xml/index.php`
```html
<!-- Odstranit nebo zakomentovat celý accordion: -->
<!-- <div id="xmlMapping"> ... </div> -->
```

- [ ] **2.2** Upravit `XmlController::start()`
```php
// Odstranit zpracování XML field_map (řádky 97-104):
// } else {
//     // XML mapování
//     foreach (self::XML_DEFAULT_MAP as $internal => $tag) {
//         ...
//     }
// }

// Nahradit:
} else {
    // XML má pevné mapování v XmlParser.php
    $fieldMap = [];
}
```

- [ ] **2.3** Odstranit konstantu `XML_DEFAULT_MAP` z `XmlController`
```php
// Smazat řádky 38-45
```

### Fáze 3: Dokumentace ⏱️ 5 minut

- [ ] **3.1** Přidat komentář do `XmlParser.php`
```php
/**
 * Pevné mapování XML tagů pro Shoptet Marketing Feed.
 * 
 * Pokud váš feed používá jiné názvy tagů, upravte tuto konstantu.
 * Alternativně kontaktujte podporu pro konfiguraci specifického feedu.
 */
private const FIELD_MAP = [ ... ];
```

- [ ] **3.2** Aktualizovat README nebo docs
```markdown
## XML Import

XML import používá pevné mapování tagů optimalizované pro Shoptet Marketing Feed.

Podporované tagy:
- `<CODE>` → kód produktu
- `<n>` → název produktu
- `<PRICE_VAT>` → cena s DPH
- ... (viz XmlParser.php)

Pro vlastní XML strukturu kontaktujte podporu.
```

### Fáze 4: Testování ⏱️ 20 minut

- [ ] **4.1** Otestovat s reálným Shoptet XML feedem
- [ ] **4.2** Ověřit parsování všech polí
- [ ] **4.3** Zkontrolovat varianty
- [ ] **4.4** Ověřit error handling

---

## 🔧 Implementační detaily

### Úprava parseProductNode()

**Před:**
```php
$product = [
    'shoptet_id'   => isset($attrs['id']) ? (string)$attrs['id'] : null,
    'name'         => self::text($node->n),
    'description'  => self::text($node->DESCRIPTION),
    'price'        => self::decimal((string)($node->PRICE_VAT ?? '')),
    'currency'     => self::text($node->CURRENCY) ?: 'CZK',
    'code'         => self::text($node->CODE),
    'url'          => self::text($node->ORIG_URL),
    'availability' => self::text($node->AVAILABILITY_OUT_OF_STOCK),
    // ...
];
```

**Po:**
```php
$map = self::FIELD_MAP;

$product = [
    'shoptet_id'   => isset($attrs['id']) ? (string)$attrs['id'] : null,
    'name'         => self::text($node->{$map['name']}),
    'description'  => self::text($node->{$map['description']}),
    'price'        => self::decimal((string)($node->{$map['price']} ?? '')),
    'currency'     => self::text($node->{$map['currency']}) ?: 'CZK',
    'code'         => self::text($node->{$map['code']}),
    'url'          => self::text($node->{$map['url']}),
    'availability' => self::text($node->{$map['availability']}),
    
    // Speciální logika zůstává:
    'category'     => self::parsePrimaryCategory($node),
    'brand'        => self::parseTextProperty($node, 'Výrobce')
                   ?: self::parseTextProperty($node, 'Značka')
                   ?: self::parseTextProperty($node, 'Brand'),
    'stock'        => self::parseStock($node),
    'images'       => self::parseImages($node),
    'parameters'   => self::parseParameters($node),
    'xml_data'     => null,
];
```

### Úprava parseVariants()

**Před:**
```php
$variants[] = [
    'shoptet_variant_id' => $variantId,
    'name'               => self::text($varNode->n ?? null),
    'code'               => self::text($varNode->CODE ?? null),
    'price'              => self::decimal((string)($varNode->PRICE_VAT ?? '')),
    // ...
];
```

**Po:**
```php
$map = self::FIELD_MAP;

$variants[] = [
    'shoptet_variant_id' => $variantId,
    'name'               => self::text($varNode->{$map['name']} ?? null),
    'code'               => self::text($varNode->{$map['code']} ?? null),
    'price'              => self::decimal((string)($varNode->{$map['price']} ?? '')),
    // ...
];
```

---

## 🎨 UI Changes

### xml/index.php

**Odstranit tento blok (řádky ~200-250):**
```html
<!-- XML Mapování (volitelné, skryté v accordionu) -->
<div id="xmlMapping">
    <div class="accordion accordion-flush mt-3" id="xmlAccordion">
        <div class="accordion-item border rounded">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed py-2 px-3 small" type="button"
                        data-bs-toggle="collapse" data-bs-target="#xmlMapBody">
                    <i class="bi bi-sliders me-2 text-muted"></i>Pokročilé: vlastní mapování XML tagů
                </button>
            </h2>
            <div id="xmlMapBody" class="accordion-collapse collapse">
                <div class="accordion-body py-2">
                    <!-- TENTO CELÝ BLOK ODSTRANIT -->
                </div>
            </div>
        </div>
    </div>
</div>
```

**Nahradit informativním hlášením:**
```html
<!-- XML Info -->
<div id="xmlMapping">
    <div class="alert alert-info py-2 px-3 mt-3 small">
        <i class="bi bi-info-circle me-1"></i>
        <strong>XML import:</strong> Používá standardní Shoptet Marketing Feed strukturu.
        Pro nestandardní XML formáty kontaktujte podporu.
    </div>
</div>
```

---

## 🔍 Testing Checklist

### Test 1: Základní XML import
```xml
<SHOP>
  <SHOPITEM id="12345">
    <n>Test Produkt</n>
    <CODE>SKU-001</CODE>
    <PRICE_VAT>299.50</PRICE_VAT>
    <CURRENCY>CZK</CURRENCY>
    <DESCRIPTION>Popis produktu</DESCRIPTION>
  </SHOPITEM>
</SHOP>
```

**Očekávaný výsledek:**
- ✅ Produkt vytvořen
- ✅ Všechna pole správně parsována
- ✅ Žádné chyby

### Test 2: Produkt s variantami
```xml
<SHOPITEM id="12345">
  <n>Tričko</n>
  <CODE>TRICKO-MAIN</CODE>
  <PRICE_VAT>399</PRICE_VAT>
  <VARIANTS>
    <VARIANT id="12346">
      <n>Tričko M</n>
      <CODE>TRICKO-M</CODE>
      <PRICE_VAT>399</PRICE_VAT>
    </VARIANT>
  </VARIANTS>
</SHOPITEM>
```

**Očekávaný výsledek:**
- ✅ 1 produkt s 1 variantou
- ✅ Varianta má správný code a price

### Test 3: Chybějící tagy (fallback)
```xml
<SHOPITEM id="12345">
  <n>Minimální produkt</n>
  <CODE>SKU-MIN</CODE>
  <!-- Chybí PRICE_VAT, CURRENCY, atd. -->
</SHOPITEM>
```

**Očekávaný výsledek:**
- ✅ Produkt vytvořen
- ✅ price = null
- ✅ currency = 'CZK' (default)
- ✅ Žádná chyba

---

## ⚠️ Backwards Compatibility

### Databáze
- ✅ Žádné změny DB schématu
- ✅ Existující data nejsou ovlivněna

### API
- ✅ Žádné změny v API endpointech
- ✅ CSV import funguje stejně

### UI
- ⚠️ XML mapping formulář odstraněn
- ℹ️ Uživatelé uvidí info hlášku místo formuláře

---

## 📦 Git Commit Message

```
feat: Implement hardcoded XML field mapping

- Add FIELD_MAP constant to XmlParser.php
- Update parseProductNode() to use field map
- Update parseVariants() to use field map
- Remove XML mapping UI from xml/index.php
- Remove XML_DEFAULT_MAP from XmlController
- Add documentation for field mapping

XML import now uses fixed field mapping optimized for Shoptet Marketing Feed.
CSV import retains full UI-based field mapping flexibility.

Refs: Final implementation before deployment
```

---

## 🎯 Alternativní přístup (pokud se rozhodneš jinak)

### Config-based mapping

**1. Vytvořit:** `config/xml-field-map.php`
```php
<?php
return [
    'code'         => env('XML_TAG_CODE', 'CODE'),
    'name'         => env('XML_TAG_NAME', 'n'),
    'price'        => env('XML_TAG_PRICE', 'PRICE_VAT'),
    // ...
];
```

**2. Načíst v XmlParser:**
```php
private static function getFieldMap(): array
{
    static $map = null;
    if ($map === null) {
        $map = require ROOT . '/config/xml-field-map.php';
    }
    return $map;
}
```

**Výhody:**
- ✅ Lze změnit bez úpravy kódu
- ✅ Environment-specific konfigurace

**Nevýhody:**
- ❌ Složitější
- ❌ Možné chyby při špatné konfiguraci

---

## ⏱️ Časový odhad

| Fáze | Čas | Poznámka |
|------|-----|----------|
| XmlParser úprava | 15 min | Přidání konstanty + použití |
| UI cleanup | 10 min | Odstranění XML mapping form |
| Testování | 20 min | Import reálného XML feedu |
| Dokumentace | 5 min | Komentáře + README |
| **CELKEM** | **50 min** | Quick win |

---

## ✅ Definition of Done

- [ ] `XmlParser::FIELD_MAP` konstanta existuje
- [ ] `parseProductNode()` používá field map
- [ ] `parseVariants()` používá field map
- [ ] XML mapping UI odstraněn/zakomentován
- [ ] `XML_DEFAULT_MAP` odstraněna z controlleru
- [ ] Testováno s reálným XML feedem
- [ ] Dokumentace aktualizována
- [ ] Commit pushed do main branch
- [ ] Žádné regression chyby v CSV importu

---

**Připraveno k implementaci:** ✅ Ano
**Riziko:** 🟢 Nízké (izolovaná změna)
**Impact:** 🟢 Pozitivní (jednodušší údržba)
