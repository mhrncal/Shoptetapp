#!/bin/bash

echo "=== KONTROLA STRUKTURY PRO /public/ DOCUMENT ROOT ==="
echo ""

# 1. Zkontroluj index.php
if [ -f "public/index.php" ]; then
    echo "✅ public/index.php existuje"
    grep "define('ROOT'" public/index.php | head -1
else
    echo "❌ public/index.php CHYBÍ!"
fi

# 2. Zkontroluj API endpoint
if [ -f "public/api/submit-review.php" ]; then
    echo "✅ public/api/submit-review.php existuje"
else
    echo "❌ API endpoint CHYBÍ!"
fi

# 3. Zkontroluj .htaccess
if [ -f "public/.htaccess" ]; then
    echo "✅ public/.htaccess existuje"
else
    echo "❌ .htaccess CHYBÍ!"
fi

# 4. Zkontroluj upload složky
echo ""
echo "=== UPLOAD SLOŽKY ==="
if [ -d "public/uploads" ]; then
    echo "✅ public/uploads existuje"
else
    echo "⚠️  public/uploads NEEXISTUJE - vytvářím..."
    mkdir -p public/uploads
    chmod 755 public/uploads
fi

if [ -d "public/feeds" ]; then
    echo "✅ public/feeds existuje"
else
    echo "⚠️  public/feeds NEEXISTUJE - vytvářím..."
    mkdir -p public/feeds
    chmod 755 public/feeds
fi

# 5. Zkontroluj tmp složku
echo ""
echo "=== TMP SLOŽKA ==="
if [ -d "tmp" ]; then
    echo "✅ tmp/ existuje (mimo public - OK)"
else
    echo "⚠️  tmp/ NEEXISTUJE - vytvářím..."
    mkdir -p tmp
    chmod 755 tmp
fi

# 6. Struktura
echo ""
echo "=== FINÁLNÍ STRUKTURA ==="
tree -L 2 -d public/ 2>/dev/null || find public/ -type d -maxdepth 2

echo ""
echo "=== HOTOVO ==="
