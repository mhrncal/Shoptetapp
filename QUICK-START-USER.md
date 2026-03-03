# 🚀 Quick Start - Vytvoření prvního uživatele

## Problém:
```
❌ "Uživatel s ID 1 neexistuje nebo není schválený"
```

## Řešení:

### ✅ VARIANTA A: Přes Admin UI (NEJJEDNODUŠŠÍ)

1. **Přihlaš se jako admin:**
   ```
   https://aplikace.shopcode.cz/login
   Email: info@shopcode.cz
   ```

2. **Vytvoř nového uživatele:**
   - Klikni na **"Uživatelé"** v menu
   - Klikni na **"+ Přidat uživatele"**
   
3. **Vyplň formulář:**
   ```
   Jméno: Test
   Příjmení: Obchod
   Email: obchod@test.cz
   Heslo: Test1234!
   Název shopu: Test E-shop
   URL shopu: https://test-eshop.cz
   Role: User (NE superadmin!)
   Stav: Schválený (approved)
   ```

4. **Ulož**

5. **Zjisti ID uživatele:**
   - V seznamu uživatelů uvidíš v prvním sloupci **ID**
   - Poznamenej si ho (např. ID = 3)

6. **Uprav test formulář:**
   - Změň `user_id` v `test-fotorecenze.html`:
   ```html
   <input type="hidden" name="user_id" value="3">
   ```
   Nebo v JS:
   ```javascript
   formData.append('user_id', '3');  // změň na skutečné ID
   ```

---

### ✅ VARIANTA B: Přes SQL

```bash
# Na serveru:
mysql -u user -p database < database/fix-user-role.sql
```

Toto vytvoří nového usera s `role='user'` a `status='approved'`.

---

### ✅ VARIANTA C: Rychlá změna existujícího

Pokud chceš jen rychle otestovat, změň roli superadmina dočasně:

```sql
-- Změň Jiřího na usera (dočasně)
UPDATE users SET role = 'user' WHERE id = 2;

-- A po testu vrať zpět:
UPDATE users SET role = 'superadmin' WHERE id = 2;
```

Pak v test formuláři použij `user_id=2`.

---

## 🎯 Doporučený postup:

**Vytvoř skutečného zákazníka přes Admin UI:**

1. Login jako admin
2. Uživatelé → + Přidat
3. Role: **User** (ne superadmin!)
4. Stav: **Schválený**
5. Poznamenej si **ID**
6. Změň `user_id` v test formuláři na toto ID

**A je to!** ✅
