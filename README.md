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

### Run with Docker Desktop (recommended)

Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/) only – no PHP, MySQL or XAMPP needed.

```bash
docker compose up -d --build
```

Open http://localhost:8080. The first start creates the MariaDB database and imports `database/apartment_management.sql` automatically.

| Command | What it does |
|---|---|
| `docker compose up -d` | start the app (port 8080) and the database |
| `docker compose logs -f app` | follow the PHP/Apache logs |
| `docker compose --profile tools up -d` | also start phpMyAdmin on http://localhost:8081 |
| `docker compose down` | stop (data is kept in the `db_data` volume) |
| `docker compose down -v` | stop and **delete the database**; the schema is re-imported on next start |

Change the database credentials with a `.env` file next to `docker-compose.yml` (see `.env.example`). `public/`, `src/` and `config/` are mounted into the container, so code edits show up on refresh.

**Demo login** (tab "Yönetici / Üye"): `admin` / `admin123` – change it before any real use.

<details><summary>Without Docker</summary>

PHP 7.4+ with `pdo_mysql` and MySQL 5.7+/MariaDB:

```bash
mysql -u root -p -e "CREATE DATABASE apartment_management CHARACTER SET utf8mb4"
mysql -u root -p apartment_management < database/apartment_management.sql
cp .env.example .env     # set DB_HOST=localhost and your DB_USER / DB_PASS
php -S localhost:8000 -t public
```
</details>

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
Dockerfile, docker-compose.yml   # PHP 8.3 + Apache and MariaDB
tests/smoke_test.py  # logs in and requests every page/endpoint
```

### Testing

```bash
docker compose up -d --build
python3 tests/smoke_test.py http://127.0.0.1:8080
```

---

## Türkçe

Apartman sakinleri ve yöneticileri için web tabanlı yönetim sistemi: aidat takibi, gelir-gider kayıtları, rol/yetki yönetimi, raporlar ve grafikler. Çerçevesiz, düz PHP + MySQL.

### Docker Desktop ile çalıştırma

Sadece [Docker Desktop](https://www.docker.com/products/docker-desktop/) yeterli; PHP, MySQL veya XAMPP kurmanız gerekmez.

```bash
docker compose up -d --build
```

http://localhost:8080 adresini açın. İlk başlatmada MariaDB veritabanı oluşturulur ve `database/apartment_management.sql` otomatik içe aktarılır. phpMyAdmin için `docker compose --profile tools up -d` (http://localhost:8081), veritabanını sıfırlamak için `docker compose down -v` kullanın. Kimlik bilgilerini `.env` ile değiştirebilirsiniz (`.env.example`).

**Demo giriş:** `admin` / `admin123` (gerçek kullanımdan önce değiştirin). Dosya yapısı ve test komutları yukarıdaki İngilizce bölümdedir.
