# 🛡️ Watchdog - Automatické zabíjení zamrzlých synchronizací

## Co to dělá

Watchdog kontroluje každých 5 minut:
- Synchronizace běžící **déle než 10 minut** = zabije je
- Označí jako `error` v databázi
- Loguje do `/srv/app/tmp/logs/watchdog.log`

## Instalace

### 1. Nastav CRON

```bash
crontab -e
```

Přidej řádek:
```
*/5 * * * * /srv/app/scripts/watchdog_feeds.sh >> /srv/app/tmp/logs/watchdog.log 2>&1
```

### 2. Otestuj ručně

```bash
cd /srv/app
./scripts/watchdog_feeds.sh
```

### 3. Zkontroluj log

```bash
tail -f /srv/app/tmp/logs/watchdog.log
```

## Jak to funguje

### Každých 5 minut:

1. **Kontrola DB:**
   - Najde záznamy: `status='running'` a `started_at > 10 minut`
   - Označí jako `status='error'`
   - Přidá `error_message: "Timeout: process killed after 10 minutes"`

2. **Kontrola procesů:**
   - Najde PHP procesy: `feed_sync_single.php`
   - Zkontroluje čas běhu (`ps -eo etime`)
   - Pokud > 10 minut → `kill -9`

3. **Logování:**
   ```
   [2026-03-04 14:25:00] Watchdog started
   Updated 2 hung sync logs
   Killed process 12345 (running 15:30)
   Killed process 12346 (running 32:15)
   Killed 2 processes
   [2026-03-04 14:25:01] Watchdog finished
   ```

## Výhody

✅ **Automatické** - žádný manuální zásah  
✅ **Rychlé** - kontrola každých 5 minut  
✅ **Bezpečné** - zabíjí jen zamrzlé procesy  
✅ **Logované** - vidíš co se stalo  

## Nastavení timeout

Změň `MAX_MINUTES=10` na jinou hodnotu ve skriptu:

```bash
nano /srv/app/scripts/watchdog_feeds.sh

# Změň:
MAX_MINUTES=15  # 15 minut místo 10
```

## Monitoring

### Sleduj co watchdog dělá:
```bash
tail -f /srv/app/tmp/logs/watchdog.log
```

### Zkontroluj běžící synchronizace:
```bash
ps aux | grep feed_sync_single
```

### Zkontroluj DB:
```bash
mysql -u infoshop_3356 -p infoshop_3356 -e "
SELECT id, feed_id, status, 
       TIMESTAMPDIFF(MINUTE, started_at, NOW()) as running_minutes
FROM feed_sync_log 
WHERE status = 'running';
"
```

## Testování

### 1. Spusť dlouhý sync manuálně:
```bash
php cron/feed_sync_single.php 1 &
```

### 2. Počkej 11 minut

### 3. Watchdog by měl zabít proces:
```bash
# Zkontroluj log
tail /srv/app/tmp/logs/watchdog.log

# Mělo by tam být:
# Killed process XXXXX (running 11:XX)
```

## FAQ

**Q: Co když běží legitimně dlouho?**  
A: Zvyš `MAX_MINUTES` (např. na 15 nebo 20)

**Q: Jak často kontrolovat?**  
A: Doporučeno každých 5 minut. Můžeš změnit CRON na `*/10` pro 10 minut.

**Q: Může to zabít aktivní sync?**  
A: Ne, zabíjí jen procesy starší než `MAX_MINUTES`. Normální sync trvá 1-3 minuty.
