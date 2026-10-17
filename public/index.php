<?php
// index.php - Pasta Grafikli Dashboard
session_start();

// APP_LOADED sabitini tanımla - ÖNEMLİ!
define('APP_LOADED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/permissions.php';
require_once __DIR__ . '/../src/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Sayfa başlığını ayarla
$page_title = 'Dashboard';

// Dashboard için yetki kontrolü
if ($current_user['role'] !== 'sakin' && !checkPermission($current_user, 'dashboard.view')) {
    header('Location: login.php?error=yetkisiz');
    exit();
}

// Dashboard istatistiklerini al
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Pasta grafik verileri için istatistikleri al
$stats = getDashboardStats($selected_month);

// Gider kategorileri için veri - TÜM AKTİF GİDERLER (HEM SAKİN HEM YÖNETİCİ GÖREBİLİR)
$gider_kategorileri = [];
$stmt = $db->prepare("
    SELECT g.kategori, SUM(g.miktar) as toplam_miktar, COUNT(*) as adet,
           COALESCE(gk.icon, '📋') as icon
    FROM giderler g 
    LEFT JOIN gider_kategorileri gk ON g.kategori = gk.kategori_adi
    WHERE g.status = 1
    GROUP BY g.kategori, gk.icon
    ORDER BY toplam_miktar DESC
");
$stmt->execute();
$gider_kategorileri = $stmt->fetchAll();

// Toplam gider hesapla
$toplam_gider = 0;
foreach($gider_kategorileri as $kategori) {
    $toplam_gider += $kategori['toplam_miktar'];
}

// Aidat durumu için veri - SADECE YÖNETİCİ İÇİN
$aidat_durumu = [];
if ($current_user['role'] !== 'sakin') {
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN odendi = 1 THEN miktar ELSE 0 END) as odenen_toplam,
            SUM(CASE WHEN odendi = 0 THEN miktar ELSE 0 END) as odenmesi_gereken_toplam,
            COUNT(CASE WHEN odendi = 1 THEN 1 END) as odenen_adet,
            COUNT(CASE WHEN odendi = 0 THEN 1 END) as odenmesi_gereken_adet
        FROM aidatlar 
        WHERE status = 1 
        AND ay = ?
    ");
    $stmt->execute([$selected_month]);
    $aidat_durumu = $stmt->fetch();
}

// Mevcut ayları getir
$available_months = getAvailableMonths();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        /* Mevcut CSS'leri koruyoruz, sadece yeni pasta grafik stilleri ekliyoruz */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #f8fafc;
            --sidebar-border: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --nav-hover: #f1f5f9;
            --nav-active: #dbeafe;
            --nav-active-border: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --border-radius: 8px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --card-background: #ffffff;
            --background-color: #f8fafc;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow-x: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            gap: 0;
        }

        /* Sidebar CSS'leri - değişmeyecek */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            position: relative;
            height: 100vh;
            overflow: hidden;
            flex-shrink: 0;
            margin: 0;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 20px 12px;
            justify-content: center;
        }

        .logo {
            font-size: 28px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            border-radius: var(--border-radius);
            color: white;
            flex-shrink: 0;
        }

        .brand-text {
            font-weight: 700;
            font-size: 18px;
            color: var(--text-primary);
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .brand-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-toggle {
            position: absolute;
            top: 20px;
            right: 5px;
            width: 30px;
            height: 30px;
            background: white;
            border: 1px solid var(--sidebar-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            color: var(--text-secondary);
            z-index: 10;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--nav-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .sidebar.collapsed .sidebar-toggle {
            transform: rotate(180deg);
        }

        .nav-menu {
            flex: 1;
            padding: 16px 0;
            overflow-y: auto;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 24px 8px;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-section-title {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            overflow: hidden;
        }

        .nav-item {
            margin: 2px 12px;
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-secondary);
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            position: relative;
            font-weight: 500;
            font-size: 14px;
        }

        .nav-link:hover {
            background: var(--nav-hover);
            color: var(--text-primary);
        }

        .nav-link.active {
            background: var(--nav-active);
            color: var(--primary-color);
            border-left: 3px solid var(--nav-active-border);
        }

        .nav-icon {
            font-size: 20px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-text {
            flex: 1;
            white-space: nowrap;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .nav-count, .nav-badge {
            background: var(--danger-color);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .nav-count {
            background: var(--info-color);
        }

        .sidebar.collapsed .nav-badge,
        .sidebar.collapsed .nav-count {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 1;
            transform: scale(0.8);
        }

        .nav-tooltip {
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--text-primary);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            margin-left: 12px;
            z-index: 1000;
        }

        .nav-tooltip::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: var(--text-primary);
        }

        .sidebar.collapsed .nav-link:hover .nav-tooltip {
            opacity: 1;
        }

        /* Site/Blok selector stilleri */
        .site-selector-container {
            padding: 16px 12px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--sidebar-border);
        }

        .site-selector-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            padding: 0 12px;
        }

        .site-icon {
            font-size: 16px;
        }

        .site-text {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .site-selector-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .selector-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .selector-label {
            font-size: 11px;
            color: var(--text-muted);
            padding: 0 4px;
        }

        .site-blok-select {
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 12px;
            background: white;
            color: var(--text-primary);
        }

        .current-selection {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--nav-active);
            border-radius: 6px;
        }

        .selection-icon {
            font-size: 14px;
            color: var(--primary-color);
        }

        .selection-text {
            flex: 1;
            font-size: 11px;
        }

        .site-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .blok-name {
            color: var(--text-secondary);
        }

        /* Main content styles */
        .main-content {
            flex: 1;
            background: white;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0;
            min-height: 80px;
        }

        .dashboard-content {
            flex: 1;
            padding: 24px;
            margin: 0;
            overflow-y: auto;
        }

        /* Yeni pasta grafik widget'ları için stiller */
        .widget-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 0;
        }

        .widget {
            background: var(--card-background);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .widget:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .widget-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .widget-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .widget-action {
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .widget-action:hover {
            text-decoration: underline;
        }

        .widget-body {
            padding: 20px 24px 24px;
        }

        /* Pasta grafik konteynerleri */
        .pie-chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .chart-legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
            flex-shrink: 0;
        }

        .legend-text {
            flex: 1;
            color: var(--text-secondary);
        }

        .legend-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .chart-summary {
            display: flex;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            margin-top: 16px;
        }

        .summary-stat {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .stat-value {
            font-size: 16px;
            font-weight: 600;
        }

        .text-success {
            color: var(--success-color) !important;
        }

        .text-danger {
            color: var(--danger-color) !important;
        }

        .text-warning {
            color: var(--warning-color) !important;
        }

        .text-info {
            color: var(--info-color) !important;
        }

        .welcome-section {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin: 0;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 0;
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-input {
            padding: 8px 40px 8px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-icon {
            position: absolute;
            right: 12px;
            color: var(--text-secondary);
            pointer-events: none;
        }

        .dropdown {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border: none;
            background: var(--background-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            position: relative;
            transition: all 0.3s ease;
        }

        .notification-btn:hover {
            background: var(--border-color);
        }

        .logout-btn {
            width: 40px;
            height: 40px;
            background: var(--background-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s ease;
            color: var(--danger-color);
        }

        .logout-btn:hover {
            background: var(--danger-color);
            color: white;
            transform: translateY(-1px);
        }

        .notification-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: var(--danger-color);
            border-radius: 50%;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: var(--background-color);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-role {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            min-width: 200px;
            z-index: 1000;
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            margin-top: 8px;
        }

        .dropdown-menu.show {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .notification-dropdown {
            width: 300px;
        }

        .notification-item {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .notification-content {
            display: flex;
            gap: 12px;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .notification-icon.success {
            background: var(--success-color);
            color: white;
        }

        .notification-text {
            flex: 1;
        }

        .notification-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .notification-description {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 11px;
            color: var(--text-muted);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fed7aa;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .month-selector {
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 12px;
            background: white;
        }

        /* Sakin için ödeme hatırlatıcısı */
        .reminder-urgent, .reminder-success {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .reminder-icon {
            font-size: 48px;
            flex-shrink: 0;
        }

        .reminder-content {
            flex: 1;
        }

        .reminder-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .reminder-urgent .reminder-title {
            color: var(--danger-color);
        }

        .reminder-success .reminder-title {
            color: var(--success-color);
        }

        .reminder-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--danger-color);
            margin-bottom: 12px;
        }

        .reminder-description {
            color: var(--text-secondary);
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .reminder-btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .reminder-btn.danger {
            background: var(--danger-color);
            color: white;
        }

        .reminder-btn.danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .next-payment {
            background: var(--background-color);
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px;
                height: 100vh;
                z-index: 1000;
                box-shadow: var(--shadow-lg);
            }

            .sidebar.open {
                left: 0;
            }

            .sidebar.collapsed {
                width: 280px;
                left: -280px;
            }

            .main-content {
                width: 100%;
                margin-left: 0;
            }

            .topbar {
                padding: 12px 16px;
            }

            .dashboard-content {
                padding: 16px;
            }

            .widget-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .widget-header {
                padding: 16px 20px 12px;
            }

            .widget-body {
                padding: 16px 20px 20px;
            }

            .welcome-section {
                margin-bottom: 16px;
            }

            .page-title {
                font-size: 24px;
            }

            .pie-chart-container {
                height: 250px;
            }

            .reminder-urgent, .reminder-success {
                flex-direction: column;
                text-align: center;
            }

            .reminder-icon {
                align-self: center;
            }

            .search-input {
                width: 200px;
            }

            .topbar-actions {
                gap: 8px;
            }
        }

        .widget {
            animation: fadeInUp 0.6s ease forwards;
        }

        .widget:nth-child(1) { animation-delay: 0.1s; }
        .widget:nth-child(2) { animation-delay: 0.2s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-item {
            animation: slideIn 0.3s ease forwards;
        }

        .nav-item:nth-child(1) { animation-delay: 0.1s; }
        .nav-item:nth-child(2) { animation-delay: 0.15s; }
        .nav-item:nth-child(3) { animation-delay: 0.2s; }
        .nav-item:nth-child(4) { animation-delay: 0.25s; }
        .nav-item:nth-child(5) { animation-delay: 0.3s; }
        .nav-item:nth-child(6) { animation-delay: 0.35s; }

        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .mobile-overlay.active {
            display: block;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php 
        // ÖNEMLİ: Sidebar'ı include et - eski HTML yerine
        include __DIR__ . '/../src/views/sidebar.php'; 
        ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="welcome-section">
                    <h1 class="page-title">
                        Hoş geldiniz, <?= htmlspecialchars(explode(' ', $current_user['ad_soyad'] ?? $current_user['username'])[0]) ?>! 👋
                    </h1>
                    <p class="page-subtitle">
                        <?php
                        $hour = date('H');
                        if ($hour < 12) echo "Günaydın!";
                        elseif ($hour < 18) echo "İyi günler!";
                        else echo "İyi akşamlar!";
                        ?> 
                        <?= $current_user['role'] === 'sakin' ? 'Daire ' . $current_user['daire_no'] : getRoleText($current_user['role']) ?>
                    </p>
                </div>
                
                <div class="topbar-actions">    
                    <div class="dropdown">
                        <div class="dropdown-menu notification-dropdown" id="notifications">
                            <div class="notification-item">
                                <div class="notification-content">
                                    <div class="notification-icon success">✓</div>
                                    <div class="notification-text">
                                        <div class="notification-title">Sistemde Aktifsiniz</div>
                                        <div class="notification-description">Apartman yönetim sistemine hoş geldiniz</div>
                                        <div class="notification-time">Şimdi</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="dropdown">
                        <div class="user-menu" onclick="toggleDropdown('userMenu')">
                            <div class="user-avatar">
                                <?= substr($current_user['ad_soyad'] ?? $current_user['username'], 0, 2) ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?= htmlspecialchars($current_user['ad_soyad'] ?? $current_user['username']) ?></div>
                                <div class="user-role"><?= getRoleText($current_user['role']) ?></div>
                            </div>
                        </div>
                        <div class="dropdown-menu" id="userMenu">
                            <a href="#" class="dropdown-item">👤 Profil</a>
                            <a href="#" class="dropdown-item">⚙️ Ayarlar</a>
                            <a href="logout.php" class="dropdown-item" style="color: #ef4444;">🚪 Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <?php 
                // Alert gösterimi
                $alert = getAlert();
                if ($alert): 
                ?>
                    <div class="alert alert-<?= $alert['type'] ?>">
                        <?= htmlspecialchars($alert['message']) ?>
                    </div>
                <?php endif; ?>

                <!-- Widget Grid -->
                <div class="widget-grid">
                    <?php if ($current_user['role'] === 'sakin'): ?>
                        <!-- Sakin için Ödeme Hatırlatıcısı -->
                        <div class="widget payment-reminder">
                            <div class="widget-header">
                                <h3 class="widget-title">💡 Ödeme Hatırlatıcısı</h3>
                            </div>
                            <div class="widget-body">
                                <?php if ($stats['bekleyen_aidat'] > 0): ?>
                                    <div class="reminder-urgent">
                                        <div class="reminder-icon">⚠️</div>
                                        <div class="reminder-content">
                                            <div class="reminder-title">Bekleyen Ödemeniz Var!</div>
                                            <div class="reminder-amount"><?= formatMoney($stats['bekleyen_aidat']) ?></div>
                                            <div class="reminder-description">
                                                Ödenmemiş aidat borcunuz bulunmaktadır. 
                                                Lütfen en kısa sürede ödeme yapın.
                                            </div>
                                            <a href="pages/aidatlarim.php" class="reminder-btn danger">
                                                💳 Ödeme Yap
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="reminder-success">
                                        <div class="reminder-icon">✅</div>
                                        <div class="reminder-content">
                                            <div class="reminder-title">Tüm Ödemeleriniz Güncel!</div>
                                            <div class="reminder-description">
                                                Harika! Bekleyen aidat borcunuz bulunmuyor. 
                                                Düzenli ödemeleriniz için teşekkür ederiz.
                                            </div>
                                            <div class="next-payment">
                                                <strong>Sonraki Aidat:</strong> <?= date('F Y', strtotime('+1 month')) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Sakin için Gider Dağılımı Pasta Grafik -->
                        <div class="widget expense-pie-chart">
                            <div class="widget-header">
                                <h3 class="widget-title">📊 Apartman Giderleri</h3>
                                <span class="widget-action">Toplam Gider</span>
                            </div>
                            <div class="widget-body">
                                <div class="pie-chart-container">
                                    <canvas id="expensesPieChartResident"></canvas>
                                </div>
                                <?php if (!empty($gider_kategorileri)): ?>
                                    <div class="chart-legend">
                                        <?php foreach ($gider_kategorileri as $kategori): ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background-color: <?= getExpenseCategoryColor($kategori['kategori']) ?>"></div>
                                                <span class="legend-text"><?= $kategori['icon'] ?? '📋' ?> <?= htmlspecialchars($kategori['kategori']) ?></span>
                                                <span class="legend-value"><?= formatMoney($kategori['toplam_miktar']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="chart-summary">
                                        <div class="summary-stat">
                                            <span class="stat-label">Toplam Gider:</span>
                                            <span class="stat-value text-danger"><?= formatMoney($toplam_gider) ?></span>
                                        </div>
                                        <div class="summary-stat">
                                            <span class="stat-label">Kategori Sayısı:</span>
                                            <span class="stat-value text-info"><?= count($gider_kategorileri) ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">💰</div>
                                        <p>Henüz hiç gider kaydı bulunmuyor.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Yönetici için Gider Dağılımı Pasta Grafik -->
                        <div class="widget expense-pie-chart">
                            <div class="widget-header">
                                <h3 class="widget-title">📊 Gider Dağılımı</h3>
                                <span class="widget-action">Toplam Gider</span>
                            </div>
                            <div class="widget-body">
                                <div class="pie-chart-container">
                                    <canvas id="expensesPieChart"></canvas>
                                </div>
                                <?php if (!empty($gider_kategorileri)): ?>
                                    <div class="chart-legend">
                                        <?php foreach ($gider_kategorileri as $kategori): ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background-color: <?= getExpenseCategoryColor($kategori['kategori']) ?>"></div>
                                                <span class="legend-text"><?= $kategori['icon'] ?? '📋' ?> <?= htmlspecialchars($kategori['kategori']) ?></span>
                                                <span class="legend-value"><?= formatMoney($kategori['toplam_miktar']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="chart-summary">
                                        <div class="summary-stat">
                                            <span class="stat-label">Toplam Gider:</span>
                                            <span class="stat-value text-danger"><?= formatMoney($toplam_gider) ?></span>
                                        </div>
                                        <div class="summary-stat">
                                            <span class="stat-label">Kategori Sayısı:</span>
                                            <span class="stat-value text-info"><?= count($gider_kategorileri) ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">💰</div>
                                        <p>Henüz hiç gider kaydı bulunmuyor.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Yönetici için Aidat Durumu Pasta Grafik -->
                        <div class="widget dues-pie-chart">
                            <div class="widget-header">
                                <h3 class="widget-title">🏠 Aidat Durumu</h3>
                                <input type="month" class="month-picker" id="monthPickerDues" 
                                       value="<?= $selected_month ?>" 
                                       onchange="updateDuesPieChart(this.value)">
                            </div>
                            <div class="widget-body">
                                <div class="pie-chart-container">
                                    <canvas id="duesPieChart"></canvas>
                                </div>
                                <?php if ($aidat_durumu && ($aidat_durumu['odenen_toplam'] > 0 || $aidat_durumu['odenmesi_gereken_toplam'] > 0)): ?>
                                    <div class="chart-legend">
                                        <?php if ($aidat_durumu['odenen_toplam'] > 0): ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background-color: #10b981"></div>
                                                <span class="legend-text">✅ Ödenen Aidatlar</span>
                                                <span class="legend-value"><?= formatMoney($aidat_durumu['odenen_toplam']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($aidat_durumu['odenmesi_gereken_toplam'] > 0): ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background-color: #f59e0b"></div>
                                                <span class="legend-text">⏳ Bekleyen Aidatlar</span>
                                                <span class="legend-value"><?= formatMoney($aidat_durumu['odenmesi_gereken_toplam']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chart-summary">
                                        <div class="summary-stat">
                                            <span class="stat-label">Tahsilat Oranı:</span>
                                            <span class="stat-value text-info">
                                                %<?= number_format($stats['tahsilat_orani'], 1) ?>
                                            </span>
                                        </div>
                                        <div class="summary-stat">
                                            <span class="stat-label">Toplam Daire:</span>
                                            <span class="stat-value text-primary">
                                                <?= $aidat_durumu['odenen_adet'] + $aidat_durumu['odenmesi_gereken_adet'] ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">🏠</div>
                                        <p>Bu ay için henüz aidat kaydı bulunmuyor.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Pasta grafik renk paleti
        const expenseColors = [
            '#ef4444', // Kırmızı
            '#f59e0b', // Turuncu
            '#10b981', // Yeşil
            '#3b82f6', // Mavi
            '#8b5cf6', // Mor
            '#ec4899', // Pembe
            '#06b6d4', // Cyan
            '#84cc16', // Lime
            '#f97316', // Orange
            '#6366f1'  // Indigo
        ];

        const duesColors = {
            'paid': '#10b981',     // Yeşil - Ödenen
            'pending': '#f59e0b'   // Turuncu - Bekleyen
        };

        // Gider kategorisi renk fonksiyonu
        function getExpenseCategoryColor(category) {
            const colorMap = {
                'Fatura': '#ef4444',
                'Temizlik': '#10b981',
                'Bakım': '#f59e0b',
                'Güvenlik': '#3b82f6',
                'Peyzaj': '#84cc16',
                'Diğer': '#6b7280'
            };
            return colorMap[category] || '#6b7280';
        }

        // Yönetici için gider pasta grafiğini oluştur
        function createExpensesPieChart() {
            const ctx = document.getElementById('expensesPieChart');
            if (!ctx) return;

            const expenseData = <?= json_encode($gider_kategorileri ?? []) ?>;
            
            if (expenseData.length === 0) {
                return;
            }

            const labels = expenseData.map(item => `${item.icon || '📋'} ${item.kategori}`);
            const data = expenseData.map(item => parseFloat(item.toplam_miktar));
            const colors = expenseData.map(item => getExpenseCategoryColor(item.kategori));

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${context.label}: ${new Intl.NumberFormat('tr-TR', {
                                        style: 'currency',
                                        currency: 'TRY'
                                    }).format(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: {
                        animateRotate: true,
                        duration: 1000
                    }
                }
            });
        }

        // Sakin için gider pasta grafiğini oluştur
        function createExpensesPieChartForResident() {
            const ctx = document.getElementById('expensesPieChartResident');
            if (!ctx) return;

            const expenseData = <?= json_encode($gider_kategorileri ?? []) ?>;
            
            if (expenseData.length === 0) {
                return;
            }

            const labels = expenseData.map(item => `${item.icon || '📋'} ${item.kategori}`);
            const data = expenseData.map(item => parseFloat(item.toplam_miktar));
            const colors = expenseData.map(item => getExpenseCategoryColor(item.kategori));

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${context.label}: ${new Intl.NumberFormat('tr-TR', {
                                        style: 'currency',
                                        currency: 'TRY'
                                    }).format(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: {
                        animateRotate: true,
                        duration: 1000
                    }
                }
            });
        }

        // Aidat durumu pasta grafiğini oluştur
        function createDuesPieChart() {
            const ctx = document.getElementById('duesPieChart');
            if (!ctx) return;

            const duesData = <?= json_encode($aidat_durumu ?? []) ?>;
            
            if (!duesData || (parseFloat(duesData.odenen_toplam) === 0 && parseFloat(duesData.odenmesi_gereken_toplam) === 0)) {
                return;
            }

            const labels = [];
            const data = [];
            const colors = [];

            if (parseFloat(duesData.odenen_toplam) > 0) {
                labels.push('✅ Ödenen Aidatlar');
                data.push(parseFloat(duesData.odenen_toplam));
                colors.push(duesColors.paid);
            }

            if (parseFloat(duesData.odenmesi_gereken_toplam) > 0) {
                labels.push('⏳ Bekleyen Aidatlar');
                data.push(parseFloat(duesData.odenmesi_gereken_toplam));
                colors.push(duesColors.pending);
            }

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${context.label}: ${new Intl.NumberFormat('tr-TR', {
                                        style: 'currency',
                                        currency: 'TRY'
                                    }).format(value)} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%',
                    animation: {
                        animateRotate: true,
                        duration: 1000
                    }
                }
            });
        }

        // Aidat pasta grafiğini güncelle
        function updateDuesPieChart(selectedMonth) {
            // AJAX ile yeni veriyi al ve grafiği güncelle
            fetch(`ajax/dues_chart_data.php?month=${selectedMonth}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Sayfayı yeniden yükle (basit çözüm)
                        const url = new URL(window.location);
                        url.searchParams.set('month', selectedMonth);
                        window.location.href = url.toString();
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    // Hata durumunda da sayfayı yenile
                    const url = new URL(window.location);
                    url.searchParams.set('month', selectedMonth);
                    window.location.href = url.toString();
                });
        }

        // Sidebar toggle fonksiyonu
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
                let overlay = document.getElementById('mobileOverlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.id = 'mobileOverlay';
                    overlay.className = 'mobile-overlay';
                    overlay.onclick = () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    };
                    document.body.appendChild(overlay);
                }
                overlay.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        }

        // Dropdown toggle fonksiyonu
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.toggle('show');
                
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => {
                    if (d.id !== dropdownId) {
                        d.classList.remove('show');
                    }
                });
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            
            if (window.innerWidth > 768) {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                }
            }
            
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && currentPath.includes(href.replace('./', ''))) {
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                }
            });

            // Pasta grafiklerini oluştur
            setTimeout(() => {
                createExpensesPieChart(); // Yönetici için
                createExpensesPieChartForResident(); // Sakin için
                createDuesPieChart(); // Sadece yönetici için
            }, 500);
        });

        // Dışarı tıklandığında dropdown'ları kapat
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown')) {
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => d.classList.remove('show'));
            }
        });

        // ESC tuşu ile dropdown'ları kapat
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => d.classList.remove('show'));
            }
        });

        // Resize olayını dinle
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                if (overlay) {
                    overlay.style.display = 'none';
                }
            } else {
                sidebar.classList.remove('collapsed');
            }
        });
    </script>
</body>
</html>

<?php
// Helper function - gider kategori renkleri için
function getExpenseCategoryColor($category) {
    $colorMap = [
        'Fatura' => '#ef4444',
        'Temizlik' => '#10b981', 
        'Bakım' => '#f59e0b',
        'Güvenlik' => '#3b82f6',
        'Peyzaj' => '#84cc16',
        'Diğer' => '#6b7280'
    ];
    return $colorMap[$category] ?? '#6b7280';
}
?>