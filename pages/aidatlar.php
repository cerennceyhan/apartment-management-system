<?php
// pages/aidatlar.php - Modern Aidat yönetimi sayfası - GÜNCELLENMIŞ
$page_title = 'Aidatlar';
session_start();

define('IN_PAGES', true);
define('APP_LOADED', true);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/functions.php';
require_once '../includes/get_chart.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();


// Aidat görüntüleme yetkisi kontrolü
if (!hasPagePermission('aidatlar')) {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Site/Blok seçim durumunu URL'den veya session'dan al
$selected_site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : (isset($_SESSION['selected_site_id']) ? $_SESSION['selected_site_id'] : null);
$selected_blok_id = isset($_GET['blok_id']) ? (int)$_GET['blok_id'] : (isset($_SESSION['selected_blok_id']) ? $_SESSION['selected_blok_id'] : null);

// Seçimi session'a kaydet
if (isset($_GET['site_id'])) {
    $_SESSION['selected_site_id'] = $selected_site_id;
}
if (isset($_GET['blok_id'])) {
    $_SESSION['selected_blok_id'] = $selected_blok_id;
}

// Tüm siteleri al (yönetici için)
if ($current_user['role'] === 'yonetici') {
    try {
        $stmt = $db->prepare("SELECT * FROM siteler WHERE status = 1 ORDER BY site_adi");
        $stmt->execute();
        $all_sites = $stmt->fetchAll();
    } catch (Exception $e) {
        $all_sites = [];
    }
} else {
    // Diğer roller için site listesi
    $all_sites = getUserSites();
}

// Site ve blok bilgilerini al
$site_info = null;
$blok_info = null;
$site_bloklar = [];

if ($selected_site_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM siteler WHERE id = ? AND status = 1");
        $stmt->execute([$selected_site_id]);
        $site_info = $stmt->fetch();
        
        if ($selected_blok_id) {
            $stmt = $db->prepare("SELECT * FROM bloklar WHERE id = ? AND site_id = ? AND status = 1");
            $stmt->execute([$selected_blok_id, $selected_site_id]);
            $blok_info = $stmt->fetch();
        }
        
        // Seçili site için blokları al
        $stmt = $db->prepare("SELECT * FROM bloklar WHERE site_id = ? AND status = 1 ORDER BY blok_adi");
        $stmt->execute([$selected_site_id]);
        $site_bloklar = $stmt->fetchAll();
    } catch (Exception $e) {
        $site_info = null;
        $blok_info = null;
        $site_bloklar = [];
    }
}

// Aidat ekleme işlemi (TEK VE TOPLU) - GÜNCELLENDİ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_aidat'])) {
    if (!hasActionPermission('add_aidat')) {
        showAlert('Aidat ekleme yetkiniz yok', 'error');
    } else {
        $ay = sanitize($_POST['ay']);
        $miktar = (float)$_POST['miktar'];
        $tum_sakinler = isset($_POST['tum_sakinler']) && $_POST['tum_sakinler'] === '1';
        
        // Site/blok bilgilerini al
        $aidat_site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : $selected_site_id;
        $aidat_blok_id = isset($_POST['blok_id']) ? (int)$_POST['blok_id'] : $selected_blok_id;
        
        $errors = [];
        
        if (empty($ay)) $errors[] = 'Ay seçimi gerekli';
        if (!validateNumeric($miktar, 1)) $errors[] = 'Geçerli bir miktar girin';
        if (!$aidat_site_id) $errors[] = 'Lütfen bir site seçin';
        
        if (!$tum_sakinler) {
            // Tek sakin için
            $sakin_id = (int)$_POST['sakin_id'];
            if (!$sakin_id) $errors[] = 'Sakin seçimi gerekli';
            
            // Duplicate kontrol
            if (empty($errors)) {
                $stmt = $db->prepare("SELECT id FROM aidatlar WHERE sakin_id = ? AND ay = ? AND status = 1");
                $stmt->execute([$sakin_id, $ay]);
                if ($stmt->fetch()) {
                    $errors[] = 'Bu sakin için bu ay zaten aidat kaydı var';
                }
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $db->prepare("INSERT INTO aidatlar (sakin_id, ay, miktar) VALUES (?, ?, ?)");
                    $stmt->execute([$sakin_id, $ay, $miktar]);
                    
                    showAlert('Aidat başarıyla eklendi', 'success');
                    $_POST = [];
                    
                } catch (Exception $e) {
                    showAlert('Aidat eklenirken hata oluştu', 'error');
                }
            }
        } else {
            // TÜM SAKİNLER İÇİN TOPLU EKLEME - Site/Blok Filtreli
            if (empty($errors)) {
                try {
                    // Seçili site/blok için sakinleri getir
                    $where_conditions = ["status = 1"];
                    $params = [];
                    
                    $where_conditions[] = "site_id = ?";
                    $params[] = $aidat_site_id;
                    
                    if ($aidat_blok_id) {
                        $where_conditions[] = "blok_id = ?";
                        $params[] = $aidat_blok_id;
                    }
                    
                    $where_clause = implode(' AND ', $where_conditions);
                    
                    $sakinler_stmt = $db->prepare("SELECT id, ad_soyad, daire_no FROM sakinler WHERE {$where_clause} ORDER BY ad_soyad");
                    $sakinler_stmt->execute($params);
                    $tum_sakinler_list = $sakinler_stmt->fetchAll();
                    
                    $eklenen = 0;
                    $atlanan = 0;
                    $hatalar = [];
                    
                    foreach ($tum_sakinler_list as $sakin) {
                        // Bu sakin için bu ay zaten aidat var mı kontrol et
                        $check_stmt = $db->prepare("SELECT id FROM aidatlar WHERE sakin_id = ? AND ay = ? AND status = 1");
                        $check_stmt->execute([$sakin['id'], $ay]);
                        
                        if ($check_stmt->fetch()) {
                            // Zaten var, atla
                            $atlanan++;
                        } else {
                            // Yok, ekle
                            try {
                                $insert_stmt = $db->prepare("INSERT INTO aidatlar (sakin_id, ay, miktar) VALUES (?, ?, ?)");
                                $insert_stmt->execute([$sakin['id'], $ay, $miktar]);
                                $eklenen++;
                            } catch (Exception $e) {
                                $hatalar[] = $sakin['ad_soyad'] . ' (' . $sakin['daire_no'] . ')';
                            }
                        }
                    }
                    
                    // Sonuç mesajı
                    $mesaj = "🎉 Toplu aidat ekleme tamamlandı!<br>";
                    $mesaj .= "✅ {$eklenen} sakin için aidat eklendi<br>";
                    if ($atlanan > 0) {
                        $mesaj .= "⚠️ {$atlanan} sakin zaten bu ay için aidatı olduğu için atlandı<br>";
                    }
                    if (!empty($hatalar)) {
                        $mesaj .= "❌ Hata olan sakinler: " . implode(', ', $hatalar);
                        showAlert($mesaj, 'warning');
                    } else {
                        showAlert($mesaj, 'success');
                    }
                    $_POST = [];
                    
                } catch (Exception $e) {
                    showAlert('Toplu aidat ekleme sırasında hata oluştu', 'error');
                }
            }
        }
        
        if (!empty($errors)) {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Aidat silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_aidat'])) {
    if (!hasActionPermission('delete_aidat')) {
        showAlert('Aidat silme yetkiniz yok', 'error');
    } else {
        $aidat_id = (int)$_POST['aidat_id'];
        
        try {
            $stmt = $db->prepare("UPDATE aidatlar SET status = 0 WHERE id = ?");
            $stmt->execute([$aidat_id]);
            
            if ($stmt->rowCount() > 0) {
                showAlert('Aidat başarıyla silindi', 'success');
            } else {
                showAlert('Aidat bulunamadı', 'error');
            }
        } catch (Exception $e) {
            showAlert('Aidat silinirken hata oluştu', 'error');
        }
    }
}

