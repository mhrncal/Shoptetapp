# 🚀 Production Deployment Guide

Kompletní návod pro nasazení ShopCode do produkce.

---

## 📋 Pre-Deployment Checklist

### Server Requirements

```bash
✅ PHP 8.2 nebo vyšší
✅ MySQL 8.0 nebo vyšší  
✅ Nginx nebo Apache
✅ SSL certifikát (Let's Encrypt)
✅ Min 1GB RAM
✅ Min 10GB disk space
✅ CRON access
```

### PHP Extensions

```bash
php -m | grep -E "pdo|mysqli|gd|mbstring|xml|curl|zip|json"

# Potřebné:
- pdo_mysql
- mysqli
- gd (pro zpracování obrázků)
- mbstring
- xml
- curl
- zip
- json
```

---

## 🔧 Krok 1: Server Setup

### Update systému

```bash
sudo apt update
sudo apt upgrade -y
```

### Instalace PHP 8.2

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-gd php8.2-mbstring \
                     php8.2-xml php8.2-curl php8.2-zip php8.2-cli
```

### Instalace MySQL

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

### Instalace Nginx

```bash
sudo apt install -y nginx
```

---

## 📦 Krok 2: Stažení Aplikace

```bash
# Přepni se na www-data user (nebo vytvořeného deployment usera)
sudo -u www-data -i

# Clone repo
cd /var/www
git clone https://github.com/mhrncal/Shoptetapp.git shopcode
cd shopcode

# Nebo nahraj ZIP a rozbal
```

---

## ⚙️ Krok 3: Konfigurace

### 3.1 Vytvoř .env soubor

```bash
cp .env.example .env
nano .env
```

**Vyplň tyto hodnoty:**

```bash
# Database
DB_HOST=localhost
DB_NAME=shopcode
DB_USER=shopcode_user
DB_PASS=STRONG_PASSWORD_HERE

# App
APP_NAME="ShopCode"
APP_URL=https://tvoje-domena.cz
APP_ENV=production

# Security  
SESSION_SECRET=RANDOM_STRING_32_CHARS
CSRF_TOKEN_NAME=_csrf

# Admin email
ADMIN_EMAIL=admin@tvoje-domena.cz

# SMTP (volitelné)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@email.com
SMTP_PASS=your_password
SMTP_FROM_EMAIL=noreply@tvoje-domena.cz
SMTP_FROM_NAME="ShopCode"
```

### 3.2 Generuj secrets

```bash
# Session secret (32 znaků)
openssl rand -base64 32

# API encryption key (pokud používáš)
openssl rand -base64 32
```

---

## 🗄️ Krok 4: Databáze

### 4.1 Vytvoř databázi a uživatele

```bash
sudo mysql
```

```sql
CREATE DATABASE shopcode CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'shopcode_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON shopcode.* TO 'shopcode_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4.2 Importuj schéma

```bash
mysql -u shopcode_user -p shopcode < database/schema.sql
mysql -u shopcode_user -p shopcode < database/seed.sql
```

### 4.3 Ověř import

```bash
mysql -u shopcode_user -p shopcode -e "SHOW TABLES;"

# Měl bys vidět:
# users, products, reviews, api_tokens, atd.
```

---

## 📁 Krok 5: Oprávnění Souborů

```bash
cd /var/www/shopcode

# Vlastník: www-data
sudo chown -R www-data:www-data .

# Složky: 755
sudo find . -type d -exec chmod 755 {} \;

# Soubory: 644
sudo find . -type f -exec chmod 644 {} \;

# .env: 600 (pouze čtení pro vlastníka)
sudo chmod 600 .env

# Upload složky: 755 (writable)
sudo chmod 755 public/uploads public/feeds tmp

# CRON skripty: 755 (executable)
sudo chmod 755 cron/*.php scripts/*.sh
```

---

## 🌐 Krok 6: Nginx Konfigurace

### 6.1 Vytvoř server block

```bash
sudo nano /etc/nginx/sites-available/shopcode
```

```nginx
server {
    listen 80;
    server_name tvoje-domena.cz www.tvoje-domena.cz;
    root /var/www/shopcode/public;
    index index.php;

    # Logging
    access_log /var/log/nginx/shopcode-access.log;
    error_log /var/log/nginx/shopcode-error.log;

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to .env
    location ~ /\.env {
        deny all;
        access_log off;
        log_not_found off;
    }

    # PHP handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Front controller pattern
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Deny access to uploads PHP files (security)
    location ~* /uploads/.*\.php$ {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```

