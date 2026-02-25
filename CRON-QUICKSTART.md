# ⚡ QUICK START - Automatický CRON za 2 minuty

## 🚀 Automatická instalace (NEJRYCHLEJŠÍ)

```bash
cd /path/to/Shoptetapp
sudo bash install-cron.sh
```

✅ **Hotovo!** Worker nyní běží každých 5 minut.

---

## 🔧 Manuální instalace (3 příkazy)

### 1. Otevři crontab:
```bash
sudo crontab -u www-data -e
```

### 2. Přidej tento řádek na konec:
```
*/5 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode-xml.log 2>&1
```

**⚠️ UPRAV CESTY:**
- `/usr/bin/php` → zjisti: `which php`
- `/var/www/shopcode` → tvoje cesta k projektu
- `www-data` → tvůj web uživatel (CentOS: `apache`, Nginx: `nginx`)

### 3. Ulož (Ctrl+X, Y, Enter)

✅ **Hotovo!** Worker nyní běží každých 5 minut.

---

## 🧪 Ověření

```bash
# Počkej 5 minut a sleduj log:
tail -f /var/log/shopcode-xml.log

# Měl bys vidět každých 5 minut:
# [2026-02-25 16:30:00] ===== XML Worker START =====
# [2026-02-25 16:30:00] 📭 Fronta je prázdná
# [2026-02-25 16:30:00] ===== XML Worker END =====
```

---

## 🎯 Test s importem

1. Přihlaš se do ShopCode
2. Jdi na `/xml`
3. Přidej feed URL
4. Klikni **Spustit import**
5. Počkej max 5 minut

Worker automaticky zpracuje import!

---

## 📊 Sledování

```bash
# Real-time sledování:
tail -f /var/log/shopcode-xml.log

# Zkontroluj cron:
sudo crontab -u www-data -l

# Zkontroluj frontu v DB:
mysql shopcode -e "SELECT id, status, products_processed FROM xml_processing_queue ORDER BY id DESC LIMIT 5;"
```

---

## 🐛 Nefunguje?

### Zkontroluj:
```bash
# 1. Běží cron služba?
sudo systemctl status cron

# 2. Spusť worker manuálně:
sudo -u www-data php /var/www/shopcode/cron/process-xml.php

# 3. Zkontroluj oprávnění:
ls -la /var/www/shopcode/cron/process-xml.php
# Měl bys vidět: -rwxr-xr-x
```

### Oprav oprávnění:
```bash
cd /var/www/shopcode
sudo chown -R www-data:www-data .
sudo chmod +x cron/process-xml.php
```

---

**To je vše!** 🎉

Pro detailní instrukce viz: `docs/CRON-SETUP.md`
