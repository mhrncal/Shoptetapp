# 📧 Roští SMTP Setup

## SMTP Credentials:
```
Server: smtp.rosti.cz
Port: 587
Username: 8748@rostiapp.cz
Password: 3a1a03f06ded4f80a5d91058f2283cb8
Encryption: TLS
```

## Setup v config/config.php:

```php
define('MAIL_HOST', 'smtp.rosti.cz');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '8748@rostiapp.cz');
define('MAIL_PASSWORD', '3a1a03f06ded4f80a5d91058f2283cb8');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM', '8748@rostiapp.cz');
define('MAIL_FROM_NAME', 'ShopCode');
```

## Kde se posílají emaily:

1. **Nová fotorecenze** → SUPERADMIN_EMAIL
2. **Schválení účtu** → Email uživatele
3. **Reset hesla** → Email uživatele
4. **Welcome email** → Email uživatele

## Test:

```bash
cd /srv/app/[app-id]/app_backup/src/app/Shoptetapp
cp config.example.php config.php
nano config.php  # Nastav MAIL_* konstanty
```

Hotovo! Emaily budou fungovat.
