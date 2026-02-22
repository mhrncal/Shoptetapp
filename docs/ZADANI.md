# ShopCode — Projektové zadání

**Verze:** 1.0  
**Datum:** 2026-02-22  
**Autor:** Milan Hrnčál  
**Stack:** PHP 8.2+ · MySQL 8 · Bootstrap 5 · jQuery 3  

---

## 1. Přehled projektu

ShopCode je multitenantní webová aplikace pro správu produktů a obsahu e-shopů provozovaných na platformě Shoptet. Každý uživatel (e-shop) má svůj izolovaný datový prostor. Superadmin spravuje celý systém.

Aplikace nahrazuje původní řešení postavené na React + Supabase. Nové jádro je čisté PHP 8.2+ bez frameworku, s vlastní MySQL databází, vlastní autentifikací a Bootstrap 5 + jQuery frontendem.

---

## 2. Technologický stack

| Vrstva | Technologie |
|---|---|
| Backend | PHP 8.2+, čisté OOP, MVC bez frameworku |
| Databáze | MySQL 8.0+ (PDO, prepared statements) |
| Frontend | Bootstrap 5.3, jQuery 3.7, vlastní CSS |
| Auth | PHP sessions + bcrypt + remember-me tokeny |
| Server | Apache / Nginx s .htaccess rewrite |
| Verzování | Git → GitHub (mhrncal/Shoptetapp) |

---

## 3. Role uživatelů

### 3.1 Superadmin
- Plný přístup k celé aplikaci
- Správa všech uživatelů (schvalování, zamítání, editace, mazání, impersonace)
- Správa modulů (aktivace/deaktivace pro konkrétní uživatele)
- Přehled systémového zdraví, auditní logy
- XML fronta (přehled zpracování všech uživatelů)

### 3.2 User (schválený)
- Přístup pouze ke svým datům
- Přístup jen k modulům, které mu superadmin aktivoval
- Správa vlastního profilu a nastavení shopu

### 3.3 User (pending)
- Po registraci čeká na schválení superadminem
- Vidí pouze informační stránku o čekání na schválení
- Nesmí přistupovat k datům

---

## 4. Adresářová struktura projektu

```
/
├── public/                  # Document root (jediná veřejně přístupná složka)
│   ├── index.php            # Front controller
│   ├── .htaccess            # URL rewrite pravidla
│   └── assets/
│       ├── css/
│       │   └── app.css      # Vlastní styly (nad Bootstrap)
│       └── js/
│           └── app.js       # Vlastní JS (nad jQuery)
│
├── src/
│   ├── Core/
│   │   ├── App.php          # Bootstrap aplikace, DI container
│   │   ├── Router.php       # URL router
│   │   ├── Request.php      # HTTP request wrapper
│   │   ├── Response.php     # HTTP response helper
│   │   ├── Session.php      # Session wrapper
│   │   ├── Database.php     # PDO singleton wrapper
│   │   └── View.php         # Template renderer
│   │
│   ├── Middleware/
│   │   ├── AuthMiddleware.php      # Ověření přihlášení
│   │   ├── RoleMiddleware.php      # Ověření role (superadmin)
│   │   ├── ApprovedMiddleware.php  # Ověření schválení účtu
│   │   └── ModuleMiddleware.php    # Ověření přístupu k modulu
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── Module.php
│   │   ├── UserModule.php
│   │   ├── Product.php
│   │   ├── ProductVariant.php
│   │   ├── Faq.php
│   │   ├── Branch.php
│   │   ├── Event.php
│   │   ├── XmlImport.php
│   │   ├── ApiToken.php
│   │   ├── Webhook.php
│   │   └── AuditLog.php
│   │
│   ├── Controllers/
│   │   ├── AuthController.php       # Login, logout, registrace
│   │   ├── DashboardController.php  # Hlavní přehled
│   │   ├── ProfileController.php    # Vlastní profil
│   │   ├── ProductController.php    # Správa produktů
│   │   ├── FaqController.php        # FAQ
│   │   ├── BranchController.php     # Pobočky
│   │   ├── EventController.php      # Kalendář událostí
│   │   ├── XmlController.php        # XML import
│   │   ├── ApiTokenController.php   # API tokeny
│   │   ├── WebhookController.php    # Webhooky
│   │   ├── StatisticsController.php # Statistiky
│   │   ├── SettingsController.php   # Nastavení
│   │   └── Admin/
│   │       ├── AdminController.php       # Admin dashboard
│   │       ├── UserController.php        # Správa uživatelů
│   │       ├── ModuleController.php      # Správa modulů
│   │       ├── XmlQueueController.php    # XML fronta (admin)
│   │       └── SystemController.php      # Systémový přehled
│   │
│   └── Views/
│       ├── layouts/
│       │   ├── main.php        # Hlavní layout (přihlášený uživatel)
│       │   ├── auth.php        # Layout pro přihlašovací stránky
│       │   └── admin.php       # Admin layout (sidebar s admin sekcemi)
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   └── pending.php     # Čekání na schválení
│       ├── dashboard/
│       │   └── index.php
│       ├── profile/
│       │   └── edit.php
│       ├── products/
│       │   ├── index.php
│       │   └── detail.php
│       ├── faq/
│       │   └── index.php
│       ├── branches/
│       │   └── index.php
│       ├── events/
│       │   └── index.php
│       ├── xml/
│       │   └── index.php
│       ├── settings/
│       │   └── index.php
│       ├── statistics/
│       │   └── index.php
│       └── admin/
│           ├── dashboard.php
│           ├── users/
│           │   ├── index.php
│           │   ├── detail.php
│           │   └── edit.php
│           ├── modules/
│           │   └── index.php
│           ├── xml_queue/
│           │   └── index.php
│           └── system/
│               └── index.php
│
├── config/
│   ├── config.php        # Hlavní konfigurace (db, app, mail...)
│   └── routes.php        # Definice rout
│
├── database/
│   ├── schema.sql        # Kompletní DB schéma
│   └── seed.sql          # Výchozí data (superadmin, moduly)
│
├── docs/
│   ├── ZADANI.md         # Toto zadání
│   ├── ARCHITEKTURA.md   # Technická architektura
│   ├── DB_SCHEMA.md      # Popis databázového schématu
│   └── CHANGELOG.md      # Historie změn
│
├── .gitignore
└── README.md
```

