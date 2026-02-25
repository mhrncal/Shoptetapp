# ShopCode - Dokumentace parsování XML a CSV

## 📦 Obsah dokumentace

Tato složka obsahuje kompletní analýzu systému importu produktů v ShopCode.

### Soubory

1. **analiza-parsovani.md** (hlavní přehled)
   - Kompletní analýza XML i CSV parsování
   - Srovnání obou přístupů
   - Identifikace problémů a doporučení
   - Databázová struktura
   - 3 možné přístupy k řešení XML field mappingu

2. **analiza-csv-parsing.md** (detailní CSV)
   - Technická analýza `CsvParser.php`
   - Detekce kódování (6 strategií)
   - Field mapping workflow
   - Grupování variant (pairCode logika)
   - Performance charakteristiky
   - Testovací scénáře

3. **action-plan-xml-mapping.md** (implementační plán)
   - Krok-za-krokem checklist
   - Kódové příklady
   - UI změny
   - Testing checklist
   - Časový odhad (50 minut)

## 🎯 Klíčová zjištění

### ✅ CSV Parsování - PRODUCTION READY
- 100% funkční s flexibilním field mappingem
- Automatická detekce 6 různých kódování
- Robustní error handling
- Batch processing pro výkon

### ⚠️ XML Parsování - Potřebuje finalizaci
- Funkční parser, ale field mapping UI není propojeno
- XmlParser má hardcoded názvy tagů
- Řešení: Implementovat hardcoded přístup (viz action-plan)

## 📊 Statistiky

- **Celkový rozsah analýzy:** ~1900 řádků dokumentace
- **Analyzované soubory:** 7 PHP souborů
- **Identifikované problémy:** 4
- **Navržená řešení:** 3 přístupy

## 🚀 Další kroky

1. Prostuduj `analiza-parsovani.md` pro celkový přehled
2. Pokud potřebuješ detaily o CSV, čti `analiza-csv-parsing.md`
3. Pro implementaci XML mappingu následuj `action-plan-xml-mapping.md`

## 📝 Git Status

**Commit:** `be5b1bc` - docs: Add comprehensive analysis of XML and CSV parsing
**Branch:** main
**Status:** Committed lokálně, čeká na push

### Jak pushnut:

```bash
cd /path/to/Shoptetapp
git push origin main
```

Nebo aplikuj patch:
```bash
git am < 0001-docs-Add-comprehensive-analysis-of-XML-and-CSV-parsi.patch
```

## 📧 Kontakt

Pro dotazy k analýze nebo implementaci kontaktuj autora.

---

**Datum vytvoření:** 25. února 2026
**Verze:** 1.0
**Status:** ✅ Complete
