# ⏰ ShopCode - Nastavení automatického CRON workeru

## 🎯 Cíl
Nastavit automatické spouštění XML/CSV import workeru každých 5 minut.

---

## 🚀 Rychlá instalace (automatická)

### Krok 1: Spusť instalační script

```bash
cd /path/to/Shoptetapp
sudo bash install-cron.sh
```

Script automaticky:
- ✅ Zjistí PHP cestu
- ✅ Vytvoří log adresář
- ✅ Nastaví crontab
- ✅ Nakonfiguruje logrotate
- ✅ Otestuje spuštění

**Hotovo!** Worker nyní běží každých 5 minut.

---

## 🔧 Manuální instalace (krok za krokem)

### Krok 1: Zjisti cestu k PHP

```bash
which php
# Výstup např: /usr/bin/php
```

### Krok 2: Zjisti cestu k projektu

```bash
cd /path/to/Shoptetapp
pwd
# Výstup např: /var/www/shopcode
```

### Krok 3: Vytvoř log adresář

```bash
sudo mkdir -p /var/log/shopcode
sudo chown www-data:www-data /var/log/shopcode
sudo chmod 755 /var/log/shopcode
```

**Poznámka:** Nahraď `www-data` svým web uživatelem:
- Ubuntu/Debian: `www-data`
- CentOS/RHEL: `apache`
- Nginx: `nginx`

### Krok 4: Vytvoř tmp adresář

```bash
cd /var/www/shopcode  # Tvoje cesta k projektu
mkdir -p tmp
sudo chown www-data:www-data tmp
sudo chmod 750 tmp
```

### Krok 5: Přidej cron záznam

```bash
# Otevři crontab
sudo crontab -u www-data -e

# Přidej tento řádek na konec:
*/5 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

**Uprav cesty:**
- `/usr/bin/php` → tvoje PHP cesta
- `/var/www/shopcode` → tvoje cesta k projektu
- `www-data` → tvůj web uživatel

**Ulož a zavři** (Ctrl+X, Y, Enter ve vim/nano)

### Krok 6: Ověř cron

```bash
# Zobraz crontab
sudo crontab -u www-data -l

# Měl bys vidět:
# */5 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

### Krok 7: Test spuštění

```bash
# Spusť worker manuálně
sudo -u www-data php /var/www/shopcode/cron/process-xml.php

# Měl bys vidět výstup:
# [2026-02-25 16:30:00] ===== XML Worker START (PID: 12345) =====
# [2026-02-25 16:30:00] 📭 Fronta je prázdná
# [2026-02-25 16:30:00] ===== XML Worker END | Zpracováno: 0 =====
```

✅ **Hotovo!** Cron je nastaven.

---

## 📊 Ověření, že cron běží

### Čekej 5 minut a zkontroluj log:

```bash
tail -f /var/log/shopcode/xml-import.log
```

**Měl bys vidět každých 5 minut:**
```
[2026-02-25 16:30:00] ===== XML Worker START (PID: 12345) =====
[2026-02-25 16:30:00] 📭 Fronta je prázdná
[2026-02-25 16:30:00] ===== XML Worker END | Zpracováno: 0 =====
```

### Nebo zkontroluj running procesy:

```bash
ps aux | grep process-xml
```

**Během běhu workeru uvidíš:**
```
www-data  12345  0.0  0.5 php /var/www/shopcode/cron/process-xml.php
```

---

## 🧪 Test s reálným importem

### Krok 1: Přidej import do fronty

Přes UI:
1. Přihlaš se do ShopCode
2. Jdi na `/xml`
3. Zadej feed URL
4. Vyber CSV nebo XML
5. Klikni **Spustit import**

### Krok 2: Sleduj zpracování

```bash
# Real-time sledování logu
tail -f /var/log/shopcode/xml-import.log
```

**Uvidíš:**
```
[2026-02-25 16:30:00] ===== XML Worker START (PID: 12345) =====
[2026-02-25 16:30:01] [Queue#5] 🚀 Zahájení zpracování | Formát: CSV | URL: http://...
[2026-02-25 16:30:02] [Queue#5] ⬇️  Stahuji feed...
[2026-02-25 16:30:03] [Queue#5] ✅ Staženo 0.01 MB
[2026-02-25 16:30:03] [Queue#5]   ↻ Zpracováno: 100
[2026-02-25 16:30:04] [Queue#5] ✅ Hotovo | Produktů: 156 | Nových: 156 | Akt.: 0 | Chyb parseru: 0
[2026-02-25 16:30:04] ===== XML Worker END | Zpracováno: 1 =====
```

### Krok 3: Zkontroluj databázi

```sql
-- Stav fronty
SELECT id, feed_format, status, products_processed 
FROM xml_processing_queue 
ORDER BY id DESC LIMIT 5;

-- Nové produkty
SELECT COUNT(*) FROM products;
```

---

## 🔧 Nastavení frekvence

### Každou minutu (rychlé zpracování):
```bash
* * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

### Každých 5 minut (doporučeno):
```bash
*/5 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