// Aidat ödeme işlemi - SADECE SAKİNLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_aidat'])) {
    // Sadece sakinler ödeme yapabilir
    if ($current_user['role'] !== 'sakin') {
        showAlert('Ödeme yetkisi sadece sakinlere aittir', 'error');
    } elseif (!hasActionPermission('pay_aidat')) {
        showAlert('Aidat ödeme yetkiniz yok', 'error');
    } else {
        $aidat_id = (int)$_POST['aidat_id'];
        
        try {
            // Aidatı kontrol et
            $stmt = $db->prepare("SELECT * FROM aidatlar WHERE id = ? AND status = 1");
            $stmt->execute([$aidat_id]);
            $aidat = $stmt->fetch();
            
            if (!$aidat) {
                showAlert('Aidat bulunamadı', 'error');
            } elseif ($aidat['odendi']) {
                showAlert('Bu aidat zaten ödenmiş', 'error');
            } elseif ($current_user['sakin_id'] != $aidat['sakin_id']) {
                showAlert('Bu aidatı ödeme yetkiniz yok', 'error');
            } else {
                $stmt = $db->prepare("UPDATE aidatlar SET odendi = 1, odeme_tarihi = CURDATE() WHERE id = ?");
                $stmt->execute([$aidat_id]);
                
                showAlert('Ödeme başarıyla tamamlandı', 'success');
            }
        } catch (Exception $e) {
            showAlert('Ödeme işlemi sırasında hata oluştu', 'error');
        }
    }
}

