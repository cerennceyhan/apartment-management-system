# 🏢 Apartment Management System

**[Türkçe](#turkish) | [English](#english)**

---

<a name="turkish"></a>
## 🇹🇷 Türkçe

Apartman sakinleri ve yöneticileri için geliştirilmiş kapsamlı web tabanlı yönetim sistemi.

### 📝 Proje Hakkında

Bu proje, apartman yönetimini kolaylaştırmak için geliştirilmiş bir web uygulamasıdır. Aidat takibi, gelir-gider yönetimi ve kullanıcı yetkilendirmesi gibi temel özellikleri içerir.

### ✨ Özellikler

- 💰 **Aidat Yönetimi:** Apartman sakinlerinin aidatlarını takip etme
- 📊 **Gelir-Gider Takibi:** Apartman gelir ve giderlerinin detaylı kayıtları
- 👥 **Kullanıcı Rolleri:** Apartman sakini, yönetici ve diğer yetki grupları
- 🔐 **Güvenli Giriş:** Kullanıcı kimlik doğrulama sistemi (şifreler hash'li)
- 📈 **Raporlama:** Gelir-gider raporları ve grafikler
- 🏠 **Daire Yönetimi:** Daire sahipleri ve kiracı bilgilerinin yönetimi

### 🚀 Kurulum

#### 1. Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- Apache/Nginx web sunucusu
- XAMPP, WAMP veya benzeri bir yerel sunucu

#### 2. Veritabanı Kurulumu
```sql
-- MySQL'de yeni bir veritabanı oluşturun
CREATE DATABASE apartment_management;

-- SQL dosyasını içe aktarın
mysql -u root -p apartment_management < apartment_management.sql
```

#### 3. Yapılandırma

`config/database.php` dosyasını açın ve veritabanı bilgilerinizi kontrol edin/güncelleyin:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'apartment_management');
?>
```

#### 4. Giriş Bilgileri

**Demo Hesap:**
- **Kullanıcı Adı:** `admin`
- **Şifre:** `admin123`


---

<a name="english"></a>
## 🇬🇧 English

Comprehensive web-based management system developed for apartment residents and managers.

### 📝 About the Project

This project is a web application developed to facilitate apartment management. It includes essential features such as dues tracking, income-expense management, and user authorization.

### ✨ Features

- 💰 **Dues Management:** Track apartment residents' dues
- 📊 **Income-Expense Tracking:** Detailed records of apartment income and expenses
- 👥 **User Roles:** Apartment resident, manager, and other authorization groups
- 🔐 **Secure Login:** User authentication system (passwords are hashed)
- 📈 **Reporting:** Income-expense reports and charts
- 🏠 **Apartment Management:** Management of apartment owners and tenant information

### 🚀 Installation

#### 1. Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP, WAMP, or similar local server

#### 2. Database Setup
```sql
-- Create a new database in MySQL
CREATE DATABASE apartment_management;

-- Import the SQL file
mysql -u root -p apartment_management < apartment_management.sql
```

#### 3. Configuration

Open `config/database.php` file and check/update your database credentials:
```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'apartment_management');
?>
```

#### 4. Login Credentials

**Demo Account:**
- **Username:** `admin`
- **Password:** `admin123`