### 6.2 Enable site

```bash
sudo ln -s /etc/nginx/sites-available/shopcode /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🔒 Krok 7: SSL Certifikát

### 7.1 Instalace Certbot

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 7.2 Získání certifikátu

```bash
sudo certbot --nginx -d tvoje-domena.cz -d www.tvoje-domena.cz
```

### 7.3 Auto-renewal test

```bash
sudo certbot renew --dry-run
```

**Certbot automaticky:**
- Vytvoří SSL certifikát
- Upraví Nginx config (přidá HTTPS)
- Nastaví auto-renewal (CRON)

---

## ⏰ Krok 8: CRON Jobs

### 8.1 Otevři crontab

```bash
sudo crontab -u www-data -e
```

### 8.2 Přidej joby

```bash
# XML Feed Generator (denně v 18:00)
0 18 * * * php /var/www/shopcode/cron/generate-xml-feeds.php >> /var/log/shopcode-xml-feeds.log 2>&1

# Health Monitor (každých 15 minut)
*/15 * * * * bash /var/www/shopcode/scripts/cron-health-check.sh >> /var/log/shopcode-monitor.log 2>&1

# Cleanup starých logů (týdně v neděli 3:00)
0 3 * * 0 find /var/www/shopcode/tmp -name "*.csv" -mtime +7 -delete 2>&1
```

### 8.3 Vytvoř log soubory

```bash
sudo touch /var/log/shopcode-xml-feeds.log
sudo touch /var/log/shopcode-monitor.log
sudo chown www-data:www-data /var/log/shopcode-*.log
sudo chmod 644 /var/log/shopcode-*.log
```

### 8.4 Test CRON

```bash
# Manuální spuštění
sudo -u www-data php /var/www/shopcode/cron/generate-xml-feeds.php

# Zkontroluj output
tail -20 /var/log/shopcode-xml-feeds.log
```

---

## 🔧 Krok 9: PHP-FPM Optimalizace

```bash
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

```ini
; Zvýš limity pro production
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; Upload limits
php_value[upload_max_filesize] = 10M
php_value[post_max_size] = 10M
php_value[max_execution_time] = 60
php_value[memory_limit] = 256M
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 🧪 Krok 10: Ověření Deploymentu

### 10.1 Zkontroluj web

```bash
curl -I https://tvoje-domena.cz
# Mělo by vrátit 200 OK
```

### 10.2 Test přihlášení

```
URL: https://tvoje-domena.cz/login
Email: admin@shopcode.local
Heslo: admin123
```

**⚠️ DŮLEŽITÉ: Změň admin heslo okamžitě!**

### 10.3 Test API

```bash
# Vytvoř API token v UI
# Pak test:
curl -H "Authorization: Bearer YOUR_TOKEN" \
     https://tvoje-domena.cz/api/v1/products
```

### 10.4 Test fotorecenzí

```bash
curl -X POST https://tvoje-domena.cz/api/submit-review \
  -F "name=Test" \
  -F "email=test@example.com" \
  -F "product_id=TEST-001" \
  -F "photos[]=@test.jpg"
```

### 10.5 Test CRON

```bash
# Sleduj logy real-time
tail -f /var/log/shopcode-xml-feeds.log

# V jiném terminálu spusť CRON manuálně
sudo -u www-data php /var/www/shopcode/cron/generate-xml-feeds.php

# Měl bys vidět output v logu
```

---

## 🔐 Krok 11: Bezpečnostní Hardening

### 11.1 Firewall

```bash
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS
sudo ufw enable
```

### 11.2 Fail2ban (ochrana proti brute-force)

```bash
sudo apt install -y fail2ban

# Vytvoř jail pro nginx
sudo nano /etc/fail2ban/jail.local
```

```ini
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/shopcode-error.log
```

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### 11.3 Disable directory listing

**Nginx** (už je v konfigu výše):
```nginx
autoindex off;
```

### 11.4 Hide PHP version

```bash
sudo nano /etc/php/8.2/fpm/php.ini
```

```ini
expose_php = Off
```

```bash
sudo systemctl restart php8.2-fpm
```

---

## 📊 Krok 12: Monitoring & Logging

### 12.1 Logrotate pro aplikační logy

```bash
sudo nano /etc/logrotate.d/shopcode
```

```
/var/log/shopcode-*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### 12.2 Test logrotate

```bash
sudo logrotate -f /etc/logrotate.d/shopcode
```

