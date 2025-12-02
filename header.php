<?php
// header.php - Ortak header dosyası
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/functions.php';

// Sayfa kontrolü
requireLogin();
$current_user = getCurrentUser();

// Sayfa başlığını al (eğer ayarlanmışsa)
$page_title = isset($page_title) ? $page_title : 'Apartman Yönetimi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                <h1>🏢 Apartman Yönetim Sistemi</h1>
                <p>Aidat ve gider takibinizi kolayca yapın</p>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($current_user['ad_soyad'] ?? $current_user['username']) ?></div>
                    <div class="user-role">
                        <?= getRoleText($current_user['role']) ?>
                        <?php if (isset($current_user['grup_adi'])): ?>
                            (<?= htmlspecialchars($current_user['grup_adi']) ?>)
                        <?php endif; ?>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">Çıkış</a>
            </div>
        </div>
        
        <div class="nav-tabs">
            <div class="nav-item">
                <a href="../index.php" class="nav-link">📊 Dashboard</a>
            </div>
            <?php if (hasPagePermission('sakinler')): ?>
                <div class="nav-item">
                    <a href="../pages/sakinler.php" class="nav-link">👥 Sakinler</a>
                </div>
            <?php endif; ?>
            <?php if (hasPagePermission('aidatlar')): ?>
                <div class="nav-item">
                    <a href="../pages/aidatlar.php" class="nav-link">💰 Aidatlar</a>
                </div>
            <?php endif; ?>
            <?php if (hasPagePermission('giderler')): ?>
                <div class="nav-item">
                    <a href="../pages/giderler.php" class="nav-link">📋 Giderler</a>
                </div>
            <?php endif; ?>
            <?php if ($current_user['role'] === 'yonetici'): ?>
                <div class="nav-item">
                    <a href="../pages/yetkiler.php" class="nav-link">⚙️ Yetki Yönetimi</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php 
        // Alert gösterimi
        $alert = getAlert();
        if ($alert): 
        ?>
            <div class="alert alert-<?= $alert['type'] ?>">
                <?= htmlspecialchars($alert['message']) ?>
            </div>
        <?php endif; ?>
        
       