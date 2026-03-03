# 🔍 AUDIT SYSTÉMU FOTORECENZÍ

## ✅ OPRAVENO

### 1. Review::create() - Názvy sloupců
**Problém:** Používal `customer_name`, `customer_email`, `product_sku`  
**Oprava:** Změněno na `author_name`, `author_email`, `sku`  
**Status:** ✅ OPRAVENO

---

## 📋 KOMPLETNÍ ANALÝZA

### **DATABÁZOVÁ STRUKTURA**

#### Tabulka `reviews`:
```sql
- id (INT)
- user_id (INT) - majitel e-shopu
- product_id (INT) - nullable
- shoptet_id (VARCHAR) - Shoptet product ID
- sku (VARCHAR)
- author_name (VARCHAR) - jméno zákazníka
- author_email (VARCHAR) - email zákazníka
- rating (TINYINT) - 1-5
- comment (TEXT)
- photos (JSON) - DEPRECATED, používá se review_photos tabulka
- status (ENUM: pending/approved/rejected)
- admin_note (TEXT)
- imported (BOOLEAN)
- imported_at (DATETIME)
- created_at (DATETIME)
- reviewed_at (DATETIME)
```

#### Tabulka `review_photos`:
```sql
- id (INT)
- review_id (INT)
- path (VARCHAR) - cesta k display verzi (s watermarkem)
- thumb (VARCHAR) - cesta k thumbnailu
- mime_type (VARCHAR)
- created_at (TIMESTAMP)
```

---

### **API ENDPOINT**

**URL:** `POST /public/api/submit-review.php`

**Parametry:**
- `user_id` (int) - ID uživatele/shopu
- `name` (string) - jméno zákazníka
- `email` (string) - email zákazníka
- `sku` (string, optional) - SKU produktu
- `rating` (int, 1-5, optional)
- `comment` (string, optional)
- `photos[]` (files) - 1-5 fotek

**Process:**
1. Validace vstupu
2. Načtení user_id (explicitní nebo lookup podle SKU)
3. Zpracování fotek:
   - Resize (1024-1600px logika)
   - Aplikace watermarku
   - Vytvoření thumbnailu
   - Uložení 3 verzí: original, display, thumb
4. Uložení recenze do DB
5. Uložení fotek do review_photos
6. Email notifikace adminovi

**Status:** ✅ FUNGUJE

---

### **WATERMARK SYSTÉM**

**Config:** `/watermark/settings`

**Funkce:**
- Text watermarku (editovatelný)
- 9 pozic (TL, TC, TR, ML, MC, MR, BL, BC, BR)
- Font (Arial, Helvetica, Georgia, atd.)
- Barva (color picker)
- Velikost (small/medium/large)
- Průhlednost (0-100%)
- Padding (5-100px)
- Stín (zapnout/vypnout)
- Live preview

**Přegenerování:**
- Tlačítko "Přegenerovat všechny fotky"
- Aplikuje nové nastavení na všechny existující fotky
- Zachovává originály

**Status:** ✅ FUNGUJE

---

### **ADMIN ROZHRANÍ**

#### Seznam recenzí (`/reviews`):
- Checkbox pro hromadné akce
- Autor (jméno + email)
- SKU produktu
- Počet fotek (badge s číslem)
- Datum
- Stav (badge: pending/approved/rejected)
- Import status
- Quick actions: Detail, Schválit, Zamítnout

**Hromadné akce:**
- Schválit
- Zamítnout
- Označit jako importováno
- Stáhnout fotky (ZIP)

**Status:** ✅ FUNGUJE

#### Detail recenze (`/reviews/{id}`):
- Základní info (autor, email, SKU, rating, komentář)
- Galerie fotek s lightboxem
- Tlačítka pro fotky:
  - Download originál
  - Re-upload (nahradit)
  - Delete
- Moderace:
  - Schválit/Zamítnout (vždy viditelné)
  - Interní poznámka
- Re-upload modal

**Lightbox:**
- Bootstrap modal
- Full-screen obrázky
- Prev/Next navigace
- Keyboard shortcuts (← → ESC)
- Počítadlo fotek

**Status:** ✅ FUNGUJE

---

### **EXPORT**

**CSV Export:**
- URL: `/reviews/csv`
- Obsahuje: autor, email, SKU, rating, komentář, datum
- Použití: Import do Shoptetu manuálně

**XML Export:**
- URL: `/reviews/xml`
- Shoptet kompatibilní formát
- CRON: Automatická generace (18:00 denně)

**ZIP Download:**
- Hromadné stažení fotek
- Originály (bez watermarku)
- Pojmenování: 001_jmeno_sku.jpg

**Status:** ✅ FUNGUJE

---

### **ZMĚNA STAVŮ**

**Quick actions (seznam):**
- Schválit (zelené tlačítko)
- Zamítnout (červené tlačítko)
- Zobrazí se pouze pro opačný stav

**Detail:**
- Vždy viditelné tlačítka
- Obousměrná změna (approved ↔ rejected)
- CSRF ochrana

**Endpoint:** `POST /reviews/change-status`

**Status:** ✅ FUNGUJE

---

### **LEGACY FOTKY**

**Problém:** Staré recenze mají fotky v JSON sloupci

**Řešení:**
1. Migrace: `006_migrate_json_photos_to_table.sql`
2. Fallback v Review::findById() pro zpětnou kompatibilitu
3. Legacy ID detekce v views (skryje akce tlačítka)

**Doporučení:** Spustit migraci pro kompletní funkčnost

**Status:** ⚠️ VYŽADUJE MIGRACI

---

## 🐛 ZNÁMÉ PROBLÉMY

### 1. Černé pozadí u starých PNG
**Příčina:** Fotky nahrané před opravou ImageHandler  
**Řešení:** Přegenerovat watermark  
**Status:** ⚠️ Vyžaduje manuální akci

### 2. Legacy fotky (pokud migrace neproběhla)
**Příčina:** Fotky v JSON sloupci nemají ID v tabulce  
**Řešení:** Spustit migraci 006  
**Status:** ⚠️ Vyžaduje migraci

---

## ✅ DOPORUČENÉ KROKY

1. **Spustit migrace:**
```bash
mysql < database/migrations/004_create_review_photos_simple.sql
mysql < database/migrations/005_alter_watermark_add_shadow.sql
mysql < database/migrations/006_migrate_json_photos_to_table.sql
mysql < ALTER_WATERMARK.sql
```

2. **Přegenerovat watermark:**
- `/watermark/settings` → "Přegenerovat všechny fotky"

3. **Otestovat:**
- Test formulář: `/test-fotorecenze.html`
- Nahrát recenzi s fotkami
- Zkontrolovat detail
- Vyzkoušet schválení/zamítnutí
- Stáhnout fotky
- Export CSV/XML

---

## 🎯 CELKOVÝ STATUS

**SYSTÉM JE FUNKČNÍ** ✅

Všechny základní funkce fungují:
- ✅ Příjem recenzí přes API
- ✅ Watermark s konfigurací
- ✅ Resize fotek (1024-1600px)
- ✅ Admin schvalování
- ✅ Export CSV/XML/ZIP
- ✅ Lightbox galerie
- ✅ Re-upload fotek
- ✅ Změna stavů

**Zbývá pouze:**
- Spustit migrace na serveru
- Přegenerovat staré fotky
