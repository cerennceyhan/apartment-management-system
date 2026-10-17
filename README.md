# 🏢 Apartment Management System / Apartman Yönetim Sistemi

**[Türkçe](#türkçe) | [English](#english)**

## English

A web application for apartment residents and managers: dues tracking, income/expense records, role-based permissions, reports and charts. Plain PHP + MySQL, no framework.

### Features

- 💰 Dues management and resident payment history
- 📊 Income / expense tracking with charts
- 👥 Roles and fine-grained permission groups (admin, accountant, manager, resident)
- 🏠 Sites, blocks, flats, owners and tenants
- 🎫 Support requests from residents
- 🔐 Hashed passwords, first-login password creation

### Requirements

PHP 7.4+ (with `pdo_mysql`) and MySQL 5.7+ / MariaDB.

### Setup

```bash
# 1. create the database and import the schema + demo data
mysql -u root -p -e "CREATE DATABASE apartment_management CHARACTER SET utf8mb4"
mysql -u root -p apartment_management < database/apartment_management.sql

# 2. configure the connection (defaults: localhost / root / empty password)
cp .env.example .env     # edit DB_HOST, DB_NAME, DB_USER, DB_PASS

# 3. run
php -S localhost:8000 -t public
```

Open http://localhost:8000. With XAMPP/Apache, point the document root (or an alias) to the `public/` folder.

**Demo login** (tab "Yönetici / Üye"): `admin` / `admin123` – change it before any real use.

### Project layout

```
public/              # web root – the only folder exposed to the web
├── index.php        # dashboard
├── login.php, logout.php, create_password.php
├── api.php          # JSON API (?endpoint=...)
├── pages/           # one file per screen (siteler, bloklar, daireler, sakinler, aidatlar, ...)
├── ajax/            # small endpoints called from the pages
└── assets/          # css + js
src/                 # application code (not web accessible)
├── Database.php     # PDO connection from env settings
├── env.php          # .env loader
├── auth.php, permissions.php, functions.php
├── chart.php        # reusable chart component
└── views/sidebar.php
config/database.php  # opens the global $db
database/            # SQL schema + demo data
tests/smoke_test.py  # logs in and requests every page/endpoint
```

### Testing

```bash
php -S 127.0.0.1:8000 -t public &
python3 tests/smoke_test.py http://127.0.0.1:8000
```

---

## Türkçe

Apartman sakinleri ve yöneticileri için web tabanlı yönetim sistemi: aidat takibi, gelir-gider kayıtları, rol/yetki yönetimi, raporlar ve grafikler. Çerçevesiz, düz PHP + MySQL.

### Kurulum

```bash
mysql -u root -p -e "CREATE DATABASE apartment_management CHARACTER SET utf8mb4"
mysql -u root -p apartment_management < database/apartment_management.sql
cp .env.example .env     # DB_HOST, DB_NAME, DB_USER, DB_PASS değerlerini düzenleyin
php -S localhost:8000 -t public
```

Tarayıcıda http://localhost:8000 adresini açın. XAMPP/Apache kullanıyorsanız belge kökünü `public/` klasörüne yönlendirin.

**Demo giriş:** `admin` / `admin123` (gerçek kullanımdan önce değiştirin). Dosya yapısı ve test komutları yukarıdaki İngilizce bölümdedir.
