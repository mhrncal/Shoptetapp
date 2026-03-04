#!/bin/bash
# Watchdog: Zabij zamrzlé feed synchronizace
# Spouští se každých 5 minut přes CRON

LOG_FILE="/srv/app/tmp/logs/watchdog.log"
MAX_MINUTES=10

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watchdog started" >> "$LOG_FILE"

# 1. Označ v DB jako error pokud běží déle než X minut
mysql -u infoshop_3356 -pShopcode2024?? infoshop_3356 << SQL >> "$LOG_FILE" 2>&1
UPDATE feed_sync_log 
SET status = 'error',
    error_message = 'Timeout: process killed after ${MAX_MINUTES} minutes',
    finished_at = NOW(),
    duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
WHERE status = 'running' 
  AND started_at < DATE_SUB(NOW(), INTERVAL ${MAX_MINUTES} MINUTE);

SELECT CONCAT('Updated ', ROW_COUNT(), ' hung sync logs') as result;
SQL

# 2. Zabij PHP procesy feed_sync_single starší než X minut
KILLED=0
while IFS= read -r line; do
    PID=$(echo "$line" | awk '{print $1}')
    ETIME=$(echo "$line" | awk '{print $2}')
    
    # Převeď elapsed time na sekundy
    if [[ "$ETIME" =~ ([0-9]+)-([0-9]+):([0-9]+):([0-9]+) ]]; then
        # Formát: days-HH:MM:SS
        SECONDS=$((${BASH_REMATCH[1]}*86400 + ${BASH_REMATCH[2]}*3600 + ${BASH_REMATCH[3]}*60 + ${BASH_REMATCH[4]}))
    elif [[ "$ETIME" =~ ([0-9]+):([0-9]+):([0-9]+) ]]; then
        # Formát: HH:MM:SS
        SECONDS=$((${BASH_REMATCH[1]}*3600 + ${BASH_REMATCH[2]}*60 + ${BASH_REMATCH[3]}))
    elif [[ "$ETIME" =~ ([0-9]+):([0-9]+) ]]; then
        # Formát: MM:SS
        SECONDS=$((${BASH_REMATCH[1]}*60 + ${BASH_REMATCH[2]}))
    else
        SECONDS=0
    fi
    
    # Pokud starší než X minut, zabij
    if [ $SECONDS -gt $((MAX_MINUTES * 60)) ]; then
        kill -9 "$PID" 2>/dev/null
        if [ $? -eq 0 ]; then
            echo "  Killed process $PID (running ${ETIME})" >> "$LOG_FILE"
            KILLED=$((KILLED + 1))
        fi
    fi
done < <(ps -eo pid,etime,cmd | grep "feed_sync_single.php" | grep -v grep)

echo "  Killed $KILLED processes" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Watchdog finished" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

exit 0