---

## 5. Databázové schéma (přehled tabulek)

| Tabulka | Popis |
|---|---|
| `users` | Uživatelé (role, status, profil, XML feed) |
| `remember_tokens` | Tokeny pro "zapamatovat mě" |
| `modules` | Definice dostupných modulů systému |
| `user_modules` | Přiřazení modulů konkrétním uživatelům (active/inactive) |
| `products` | Produkty importované z XML |
| `product_variants` | Varianty produktů |
| `faqs` | FAQ — obecné i k produktům |
| `branches` | Pobočky e-shopu |
| `events` | Akce a události (kalendář) |
| `xml_imports` | Historie XML importů |
| `xml_processing_queue` | Fronta XML zpracování s prioritami |
| `xml_fields_cache` | Cache analyzovaných XML polí |
| `api_tokens` | API přístupové tokeny |
| `webhooks` | Webhooky |
| `webhook_logs` | Logy webhooků |
| `audit_logs` | Auditní log akcí |

---

## 6. Moduly systému

Každý modul lze superadminem aktivovat/deaktivovat per uživatel.

| Název modulu | Label | Popis |
|---|---|---|
| `xml_import` | XML Import | Import produktů z XML feedu Shoptetu |
| `faq` | FAQ | Správa FAQ (obecné i k produktům) |
| `branches` | Pobočky | Správa poboček s mapou |
| `event_calendar` | Kalendář akcí | Správa akcí a událostí |
| `product_tabs` | Záložky produktů | Vlastní záložky k produktům |
| `product_videos` | Videa k produktům | Přiřazení videí k produktům |
| `api_access` | API přístup | API tokeny a přístup k datům |
| `webhooks` | Webhooky | Webhooky pro externí integrace |
| `statistics` | Statistiky | Přehledy a reporty |
| `settings` | Nastavení | Nastavení systému |

---

## 7. Plán fází vývoje

### ✅ Fáze 1 — Základ (nyní)
- Adresářová struktura, .gitignore, README
- Core třídy: App, Router, Request, Response, Session, Database, View
- Konfigurace (config.php, routes.php)
- DB schéma + seed data
- Auth systém: login, logout, registrace, remember-me, middleware
- Základní layouty (main, auth)
- Pending stránka pro neschválené uživatele

### ✅ Fáze 2 — Uživatelé & Admin
- Dashboard (přehled pro uživatele)
- Správa profilu
- Admin: seznam uživatelů, detail, editace, schválení/zamítnutí
- Admin: impersonace uživatele
- Admin: přiřazení modulů uživatelům
- Auditní log

### ✅ Fáze 7 — Admin panel (systém)
- Admin dashboard se statistikami
- Přehled systémového zdraví (počty uživatelů, produktů, importů)
- XML fronta (přehled zpracování)
- Správa modulů (definice)

### 🔜 Fáze 3 — Produkty & XML import
- XML parser, mapování polí
- Fronta zpracování
- Seznam produktů, detail produktu
- Product tabs, product videos

### 🔜 Fáze 4 — FAQ, Pobočky, Události
- FAQ manager (obecné + k produktům)
- Pobočky s Google Maps
- Kalendář událostí (ICS export)

### 🔜 Fáze 5 — API & Webhooky
- API tokeny (generování, správa)
- Webhooky (konfigurace, logy)
- REST API endpointy

### 🔜 Fáze 6 — Statistiky & Nastavení
- Statistiky importů, produktů, aktivity
- Nastavení uživatele (profil, XML mapping)
- Nastavení notifikací

---

## 8. Konvence a pravidla kódu

- **PHP:** PSR-4 autoloading, namespace `ShopCode\`
- **Views:** čisté PHP šablony, žádný Smarty/Twig
- **DB:** výhradně PDO s prepared statements, žádný raw SQL s interpolací
- **Bootstrap:** verze 5.3, CDN
- **jQuery:** verze 3.7, CDN
- **AJAX:** jQuery `$.ajax()` / `$.post()` pro dynamické operace
- **Flash zprávy:** session-based (success, error, warning, info)
- **Hesla:** `password_hash()` / `password_verify()` s `PASSWORD_BCRYPT`
- **CSRF:** token v každém formuláři
- **Auditní log:** každá důležitá akce se loguje (admin operace, login, změna dat)

---

## 9. UI / UX principy

- Bootstrap 5 dark sidebar + světlý content area (stejný feel jako původní React app)
- Responzivní design (mobile-friendly)
- Tabulky s řazením a stránkováním (jQuery DataTables nebo vlastní)
- Flash notifikace (Bootstrap alerts, auto-dismiss)
- Potvrzovací dialogy před mazáním (Bootstrap modal)
- Ikony: Bootstrap Icons

---

## 10. Bezpečnost

- CSRF tokeny na všech formulářích
- XSS ochrana: `htmlspecialchars()` ve všech views
- SQL injection: PDO prepared statements
- Session fixation prevence při přihlášení
- Brute-force ochrana (rate limiting na login)
- Hesla minimálně 8 znaků, bcrypt
- Superadmin email nelze změnit přes UI
