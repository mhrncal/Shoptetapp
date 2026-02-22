# ShopCode — PHP Edition

Multitenantní webová aplikace pro správu produktů a obsahu Shoptet e-shopů.

## Stack
- **Backend:** PHP 8.2+, čisté OOP MVC
- **Databáze:** MySQL 8.0+
- **Frontend:** Bootstrap 5.3 + jQuery 3.7

## Dokumentace
- [Zadání projektu](docs/ZADANI.md)
- [Architektura](docs/ARCHITEKTURA.md)
- [DB schéma](docs/DB_SCHEMA.md)
- [Changelog](docs/CHANGELOG.md)

## Instalace
```bash
# 1. Naklonuj repo
git clone https://github.com/mhrncal/Shoptetapp.git
cd Shoptetapp

# 2. Nakopíruj a uprav konfiguraci
cp config/config.example.php config/config.php

# 3. Importuj DB schéma
mysql -u root -p shopcode < database/schema.sql
mysql -u root -p shopcode < database/seed.sql

# 4. Nastav document root na /public
```

## Vývoj — fáze
- [x] Fáze 1 — Základ (Core, Router, Auth)
- [x] Fáze 2 — Uživatelé & Admin
- [x] Fáze 7 — Admin panel (systém)
- [ ] Fáze 3 — Produkty & XML import
- [ ] Fáze 4 — FAQ, Pobočky, Události
- [ ] Fáze 5 — API & Webhooky
- [ ] Fáze 6 — Statistiky & Nastavení
