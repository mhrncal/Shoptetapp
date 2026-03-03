# ✅ FOTORECENZE - TEST CHECKLIST

Proveď tyto testy v tomto pořadí:

## 1. PŘÍPRAVA

```bash
# Na serveru
cd /srv/app
git pull

# Spusť migrace
mysql -u user -p database < database/migrations/004_create_review_photos_simple.sql
mysql -u user -p database < ALTER_WATERMARK.sql
mysql -u user -p database < database/migrations/006_migrate_json_photos_to_table.sql
```

---

## 2. WATERMARK KONFIGURACE

**URL:** `https://aplikace.shopcode.cz/watermark/settings`

### Test 1: Načtení stránky
- [ ] Stránka se načte bez chyb
- [ ] Zobrazí se formulář s nastavením
- [ ] Live preview je viditelný

### Test 2: Změna nastavení
- [ ] Změň text na "Test Watermark"
- [ ] Změň pozici na "Vlevo nahoře" (TL)
- [ ] Změň barvu na červenou
- [ ] Změň velikost na "Velká"
- [ ] Preview se aktualizuje live
- [ ] Klikni "Uložit nastavení"
- [ ] Zobrazí se success zpráva

### Test 3: Přegenerování
- [ ] Scroll dolů na "Přegenerovat watermark"
- [ ] Klikni tlačítko
- [ ] Potvrď dialog
- [ ] Zobrazí se počet přegenerovaných fotek

---

## 3. NAHRÁNÍ RECENZE

**URL:** `https://aplikace.shopcode.cz/test-fotorecenze.html`

### Test 4: Základní nahrání
- [ ] Vyplň jméno: "Jan Testovací"
- [ ] Vyplň email: "test@example.com"
- [ ] Vyplň SKU: "TEST-123"
- [ ] Vyber 5 hvězdiček
- [ ] Napiš komentář: "Výborný produkt!"
- [ ] Nahraj 3 fotky (PNG, JPG, WEBP)
- [ ] Klikni "Odeslat hodnocení"
- [ ] Zobrazí se success zpráva
- [ ] Formulář se resetuje

### Test 5: Validace
- [ ] Zkus odeslat bez jména → Chyba
- [ ] Zkus odeslat bez emailu → Chyba
- [ ] Zkus odeslat bez fotek → Chyba

---

## 4. ADMIN SEZNAM

**URL:** `https://aplikace.shopcode.cz/reviews`

### Test 6: Zobrazení seznamu
- [ ] Zobrazí se nová recenze
- [ ] Autor: "Jan Testovací"
- [ ] Email: "test@example.com"
- [ ] SKU: "TEST-123"
- [ ] Počet fotek: badge "3"
- [ ] Stav: "Čekající" (pending)

### Test 7: Quick actions
- [ ] Zelené tlačítko ✓ je viditelné
- [ ] Červené tlačítko ✗ je viditelné
- [ ] Klikni na ✓ → Stav se změní na "Schválena"
- [ ] Refresh → Červené ✗ je viditelné, zelené zmizelo
- [ ] Klikni na ✗ → Stav se změní na "Zamítnuta"

### Test 8: Hromadné akce
- [ ] Zaškrtni recenzi
- [ ] Vyber "Schválit" → Provést
- [ ] Stav se změní

---

## 5. ADMIN DETAIL

**URL:** `https://aplikace.shopcode.cz/reviews/X` (kde X = ID recenze)

### Test 9: Zobrazení detailu
- [ ] Zobrazí se všechny informace
- [ ] Autor, email, SKU, rating, komentář
- [ ] Fotky jsou viditelné (3 ks)
- [ ] Tlačítka pod fotkami: Download, Re-upload, Delete

### Test 10: Lightbox
- [ ] Klikni na první fotku
- [ ] Otevře se modal s velkou fotkou
- [ ] Vidíš "Fotka 1 z 3"
- [ ] Klikni "Další" → přepne na fotku 2
- [ ] Šipka → funguje
- [ ] ESC zavře modal