### 12.3 Monitoring nástroje (volitelné)

```bash
# htop pro sledování procesů
sudo apt install -y htop

# ncdu pro disk usage
sudo apt install -y ncdu
```

---

## 🔄 Krok 13: Deployment Script (pro budoucí updates)

```bash
nano /var/www/shopcode/deploy.sh
```

```bash
#!/bin/bash

set -e

echo "🚀 ShopCode Deployment Script"
echo "=============================="

# Backup
echo "📦 Creating backup..."
timestamp=$(date +%Y%m%d_%H%M%S)
mkdir -p /var/backups/shopcode
mysqldump -u shopcode_user -p shopcode > /var/backups/shopcode/db_$timestamp.sql
tar -czf /var/backups/shopcode/files_$timestamp.tar.gz \
    public/uploads \
    public/feeds \
    .env

# Git pull
echo "📥 Pulling latest code..."
git pull origin main

# Database migrations (pokud existují)
echo "🗄️  Running migrations..."
if [ -d "database/migrations" ]; then
    for file in database/migrations/*.sql; do
        [ -f "$file" ] || continue
        echo "Running: $file"
        mysql -u shopcode_user -p shopcode < "$file"
    done
fi

# Oprávnění
echo "🔧 Setting permissions..."
chown -R www-data:www-data .
chmod 600 .env
chmod 755 public/uploads public/feeds tmp

# Clear cache (pokud máš)
echo "🗑️  Clearing cache..."
rm -rf tmp/cache/*

# Reload services
echo "♻️  Reloading services..."
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

echo "✅ Deployment complete!"
```

```bash
chmod +x /var/www/shopcode/deploy.sh
```

---

## 🆘 Troubleshooting

### Problem: 500 Internal Server Error

```bash
# Zkontroluj PHP-FPM logy
sudo tail -50 /var/log/php8.2-fpm.log

# Zkontroluj Nginx logy
sudo tail -50 /var/log/nginx/shopcode-error.log

# Zkontroluj oprávnění
ls -la /var/www/shopcode
```

### Problem: Permission denied

```bash
# Resetuj oprávnění
cd /var/www/shopcode
sudo chown -R www-data:www-data .
sudo chmod 755 public/uploads public/feeds tmp
sudo chmod 600 .env
```

### Problem: Database connection failed

```bash
# Test MySQL
mysql -u shopcode_user -p shopcode -e "SELECT 1;"

# Zkontroluj .env
cat .env | grep DB_
```

### Problem: CRON neběží

```bash
# Zkontroluj crontab
sudo crontab -u www-data -l

# Test manuálně
sudo -u www-data php /var/www/shopcode/cron/generate-xml-feeds.php

# Zkontroluj logy
tail -50 /var/log/shopcode-xml-feeds.log
```

### Problem: Upload souborů nefunguje

```bash
# Zkontroluj oprávnění
ls -la /var/www/shopcode/public/uploads

# Zkontroluj PHP limity
php -i | grep -E "upload_max_filesize|post_max_size"

# Zvýš limity pokud potřeba
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

---

## 📋 Post-Deployment Checklist

```bash
✅ Web je dostupný přes HTTPS
✅ SSL certifikát platný
✅ Přihlášení funguje
✅ Admin heslo změněno
✅ API funguje (test s tokenem)
✅ Photo review API funguje
✅ CRON joby nastaveny
✅ CRON logy se zapisují
✅ Health check funguje
✅ Firewall zapnutý
✅ Backups nastaveny
✅ Monitoring funguje
✅ Email notifikace fungují
✅ Oprávnění souborů správná
✅ PHP error logging zapnutý
✅ Logrotate nakonfigurován
```

---

## 🔄 Maintenance

### Denní

```bash
# Sleduj CRON logy
tail -f /var/log/shopcode-xml-feeds.log
```

### Týdně

```bash
# Zkontroluj disk space
df -h

# Zkontroluj logy na chyby
grep -i error /var/log/nginx/shopcode-error.log | tail -20
```

### Měsíčně

```bash
# Update systému
sudo apt update && sudo apt upgrade -y

# Zkontroluj backups
ls -lh /var/backups/shopcode/

# Otestuj restore z backupu
```

---

## 📞 Support

Při problémech:

1. Zkontroluj logy
2. Spusť health check
3. Podívej se do Troubleshooting sekce
4. Kontaktuj vývojáře

---

**Datum:** 25. února 2026  
**Version:** 1.0.0  
**Status:** ✅ Production Ready