### Každých 10 minut:
```bash
*/10 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

### Každou hodinu:
```bash
0 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

---

## 📁 Logrotate (prevence plného disku)

### Vytvoř logrotate config:

```bash
sudo nano /etc/logrotate.d/shopcode
```

### Vlož:

```
/var/log/shopcode/xml-import.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

### Ulož a zavři (Ctrl+X, Y, Enter)

**Co to dělá:**
- Rotuje logy denně
- Uchovává 14 dní
- Komprimuje staré logy
- Pokud log chybí, nevadí
- Vytvoří nový log s oprávněními

---

## 🐛 Troubleshooting

### Problem: Cron neběží

**Řešení:**
```bash
# Zkontroluj cron službu
sudo systemctl status cron

# Spusť cron
sudo systemctl start cron

# Povolí autostart
sudo systemctl enable cron
```

### Problem: Log soubor se nevytváří

**Řešení:**
```bash
# Vytvoř manuálně
sudo touch /var/log/shopcode/xml-import.log
sudo chown www-data:www-data /var/log/shopcode/xml-import.log
sudo chmod 644 /var/log/shopcode/xml-import.log
```

### Problem: "Permission denied"

**Řešení:**
```bash
# Nastav oprávnění
cd /var/www/shopcode
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 tmp/
```

### Problem: Worker se spouští vícekrát najednou

**Řešení:**
Worker má lock mechanismus, který by měl zabránit paralelnímu běhu.

Zkontroluj:
```bash
# Existuje lock soubor?
ls -la /var/www/shopcode/tmp/xml-worker.lock

# Odstraň starý lock pokud worker není aktivní
rm /var/www/shopcode/tmp/xml-worker.lock
```

### Problem: Import se zasekl

**Řešení:**
```sql
-- Uvolni zaseknutý import
UPDATE xml_processing_queue 
SET status = 'pending' 
WHERE status = 'processing' 
  AND started_at < DATE_SUB(NOW(), INTERVAL 2 HOUR);
```

Nebo spusť worker manuálně - má built-in funkci `releaseStuck()`.

---

## 📊 Monitoring (produkční prostředí)

### Email při selhání:

Přidej do crontabu:
```bash
MAILTO=admin@example.com
*/5 * * * * /usr/bin/php /var/www/shopcode/cron/process-xml.php >> /var/log/shopcode/xml-import.log 2>&1
```

Pokud worker selže (exit code != 0), dostaneš email.

### Sledování zaseknutých importů:

```bash
# Vytvoř monitoring script
sudo nano /usr/local/bin/shopcode-monitor.sh
```

```bash
#!/bin/bash
STUCK=$(mysql shopcode -N -e "SELECT COUNT(*) FROM xml_processing_queue WHERE status='processing' AND started_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)")

if [ "$STUCK" -gt 0 ]; then
    echo "ALERT: $STUCK zaseknutých importů!" | mail -s "ShopCode Alert" admin@example.com
fi
```

```bash
sudo chmod +x /usr/local/bin/shopcode-monitor.sh

# Přidej do crontabu (každých 15 minut)
*/15 * * * * /usr/local/bin/shopcode-monitor.sh
```

---

## ✅ Checklist úspěšné instalace

Po nastavení by mělo platit:

- [ ] PHP je nainstalované (`which php`)
- [ ] Crontab obsahuje správný záznam (`sudo crontab -u www-data -l`)
- [ ] Log adresář existuje (`ls -la /var/log/shopcode`)
- [ ] Tmp adresář existuje (`ls -la /var/www/shopcode/tmp`)
- [ ] Log se generuje každých 5 minut (`tail -f /var/log/shopcode/xml-import.log`)
- [ ] Worker běží bez chyb (zkontroluj log)
- [ ] Cron služba je aktivní (`systemctl status cron`)
- [ ] Logrotate je nakonfigurován (`ls /etc/logrotate.d/shopcode`)

---

## 🎯 Jak to funguje

1. **Každých 5 minut** cron spustí `process-xml.php`
2. Worker **zkontroluje frontu** (`xml_processing_queue`)
3. Pokud jsou **položky s status pending**, zpracuje je:
   - Stáhne feed
   - Parsuje (XML nebo CSV)
   - Uloží do databáze
   - Aktualizuje status na `completed`
4. **Uvolní zaseknuté** importy (>2h v processing)
5. **Ukončí se** a počká na další spuštění

**Lock mechanismus** zajistí, že nikdy neběží dvě instance najednou.

---

## 📞 Support

Pokud něco nefunguje:

1. Zkontroluj log: `tail -50 /var/log/shopcode/xml-import.log`
2. Ověř oprávnění: `ls -la /var/www/shopcode`
3. Test manuální: `sudo -u www-data php /var/www/shopcode/cron/process-xml.php`
4. Zkontroluj cron: `sudo crontab -u www-data -l`
5. Zkontroluj službu: `systemctl status cron`

---

**Datum:** 25. února 2026  
**Verze:** Production  
**Status:** ✅ Ready for deployment