### Test 11: Download fotky
- [ ] Klikni ikonu Download pod fotkou
- [ ] Stáhne se originál (bez watermarku)
- [ ] Soubor je validní obrázek

### Test 12: Re-upload fotky
- [ ] Klikni ikonu Re-upload
- [ ] Otevře se modal
- [ ] Vyber novou fotku
- [ ] Klikni "Nahrát a nahradit"
- [ ] Fotka se aktualizuje
- [ ] Stará fotka zmizela (nenačte se 404)
- [ ] Nová fotka má watermark

### Test 13: Delete fotky
- [ ] Klikni ikonu Delete
- [ ] Potvrď dialog
- [ ] Fotka zmizí ze seznamu
- [ ] Počet fotek se sníží

### Test 14: Změna stavu v detailu
- [ ] Tlačítko "Schválit" je viditelné
- [ ] Tlačítko "Zamítnout" je viditelné
- [ ] Klikni "Schválit" → Stav se změní
- [ ] Zelené tlačítko zmizí
- [ ] Červené zůstane

---

## 6. WATERMARK KVALITA

### Test 15: Kontrola watermarku
- [ ] Otevři schválenou fotku v novém okně
- [ ] Watermark je ostrý (ne rozmazaný)
- [ ] Text je čitelný
- [ ] Pozice odpovídá nastavení
- [ ] Barva odpovídá nastavení
- [ ] Stín je viditelný (pokud zapnutý)

### Test 16: Průhledné PNG
- [ ] Nahraj PNG s průhledným pozadím
- [ ] Zkontroluj výsledek
- [ ] Pozadí je BÍLÉ (ne černé)

---

## 7. EXPORT

### Test 17: CSV Export
**URL:** `https://aplikace.shopcode.cz/reviews/csv`
- [ ] Stáhne se CSV soubor
- [ ] Obsahuje recenze
- [ ] Sloupce: autor, email, SKU, rating, komentář

### Test 18: XML Export
**URL:** `https://aplikace.shopcode.cz/reviews/xml`
- [ ] Stáhne se XML soubor
- [ ] Validní XML struktura
- [ ] Obsahuje recenze

### Test 19: ZIP Download
- [ ] Zaškrtni několik recenzí
- [ ] Vyber "Stáhnout fotky (ZIP)"
- [ ] Stáhne se ZIP archiv
- [ ] Obsahuje fotky s číselnými názvy
- [ ] Fotky jsou originály (BEZ watermarku)

---

## 8. EDGE CASES

### Test 20: Legacy fotky (pokud existují)
- [ ] Otevři starou recenzi s JSON fotkami
- [ ] Fotky se zobrazují
- [ ] Místo tlačítek: "Stará fotka - použijte CSV/XML export"
- [ ] Lightbox funguje

### Test 21: Velké fotky
- [ ] Nahraj fotku 5000x5000px
- [ ] Výsledek je max 1600px na delší straně
- [ ] Kvalita je zachována

### Test 22: Malé fotky
- [ ] Nahraj fotku 800x800px
- [ ] Výsledek je vycentrovaný v 1024x1024
- [ ] Bílé okraje kolem

---

## 9. CHECKLIST VÝSLEDKŮ

### Kritické funkce:
- [ ] Nahrání recenze funguje
- [ ] Watermark se aplikuje
- [ ] Fotky mají správnou velikost
- [ ] Schválení/zamítnutí funguje
- [ ] Download funguje
- [ ] Re-upload funguje (a maže staré)
- [ ] Delete funguje
- [ ] Export funguje

### UI/UX:
- [ ] Lightbox funguje
- [ ] Quick actions fungují
- [ ] Live preview funguje
- [ ] Žádné 404 chyby
- [ ] Žádné PHP chyby

### Kvalita:
- [ ] Watermark je OSTRÝ
- [ ] PNG mají BÍLÉ pozadí
- [ ] Velikosti jsou správné

---

## ✅ PO DOKONČENÍ TESTŮ

**Pokud všechny testy prošly:**
✅ Systém je PRODUCTION READY

**Pokud něco selhalo:**
❌ Napiš mi číslo testu a co se stalo
