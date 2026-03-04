#!/bin/bash
# Test konfigurace

echo "=== CONFIG TEST ==="
echo ""

echo "1. Zkontroluj .env soubor:"
if [ -f .env ]; then
    echo "✅ .env existuje"
    echo "   Řádků: $(wc -l < .env)"
    echo "   DB_HOST: $(grep DB_HOST .env)"
else
    echo "❌ .env neexistuje!"
    exit 1
fi

echo ""
echo "2. Zkontroluj config.php:"
if [ -f config/config.php ]; then
    echo "✅ config/config.php existuje"
else
    echo "❌ config/config.php neexistuje!"
    exit 1
fi

echo ""
echo "3. Zkontroluj index.php:"
if [ -f index.php ]; then
    echo "✅ index.php existuje"
    grep -n "define('ROOT'" index.php
    grep -n "require_once \$configFile" index.php
else
    echo "❌ index.php neexistuje!"
    exit 1
fi

echo ""
echo "4. Zkontroluj všechny používané konstanty:"
echo "   DB konstanty:"
grep -rh "\bDB_[A-Z_]*" src/ --include="*.php" | grep -o "DB_[A-Z_]*" | sort -u

echo ""
echo "   APP konstanty:"
grep -rh "\bAPP_[A-Z_]*" src/ --include="*.php" | grep -o "APP_[A-Z_]*" | sort -u

echo ""
echo "5. Zkontroluj které konstanty NEJSOU v defined():"
echo "   Nebezpečné použití (bez defined):"
grep -rn "APP_DEBUG\|APP_ENV\|APP_URL" src/ --include="*.php" | grep -v "defined(" | wc -l
echo "   řádků"

echo ""
echo "=== TEST HOTOVÝ ==="
