#!/bin/bash

###############################################################################
# ShopCode - CRON Health Check Monitor
###############################################################################
#
# Tento script monitoruje zdraví CRON workerů a detekuje problémy:
# - Zaseknuté procesy
# - Staré lock soubory
# - Dlouhé běhy
# - Chybějící logy
#
# POUŽITÍ:
#   bash scripts/cron-health-check.sh
#
# CRON SETUP (každých 15 minut):
#   */15 * * * * bash /var/www/shopcode/scripts/cron-health-check.sh >> /var/log/shopcode-monitor.log 2>&1
#
###############################################################################

set -e

# Barvy
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ===== CRON Health Check START =====${NC}"

# Konfigurace
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOCK_DIR="$PROJECT_ROOT/tmp"
LOG_DIR="/var/log"

# Limity
MAX_LOCK_AGE=3600        # 1 hodina = zaseknutý proces
MAX_LOG_AGE=86400        # 24 hodin = worker neběžel
WARNING_LOCK_AGE=1800    # 30 minut = varování

ISSUES_FOUND=0

###############################################################################
# 1. Kontrola lock souborů
###############################################################################

echo "📁 Kontroluji lock soubory..."

for LOCK_FILE in "$LOCK_DIR"/*.lock; do
    if [ ! -f "$LOCK_FILE" ]; then
        continue
    fi
    
    LOCK_NAME=$(basename "$LOCK_FILE")
    LOCK_AGE=$(($(date +%s) - $(stat -f%m "$LOCK_FILE" 2>/dev/null || stat -c%Y "$LOCK_FILE")))
    
    if [ $LOCK_AGE -gt $MAX_LOCK_AGE ]; then
        echo -e "${RED}❌ PROBLÉM: $LOCK_NAME je starý $((LOCK_AGE/60)) minut!${NC}"
        echo "   Pravděpodobně zaseknutý proces - odstraňuji lock..."
        
        # Přečti PID z lock souboru
        if [ -s "$LOCK_FILE" ]; then
            LOCK_CONTENT=$(cat "$LOCK_FILE")
            echo "   Lock obsahuje: $LOCK_CONTENT"
            
            # Pokus se přečíst PID
            PID=$(echo "$LOCK_CONTENT" | grep -oP '"pid":\s*\K\d+' || echo "")
            
            if [ -n "$PID" ]; then
                # Zkontroluj jestli proces běží
                if ps -p $PID > /dev/null 2>&1; then
                    echo -e "${YELLOW}   ⚠️  Proces $PID stále běží - NECHÁVÁM${NC}"
                else
                    echo "   Process $PID už neběží - odstraňuji lock"
                    rm -f "$LOCK_FILE"
                fi
            else
                # Starý formát (jen PID)
                rm -f "$LOCK_FILE"
                echo "   Lock odstraněn (starý formát)"
            fi
        else
            rm -f "$LOCK_FILE"
            echo "   Prázdný lock soubor odstraněn"
        fi
        
        ISSUES_FOUND=$((ISSUES_FOUND + 1))
        
    elif [ $LOCK_AGE -gt $WARNING_LOCK_AGE ]; then
        echo -e "${YELLOW}⚠️  VAROVÁNÍ: $LOCK_NAME běží $((LOCK_AGE/60)) minut${NC}"
    else
        echo -e "${GREEN}✅ $LOCK_NAME: OK ($((LOCK_AGE/60)) min)${NC}"
    fi
done

###############################################################################
# 2. Kontrola logů (zda worker běžel nedávno)
###############################################################################

echo ""
echo "📋 Kontroluji logy..."

# XML Feed Generator
XML_LOG="$LOG_DIR/shopcode-xml-feeds.log"
if [ -f "$XML_LOG" ]; then
    LAST_RUN=$(grep -E "===== XML Feed Generator (START|END)" "$XML_LOG" | tail -1 | grep -oP '^\[\K[^\]]+')
    if [ -n "$LAST_RUN" ]; then
        LAST_RUN_TS=$(date -d "$LAST_RUN" +%s 2>/dev/null || echo "0")
        NOW_TS=$(date +%s)
        LOG_AGE=$((NOW_TS - LAST_RUN_TS))
        
        if [ $LOG_AGE -gt $MAX_LOG_AGE ]; then
            echo -e "${RED}❌ XML Feed Generator neběžel $((LOG_AGE/3600)) hodin!${NC}"
            echo "   Poslední běh: $LAST_RUN"
            ISSUES_FOUND=$((ISSUES_FOUND + 1))
        else
            echo -e "${GREEN}✅ XML Feed Generator: poslední běh před $((LOG_AGE/60)) minutami${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  XML Feed Generator: nelze najít poslední běh v logu${NC}"
    fi
else
    echo -e "${YELLOW}⚠️  XML Feed Generator: log soubor neexistuje${NC}"
fi

###############################################################################
# 3. Kontrola velikosti logů
###############################################################################

echo ""
echo "📊 Kontroluji velikost logů..."

for LOG_FILE in "$LOG_DIR"/shopcode-*.log; do
    if [ ! -f "$LOG_FILE" ]; then
        continue
    fi
    
    LOG_NAME=$(basename "$LOG_FILE")
    LOG_SIZE=$(du -h "$LOG_FILE" | cut -f1)
    LOG_SIZE_BYTES=$(stat -f%z "$LOG_FILE" 2>/dev/null || stat -c%s "$LOG_FILE")
    
    # Varování pokud log je větší než 100MB
    if [ $LOG_SIZE_BYTES -gt 104857600 ]; then
        echo -e "${YELLOW}⚠️  $LOG_NAME je velký: $LOG_SIZE - zvažte logrotate${NC}"
    else
        echo -e "${GREEN}✅ $LOG_NAME: $LOG_SIZE${NC}"
    fi
done

###############################################################################
# 4. Kontrola běžících procesů
###############################################################################

echo ""
echo "🔍 Kontroluji běžící CRON procesy..."

RUNNING_PROCS=$(ps aux | grep -E 'cron/(generate-xml-feeds|process-xml).php' | grep -v grep || true)

if [ -n "$RUNNING_PROCS" ]; then
    echo -e "${GREEN}Běžící procesy:${NC}"
    echo "$RUNNING_PROCS" | while read line; do
        echo "  $line"
    done
else
    echo "Žádné CRON procesy právě neběží"
fi

###############################################################################
# 5. Kontrola tmp adresáře
###############################################################################

echo ""
echo "🗑️  Kontroluji tmp adresář..."

TMP_FILE_COUNT=$(find "$LOCK_DIR" -name "*.csv" -o -name "*.xml" -o -name "selenium_error_*.png" 2>/dev/null | wc -l)

if [ $TMP_FILE_COUNT -gt 10 ]; then
    echo -e "${YELLOW}⚠️  Nalezeno $TMP_FILE_COUNT dočasných souborů - zvažte cleanup${NC}"
    
    # Smaž staré tmp soubory (starší než 7 dní)
    DELETED=$(find "$LOCK_DIR" \( -name "*.csv" -o -name "*.xml" -o -name "selenium_error_*.png" \) -mtime +7 -delete -print 2>/dev/null | wc -l)
    
    if [ $DELETED -gt 0 ]; then
        echo "   Smazáno $DELETED starých tmp souborů (>7 dní)"
    fi
else
    echo -e "${GREEN}✅ Tmp adresář je čistý ($TMP_FILE_COUNT souborů)${NC}"
fi

###############################################################################
# 6. Shrnutí
###############################################################################

echo ""
echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] ===== CRON Health Check END =====${NC}"

if [ $ISSUES_FOUND -gt 0 ]; then
    echo -e "${RED}Nalezeno $ISSUES_FOUND problémů!${NC}"
    exit 1
else
    echo -e "${GREEN}Vše v pořádku ✅${NC}"
    exit 0
fi
