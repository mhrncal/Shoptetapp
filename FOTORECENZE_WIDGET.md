# 📸 Fotorecenze Widget - Návod na integraci

Tento widget umožňuje zákazníkům nahrávat fotky produktů přímo z e-shopu.

---

## 🎯 DVĚ VERZE

### 1. **Standalone HTML** (pro testování)
**Soubor:** `public/fotorecenze-widget.html`

✅ Bootstrap 5  
✅ Moderní design  
✅ Drag & Drop  
✅ Live validace  
✅ Funguje samostatně  

**Použití:**
```
https://aplikace.shopcode.cz/fotorecenze-widget.html
```

---

### 2. **Shoptet Integrace** (pro produkci)
**Soubor:** `public/fotorecenze-shoptet.js`

✅ jQuery + ColorBox kompatibilní  
✅ Stejný design jako původní  
✅ Snadná integrace  
✅ Inline styly (funguje všude)  

---

## 🚀 INTEGRACE DO SHOPTETU

### Krok 1: Nahraj soubor
1. Stáhni `public/fotorecenze-shoptet.js`
2. V Shoptetu: **Nastavení → Soubory**
3. Nahraj soubor

### Krok 2: Přidej do šablony
V Shoptet Admin → **Vzhled → Vlastní úpravy** přidej:

```html
<!-- Na konec před </body> -->
<script src="/user/documents/fotorecenze-shoptet.js"></script>
```

### Krok 3: Přidej tlačítko na stránku produktu
V šabloně produktu (nebo Custom kód) přidej:

```html
<button class="photoRecension" data-sku="{$PRODUCT.code}">
    📸 Podělte se s námi o váš pěstitelský úspěch
</button>
```

**Hotovo!** 🎉

---

## ⚙️ KONFIGURACE

V souboru `fotorecenze-shoptet.js` na začátku uprav:

```javascript
const CONFIG = {
    apiUrl: 'https://aplikace.shopcode.cz/public/api/submit-review.php',
    userId: 1, // ← ZMĚŇ NA TVOJE ID!
    maxFileSize: 10 * 1024 * 1024 // 10 MB
};
```

---

## 🎨 STYLING

Widget má inline styly, takže funguje všude. Pokud chceš upravit barvy:

### Barva tlačítka "Odeslat":
```javascript
background: #28a745; // zelená
```

### Barva consent boxu:
```javascript
background: #fff3cd; // žlutá
border: 1px solid #ffc107;
```

---

## 📋 JAK TO FUNGUJE

### Tok dat:
```
1. Zákazník klikne tlačítko
   ↓
2. Otevře se ColorBox modal
   ↓
3. Vybere/přetáhne fotku
   ↓
4. Vyplní jméno a email
   ↓
5. Klikne "Odeslat fotografii"
   ↓
6. AJAX POST na API
   ↓
7. Fotka se nahraje + zpracuje (resize, watermark)
   ↓
8. Uloží se do DB jako "pending"
   ↓
9. Email notifikace adminovi
   ↓
10. Admin schválí/zamítne
```

---

## ✅ FUNKCE

- ✅ **Drag & Drop** - přetažení fotky
- ✅ **Validace** - max 10 MB, pouze obrázky
- ✅ **Live feedback** - zelená ✓ po výběru souboru
- ✅ **AJAX odeslání** - bez refreshe stránky
- ✅ **Success/Error zprávy**
- ✅ **Auto-zavření** po úspěchu
- ✅ **SKU automaticky** z `data-sku` atributu

---

## 🧪 TESTOVÁNÍ

### Test 1: Otevření
```javascript
// V konzoli prohlížeče
$('.photoRecension').click();
```

### Test 2: Odeslání
1. Vyber fotku
2. Vyplň jméno: "Test"
3. Vyplň email: "test@example.com"
4. Odešli
5. ✅ Měla by se zobrazit zelená zpráva

### Test 3: Admin
```
https://aplikace.shopcode.cz/reviews
```
→ Měla by se tam objevit nová recenze

---

## 🎯 STYLING TLAČÍTKA V SHOPTETU

Doporučený CSS pro tlačítko:

```css
.photoRecension {
    background: #28a745;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    margin: 20px 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.3s;
}

.photoRecension:hover {
    background: #218838;
}
```

---

## 📱 RESPONSIVE

Widget je **plně responzivní**:
- Desktop: 2 sloupce (fotka vlevo, formulář vpravo)
- Mobil: 1 sloupec (fotka nahoře, formulář dole)

---

## 🔧 TROUBLESHOOTING

### ❌ ColorBox se neotevírá
**Řešení:** Ujisti se že máš jQuery a ColorBox v šabloně:
```html
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.6.4/jquery.colorbox.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery.colorbox/1.6.4/example1/colorbox.min.css">
```

### ❌ Odeslání nefunguje
**Řešení:** Zkontroluj:
1. `userId` v CONFIG
2. API URL
3. Browser console (F12) - hledej chyby

### ❌ SKU se neposílá
**Řešení:** Zkontroluj že tlačítko má:
```html
data-sku="{$PRODUCT.code}"
```

---

## 📦 SOUBORY

```
public/
├── fotorecenze-widget.html      # Standalone verze (Bootstrap)
└── fotorecenze-shoptet.js       # Shoptet integrace (jQuery + ColorBox)
```

---

## 🎉 HOTOVO!

Widget je připraven k použití. Pokud něco nefunguje, kontaktuj support.

**Live demo:**
```
https://aplikace.shopcode.cz/fotorecenze-widget.html
```
