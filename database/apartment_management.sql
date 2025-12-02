-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 08 Ağu 2025, 18:43:40
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `apartment_management`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `aidatlar`
--

CREATE TABLE `aidatlar` (
  `id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `blok_id` int(11) DEFAULT NULL,
  `sakin_id` int(11) NOT NULL,
  `ay` varchar(7) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `toplam_odeme` decimal(10,2) DEFAULT 0.00,
  `kalan_borc` decimal(10,2) DEFAULT 0.00,
  `odendi` tinyint(1) DEFAULT 0,
  `odeme_tarihi` date DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `aidatlar`
--

INSERT INTO `aidatlar` (`id`, `site_id`, `blok_id`, `sakin_id`, `ay`, `miktar`, `toplam_odeme`, `kalan_borc`, `odendi`, `odeme_tarihi`, `status`, `created_at`) VALUES
(1, 1, 1, 1, '2025-06', 500.00, 500.00, 0.00, 1, '2025-06-29', 1, '2025-06-29 09:32:00'),
(2, 1, 1, 2, '2025-06', 500.00, 0.00, 500.00, 0, NULL, 1, '2025-06-29 10:10:47'),
(3, 1, 1, 3, '2025-06', 500.00, 500.00, 0.00, 1, '2025-06-29', 1, '2025-06-29 10:13:45'),
(4, 1, 1, 4, '2025-06', 1000.00, 1000.00, 0.00, 1, '2025-06-29', 1, '2025-06-29 14:00:43'),
(5, 1, 1, 5, '2025-06', 500.00, 0.00, 500.00, 0, NULL, 0, '2025-06-29 20:04:42'),
(6, 1, 1, 5, '2025-07', 500.00, 300.00, 200.00, 0, NULL, 1, '2025-06-29 20:06:10'),
(7, 1, 1, 1, '2025-07', 500.00, 500.00, 0.00, 1, '2025-06-30', 1, '2025-06-29 20:06:26'),
(8, 1, 1, 7, '2025-06', 500.00, 500.00, 0.00, 1, '2025-07-30', 1, '2025-06-29 23:48:26'),
(9, 1, 1, 8, '2025-06', 500.00, 500.00, 0.00, 1, '2025-06-30', 1, '2025-06-30 00:02:49'),
(10, 1, 1, 9, '2025-07', 500.00, 500.00, 0.00, 1, '2025-07-02', 1, '2025-07-02 20:45:25'),
(11, 1, 1, 8, '2025-07', 1000.00, 0.00, 0.00, 1, '2025-07-14', 1, '2025-07-14 09:27:36'),
(12, 1, 1, 1, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(13, 1, 1, 2, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(14, 1, 1, 3, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(15, 1, 1, 4, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(16, 1, 1, 5, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(17, 1, 1, 7, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(18, 1, 1, 8, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03'),
(19, 1, 1, 9, '2025-08', 1400.00, 0.00, 0.00, 0, NULL, 1, '2025-07-30 12:39:03');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bekleyen_kayitlar`
--

CREATE TABLE `bekleyen_kayitlar` (
  `id` int(11) NOT NULL,
  `ad_soyad` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `sifre_hash` varchar(255) NOT NULL,
  `grup_id` int(11) NOT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  `durum` enum('bekliyor','onaylandi','reddedildi') DEFAULT 'bekliyor',
  `onaylayan_id` int(11) DEFAULT NULL,
  `onaylanma_tarihi` timestamp NULL DEFAULT NULL,
  `notlar` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `bekleyen_kayitlar`
--

INSERT INTO `bekleyen_kayitlar` (`id`, `ad_soyad`, `email`, `sifre_hash`, `grup_id`, `olusturma_tarihi`, `durum`, `onaylayan_id`, `onaylanma_tarihi`, `notlar`) VALUES
(1, 'Ahmet Yılmaz', 'ahmetyilmaz@gmail.com', '$2y$10$7knuo0ellPF1.R9R3Vkv3OAGqx4DARcRiSejXJKw/75q37lLAz1mK', 7, '2025-07-16 21:33:46', 'bekliyor', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bloklar`
--

CREATE TABLE `bloklar` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `blok_adi` varchar(100) NOT NULL,
  `blok_kodu` varchar(20) DEFAULT NULL,
  `kat_sayisi` int(11) DEFAULT 0,
  `daire_sayisi` int(11) DEFAULT 0,
  `aciklama` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `bloklar`
--

INSERT INTO `bloklar` (`id`, `site_id`, `blok_adi`, `blok_kodu`, `kat_sayisi`, `daire_sayisi`, `aciklama`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'A Blok', 'A', 5, 20, NULL, 1, '2025-08-02 11:05:04', '2025-08-02 11:05:04'),
(2, 1, 'B Blok', 'B', 5, 20, '', 1, '2025-08-02 13:22:02', '2025-08-02 13:22:02'),
(3, 1, 'C Blok', 'C', 5, 20, '', 0, '2025-08-05 20:35:18', '2025-08-07 12:01:02'),
(4, 1, 'C Blok', 'C', 5, 20, '', 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cari_hareketler`
--

CREATE TABLE `cari_hareketler` (
  `id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `blok_id` int(11) DEFAULT NULL,
  `sakin_id` int(11) NOT NULL,
  `aidat_id` int(11) NOT NULL,
  `odeme_miktari` decimal(10,2) NOT NULL,
  `odeme_tarihi` date NOT NULL,
  `odeme_aciklamasi` text DEFAULT NULL,
  `islem_tipi` enum('odeme','iade') DEFAULT 'odeme',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `cari_hareketler`
--

INSERT INTO `cari_hareketler` (`id`, `site_id`, `blok_id`, `sakin_id`, `aidat_id`, `odeme_miktari`, `odeme_tarihi`, `odeme_aciklamasi`, `islem_tipi`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 5, 6, 200.00, '2025-07-14', 'Temmuz 2. hafta', 'odeme', 1, '2025-07-14 10:27:51', '2025-08-02 11:05:04'),
(2, 1, 1, 5, 6, 100.00, '2025-07-14', 'temmuz ayı 2. ödeme', 'odeme', 1, '2025-07-14 20:47:31', '2025-08-02 11:05:04'),
(3, 1, 1, 7, 8, 500.00, '2025-07-30', '', 'odeme', 1, '2025-07-30 12:54:56', '2025-08-02 11:05:04');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `daireler`
--

CREATE TABLE `daireler` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `blok_id` int(11) NOT NULL,
  `daire_no` varchar(20) NOT NULL,
  `kat` int(11) DEFAULT 1,
  `oda_sayisi` int(11) DEFAULT 3,
  `metrekare` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `daireler`
--

INSERT INTO `daireler` (`id`, `site_id`, `blok_id`, `daire_no`, `kat`, `oda_sayisi`, `metrekare`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '1', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(2, 1, 1, '2', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(3, 1, 1, '3', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(4, 1, 1, '4', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(5, 1, 1, '5', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(6, 1, 1, '6', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(7, 1, 1, '7', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(8, 1, 1, '8', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(9, 1, 1, '9', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(10, 1, 1, '10', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(11, 1, 1, '11', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(12, 1, 1, '12', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(13, 1, 1, '13', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(14, 1, 1, '14', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(15, 1, 1, '15', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(16, 1, 1, '16', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(17, 1, 1, '17', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(18, 1, 1, '18', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(19, 1, 1, '19', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(20, 1, 1, '20', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(21, 1, 2, '1', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(22, 1, 2, '2', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(23, 1, 2, '3', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(24, 1, 2, '4', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(25, 1, 2, '5', 1, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(26, 1, 2, '6', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(27, 1, 2, '7', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(28, 1, 2, '8', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(29, 1, 2, '9', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(30, 1, 2, '10', 2, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(31, 1, 2, '11', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(32, 1, 2, '12', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(33, 1, 2, '13', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(34, 1, 2, '14', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(35, 1, 2, '15', 3, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(36, 1, 2, '16', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(37, 1, 2, '17', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(38, 1, 2, '18', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(39, 1, 2, '19', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(40, 1, 2, '20', 4, 3, 120.00, 1, '2025-08-05 17:45:01', '2025-08-05 17:45:01'),
(41, 1, 4, '1', 1, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(42, 1, 4, '2', 1, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(43, 1, 4, '3', 1, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(44, 1, 4, '4', 1, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(45, 1, 4, '5', 2, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(46, 1, 4, '6', 2, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(47, 1, 4, '7', 2, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(48, 1, 4, '8', 2, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(49, 1, 4, '9', 3, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(50, 1, 4, '10', 3, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(51, 1, 4, '11', 3, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(52, 1, 4, '12', 3, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(53, 1, 4, '13', 4, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(54, 1, 4, '14', 4, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(55, 1, 4, '15', 4, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(56, 1, 4, '16', 4, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(57, 1, 4, '17', 5, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(58, 1, 4, '18', 5, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(59, 1, 4, '19', 5, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15'),
(60, 1, 4, '20', 5, 3, 120.00, 1, '2025-08-07 12:01:15', '2025-08-07 12:01:15');

--
-- Tetikleyiciler `daireler`
--
DELIMITER $$
CREATE TRIGGER `after_daire_delete` AFTER UPDATE ON `daireler` FOR EACH ROW BEGIN
    IF NEW.status = 0 AND OLD.status = 1 THEN
        UPDATE daire_sakinler 
        SET status = 0 
        WHERE daire_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Görünüm yapısı durumu `daire_doluluk`
-- (Asıl görünüm için aşağıya bakın)
--
CREATE TABLE `daire_doluluk` (
`id` int(11)
,`site_id` int(11)
,`blok_id` int(11)
,`daire_no` varchar(20)
,`site_adi` varchar(255)
,`blok_adi` varchar(100)
,`sakin_sayisi` bigint(21)
,`ev_sahibi_sayisi` bigint(21)
,`kiraci_sayisi` bigint(21)
,`doluluk_durumu` varchar(10)
);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `daire_sakinler`
--

CREATE TABLE `daire_sakinler` (
  `id` int(11) NOT NULL,
  `daire_id` int(11) NOT NULL,
  `sakin_id` int(11) NOT NULL,
  `sakin_tipi` enum('ev_sahibi','kiraci') NOT NULL DEFAULT 'kiraci',
  `baslangic_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `daire_sakinler`
--

INSERT INTO `daire_sakinler` (`id`, `daire_id`, `sakin_id`, `sakin_tipi`, `baslangic_tarihi`, `bitis_tarihi`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(2, 2, 2, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(3, 3, 3, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(4, 12, 4, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(5, 6, 5, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(6, 10, 7, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(7, 15, 8, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(8, 16, 9, 'kiraci', NULL, NULL, 0, '2025-08-05 17:45:01', '2025-08-05 17:51:32'),
(16, 6, 1, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(17, 7, 2, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(18, 4, 3, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(19, 3, 4, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(20, 5, 5, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(21, 2, 7, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(22, 1, 8, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32'),
(23, 8, 9, 'kiraci', NULL, NULL, 1, '2025-08-05 17:51:32', '2025-08-05 17:51:32');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `destek_kategorileri`
--

CREATE TABLE `destek_kategorileri` (
  `id` int(11) NOT NULL,
  `kategori_adi` varchar(100) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `destek_kategorileri`
--

INSERT INTO `destek_kategorileri` (`id`, `kategori_adi`, `aciklama`, `status`) VALUES
(1, 'Elektrik', 'Elektrik arızaları ve problemleri', 1),
(2, 'Su/Tesisat', 'Su kesintisi, tesisat problemleri', 1),
(3, 'Asansör', 'Asansör arızaları ve bakım', 1),
(4, 'Güvenlik', 'Güvenlik kameraları, kapı sistemleri', 1),
(5, 'Temizlik', 'Ortak alanların temizliği', 1),
(6, 'Bahçe/Peyzaj', 'Bahçe bakımı ve peyzaj işleri', 1),
(7, 'Isıtma/Soğutma', 'Merkezi ısıtma, klima problemleri', 1),
(8, 'İnternet/Kablo', 'İnternet ve kablo TV problemleri', 1),
(9, 'Otopark', 'Otopark ile ilgili problemler', 1),
(10, 'Diğer', 'Diğer konular', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `destek_talepleri`
--

CREATE TABLE `destek_talepleri` (
  `id` int(11) NOT NULL,
  `sakin_id` int(11) NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `baslik` varchar(255) NOT NULL,
  `aciklama` text NOT NULL,
  `oncelik` enum('düşük','orta','yüksek','acil') DEFAULT 'orta',
  `durum` enum('açık','devam_ediyor','çözüldü','reddedildi') DEFAULT 'açık',
  `fotograf` varchar(255) DEFAULT NULL,
  `yonetici_notu` text DEFAULT NULL,
  `cozum_tarihi` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `destek_talepleri`
--

INSERT INTO `destek_talepleri` (`id`, `sakin_id`, `kategori`, `baslik`, `aciklama`, `oncelik`, `durum`, `fotograf`, `yonetici_notu`, `cozum_tarihi`, `created_at`, `updated_at`, `status`) VALUES
(1, 8, 'Asansör', 'Asansör Arızası', 'A Blok asansörü bozuk', 'orta', 'çözüldü', NULL, 'Asansör tamir edilmiştir', '2025-08-05 23:33:41', '2025-08-03 18:36:12', '2025-08-05 20:33:41', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `giderler`
--

CREATE TABLE `giderler` (
  `id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `blok_id` int(11) DEFAULT NULL,
  `tarih` date NOT NULL,
  `aciklama` text NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `giderler`
--

INSERT INTO `giderler` (`id`, `site_id`, `blok_id`, `tarih`, `aciklama`, `kategori`, `miktar`, `status`, `created_at`) VALUES
(1, 1, NULL, '2025-06-29', 'Elektrik Faturası', 'Fatura', 1200.00, 1, '2025-06-29 09:33:15'),
(2, 1, NULL, '2025-06-29', 'Su Faturası', 'Fatura', 800.00, 1, '2025-06-29 09:33:34'),
(3, 1, NULL, '2025-06-29', 'Temizlik Malzemesi', 'Temizlik', 350.00, 1, '2025-06-29 09:33:48'),
(4, 1, NULL, '2025-06-29', 'Boya Badana', 'Bakım', 3000.00, 0, '2025-06-29 10:14:26'),
(25, 1, NULL, '2025-07-06', 'Bakım', 'Bakım', 500.00, 1, '2025-07-06 15:01:20');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `gider_kategorileri`
--

CREATE TABLE `gider_kategorileri` (
  `id` int(11) NOT NULL,
  `kategori_adi` varchar(100) NOT NULL,
  `icon` varchar(10) DEFAULT '?',
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `gider_kategorileri`
--

INSERT INTO `gider_kategorileri` (`id`, `kategori_adi`, `icon`, `status`, `created_at`) VALUES
(1, 'Fatura', '⚡', 1, '2025-07-16 17:23:11'),
(2, 'Temizlik', '🧹', 1, '2025-07-16 17:23:11'),
(3, 'Bakım', '🔧', 1, '2025-07-16 17:23:11');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `grup_yetkiler`
--

CREATE TABLE `grup_yetkiler` (
  `id` int(11) NOT NULL,
  `grup_id` int(11) DEFAULT NULL,
  `yetki_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `grup_yetkiler`
--

INSERT INTO `grup_yetkiler` (`id`, `grup_id`, `yetki_id`, `created_at`, `status`) VALUES
(174, 1, 16, '2025-06-29 23:06:31', 1),
(175, 1, 9, '2025-06-29 23:06:31', 1),
(176, 1, 7, '2025-06-29 23:06:31', 1),
(177, 1, 11, '2025-06-29 23:06:31', 1),
(178, 1, 10, '2025-06-29 23:06:31', 1),
(179, 1, 8, '2025-06-29 23:06:31', 1),
(180, 1, 1, '2025-06-29 23:06:31', 1),
(181, 1, 2, '2025-06-29 23:06:31', 1),
(182, 1, 17, '2025-06-29 23:06:31', 1),
(183, 1, 13, '2025-06-29 23:06:31', 1),
(184, 1, 12, '2025-06-29 23:06:31', 1),
(185, 1, 14, '2025-06-29 23:06:31', 1),
(186, 1, 5, '2025-06-29 23:06:31', 1),
(187, 1, 4, '2025-06-29 23:06:31', 1),
(188, 1, 3, '2025-06-29 23:06:31', 1),
(189, 1, 6, '2025-06-29 23:06:31', 1),
(242, 2, 16, '2025-06-29 23:46:18', 1),
(243, 2, 9, '2025-06-29 23:46:18', 1),
(244, 2, 7, '2025-06-29 23:46:18', 1),
(245, 2, 11, '2025-06-29 23:46:18', 1),
(246, 2, 10, '2025-06-29 23:46:18', 1),
(247, 2, 1, '2025-06-29 23:46:18', 1),
(248, 2, 2, '2025-06-29 23:46:18', 1),
(249, 2, 17, '2025-06-29 23:46:18', 1),
(250, 2, 13, '2025-06-29 23:46:18', 1),
(251, 2, 12, '2025-06-29 23:46:18', 1),
(252, 2, 14, '2025-06-29 23:46:18', 1),
(253, 2, 3, '2025-06-29 23:46:18', 1),
(323, 3, 9, '2025-07-06 14:59:53', 1),
(324, 3, 11, '2025-07-06 14:59:53', 1),
(325, 3, 8, '2025-07-06 14:59:53', 1),
(326, 3, 1, '2025-07-06 14:59:53', 1),
(327, 7, 9, '2025-07-15 10:05:40', 1),
(328, 7, 10, '2025-07-15 10:05:40', 1),
(329, 7, 1, '2025-07-15 10:05:40', 1),
(330, 7, 13, '2025-07-15 10:05:40', 1),
(331, 7, 14, '2025-07-15 10:05:40', 1),
(334, 1, 60, '2025-08-05 17:45:01', 1),
(335, 1, 61, '2025-08-05 17:45:01', 1),
(336, 1, 62, '2025-08-05 17:45:01', 1),
(337, 1, 59, '2025-08-05 17:45:01', 1),
(341, 2, 59, '2025-08-05 17:45:01', 1),
(342, 3, 59, '2025-08-05 17:45:01', 1),
(343, 7, 60, '2025-08-05 17:45:01', 1),
(344, 7, 61, '2025-08-05 17:45:01', 1),
(345, 7, 62, '2025-08-05 17:45:01', 1),
(346, 7, 59, '2025-08-05 17:45:01', 1),
(350, 1, 63, '2025-08-07 14:43:58', 1),
(351, 1, 64, '2025-08-07 14:43:58', 1),
(353, 2, 63, '2025-08-07 14:43:58', 1),
(354, 2, 64, '2025-08-07 14:43:58', 1),
(356, 7, 63, '2025-08-07 14:43:58', 1),
(357, 7, 64, '2025-08-07 14:43:58', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sakinler`
--

CREATE TABLE `sakinler` (
  `id` int(11) NOT NULL,
  `site_id` int(11) DEFAULT NULL,
  `blok_id` int(11) DEFAULT NULL,
  `ad_soyad` varchar(200) NOT NULL,
  `daire_no` varchar(20) NOT NULL,
  `telefon` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_login` tinyint(1) DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `yetki_grup_id` int(11) DEFAULT 3,
  `grup_id` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `sakinler`
--

INSERT INTO `sakinler` (`id`, `site_id`, `blok_id`, `ad_soyad`, `daire_no`, `telefon`, `email`, `password`, `first_login`, `status`, `created_at`, `yetki_grup_id`, `grup_id`, `updated_at`) VALUES
(1, 1, 1, 'Ramazan Esmeray', '6', '0553-550-1816', 'ramazanesmeray@gmail.com', '$2y$10$aH14vyI8ooseImwqcHxud.GBH5PDw/y12HZYY88IlLZi0GCrUo5EC', 0, 1, '2025-06-29 09:26:50', 3, 3, '2025-08-05 20:51:32'),
(2, 1, 1, 'Süleyman Yıldırım', '7', '0545-598-4812', 'suleymanyildirim@gmail.com', '$2y$10$fIgdwn1DHDvdDRKNjQ.VE.llDRNvBXO5PsOdFJnKwKU6b3lpH0k56', 0, 1, '2025-06-29 10:10:28', 3, 3, '2025-08-05 20:51:32'),
(3, 1, 1, 'Mehmet Öcal', '4', '0510-598-4748', 'mehmetocal@gmail.com', '$2y$10$f4q0ew9a2aS/e8OM5AUpB.gJvGOp6i4A4sSIsZXfEUQzpxFhrdum6', 0, 1, '2025-06-29 10:13:18', 3, 3, '2025-08-05 20:51:32'),
(4, 1, 1, 'Emrehan Bal', '3', '0518-195-6967', 'emrehanbal@gmail.com', '$2y$10$apR5QHpqBYnrgPwI.hBzE.T/fC235dMiSln3ypVvDbH4YSAPXEyU2', 0, 1, '2025-06-29 14:00:14', 3, 3, '2025-08-05 20:51:32'),
(5, 1, 1, 'Mustafa Erhan', '5', '0580-159-4878', 'mustafaerhan@gmail.com', '$2y$10$iMdIX8IPPraD1U68vt5S1ewXWYamxX3RfpVed3ViXSGeKSdlZIPSW', 0, 1, '2025-06-29 20:03:31', 3, NULL, '2025-08-05 20:51:32'),
(7, 1, 1, 'Ayşegül Aydemir', '2', '0550-148-1512', 'aglaydemir@gmail.com', '$2y$10$rbQ15JvJ9KKK2Y/7KLM7FON08gM6QDlIGp.Ob8LXNykOmNNTw24Mm', 0, 1, '2025-06-29 23:47:10', 3, NULL, '2025-08-05 20:51:32'),
(8, 1, 1, 'Ali Körpe', '1', '0557-159-6060', 'alikorpe@gmail.com', '$2y$10$2M2KIgcFAgf8..XFHwstSuutPtAvEzExLJ5Oi0T0ggibTVfbFr8vq', 0, 1, '2025-06-30 00:02:39', 3, 3, '2025-08-05 20:51:32'),
(9, 1, 1, 'Tuncay Yazaroğlu', '8', '0550-123-4826', 'tuncayyazaroglu@gmail.com', '$2y$10$S2sfHlGK/5dfeC19duxnY.l2Tex/U4ys.U5AuHhy.9GBLKUXl2B1y', 0, 1, '2025-07-02 20:43:47', 3, 3, '2025-08-05 20:51:32');

--
-- Tetikleyiciler `sakinler`
--
DELIMITER $$
CREATE TRIGGER `after_sakin_delete` AFTER UPDATE ON `sakinler` FOR EACH ROW BEGIN
    IF NEW.status = 0 AND OLD.status = 1 THEN
        UPDATE daire_sakinler 
        SET status = 0 
        WHERE sakin_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Görünüm yapısı durumu `sakin_detay`
-- (Asıl görünüm için aşağıya bakın)
--
CREATE TABLE `sakin_detay` (
`id` int(11)
,`ad_soyad` varchar(200)
,`telefon` varchar(20)
,`email` varchar(100)
,`site_adi` varchar(255)
,`blok_adi` varchar(100)
,`daire_no` varchar(20)
,`sakin_tipi` enum('ev_sahibi','kiraci')
,`baslangic_tarihi` date
,`bitis_tarihi` date
,`sakin_tipi_text` varchar(12)
);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siteler`
--

CREATE TABLE `siteler` (
  `id` int(11) NOT NULL,
  `site_adi` varchar(255) NOT NULL,
  `adres` text DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `yonetici_adi` varchar(255) DEFAULT NULL,
  `yonetici_telefon` varchar(20) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `siteler`
--

INSERT INTO `siteler` (`id`, `site_adi`, `adres`, `telefon`, `email`, `yonetici_adi`, `yonetici_telefon`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Beykent Sitesi', 'Beykent Mahallesi', '0555-123-4567', 'yonetim@beykentsitesi.com', 'Site Yöneticisi', NULL, 1, '2025-08-02 11:05:04', '2025-08-02 11:05:04');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('yonetici','muhasebeci','sakin') NOT NULL,
  `ad_soyad` varchar(200) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `yetki_grup_id` int(11) DEFAULT 1,
  `grup_id` int(11) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `kullanici_tipi` enum('sakin','uye') DEFAULT 'uye',
  `first_login` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `ad_soyad`, `status`, `yetki_grup_id`, `grup_id`, `telefon`, `email`, `kullanici_tipi`, `first_login`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$5yPRxbEhVlavslQfDQtgUe.3WMyHA2Swf5HjUMv2bFYxIq9tFoazm', 'yonetici', 'Admin Yönetici', 1, 1, 1, NULL, NULL, 'uye', 0, '2025-08-04 21:20:16', '2025-07-17 21:51:54', '2025-08-04 21:20:16'),
(2, 'muhasebe', '$2y$10$CnGx7/I/S922t8jEStqa7evq1LDR78PikZyzWO7NCAeroKQnG9A.a', 'muhasebeci', 'Muhasebe Personeli', 1, 2, 2, NULL, NULL, 'uye', 0, NULL, '2025-07-17 21:51:54', '2025-07-17 21:51:54'),
(3, 'Ömer_faruk_babur', '$2y$10$JCJKURvDYMx8xzbfwA.Rk.52BxFtoGav4ri3MJjqTFjUJfk5MpFl6', 'yonetici', 'Ömer Faruk Babur', 1, 7, NULL, '0550-164-4859', 'ofbbabur@gmail.com', 'uye', 0, '2025-07-17 22:36:03', '2025-07-17 22:05:50', '2025-07-17 22:36:03'),
(4, 'deniz_demir', '$2y$10$qRB/7rwiBZurIONa/sMwBO.Tm3XdS9VSFFmIYJiTCwXuFY7NQe8UK', 'yonetici', 'Deniz Demir', 1, 7, NULL, '0550-164-4857', 'denizdemir@gmail.com', 'uye', 0, '2025-07-17 22:37:49', '2025-07-17 22:37:11', '2025-07-17 22:37:49');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `selected_site_id` int(11) DEFAULT NULL,
  `selected_blok_id` int(11) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yetkiler`
--

CREATE TABLE `yetkiler` (
  `id` int(11) NOT NULL,
  `yetki_kodu` varchar(50) NOT NULL,
  `yetki_adi` varchar(100) NOT NULL,
  `modul` varchar(50) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `yetkiler`
--

INSERT INTO `yetkiler` (`id`, `yetki_kodu`, `yetki_adi`, `modul`, `aciklama`, `status`, `created_at`) VALUES
(1, 'dashboard.view', 'Dashboard Görüntüleme', 'Dashboard', 'Ana sayfa istatistiklerini görüntüleyebilir', 1, '2025-06-29 12:58:50'),
(2, 'dashboard.full_stats', 'Tüm İstatistikler', 'Dashboard', 'Tüm mali verileri ve istatistikleri görebilir', 1, '2025-06-29 12:58:50'),
(3, 'sakinler.view', 'Sakin Listesi Görüntüleme', 'Sakinler', 'Sakin listesini görüntüleyebilir', 1, '2025-06-29 12:58:50'),
(4, 'sakinler.add', 'Sakin Ekleme', 'Sakinler', 'Yeni sakin ekleyebilir', 1, '2025-06-29 12:58:50'),
(5, 'sakinler.edit', 'Sakin Düzenleme', 'Sakinler', 'Sakin bilgilerini düzenleyebilir', 1, '2025-06-29 12:58:50'),
(6, 'sakinler.delete', 'Sakin Silme', 'Sakinler', 'Sakin kaydını pasif hale getirebilir', 1, '2025-06-29 12:58:50'),
(7, 'aidatlar.view', 'Aidat Listesi Görüntüleme', 'Aidatlar', 'Tüm aidat listesini görüntüleyebilir', 1, '2025-06-29 12:58:50'),
(8, 'aidatlar.view_own', 'Kendi Aidatlarını Görme', 'Aidatlar', 'Sadece kendi aidatlarını görebilir', 1, '2025-06-29 12:58:50'),
(9, 'aidatlar.add', 'Aidat Ekleme', 'Aidatlar', 'Yeni aidat kaydı oluşturabilir', 1, '2025-06-29 12:58:50'),
(10, 'aidatlar.delete', 'Aidat Silme', 'Aidatlar', 'Aidat kaydını pasif hale getirebilir', 1, '2025-06-29 12:58:50'),
(11, 'aidatlar.payment', 'Aidat Ödeme', 'Aidatlar', 'Aidat ödemesi yapabilir', 1, '2025-06-29 12:58:50'),
(12, 'giderler.view', 'Gider Listesi Görüntüleme', 'Giderler', 'Gider listesini görüntüleyebilir', 1, '2025-06-29 12:58:50'),
(13, 'giderler.add', 'Gider Ekleme', 'Giderler', 'Yeni gider kaydı oluşturabilir', 1, '2025-06-29 12:58:50'),
(14, 'giderler.delete', 'Gider Silme', 'Giderler', 'Gider kaydını pasif hale getirebilir', 1, '2025-06-29 12:58:50'),
(16, 'aidatlar.edit', 'Aidat Düzenleme', 'Aidatlar', 'Aidat bilgilerini düzenleyebilir', 1, '2025-06-29 13:44:02'),
(17, 'giderler.edit', 'Gider Düzenleme', 'Giderler', 'Gider bilgilerini düzenleyebilir', 1, '2025-06-29 13:44:02'),
(59, 'daireler.view', 'Daireleri Görüntüleme', 'Daireler', 'Daire listesini ve detaylarını görüntüleyebilir', 1, '2025-08-05 17:45:01'),
(60, 'daireler.assign_owner', 'Ev Sahibi Atama', 'Daireler', 'Dairelere ev sahibi atayabilir', 1, '2025-08-05 17:45:01'),
(61, 'daireler.assign_tenant', 'Kiracı Atama', 'Daireler', 'Dairelere kiracı atayabilir', 1, '2025-08-05 17:45:01'),
(62, 'daireler.remove_resident', 'Sakin Çıkarma', 'Daireler', 'Dairelerden sakin çıkarabilir', 1, '2025-08-05 17:45:01'),
(63, 'daireler.add', 'Daire Ekleme', 'Daireler', 'Yeni daire ekleyebilir', 1, '2025-08-07 14:43:57'),
(64, 'daireler.edit', 'Daire Düzenleme', 'Daireler', 'Daire bilgilerini düzenleyebilir', 1, '2025-08-07 14:43:57');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `yetki_gruplari`
--

CREATE TABLE `yetki_gruplari` (
  `id` int(11) NOT NULL,
  `grup_adi` varchar(100) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `yetki_gruplari`
--

INSERT INTO `yetki_gruplari` (`id`, `grup_adi`, `aciklama`, `status`, `created_at`) VALUES
(1, 'Yönetici', 'Tüm yetkilere sahip ana yönetici', 1, '2025-06-29 12:53:18'),
(2, 'Muhasebeci', 'Mali işler ve aidat yönetimi', 1, '2025-06-29 12:53:18'),
(3, 'Sakin', 'Temel sakin yetkileri', 1, '2025-06-29 12:53:18'),
(7, 'Yardımcı Yönetici', 'Yardımcı', 1, '2025-07-15 10:05:40'),
(8, 'Kapıcı', 'Kapıcı', 0, '2025-07-16 21:50:42');

-- --------------------------------------------------------

--
-- Görünüm yapısı `daire_doluluk`
--
DROP TABLE IF EXISTS `daire_doluluk`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daire_doluluk`  AS SELECT `d`.`id` AS `id`, `d`.`site_id` AS `site_id`, `d`.`blok_id` AS `blok_id`, `d`.`daire_no` AS `daire_no`, `s`.`site_adi` AS `site_adi`, `b`.`blok_adi` AS `blok_adi`, count(`ds`.`id`) AS `sakin_sayisi`, count(case when `ds`.`sakin_tipi` = 'ev_sahibi' then 1 end) AS `ev_sahibi_sayisi`, count(case when `ds`.`sakin_tipi` = 'kiraci' then 1 end) AS `kiraci_sayisi`, CASE WHEN count(`ds`.`id`) = 0 THEN 'BOŞ' WHEN count(`ds`.`id`) = 1 THEN 'YARIM DOLU' ELSE 'DOLU' END AS `doluluk_durumu` FROM (((`daireler` `d` left join `siteler` `s` on(`d`.`site_id` = `s`.`id`)) left join `bloklar` `b` on(`d`.`blok_id` = `b`.`id`)) left join `daire_sakinler` `ds` on(`d`.`id` = `ds`.`daire_id` and `ds`.`status` = 1)) WHERE `d`.`status` = 1 GROUP BY `d`.`id`, `d`.`site_id`, `d`.`blok_id`, `d`.`daire_no`, `s`.`site_adi`, `b`.`blok_adi` ORDER BY `s`.`site_adi` ASC, `b`.`blok_adi` ASC, cast(`d`.`daire_no` as unsigned) ASC ;

-- --------------------------------------------------------

--
-- Görünüm yapısı `sakin_detay`
--
DROP TABLE IF EXISTS `sakin_detay`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `sakin_detay`  AS SELECT `sk`.`id` AS `id`, `sk`.`ad_soyad` AS `ad_soyad`, `sk`.`telefon` AS `telefon`, `sk`.`email` AS `email`, `s`.`site_adi` AS `site_adi`, `b`.`blok_adi` AS `blok_adi`, `d`.`daire_no` AS `daire_no`, `ds`.`sakin_tipi` AS `sakin_tipi`, `ds`.`baslangic_tarihi` AS `baslangic_tarihi`, `ds`.`bitis_tarihi` AS `bitis_tarihi`, CASE WHEN `ds`.`sakin_tipi` = 'ev_sahibi' THEN '🏠 Ev Sahibi' WHEN `ds`.`sakin_tipi` = 'kiraci' THEN '🔑 Kiracı' ELSE '❓ Bilinmiyor' END AS `sakin_tipi_text` FROM ((((`sakinler` `sk` left join `daire_sakinler` `ds` on(`sk`.`id` = `ds`.`sakin_id` and `ds`.`status` = 1)) left join `daireler` `d` on(`ds`.`daire_id` = `d`.`id`)) left join `siteler` `s` on(`sk`.`site_id` = `s`.`id`)) left join `bloklar` `b` on(`sk`.`blok_id` = `b`.`id`)) WHERE `sk`.`status` = 1 ORDER BY `s`.`site_adi` ASC, `b`.`blok_adi` ASC, `d`.`daire_no` ASC, `sk`.`ad_soyad` ASC ;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `aidatlar`
--
ALTER TABLE `aidatlar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sakin_ay` (`sakin_id`,`ay`),
  ADD KEY `blok_id` (`blok_id`),
  ADD KEY `idx_aidat_site_blok` (`site_id`,`blok_id`);

--
-- Tablo için indeksler `bekleyen_kayitlar`
--
ALTER TABLE `bekleyen_kayitlar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `onaylayan_id` (`onaylayan_id`),
  ADD KEY `idx_durum` (`durum`),
  ADD KEY `idx_olusturma_tarihi` (`olusturma_tarihi`),
  ADD KEY `idx_grup_id` (`grup_id`);

--
-- Tablo için indeksler `bloklar`
--
ALTER TABLE `bloklar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blok_site` (`site_id`),
  ADD KEY `idx_blok_status` (`status`);

--
-- Tablo için indeksler `cari_hareketler`
--
ALTER TABLE `cari_hareketler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sakin_id` (`sakin_id`),
  ADD KEY `idx_aidat_id` (`aidat_id`),
  ADD KEY `idx_odeme_tarihi` (`odeme_tarihi`),
  ADD KEY `blok_id` (`blok_id`),
  ADD KEY `idx_cari_site_blok` (`site_id`,`blok_id`);

--
-- Tablo için indeksler `daireler`
--
ALTER TABLE `daireler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daire` (`site_id`,`blok_id`,`daire_no`),
  ADD KEY `idx_site_id` (`site_id`),
  ADD KEY `idx_blok_id` (`blok_id`),
  ADD KEY `idx_daire_no` (`daire_no`),
  ADD KEY `idx_status` (`status`);

--
-- Tablo için indeksler `daire_sakinler`
--
ALTER TABLE `daire_sakinler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_daire_id` (`daire_id`),
  ADD KEY `idx_sakin_id` (`sakin_id`),
  ADD KEY `idx_sakin_tipi` (`sakin_tipi`),
  ADD KEY `idx_status` (`status`);

--
-- Tablo için indeksler `destek_kategorileri`
--
ALTER TABLE `destek_kategorileri`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `destek_talepleri`
--
ALTER TABLE `destek_talepleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sakin_id` (`sakin_id`),
  ADD KEY `durum` (`durum`),
  ADD KEY `kategori` (`kategori`);

--
-- Tablo için indeksler `giderler`
--
ALTER TABLE `giderler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `blok_id` (`blok_id`),
  ADD KEY `idx_gider_site_blok` (`site_id`,`blok_id`);

--
-- Tablo için indeksler `gider_kategorileri`
--
ALTER TABLE `gider_kategorileri`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `grup_yetkiler`
--
ALTER TABLE `grup_yetkiler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_grup_yetki` (`grup_id`,`yetki_id`),
  ADD KEY `yetki_id` (`yetki_id`);

--
-- Tablo için indeksler `sakinler`
--
ALTER TABLE `sakinler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ad_soyad` (`ad_soyad`),
  ADD KEY `fk_sakinler_grup` (`grup_id`),
  ADD KEY `blok_id` (`blok_id`),
  ADD KEY `idx_sakin_site_blok` (`site_id`,`blok_id`);

--
-- Tablo için indeksler `siteler`
--
ALTER TABLE `siteler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_status` (`status`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_grup` (`grup_id`),
  ADD KEY `idx_users_yetki_grup_id` (`yetki_grup_id`),
  ADD KEY `idx_users_kullanici_tipi` (`kullanici_tipi`);

--
-- Tablo için indeksler `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_session` (`user_id`,`session_id`),
  ADD KEY `selected_site_id` (`selected_site_id`),
  ADD KEY `selected_blok_id` (`selected_blok_id`);

--
-- Tablo için indeksler `yetkiler`
--
ALTER TABLE `yetkiler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `yetki_kodu` (`yetki_kodu`);

--
-- Tablo için indeksler `yetki_gruplari`
--
ALTER TABLE `yetki_gruplari`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_grup_adi` (`grup_adi`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `aidatlar`
--
ALTER TABLE `aidatlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Tablo için AUTO_INCREMENT değeri `bekleyen_kayitlar`
--
ALTER TABLE `bekleyen_kayitlar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `bloklar`
--
ALTER TABLE `bloklar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `cari_hareketler`
--
ALTER TABLE `cari_hareketler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `daireler`
--
ALTER TABLE `daireler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- Tablo için AUTO_INCREMENT değeri `daire_sakinler`
--
ALTER TABLE `daire_sakinler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Tablo için AUTO_INCREMENT değeri `destek_kategorileri`
--
ALTER TABLE `destek_kategorileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Tablo için AUTO_INCREMENT değeri `destek_talepleri`
--
ALTER TABLE `destek_talepleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `giderler`
--
ALTER TABLE `giderler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Tablo için AUTO_INCREMENT değeri `gider_kategorileri`
--
ALTER TABLE `gider_kategorileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `grup_yetkiler`
--
ALTER TABLE `grup_yetkiler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- Tablo için AUTO_INCREMENT değeri `sakinler`
--
ALTER TABLE `sakinler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Tablo için AUTO_INCREMENT değeri `siteler`
--
ALTER TABLE `siteler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `yetkiler`
--
ALTER TABLE `yetkiler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- Tablo için AUTO_INCREMENT değeri `yetki_gruplari`
--
ALTER TABLE `yetki_gruplari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `aidatlar`
--
ALTER TABLE `aidatlar`
  ADD CONSTRAINT `aidatlar_ibfk_1` FOREIGN KEY (`sakin_id`) REFERENCES `sakinler` (`id`),
  ADD CONSTRAINT `aidatlar_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`),
  ADD CONSTRAINT `aidatlar_ibfk_3` FOREIGN KEY (`blok_id`) REFERENCES `bloklar` (`id`);

--
-- Tablo kısıtlamaları `bekleyen_kayitlar`
--
ALTER TABLE `bekleyen_kayitlar`
  ADD CONSTRAINT `bekleyen_kayitlar_ibfk_1` FOREIGN KEY (`grup_id`) REFERENCES `yetki_gruplari` (`id`),
  ADD CONSTRAINT `bekleyen_kayitlar_ibfk_2` FOREIGN KEY (`onaylayan_id`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `bloklar`
--
ALTER TABLE `bloklar`
  ADD CONSTRAINT `bloklar_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `cari_hareketler`
--
ALTER TABLE `cari_hareketler`
  ADD CONSTRAINT `cari_hareketler_ibfk_1` FOREIGN KEY (`sakin_id`) REFERENCES `sakinler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cari_hareketler_ibfk_2` FOREIGN KEY (`aidat_id`) REFERENCES `aidatlar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cari_hareketler_ibfk_3` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`),
  ADD CONSTRAINT `cari_hareketler_ibfk_4` FOREIGN KEY (`blok_id`) REFERENCES `bloklar` (`id`);

--
-- Tablo kısıtlamaları `daireler`
--
ALTER TABLE `daireler`
  ADD CONSTRAINT `fk_daireler_blok` FOREIGN KEY (`blok_id`) REFERENCES `bloklar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_daireler_site` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `daire_sakinler`
--
ALTER TABLE `daire_sakinler`
  ADD CONSTRAINT `fk_daire_sakinler_daire` FOREIGN KEY (`daire_id`) REFERENCES `daireler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_daire_sakinler_sakin` FOREIGN KEY (`sakin_id`) REFERENCES `sakinler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `destek_talepleri`
--
ALTER TABLE `destek_talepleri`
  ADD CONSTRAINT `fk_destek_sakin` FOREIGN KEY (`sakin_id`) REFERENCES `sakinler` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `giderler`
--
ALTER TABLE `giderler`
  ADD CONSTRAINT `giderler_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`),
  ADD CONSTRAINT `giderler_ibfk_2` FOREIGN KEY (`blok_id`) REFERENCES `bloklar` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `grup_yetkiler`
--
ALTER TABLE `grup_yetkiler`
  ADD CONSTRAINT `grup_yetkiler_ibfk_1` FOREIGN KEY (`grup_id`) REFERENCES `yetki_gruplari` (`id`),
  ADD CONSTRAINT `grup_yetkiler_ibfk_2` FOREIGN KEY (`yetki_id`) REFERENCES `yetkiler` (`id`);

--
-- Tablo kısıtlamaları `sakinler`
--
ALTER TABLE `sakinler`
  ADD CONSTRAINT `fk_sakinler_grup` FOREIGN KEY (`grup_id`) REFERENCES `yetki_gruplari` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sakinler_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `siteler` (`id`),
  ADD CONSTRAINT `sakinler_ibfk_2` FOREIGN KEY (`blok_id`) REFERENCES `bloklar` (`id`);

--
-- Tablo kısıtlamaları `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_grup` FOREIGN KEY (`grup_id`) REFERENCES `yetki_gruplari` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_yetki_grup` FOREIGN KEY (`yetki_grup_id`) REFERENCES `yetki_gruplari` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_sessions_ibfk_2` FOREIGN KEY (`selected_site_id`) REFERENCES `siteler` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_sessions_ibfk_3` FOREIGN KEY (`selected_blok_id`) REFERENCES `bloklar` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
