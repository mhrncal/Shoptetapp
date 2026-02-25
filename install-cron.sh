#!/bin/bash

###############################################################################
# ShopCode - Instalace automatického CRON workeru
###############################################################################
#
# Tento script nastaví automatické spouštění XML/CSV import workeru.
# Worker běží každých 5 minut a zpracovává frontu importů.
#
# POUŽITÍ:
#   sudo bash install-cron.sh
#
###############################################################################

set -e  # Zastav při chybě

# Barvy pro output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  ShopCode - Instalace automatického CRON workeru          ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

###############################################################################
# 1. Zjištění cest
###############################################################################

# Aktuální adresář (kde je tento script)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${YELLOW}📂 Zjištěné cesty:${NC}"
echo "   Project root: $PROJECT_ROOT"
echo "   Cron script:  $PROJECT_ROOT/cron/process-xml.php"
echo ""

# Ověř existenci cron skriptu
if [ ! -f "$PROJECT_ROOT/cron/process-xml.php" ]; then
    echo -e "${RED}❌ CHYBA: Soubor cron/process-xml.php nenalezen!${NC}"
    exit 1
fi

###############################################################################
# 2. Detekce PHP cesty
###############################################################################

echo -e "${YELLOW}🔍 Hledám PHP...${NC}"

PHP_PATH=$(which php 2>/dev/null || echo "")

if [ -z "$PHP_PATH" ]; then
    echo -e "${RED}❌ CHYBA: PHP není nainstalované nebo není v PATH!${NC}"
    echo "   Nainstaluj PHP: apt-get install php-cli"
    exit 1
fi

PHP_VERSION=$($PHP_PATH -r 'echo PHP_VERSION;')
echo -e "${GREEN}   ✅ PHP nalezeno: $PHP_PATH (verze $PHP_VERSION)${NC}"
echo ""

###############################################################################
# 3. Ověření oprávnění
###############################################################################

echo -e "${YELLOW}🔐 Kontrolujem oprávnění...${NC}"

if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Tento script musí běžet jako root (sudo).${NC}"
    echo "   Spusť: sudo bash install-cron.sh"
    exit 1
fi

echo -e "${GREEN}   ✅ Běží jako root${NC}"
echo ""

###############################################################################
# 4. Detekce web uživatele
###############################################################################

echo -e "${YELLOW}👤 Zjišťuji web uživatele...${NC}"

# Zkus různé možnosti
if id "www-data" &>/dev/null; then
    WEB_USER="www-data"
elif id "apache" &>/dev/null; then
    WEB_USER="apache"
elif id "nginx" &>/dev/null; then
    WEB_USER="nginx"
else
    echo -e "${YELLOW}   ⚠️  Standardní web uživatel nenalezen (www-data, apache, nginx)${NC}"
    read -p "   Zadej uživatele pro cron (výchozí: www-data): " WEB_USER
    WEB_USER=${WEB_USER:-www-data}
fi

echo -e "${GREEN}   ✅ Použije se uživatel: $WEB_USER${NC}"
echo ""

###############################################################################
# 5. Vytvoření log adresáře
###############################################################################

echo -e "${YELLOW}📁 Vytvářím log adresář...${NC}"

LOG_DIR="/var/log/shopcode"
LOG_FILE="$LOG_DIR/xml-import.log"

if [ ! -d "$LOG_DIR" ]; then
    mkdir -p "$LOG_DIR"
    echo -e "${GREEN}   ✅ Vytvořen: $LOG_DIR${NC}"
else
    echo -e "${GREEN}   ✅ Již existuje: $LOG_DIR${NC}"
fi

# Nastav oprávnění
chown -R $WEB_USER:$WEB_USER "$LOG_DIR"
chmod 755 "$LOG_DIR"

# Vytvoř prázdný log soubor pokud neexistuje
if [ ! -f "$LOG_FILE" ]; then
    touch "$LOG_FILE"
    chown $WEB_USER:$WEB_USER "$LOG_FILE"
    chmod 644 "$LOG_FILE"
    echo -e "${GREEN}   ✅ Vytvořen log soubor: $LOG_FILE${NC}"
fi

echo ""

###############################################################################
# 6. Vytvoření tmp adresáře
###############################################################################

echo -e "${YELLOW}📁 Vytvářím tmp adresář pro lock soubory...${NC}"

TMP_DIR="$PROJECT_ROOT/tmp"

if [ ! -d "$TMP_DIR" ]; then
    mkdir -p "$TMP_DIR"
    echo -e "${GREEN}   ✅ Vytvořen: $TMP_DIR${NC}"
else
    echo -e "${GREEN}   ✅ Již existuje: $TMP_DIR${NC}"
fi

chown -R $WEB_USER:$WEB_USER "$TMP_DIR"
chmod 750 "$TMP_DIR"

echo ""

###############################################################################
# 7. Nastavení crontabu
###############################################################################

echo -e "${YELLOW}⏰ Nastavuji crontab...${NC}"

