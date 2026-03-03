# 🗑️ Odstranění Selenium robota

## Co odstranit:

### 1. Soubory
- `src/Services/ShoptetBot.php`
- `src/Services/Encryption.php`
- `cron/import-reviews.php`

### 2. Database sloupce
```sql
ALTER TABLE users 
DROP COLUMN shoptet_email,
DROP COLUMN shoptet_password_encrypted,
DROP COLUMN shoptet_url,
DROP COLUMN shoptet_auto_import;
```

### 3. Metody v User.php
- `updateShoptetCredentials()`
- `getShoptetCredentials()`

### 4. Config konstanty v config.example.php
- `SHOPTET_URL`
- `SHOPTET_EMAIL`
- `SHOPTET_PASSWORD`
- `CHROMEDRIVER_URL`
- `ENCRYPTION_KEY`

D�vod: Používáme CSV/XML export místo Selenium automatizace