// Sakinleri getir (aidat ekleme için) - Site/Blok filtreli
$sakinler = [];
if (hasActionPermission('add_aidat') && $selected_site_id) {
    try {
        $where_conditions = ["status = 1"];
        $params = [];
        
        $where_conditions[] = "site_id = ?";
        $params[] = $selected_site_id;
        
        if ($selected_blok_id) {
            $where_conditions[] = "blok_id = ?";
            $params[] = $selected_blok_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $db->prepare("SELECT id, ad_soyad, daire_no FROM sakinler WHERE {$where_clause} ORDER BY ad_soyad");
        $stmt->execute($params);
        $sakinler = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hata log'la
        $sakinler = [];
    }
}

// İstatistikleri hesapla ve aidatları getir (site/blok filtreli)
$aidatlar = [];
$toplam_aidat = 0;
$odenen_aidat = 0;
$bekleyen_aidat = 0;
$odeme_orani = 0;
$aylar = [];

if ($selected_site_id) {
    try {
        $where_conditions = ["a.status = 1"];
        $params = [];
        
        // Site filtresi
        $where_conditions[] = "s.site_id = ?";
        $params[] = $selected_site_id;
        
        // Blok filtresi (sadece seçili blok varsa)
        if ($selected_blok_id) {
            $where_conditions[] = "s.blok_id = ?";
            $params[] = $selected_blok_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $db->prepare("
            SELECT a.*, s.ad_soyad, s.daire_no, s.telefon, s.email,
                   st.site_adi, b.blok_adi,
                   CASE WHEN a.odeme_tarihi IS NOT NULL THEN 'Ödendi' ELSE 'Beklemede' END as durum
            FROM aidatlar a
            LEFT JOIN sakinler s ON a.sakin_id = s.id
            LEFT JOIN siteler st ON s.site_id = st.id
            LEFT JOIN bloklar b ON s.blok_id = b.id
            WHERE $where_clause 
            ORDER BY a.ay DESC, s.ad_soyad
        ");
        $stmt->execute($params);
        $aidatlar = $stmt->fetchAll();
        
        // İstatistikleri hesapla
        foreach ($aidatlar as $aidat) {
            $toplam_aidat += $aidat['miktar'];
            if ($aidat['odendi']) {
                $odenen_aidat += $aidat['miktar'];
            } else {
                $bekleyen_aidat += $aidat['miktar'];
            }
        }
        
        if ($toplam_aidat > 0) {
            $odeme_orani = ($odenen_aidat / $toplam_aidat) * 100;
        }
        
        // Benzersiz ayları al
        $aylar = array_unique(array_column($aidatlar, 'ay'));
        sort($aylar);
        $aylar = array_reverse($aylar); // En yeni aylar önce
        
    } catch (Exception $e) {
        $aidatlar = [];
        showAlert('Aidatlar yüklenirken hata oluştu: ' . $e->getMessage(), 'error');
    }
}

// Aidatları sakinlere göre grupla (sadece seçili site/blok için)
$aidatlar_grouped = [];
if ($selected_site_id) {
    try {
        if ($current_user['role'] === 'sakin') {
            // Sakin sadece kendi aidatlarını görebilir
            $stmt = $db->prepare("
                SELECT a.*, s.ad_soyad as sakin_adi, s.daire_no 
                FROM aidatlar a 
                JOIN sakinler s ON a.sakin_id = s.id 
                WHERE a.sakin_id = ? AND a.status = 1 AND s.site_id = ?
                ORDER BY s.ad_soyad, a.ay DESC
            ");
            $stmt->execute([$current_user['sakin_id'], $selected_site_id]);
        } else {
            // Yönetici/muhasebeci tüm aidatları görebilir (site/blok filtreli)
            $where_conditions = ["a.status = 1"];
            $params = [];
            
            $where_conditions[] = "s.site_id = ?";
            $params[] = $selected_site_id;
            
            if ($selected_blok_id) {
                $where_conditions[] = "s.blok_id = ?";
                $params[] = $selected_blok_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $stmt = $db->prepare("
                SELECT a.*, s.ad_soyad as sakin_adi, s.daire_no 
                FROM aidatlar a 
                JOIN sakinler s ON a.sakin_id = s.id 
                WHERE $where_clause
                ORDER BY s.ad_soyad, a.ay DESC
            ");
            $stmt->execute($params);
        }
        $aidatlar_raw = $stmt->fetchAll();
        
        // Sakinlere göre grupla
        foreach ($aidatlar_raw as $aidat) {
            $sakin_key = $aidat['sakin_id'];
            if (!isset($aidatlar_grouped[$sakin_key])) {
                $aidatlar_grouped[$sakin_key] = [
                    'sakin_info' => [
                        'id' => $aidat['sakin_id'],
                        'ad_soyad' => $aidat['sakin_adi'],
                        'daire_no' => $aidat['daire_no']
                    ],
                    'aidatlar' => []
                ];
            }
            $aidatlar_grouped[$sakin_key]['aidatlar'][] = $aidat;
        }
        
    } catch (Exception $e) {
        $aidatlar_grouped = [];
        showAlert('Aidatlar yüklenirken hata oluştu', 'error');
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
         <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="welcome-section">
                    <h1 class="page-title">💰 Aidatlar</h1>
                    <p class="page-subtitle">
                        <?php if ($site_info && $blok_info): ?>
                            <?= htmlspecialchars($site_info['site_adi']) ?> - <?= htmlspecialchars($blok_info['blok_adi']) ?>
                        <?php elseif ($site_info): ?>
                            <?= htmlspecialchars($site_info['site_adi']) ?> - Tüm Bloklar
                        <?php else: ?>
                            Site ve blok seçin
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="topbar-actions">
                    <?php if ($selected_site_id): ?>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Sakin ara..." id="aidatSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <?php if (hasActionPermission('add_aidat')): ?>
                    <button class="btn btn-primary" onclick="openModal('addAidatModal')">
                        ➕ Yeni Aidat
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                    
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
                            <a href="../logout.php" class="dropdown-item" style="color: #ef4444;">🚪 Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
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

                <?php if (!$selected_site_id): ?>
                <!-- Site Seçim Kartları -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🏢 Site Seçin</h3>
                        <p class="card-subtitle">Aidatları görüntülemek için önce bir site seçin</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_sites)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏢</div>
                                <h3>Henüz site eklenmemiş</h3>
                                <p>İlk siteyi eklemek için site yönetimi sayfasını kullanın.</p>
                                <?php if ($current_user['role'] === 'yonetici'): ?>
                                <a href="siteler.php" class="btn btn-primary" style="margin-top: 16px;">
                                    ➕ Site Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="site-grid">
                                <?php foreach ($all_sites as $site): ?>
                                    <?php
                                    // Site için aidat sayısını al
                                    try {
                                        $stmt = $db->prepare("
                                            SELECT COUNT(a.id) as total, SUM(a.miktar) as toplam_miktar, 
                                                   SUM(CASE WHEN a.odendi = 1 THEN a.miktar ELSE 0 END) as odenen_miktar
                                            FROM aidatlar a
                                            JOIN sakinler s ON a.sakin_id = s.id
                                            WHERE s.site_id = ? AND a.status = 1
                                        ");
                                        $stmt->execute([$site['id']]);
                                        $aidat_stats = $stmt->fetch();
                                        
                                        $aidat_count = $aidat_stats['total'] ?? 0;
                                        $toplam_miktar = $aidat_stats['toplam_miktar'] ?? 0;
                                        $odenen_miktar = $aidat_stats['odenen_miktar'] ?? 0;
                                        $bekleyen_count = 0;
                                        
                                        // Bekleyen aidat sayısını al
                                        $stmt = $db->prepare("
                                            SELECT COUNT(a.id) as bekleyen
                                            FROM aidatlar a
                                            JOIN sakinler s ON a.sakin_id = s.id
                                            WHERE s.site_id = ? AND a.status = 1 AND a.odendi = 0
                                        ");
                                        $stmt->execute([$site['id']]);
                                        $bekleyen_count = $stmt->fetch()['bekleyen'] ?? 0;
                                        
                                    } catch (Exception $e) {
                                        $aidat_count = 0;
                                        $toplam_miktar = 0;
                                        $bekleyen_count = 0;
                                    }
                                    ?>
                                    <div class="site-card" onclick="selectSite(<?= $site['id'] ?>)">
                                        <div class="site-card-header">
                                            <div class="site-icon">💰</div>
                                            <div class="site-card-title"><?= htmlspecialchars($site['site_adi']) ?></div>
                                        </div>
                                        <div class="site-card-stats">
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $aidat_count ?></span>
                                                <span class="stat-label">Aidat</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $bekleyen_count ?></span>
                                                <span class="stat-label">Bekleyen</span>
                                            </div>
                                        </div>
                                        <div class="site-card-action">
                                            <span>Seç ve Devam Et →</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary clickable-stat" data-filter="all" onclick="filterByStatCard('all', this)" title="Tüm aidatları göster">
                        <div class="stat-header">
                            <div class="stat-title">TOPLAM AİDAT</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_aidat) ?></div>
                        <div class="stat-change"><?= count($aidatlar) ?> kayıt</div>
                    </div>
                    
                    <div class="stat-card success clickable-stat" data-filter="paid" onclick="filterByStatCard('paid', this)" title="Ödenen aidatları göster">
                        <div class="stat-header">
                            <div class="stat-title">ÖDENEN AİDATLAR</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($odenen_aidat) ?></div>
                        <div class="stat-change positive">%<?= number_format($odeme_orani, 1) ?> ödeme oranı</div>
                    </div>
                    
                    <div class="stat-card danger clickable-stat" data-filter="unpaid" onclick="filterByStatCard('unpaid', this)" title="Bekleyen aidatları göster">
                        <div class="stat-header">
                            <div class="stat-title">BEKLEYEN AİDATLAR</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($bekleyen_aidat) ?></div>
                        <div class="stat-change negative"><?= count(array_filter($aidatlar, fn($a) => !$a['odendi'])) ?> bekleyen</div>
                    </div>
                    
                    <div class="stat-card info clickable-stat" data-filter="this-month" onclick="filterByStatCard('this-month', this)" title="Bu ay aidatlarını göster">
                        <div class="stat-header">
                            <div class="stat-title">BU AY</div>
                        </div>
                        <div class="stat-value">
                            <?php
                            $bu_ay = date('Y-m');
                            $bu_ay_aidatlar = array_filter($aidatlar, fn($a) => $a['ay'] === $bu_ay);
                            echo formatMoney(array_sum(array_column($bu_ay_aidatlar, 'miktar')));
                            ?>
                        </div>
                        <div class="stat-change"><?= count($bu_ay_aidatlar) ?> aidat</div>
                    </div>
                </div>

                <!-- Aidatlar Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aidat Listesi</h3>
                        <div class="card-actions">
                            <!-- Site Seçim Dropdown'u -->
                            <select class="form-select" onchange="changeSite(this.value)" title="Site Seç">
                                <option value="">Site Seç</option>
                                <?php foreach ($all_sites as $site): ?>
                                    <option value="<?= $site['id'] ?>" <?= $site['id'] == $selected_site_id ? 'selected' : '' ?>>
                                        🏢 <?= htmlspecialchars($site['site_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <!-- Blok Seçim Dropdown'u (Sadece birden fazla blok varsa) -->
                            <?php if (count($site_bloklar) > 1): ?>
                            <select class="form-select" onchange="changeBlok(this.value)" title="Blok Seç">
                                <option value="">Tüm Bloklar</option>
                                <?php foreach ($site_bloklar as $blok): ?>
                                    <option value="<?= $blok['id'] ?>" <?= $blok['id'] == $selected_blok_id ? 'selected' : '' ?>>
                                        🏢 <?= htmlspecialchars($blok['blok_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            
                            <?php if (!empty($aylar)): ?>
                            <select class="form-select" id="ayFilter" onchange="filterAidatlar()">
                                <option value="">Tüm Aylar</option>
                                <?php foreach ($aylar as $ay): ?>
                                    <option value="<?= $ay ?>"><?= formatMonth($ay) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Aktif Filtre Bilgisi -->
                        <div class="filter-info" id="filterInfo" style="display: none;">
                            <div class="filter-badge">
                                <span id="filterText">Tüm Aidatlar</span>
                                <button onclick="clearAllFilters()" title="Filtreyi temizle">×</button>
                            </div>
                        </div>

                        <?php if (empty($aidatlar_grouped)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">💰</div>
                                <h3>
                                    <?php if ($selected_blok_id): ?>
                                        Bu blokta henüz aidat eklenmemiş
                                    <?php else: ?>
                                        Bu sitede henüz aidat eklenmemiş  
                                    <?php endif; ?>
                                </h3>
                                <p>İlk aidatı eklemek için yukarıdaki "Yeni Aidat" butonunu kullanın.</p>
                                <?php if (hasActionPermission('add_aidat')): ?>
                                <button class="btn btn-primary" onclick="openModal('addAidatModal')" style="margin-top: 16px;">
                                    ➕ İlk Aidatı Ekle
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="sakin-groups">
                                <?php foreach ($aidatlar_grouped as $sakin_key => $sakin_data): ?>
                                <div class="sakin-group" 
                                     data-search="<?= strtolower($sakin_data['sakin_info']['ad_soyad'] . ' ' . $sakin_data['sakin_info']['daire_no']) ?>">
                                    
                                    <!-- Sakin Header -->
                                    <div class="sakin-header" onclick="toggleSakinAidatlar(<?= $sakin_key ?>)">
                                        <div class="sakin-main">
                                            <div class="user-avatar">
                                                <?= substr($sakin_data['sakin_info']['ad_soyad'], 0, 2) ?>
                                            </div>
                                            <div class="sakin-info">
                                                <div class="sakin-name"><?= htmlspecialchars($sakin_data['sakin_info']['ad_soyad']) ?></div>
                                                <div class="sakin-apartment">
                                                    Daire <?= htmlspecialchars($sakin_data['sakin_info']['daire_no']) ?> 
                                                    • <?= count($sakin_data['aidatlar']) ?> aidat
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="sakin-summary">
                                            <?php
                                            $sakin_toplam = array_sum(array_column($sakin_data['aidatlar'], 'miktar'));
                                            $sakin_odenen = array_sum(array_column(array_filter($sakin_data['aidatlar'], fn($a) => $a['odendi']), 'miktar'));
                                            $sakin_bekleyen = $sakin_toplam - $sakin_odenen;
                                            $bekleyen_count = count(array_filter($sakin_data['aidatlar'], fn($a) => !$a['odendi']));
                                            ?>

                                            <div class="amount-summary">
                                            <div class="amount-summary">
                                                <div class="total-amount"><?= formatMoney($sakin_toplam) ?></div>
                                                <?php if ($bekleyen_count > 0): ?>
                                                    <div class="pending-info">
                                                        <span style="color: var(--danger-color); font-size: 12px;">
                                                            <?= $bekleyen_count ?> bekleyen
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="paid-info">
                                                        <span style="color: var(--success-color); font-size: 12px;">
                                                            ✅ Tümü ödendi
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="expand-icon">
                                                <span class="chevron">▼</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Sakin Aidatları -->
                                    <div class="sakin-aidatlar" id="aidatlar-<?= $sakin_key ?>" style="display: none;">
                                            
                                    <!-- Aksiyonlar Bölümü -->
                                    <div class="sakin-actions">
                                        <button class="btn btn-chart" 
                                                onclick="event.stopPropagation(); showSakinChart(<?= $sakin_key ?>, '<?= htmlspecialchars($sakin_data['sakin_info']['ad_soyad']) ?>', '<?= htmlspecialchars($sakin_data['sakin_info']['daire_no']) ?>')" 
                                                title="Grafiği Göster">
                                                Grafiği Gör
                                        </button>
                                        
                                        <button class="btn btn-document" 
                                                onclick="event.stopPropagation(); showSakinDocument(<?= $sakin_key ?>, '<?= htmlspecialchars($sakin_data['sakin_info']['ad_soyad']) ?>', '<?= htmlspecialchars($sakin_data['sakin_info']['daire_no']) ?>')" 
                                                title="Dokümanı Göster">
                                                Dokümanı Gör
                                        </button>
                                    </div>

                                    <!-- Mevcut Aidat Items -->
                                    <div style="display: none;">
                                    <?php foreach ($sakin_data['aidatlar'] as $aidat): ?>
                                        <div class="aidat-item" 
                                            data-ay="<?= $aidat['ay'] ?>" 
                                            data-status="<?= $aidat['odendi'] ? 'paid' : 'unpaid' ?>"
                                            data-month="<?= date('Y-m', strtotime($aidat['ay'])) === date('Y-m') ? 'this-month' : 'other-month' ?>">
                                            
                                            <div class="aidat-content">
                                                <div class="aidat-details">
                                                    <div class="aidat-period">
                                                        <span class="period-text"><?= formatMonth($aidat['ay']) ?></span>
                                                        <span class="amount-text"><?= formatMoney($aidat['miktar']) ?></span>
                                                    </div>
                                                    
                                                    <div class="aidat-status">
                                                        <?php if ($aidat['odendi']): ?>
                                                            <span class="badge badge-success">✅ Ödendi</span>
                                                            <?php if ($aidat['odeme_tarihi']): ?>
                                                                <small style="color: var(--text-secondary); display: block; margin-top: 4px;">
                                                                    📅 <?= formatDate($aidat['odeme_tarihi']) ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">⏳ Bekliyor</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Aidat Modal -->
    <?php if (hasActionPermission('add_aidat') && $selected_site_id): ?>
    <div class="modal" id="addAidatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">💰 Yeni Aidat Ekle</h3>
                <button class="modal-close" onclick="closeModal('addAidatModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addAidatForm">
                    <input type="hidden" name="site_id" value="<?= $selected_site_id ?>">
                    <?php if ($selected_blok_id): ?>
                        <input type="hidden" name="blok_id" value="<?= $selected_blok_id ?>">
                    <?php endif; ?>
                    
                    <!-- TOPLU EKLEME CHECKBOX -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="tum_sakinler" value="1" id="tumSakinlerCheckbox" onchange="toggleBulkMode()">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">
                                    <strong>🏠 
                                        <?php if ($selected_blok_id && $blok_info): ?>
                                            <?= htmlspecialchars($blok_info['blok_adi']) ?> blokundaki tüm sakinlere aidat ekle
                                        <?php else: ?>
                                            <?= htmlspecialchars($site_info['site_adi']) ?> sitesindeki tüm sakinlere aidat ekle
                                        <?php endif; ?>
                                    </strong>
                                    <small style="display: block; color: var(--text-secondary); margin-top: 4px;">
                                        Bu seçenek işaretlenirse, seçilen ay için 
                                        <?php if ($selected_blok_id): ?>
                                            bu bloktaki
                                        <?php else: ?>
                                            bu sitedeki
                                        <?php endif; ?>
                                        tüm sakinlere aynı miktarda aidat eklenir. 
                                        Zaten o ay için aidatı olan sakinler atlanır.
                                    </small>
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- SAKİN SEÇİMİ (Tek aidat için) -->
                    <div class="form-group" id="sakinSelectGroup">
                        <label class="form-label">Sakin Seç</label>
                        <select name="sakin_id" class="form-select" id="sakinSelect">
                            <option value="">Sakin seçin...</option>
                            <?php foreach ($sakinler as $sakin): ?>
                                <option value="<?= $sakin['id'] ?>" 
                                        <?= (isset($_POST['sakin_id']) && $_POST['sakin_id'] == $sakin['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sakin['ad_soyad']) ?> (<?= htmlspecialchars($sakin['daire_no']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Ay</label>
                            <input type="month" name="ay" class="form-input" required 
                                   value="<?= isset($_POST['ay']) ? htmlspecialchars($_POST['ay']) : date('Y-m') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Miktar (₺)</label>
                            <input type="number" name="miktar" class="form-input" 
                                   placeholder="1500" min="1" step="0.01" required 
                                   value="<?= isset($_POST['miktar']) ? htmlspecialchars($_POST['miktar']) : '' ?>">
                        </div>
                    </div>
                    
                    <!-- BİLGİ MESAJI -->
                    <div class="alert alert-info" style="margin-bottom: 20px;" id="infoMessage">
                        <strong>ℹ️ Bilgi:</strong> Aidat eklendikten sonra sakin ödeme yapabilir.
                        <br><strong>Konum:</strong> <?= htmlspecialchars($site_info['site_adi']) ?>
                        <?php if ($selected_blok_id && $blok_info): ?>
                            - <?= htmlspecialchars($blok_info['blok_adi']) ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- TOPLU EKLEME BİLGİSİ -->
                    <div class="alert alert-warning" style="margin-bottom: 20px; display: none;" id="bulkMessage">
                        <strong>🏠 Toplu Aidat Ekleme:</strong> 
                        <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                            <li>Seçilen ay için 
                                <?php if ($selected_blok_id && $blok_info): ?>
                                    <strong><?= htmlspecialchars($blok_info['blok_adi']) ?> blokundaki tüm aktif sakinlere</strong>
                                <?php else: ?>
                                    <strong><?= htmlspecialchars($site_info['site_adi']) ?> sitesindeki tüm aktif sakinlere</strong>
                                <?php endif; ?>
                                aidat eklenecek
                            </li>
                            <li>Zaten o ay için aidatı olan sakinler <strong>atlanacak</strong></li>
                            <li>İşlem sonunda detaylı rapor gösterilecek</li>
                        </ul>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addAidatModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_aidat" class="btn btn-primary" id="submitButton">
                            💰 Aidat Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pay Aidat Modal -->
    <div class="modal" id="payAidatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">💳 Aidat Ödemesi</h3>
                <button class="modal-close" onclick="closeModal('payAidatModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="payMessage">Bu aidatı ödemek istediğinizden emin misiniz?</p>
                <div class="alert alert-success">
                    <strong>✅ Bilgi:</strong> Ödeme sonrası işlem geri alınamaz.
                </div>
                
                <form method="POST" id="payAidatForm">
                    <input type="hidden" name="aidat_id" id="payAidatId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('payAidatModal')">
                            İptal
                        </button>
                        <button type="submit" name="pay_aidat" class="btn btn-success">
                            💳 Ödeme Yap
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Aidat Modal -->
    <div class="modal" id="deleteAidatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Aidat Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteAidatModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu aidatı silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz.
                </div>
                
                <form method="POST" id="deleteAidatForm">
                    <input type="hidden" name="aidat_id" id="deleteAidatId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteAidatModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_aidat" class="btn btn-danger">
                            🗑️ Evet, Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php 
    // Sakin Chart Modal'ını render et
    renderSakinChartModal();
    ?>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <!-- Chart.js Kütüphanesi -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <?php 
    // Chart JavaScript fonksiyonlarını render et
    renderSakinChartJS(); 
    ?>
    
    <script>
        // Global değişkenler
        let currentFilter = 'all';
        let currentSearchTerm = '';

        // Site seçim fonksiyonu
        function selectSite(siteId) {
            if (siteId === null) {
                window.location.href = 'aidatlar.php';
            } else {
                window.location.href = `aidatlar.php?site_id=${siteId}`;
            }
        }

        // Site değiştirme fonksiyonu
        function changeSite(siteId) {
            if (!siteId) return;
            window.location.href = `aidatlar.php?site_id=${siteId}`;
        }

        // Blok değiştirme fonksiyonu
        function changeBlok(blokId) {
            const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
            if (currentSiteId) {
                if (blokId) {
                    window.location.href = `aidatlar.php?site_id=${currentSiteId}&blok_id=${blokId}`;
                } else {
                    window.location.href = `aidatlar.php?site_id=${currentSiteId}`;
                }
            }
        }

        // Toplu mod toggle
        function toggleBulkMode() {
            const checkbox = document.getElementById('tumSakinlerCheckbox');
            const sakinGroup = document.getElementById('sakinSelectGroup');
            const sakinSelect = document.getElementById('sakinSelect');
            const infoMessage = document.getElementById('infoMessage');
            const bulkMessage = document.getElementById('bulkMessage');
            const submitButton = document.getElementById('submitButton');
            
            if (checkbox.checked) {
                // Toplu mod aktif
                sakinGroup.style.display = 'none';
                sakinSelect.removeAttribute('required');
                infoMessage.style.display = 'none';
                bulkMessage.style.display = 'block';
                submitButton.innerHTML = '🏠 Tüm Sakinlere Aidat Ekle';
                submitButton.className = 'btn btn-warning';
            } else {
                // Tek mod aktif
                sakinGroup.style.display = 'block';
                sakinSelect.setAttribute('required', 'required');
                infoMessage.style.display = 'block';
                bulkMessage.style.display = 'none';
                submitButton.innerHTML = '💰 Aidat Ekle';
                submitButton.className = 'btn btn-primary';
            }
        }

        // Stats kartına tıklayınca filtreleme
        function filterByStatCard(filterType, cardElement) {
            // Önceki aktif kartın stilini temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // Tıklanan kartı aktif yap
            cardElement.classList.add('active');
            
            // Filtreyi uygula
            currentFilter = filterType;
            applyFilters();
            
            // Dropdown'ı da güncelle
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                if (filterType === 'all') {
                    statusFilter.value = '';
                } else {
                    statusFilter.value = filterType;
                }
            }
            
            // Filtre bilgisini göster
            showFilterInfo(filterType);
        }

        // Dropdown filtreleme
        function filterAidatlar() {
            const ayFilter = document.getElementById('ayFilter') ? document.getElementById('ayFilter').value : '';
            const statusFilter = document.getElementById('statusFilter').value;
            
            currentFilter = statusFilter || 'all';
            
            // Tüm stat kartlarının aktif durumunu temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // İlgili stat kartını aktif yap
            const targetCard = document.querySelector(`[data-filter="${currentFilter}"]`);
            if (targetCard) {
                targetCard.classList.add('active');
            }
            
            applyFilters();
            showFilterInfo(currentFilter);
        }

        // Tüm filtreleri uygula
        function applyFilters() {
            const ayFilter = document.getElementById('ayFilter') ? document.getElementById('ayFilter').value : '';
            const sakinGroups = document.querySelectorAll('.sakin-group');
            
            sakinGroups.forEach(group => {
                const aidatItems = group.querySelectorAll('.aidat-item');
                let hasVisibleAidat = false;
                
                aidatItems.forEach(item => {
                    let showItem = true;
                    
                    // Ay filtresi
                    if (ayFilter && item.dataset.ay !== ayFilter) {
                        showItem = false;
                    }
                    
                    // Durum filtresi
                    if (currentFilter !== 'all') {
                        if (currentFilter === 'paid' || currentFilter === 'unpaid') {
                            showItem = showItem && item.dataset.status === currentFilter;
                        } else if (currentFilter === 'this-month') {
                            showItem = showItem && item.dataset.month === currentFilter;
                        }
                    }
                    
                    if (showItem) {
                        item.style.display = '';
                        hasVisibleAidat = true;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Arama filtresi
                if (hasVisibleAidat && currentSearchTerm) {
                    const searchData = group.dataset.search;
                    hasVisibleAidat = searchData.includes(currentSearchTerm);
                }
                
                // Eğer hiç görünür aidat yoksa, sakin grubunu gizle
                if (hasVisibleAidat) {
                    group.style.display = '';
                } else {
                    group.style.display = 'none';
                }
            });
        }

        // Filtre bilgisini göster
        function showFilterInfo(filterType) {
            const filterInfo = document.getElementById('filterInfo');
            const filterText = document.getElementById('filterText');
            
            if (!filterInfo || !filterText) return;
            
            const filterTexts = {
                'all': 'Tüm Aidatlar',
                'paid': 'Ödenen Aidatlar',
                'unpaid': 'Bekleyen Aidatlar', 
                'this-month': 'Bu Ay Aidatları'
            };
            
            if (filterType !== 'all') {
                filterText.textContent = filterTexts[filterType];
                filterInfo.style.display = 'block';
            } else {
                filterInfo.style.display = 'none';
            }
        }

        // Tüm filtreleri temizle
        function clearAllFilters() {
            currentFilter = 'all';
            currentSearchTerm = '';
            
            // Arama kutusunu temizle
            const searchInput = document.getElementById('aidatSearch');
            if (searchInput) {
                searchInput.value = '';
            }
            
            // Dropdown'ları temizle (site/blok dropdown'larına dokunma)
            if (document.getElementById('ayFilter')) {
                document.getElementById('ayFilter').value = '';
            }
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.value = '';
            }
            
            // Stat kartlarının aktif durumunu temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // Toplam aidat kartını aktif yap
            const totalCard = document.querySelector('[data-filter="all"]');
            if (totalCard) {
                totalCard.classList.add('active');
            }
            
            // Filtreleri uygula
            applyFilters();
            
            // Filtre bilgisini gizle
            const filterInfo = document.getElementById('filterInfo');
            if (filterInfo) {
                filterInfo.style.display = 'none';
            }
        }

        // Sakin aidatlarını göster/gizle
        function toggleSakinAidatlar(sakinId) {
            const aidatlarDiv = document.getElementById('aidatlar-' + sakinId);
            const chevron = aidatlarDiv.parentElement.querySelector('.chevron');
            
            if (aidatlarDiv.style.display === 'none') {
                // Önce tüm açık grupları kapat
                document.querySelectorAll('.sakin-aidatlar').forEach(group => {
                    group.style.display = 'none';
                });
                document.querySelectorAll('.chevron').forEach(icon => {
                    icon.style.transform = 'rotate(0deg)';
                });
                
                // Seçili olanı aç
                aidatlarDiv.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                aidatlarDiv.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Sakin arama
        const searchInput = document.getElementById('aidatSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                currentSearchTerm = e.target.value.toLowerCase();
                applyFilters();
            });
        }
        
        // Aidat ödeme
        function payAidat(id, sakinAdi, ay) {
            document.getElementById('payAidatId').value = id;
            document.getElementById('payMessage').innerHTML = 
                `<strong>${sakinAdi}</strong> - <strong>${ay}</strong> aidatını ödemek istediğinizden emin misiniz?`;
            openModal('payAidatModal');
        }
        
        // Aidat silme
        function deleteAidat(id, sakinAdi, ay) {
            document.getElementById('deleteAidatId').value = id;
            document.getElementById('deleteMessage').innerHTML = 
                `<strong>${sakinAdi}</strong> - <strong>${ay}</strong> aidatını silmek istediğinizden emin misiniz?`;
            openModal('deleteAidatModal');
        }
        
        // Form temizleme
        function clearForm() {
            const addForm = document.getElementById('addAidatForm');
            if (addForm) {
                addForm.reset();
                // Ay'ı tekrar bu aya ayarla
                const monthInput = addForm.querySelector('input[name="ay"]');
                if (monthInput) {
                    monthInput.value = new Date().toISOString().slice(0, 7);
                }
                // Bulk mode'u sıfırla
                toggleBulkMode();
            }
        }
        
        // Modal açma fonksiyonu
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                if (modalId === 'addAidatModal') {
                    // Form'u sıfırla
                    toggleBulkMode();
                }
            }
        }
        
        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                if (modalId === 'addAidatModal') {
                    clearForm();
                }
            }
        }

        // Dropdown toggle fonksiyonu
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.toggle('show');
                
                // Diğer dropdown'ları kapat
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => {
                    if (d.id !== dropdownId) {
                        d.classList.remove('show');
                    }
                });
            }
        }
        
        // Event listeners
        document.addEventListener('click', function(e) {
            // Modal dışına tıklandığında kapat
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            // Dropdown dışına tıklandığında kapat
            if (!e.target.closest('.dropdown')) {
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => d.classList.remove('show'));
            }
            
            // Modal kapatıldığında form temizle
            if (e.target.classList.contains('modal-close') || e.target.classList.contains('modal')) {
                clearForm();
            }
        });

        // ESC tuşu ile modal kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // Sayfa yüklendiğinde 
        document.addEventListener('DOMContentLoaded', function() {
            const totalCard = document.querySelector('[data-filter="all"]');

            if (totalCard) {
                totalCard.classList.add('active');
            }
        });
    </script>
    
    <style>
                :root {
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --warning-color: #f59e0b;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --card-background: #ffffff;
            --border-color: #e5e7eb;
            --hover-color: #f9fafb;
        }

        /* Stats Grid - TAM AYNI */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        /* Stat Cards - SİTELER.PHP İLE AYNI */
        .stat-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .stat-header {
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* Clickable - SİTELER.PHP AYNI */
        .stat-card.clickable-stat {
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            user-select: none;
            overflow: hidden;
        }

        .stat-card.clickable-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border-radius: inherit;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .stat-card.clickable-stat:hover::before {
            opacity: 1;
        }

        .stat-card.clickable-stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Active state - SİTELER.PHP AYNI */
        .stat-card.clickable-stat.active {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border: 2px solid transparent;
        }

        .stat-card.primary.clickable-stat.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.05));
        }

        .stat-card.success.clickable-stat.active {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        }

        .stat-card.warning.clickable-stat.active {
            border-color: var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
        }

        .stat-card.info.clickable-stat.active {
            border-color: #0891b2;
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(8, 145, 178, 0.05));
        }

        /* Click animation */
        .stat-card.clickable-stat:active {
            transform: translateY(-1px);
            transition: transform 0.1s ease;
        }

        /* Enhanced hover effects for clickable cards */
        .stat-card.clickable-stat:hover .stat-value {
            font-weight: 700;
            transform: scale(1.05);
            transition: all 0.3s ease;
        }

        .stat-card.primary.clickable-stat:hover .stat-value {
            color: var(--primary-color);
        }

        .stat-card.success.clickable-stat:hover .stat-value {
            color: var(--success-color);
        }

        .stat-card.warning.clickable-stat:hover .stat-value {
            color: var(--warning-color);
        }

        .stat-card.info.clickable-stat:hover .stat-value {
            color: #0891b2;
        }

        /* Click indicators */
        .stat-card.clickable-stat::after {
            content: '👆';
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .stat-card.clickable-stat:hover::after {
            opacity: 0.6;
        }

        .stat-card.clickable-stat.active::after {
            content: '✨';
            opacity: 1;
            animation: sparkle 2s infinite;
        }

        @keyframes sparkle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Special indicators for navigation cards */
        .stat-card.success.clickable-stat::after {
            content: '🏢';
        }

        .stat-card.info.clickable-stat::after {
            content: '👥';
        }

        .stat-card.warning.clickable-stat::after {
            content: '📊';
        }

        /* Chart ve Document Button Stilleri */
        .btn-chart {
            background: linear-gradient(135deg, #F0F8FF 0%, #E6F3FF 100%);
            color: #2563eb;
            border: 1px solid #E0E7FF;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 12px;
        }

        .btn-chart:hover {
            background: linear-gradient(135deg, #EBF4FF 0%, #DBEAFE 100%);
            border-color: #C7D2FE;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        .btn-document {
            background: linear-gradient(135deg, #F0F8FF 0%, #E6F3FF 100%);
            color: #2563eb;
            border: 1px solid #E0E7FF;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-document:hover {
            background: linear-gradient(135deg, #EBF4FF 0%, #DBEAFE 100%);
            border-color: #C7D2FE;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        /* Sakin Actions */
        .sakin-actions {
            display: flex;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--hover-color);
        }
        
        /* Site/Blok Seçim Kartları */
        .site-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .site-card {
            background: var(--card-background);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .site-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.15);
        }

        .site-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .site-card:hover::before {
            left: 100%;
        }

        .site-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .site-icon {
            font-size: 24px;
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .site-card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .site-card-stats {
            display: flex;
            gap: 24px;
            margin-bottom: 16px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .site-card-action {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            padding: 8px 0;
            border-top: 1px solid var(--border-color);
            margin-top: 16px;
            transition: color 0.3s ease;
        }

        .site-card:hover .site-card-action {
            color: var(--primary-color);
        }

        /* Filtre Bilgi Badge */
        .filter-info {
            margin-bottom: 16px;
            animation: slideDown 0.3s ease;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-badge button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .filter-badge button:hover {
            background: rgba(59, 130, 246, 0.1);
            opacity: 1;
        }

        /* Card Actions */
        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Checkbox stilleri */
        .checkbox-group {
            margin-bottom: 8px;
        }

        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s;
        }

        .checkbox-label:hover {
            background: var(--hover-color);
            border-color: var(--primary-color);
        }

        .checkbox-label input[type="checkbox"] {
            display: none;
        }

        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            position: relative;
            transition: all 0.2s;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
            content: "✓";
            position: absolute;
            color: white;
            font-size: 14px;
            font-weight: bold;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .checkbox-text {
            flex: 1;
        }

        /* Sakin Groups Stilleri */
        .sakin-groups {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .sakin-group {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .sakin-group:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
        }

        /* Sakin Header */
        .sakin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            cursor: pointer;
            background: var(--card-background);
            transition: background-color 0.3s ease;
        }

        .sakin-header:hover {
            background: var(--hover-color);
        }

        .sakin-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sakin-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sakin-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }

        .sakin-apartment {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .sakin-summary {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .amount-summary {
            text-align: right;
        }

        .total-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .pending-info, .paid-info {
            margin-top: 4px;
        }

        .expand-icon {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        /* Sakin Aidatları */
        .sakin-aidatlar {
            border-top: 1px solid var(--border-color);
            background: var(--background-color);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 500px;
            }
        }

        /* Aidat Items */
        .aidat-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s ease;
        }

        .aidat-item:last-child {
            border-bottom: none;
        }

        .aidat-item:hover {
            background: var(--hover-color);
        }

        .aidat-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .aidat-details {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .aidat-period {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .period-text {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 15px;
        }

        .amount-text {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .aidat-status {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .aidat-actions {
            display: flex;
            gap: 8px;
        }

        /* Badge Stilleri */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* Button Stilleri */
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-ghost:hover {
            background: var(--background-color);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
            border: none;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
            border: none;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            margin-bottom: 16px;
        }

        /* Modal stilleri */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 24px;
        }

        /* Alert stilleri */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fed7aa;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        /* Form stilleri */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .grid-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 28px;
            }
            
            .stat-card.clickable-stat:hover {
                transform: translateY(-2px);
            }

            .site-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .site-card {
                padding: 20px;
            }

            .site-card-stats {
                gap: 16px;
            }

            .sakin-header {
                padding: 16px;
            }

            .sakin-main {
                gap: 8px;
            }

            .sakin-name {
                font-size: 14px;
            }

            .sakin-apartment {
                font-size: 12px;
            }

            .sakin-summary {
                gap: 8px;
            }

            .total-amount {
                font-size: 16px;
            }

            .aidat-item {
                padding: 12px 16px;
            }

            .aidat-content {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .aidat-details {
                justify-content: space-between;
                gap: 16px;
            }

            .aidat-actions {
                justify-content: flex-end;
            }

            .amount-text {
                font-size: 16px;
            }

            .checkbox-label {
                padding: 8px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .topbar-actions {
                gap: 8px;
            }

            .search-input {
                width: 150px;
            }
        }

        /* Responsive improvements for mobile */
        @media (max-width: 768px) {
            .stat-card.clickable:hover {
                transform: translateY(-2px);
            }
            
            .stat-card.clickable.active {
                transform: translateY(-1px);
            }
            
            .stat-card.clickable::after {
                display: none;
            }
        }

        /* Focus styles for accessibility */
        .stat-card.clickable:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        .stat-card.clickable:focus:not(:focus-visible) {
            outline: none;
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .stat-card.clickable,
            .site-stats .stat-item {
                animation: none;
                transition: none;
            }
            
            .stat-card.clickable:hover {
                transform: none;
            }
        }
    </style>
</body>
</html>