# Cron záznam - každých 5 minut
CRON_LINE="*/5 * * * * $PHP_PATH $PROJECT_ROOT/cron/process-xml.php >> $LOG_FILE 2>&1"

# Zkontroluj, jestli už cron existuje
if crontab -u $WEB_USER -l 2>/dev/null | grep -q "process-xml.php"; then
    echo -e "${YELLOW}   ⚠️  Cron už existuje v crontabu uživatele $WEB_USER${NC}"
    echo ""
    echo "   Současný cron:"
    crontab -u $WEB_USER -l 2>/dev/null | grep "process-xml.php" | sed 's/^/   /'
    echo ""
    read -p "   Chceš ho přepsat? (y/n): " OVERWRITE
    
    if [ "$OVERWRITE" != "y" ]; then
        echo -e "${YELLOW}   ℹ️  Ponechávám současný cron beze změny${NC}"
    else
        # Odstraň starý a přidej nový
        (crontab -u $WEB_USER -l 2>/dev/null | grep -v "process-xml.php"; echo "$CRON_LINE") | crontab -u $WEB_USER -
        echo -e "${GREEN}   ✅ Cron aktualizován${NC}"
    fi
else
    # Přidej nový cron
    (crontab -u $WEB_USER -l 2>/dev/null; echo "$CRON_LINE") | crontab -u $WEB_USER -
    echo -e "${GREEN}   ✅ Cron přidán${NC}"
fi

echo ""
echo "   Nový cron záznam:"
echo "   $CRON_LINE"
echo ""

###############################################################################
# 8. Nastavení logrotate (volitelné)
###############################################################################

echo -e "${YELLOW}🔄 Nastavuji log rotation...${NC}"

LOGROTATE_FILE="/etc/logrotate.d/shopcode"

cat > "$LOGROTATE_FILE" << EOF
$LOG_FILE {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 $WEB_USER $WEB_USER
    sharedscripts
    postrotate
        # Nic speciálního po rotaci
    endscript
}
EOF

echo -e "${GREEN}   ✅ Logrotate nakonfigurován: $LOGROTATE_FILE${NC}"
echo "      • Denní rotace"
echo "      • Uchovává 14 dní"
echo "      • Komprese starých logů"
echo ""

###############################################################################
# 9. Test spuštění
###############################################################################

echo -e "${YELLOW}🧪 Testuji spuštění workeru...${NC}"
echo ""

# Spusť jako web uživatel
sudo -u $WEB_USER $PHP_PATH "$PROJECT_ROOT/cron/process-xml.php"

echo ""

if [ $? -eq 0 ]; then
    echo -e "${GREEN}   ✅ Worker byl úspěšně spuštěn!${NC}"
else
    echo -e "${RED}   ❌ Worker selhal! Zkontroluj chyby výše.${NC}"
    exit 1
fi

echo ""

###############################################################################
# 10. Ověření cron služby
###############################################################################

echo -e "${YELLOW}🔍 Ověřuji cron službu...${NC}"

if systemctl is-active --quiet cron 2>/dev/null; then
    echo -e "${GREEN}   ✅ Cron služba běží (systemd)${NC}"
elif systemctl is-active --quiet crond 2>/dev/null; then
    echo -e "${GREEN}   ✅ Cron služba běží (crond)${NC}"
elif service cron status >/dev/null 2>&1; then
    echo -e "${GREEN}   ✅ Cron služba běží (service)${NC}"
else
    echo -e "${YELLOW}   ⚠️  Nelze ověřit stav cron služby${NC}"
    echo "      Ujisti se, že cron démon běží:"
    echo "      sudo systemctl start cron"
    echo "      nebo"
    echo "      sudo service cron start"
fi

echo ""

###############################################################################
# 11. Shrnutí
###############################################################################

echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║                  ✅ INSTALACE DOKONČENA                    ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "📋 Shrnutí:"
echo "   • Cron uživatel:  $WEB_USER"
echo "   • Frekvence:      Každých 5 minut"
echo "   • Log soubor:     $LOG_FILE"
echo "   • Worker script:  $PROJECT_ROOT/cron/process-xml.php"
echo "   • Lock soubor:    $TMP_DIR/xml-worker.lock"
echo ""
echo "📖 Užitečné příkazy:"
echo ""
echo "   # Zobraz crontab:"
echo "   sudo crontab -u $WEB_USER -l"
echo ""
echo "   # Sleduj logy v reálném čase:"
echo "   tail -f $LOG_FILE"
echo ""
echo "   # Manuální spuštění workeru:"
echo "   sudo -u $WEB_USER php $PROJECT_ROOT/cron/process-xml.php"
echo ""
echo "   # Zkontroluj běžící procesy:"
echo "   ps aux | grep process-xml"
echo ""
echo "   # Odinstaluj cron:"
echo "   sudo crontab -u $WEB_USER -l | grep -v process-xml | sudo crontab -u $WEB_USER -"
echo ""
echo "🎉 Worker nyní běží automaticky každých 5 minut!"
echo "   Import se spustí automaticky po přidání feedu do fronty."
echo ""
