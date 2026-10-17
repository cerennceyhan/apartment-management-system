<?php
// pages/daireler.php - Daire yönetimi sayfası (güncellenmiş)
$page_title = 'Daireler';
session_start();

define('IN_PAGES', true);
define('APP_LOADED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions.php';
require_once __DIR__ . '/../../src/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Daire görüntüleme yetkisi kontrolü
if (!hasPermission('daireler.view')) {
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

// Daire tipi metinleri için fonksiyon
function getDaireTipiText($oda_sayisi) {
    switch($oda_sayisi) {
        case 0: return 'Studio (1+0)';
        case 1: return '1+1';
        case 2: return '2+1';
        case 3: return '3+1';
        case 4: return '4+1';
        case 5: return '5+1';
        default: return $oda_sayisi . '+1';
    }
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

// Daire ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_daire'])) {
    if (!hasPermission('daireler.add')) {
        showAlert('Daire ekleme yetkiniz yok', 'error');
    } else {
        $site_id = (int)$_POST['site_id'];
        $blok_id = (int)$_POST['blok_id'];
        $daire_no = trim($_POST['daire_no']);
        $kat = (int)$_POST['kat'];
        $oda_sayisi = (int)$_POST['oda_sayisi'];
        $metrekare = floatval($_POST['metrekare']);
        
        try {
            // Aynı daire numarası kontrol et
            $stmt = $db->prepare("SELECT id FROM daireler WHERE site_id = ? AND blok_id = ? AND daire_no = ? AND status = 1");
            $stmt->execute([$site_id, $blok_id, $daire_no]);
            
            if ($stmt->fetch()) {
                showAlert('Bu daire numarası zaten mevcut', 'error');
            } else {
                $stmt = $db->prepare("INSERT INTO daireler (site_id, blok_id, daire_no, kat, oda_sayisi, metrekare) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$site_id, $blok_id, $daire_no, $kat, $oda_sayisi, $metrekare]);
                
                showAlert('Daire başarıyla eklendi', 'success');
            }
        } catch (Exception $e) {
            showAlert('Daire eklenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Daire düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_daire'])) {
    if (!hasPermission('daireler.edit')) {
        showAlert('Daire düzenleme yetkiniz yok', 'error');
    } else {
        $daire_id = (int)$_POST['daire_id'];
        $daire_no = trim($_POST['daire_no']);
        $kat = (int)$_POST['kat'];
        $oda_sayisi = (int)$_POST['oda_sayisi'];
        $metrekare = floatval($_POST['metrekare']);
        
        try {
            $stmt = $db->prepare("UPDATE daireler SET daire_no = ?, kat = ?, oda_sayisi = ?, metrekare = ? WHERE id = ?");
            $stmt->execute([$daire_no, $kat, $oda_sayisi, $metrekare, $daire_id]);
            
            showAlert('Daire bilgileri güncellendi', 'success');
        } catch (Exception $e) {
            showAlert('Daire güncellenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Sakin çıkarma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_resident'])) {
    if (!hasPermission('daireler.remove_resident')) {
        showAlert('Sakin çıkarma yetkiniz yok', 'error');
    } else {
        $assignment_id = (int)$_POST['assignment_id'];
        
        try {
            $stmt = $db->prepare("UPDATE daire_sakinler SET status = 0 WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
            showAlert('Sakin başarıyla çıkarıldı', 'success');
        } catch (Exception $e) {
            showAlert('Sakin çıkarılırken hata oluştu', 'error');
        }
    }
}

// Daireleri ve sakinlerini getir (site/blok filtreli)
$daireler = [];

if ($selected_site_id && $selected_blok_id) {
    try {
        // Daireleri getir
        $stmt = $db->prepare("
            SELECT d.*, b.blok_adi, s.site_adi,
                   COUNT(CASE WHEN ds.sakin_tipi = 'ev_sahibi' AND ds.status = 1 THEN 1 END) as ev_sahibi_sayisi,
                   COUNT(CASE WHEN ds.sakin_tipi = 'kiraci' AND ds.status = 1 THEN 1 END) as kiraci_sayisi
            FROM daireler d
            LEFT JOIN bloklar b ON d.blok_id = b.id
            LEFT JOIN siteler s ON d.site_id = s.id
            LEFT JOIN daire_sakinler ds ON d.id = ds.daire_id AND ds.status = 1
            WHERE d.site_id = ? AND d.blok_id = ? AND d.status = 1
            GROUP BY d.id
            ORDER BY CAST(d.daire_no AS UNSIGNED)
        ");
        $stmt->execute([$selected_site_id, $selected_blok_id]);
        $daireler = $stmt->fetchAll();
        
        // Her daire için sakinleri getir
        foreach ($daireler as &$daire) {
            $stmt = $db->prepare("
                SELECT ds.*, sk.ad_soyad, sk.telefon, sk.email, sk.created_at
                FROM daire_sakinler ds
                JOIN sakinler sk ON ds.sakin_id = sk.id
                WHERE ds.daire_id = ? AND ds.status = 1
                ORDER BY ds.sakin_tipi DESC, sk.ad_soyad
            ");
            $stmt->execute([$daire['id']]);
            $daire['sakinler'] = $stmt->fetchAll();
        }
        
    } catch (Exception $e) {
        $daireler = [];
        showAlert('Daireler yüklenirken hata oluştu', 'error');
    }
}

// Daireler için JavaScript verisi hazırla
$daireler_json = [];
foreach ($daireler as $daire) {
    $daireler_json[] = [
        'id' => $daire['id'],
        'daire_no' => $daire['daire_no'],
        'kat' => $daire['kat'],
        'oda_sayisi' => $daire['oda_sayisi'],
        'metrekare' => $daire['metrekare'],
        'daire_tipi' => getDaireTipiText($daire['oda_sayisi']),
        'sakinler' => $daire['sakinler'] ?? []
    ];
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
         <?php include __DIR__ . '/../../src/views/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="welcome-section">
                    <h1 class="page-title">🏠 Daireler</h1>
                    <p class="page-subtitle">
                        <?php if ($site_info && $blok_info): ?>
                            <?= htmlspecialchars($site_info['site_adi']) ?> - <?= htmlspecialchars($blok_info['blok_adi']) ?>
                        <?php elseif ($site_info): ?>
                            <?= htmlspecialchars($site_info['site_adi']) ?> - Blok seçin
                        <?php else: ?>
                            Site ve blok seçin
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="topbar-actions">
                    <?php if ($selected_site_id && $selected_blok_id): ?>
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Daire ara..." id="daireSearch">
                        <div class="search-icon">🔍</div>
                    </div>
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
                        <?= $alert['message'] ?>
                    </div>
                <?php endif; ?>

                <?php if (!$selected_site_id): ?>
                <!-- Site Seçim Kartları -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🏢 Site Seçin</h3>
                        <p class="card-subtitle">Daireleri görüntülemek için önce bir site seçin</p>
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
                                    // Site için daire sayısını al
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM daireler WHERE site_id = ? AND status = 1");
                                        $stmt->execute([$site['id']]);
                                        $daire_count = $stmt->fetch()['total'] ?? 0;
                                        
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bloklar WHERE site_id = ? AND status = 1");
                                        $stmt->execute([$site['id']]);
                                        $blok_count = $stmt->fetch()['total'] ?? 0;
                                    } catch (Exception $e) {
                                        $daire_count = 0;
                                        $blok_count = 0;
                                    }
                                    ?>
                                    <div class="site-card" onclick="selectSite(<?= $site['id'] ?>)">
                                        <div class="site-card-header">
                                            <div class="site-icon">🏠</div>
                                            <div class="site-card-title"><?= htmlspecialchars($site['site_adi']) ?></div>
                                        </div>
                                        <div class="site-card-stats">
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $daire_count ?></span>
                                                <span class="stat-label">Daire</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $blok_count ?></span>
                                                <span class="stat-label">Blok</span>
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

                <?php elseif (!$selected_blok_id): ?>
                <!-- Blok Seçim Kartları -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🏢 Blok Seçin</h3>
                        <p class="card-subtitle"><?= htmlspecialchars($site_info['site_adi']) ?> sitesinde bir blok seçin</p>
                    </div>
                    <div class="card-body">
                        <div class="breadcrumb">
                            <span onclick="selectSite(null)" style="cursor: pointer; color: var(--primary-color);">🏢 Siteler</span>
                            <span class="breadcrumb-separator">></span>
                            <span><?= htmlspecialchars($site_info['site_adi']) ?></span>
                        </div>
                        
                        <?php if (empty($site_bloklar)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏢</div>
                                <h3>Bu sitede henüz blok eklenmemiş</h3>
                                <p>İlk bloğu eklemek için blok yönetimi sayfasını kullanın.</p>
                                <?php if ($current_user['role'] === 'yonetici'): ?>
                                <a href="bloklar.php?site_id=<?= $selected_site_id ?>" class="btn btn-primary" style="margin-top: 16px;">
                                    ➕ Blok Ekle
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="site-grid">
                                <?php foreach ($site_bloklar as $blok): ?>
                                    <?php
                                    // Blok için daire sayısını al
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM daireler WHERE blok_id = ? AND status = 1");
                                        $stmt->execute([$blok['id']]);
                                        $daire_count = $stmt->fetch()['total'] ?? 0;
                                    } catch (Exception $e) {
                                        $daire_count = 0;
                                    }
                                    ?>
                                    <div class="site-card" onclick="selectBlok(<?= $blok['id'] ?>)">
                                        <div class="site-card-header">
                                            <div class="site-icon">🏠</div>
                                            <div class="site-card-title"><?= htmlspecialchars($blok['blok_adi']) ?></div>
                                        </div>
                                        <div class="site-card-stats">
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $daire_count ?></span>
                                                <span class="stat-label">Daire</span>
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
                <!-- Stats Cards - daireler.php'deki mevcut kısmı bu şekilde değiştirin -->
                <div class="stats-grid">
                    <?php
                    $toplam_daire = count($daireler);
                    $dolu_daire = count(array_filter($daireler, fn($d) => !empty($d['sakinler'])));
                    $bos_daire = $toplam_daire - $dolu_daire;
                    $ev_sahibi_toplam = array_sum(array_column($daireler, 'ev_sahibi_sayisi'));
                    $kiraci_toplam = array_sum(array_column($daireler, 'kiraci_sayisi'));
                    ?>
                    
                    <div class="stat-card primary clickable" onclick="filterDaireler('all')" id="totalCard" title="Tüm daireleri göster">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Daire</div>
                        </div>
                        <div class="stat-value"><?= $toplam_daire ?></div>
                        <div class="stat-change"><?= htmlspecialchars($blok_info['blok_adi']) ?></div>
                    </div>
                    
                    <div class="stat-card success clickable" onclick="filterDaireler('dolu')" id="doluCard" title="Sadece dolu daireleri göster">
                        <div class="stat-header">
                            <div class="stat-title">Dolu Daireler</div>
                        </div>
                        <div class="stat-value"><?= $dolu_daire ?></div>
                        <div class="stat-change positive"><?= $toplam_daire > 0 ? number_format(($dolu_daire / $toplam_daire) * 100, 1) : 0 ?>% doluluk</div>
                    </div>
                    
                    <div class="stat-card warning clickable" onclick="filterDaireler('bos')" id="bosCard" title="Sadece boş daireleri göster">
                        <div class="stat-header">
                            <div class="stat-title">Boş Daireler</div>
                        </div>
                        <div class="stat-value"><?= $bos_daire ?></div>
                        <div class="stat-change">Atanmamış daireler</div>
                    </div>
                    
                    <div class="stat-card info clickable" onclick="goToSakinler()" id="sakinCard" title="Sakinler sayfasına git">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Sakin</div>
                        </div>
                        <div class="stat-value"><?= $ev_sahibi_toplam + $kiraci_toplam ?></div>
                        <div class="stat-change"><?= $ev_sahibi_toplam ?> ev sahibi, <?= $kiraci_toplam ?> kiracı</div>
                    </div>
                </div>

                <!-- Daireler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daire Listesi</h3>
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
                            
                            <!-- Blok Seçim Dropdown'u -->
                            <?php if (!empty($site_bloklar)): ?>
                            <select class="form-select" onchange="changeBlok(this.value)" title="Blok Seç">
                                <option value="">Blok Seç</option>
                                <?php foreach ($site_bloklar as $blok): ?>
                                    <option value="<?= $blok['id'] ?>" <?= $blok['id'] == $selected_blok_id ? 'selected' : '' ?>>
                                        🏢 <?= htmlspecialchars($blok['blok_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>

                            <?php if (hasPermission('daireler.add') && $selected_site_id && $selected_blok_id): ?>
                            <button class="btn btn-primary" onclick="openModal('addDaireModal')">
                                ➕ Yeni Daire
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="breadcrumb" style="margin-bottom: 20px;">
                            <span onclick="selectSite(null)" style="cursor: pointer; color: var(--primary-color);">🏢 Siteler</span>
                            <span class="breadcrumb-separator">></span>
                            <span onclick="selectSite(<?= $selected_site_id ?>)" style="cursor: pointer; color: var(--primary-color);"><?= htmlspecialchars($site_info['site_adi']) ?></span>
                            <span class="breadcrumb-separator">></span>
                            <span><?= htmlspecialchars($blok_info['blok_adi']) ?></span>
                        </div>

                        <?php if (empty($daireler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏠</div>
                                <h3>Bu blokta henüz daire eklenmemiş</h3>
                                <p>İlk daireyi eklemek için yukarıdaki butonu kullanın.</p>
                            </div>
                        <?php else: ?>
                            <div class="daire-grid">
                                <?php foreach ($daireler as $index => $daire): ?>
                                    <div class="daire-card" data-search="<?= strtolower($daire['daire_no']) ?>" data-daire-index="<?= $index ?>">
                                        <div class="daire-header">
                                            <div class="daire-main">
                                                <div class="daire-icon">
                                                    <?= empty($daire['sakinler']) ? '🏠' : '🏡' ?>
                                                </div>
                                                <div class="daire-info">
                                                    <div class="daire-number">Daire <?= htmlspecialchars($daire['daire_no']) ?></div>
                                                    <div class="daire-type">
                                                        <?= $daire['kat'] ?>. Kat - <?= getDaireTipiText($daire['oda_sayisi']) ?>
                                                        <?php if ($daire['metrekare']): ?>
                                                            - <?= number_format($daire['metrekare'], 0) ?>m²
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <!-- Sadece Aksiyon Butonları -->
                                                <div class="daire-action-buttons">
                                                    <?php if (hasPermission('daireler.edit')): ?>
                                                    <button class="btn-icon btn-info" 
                                                            onclick="event.stopPropagation(); editDaire(<?= htmlspecialchars(json_encode($daire)) ?>)"
                                                            title="Düzenle">
                                                        ✏️
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($daire['sakinler'])): ?>
                                                        <?php foreach ($daire['sakinler'] as $sakin): ?>
                                                            <?php if (hasPermission('daireler.remove_resident')): ?>
                                                            <button class="btn-icon btn-danger" 
                                                                    onclick="event.stopPropagation(); removeResident(<?= $sakin['id'] ?>, '<?= htmlspecialchars($sakin['ad_soyad']) ?>', '<?= $daire['daire_no'] ?>')"
                                                                    title="<?= htmlspecialchars($sakin['ad_soyad']) ?> - Çıkar">
                                                                🗑️
                                                            </button>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Daire Aksiyonları -->
                                            <?php if (hasPermission('daireler.assign_owner') || hasPermission('daireler.assign_tenant')): ?>
                                            <div class="daire-actions">
                                                <?php if (hasPermission('daireler.assign_owner') && $daire['ev_sahibi_sayisi'] == 0): ?>
                                                <button class="btn btn-primary btn-sm" 
                                                        onclick="event.stopPropagation(); assignOwner(<?= $daire['id'] ?>)">
                                                    🏠 Ev Sahibi Ata
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if (hasPermission('daireler.assign_tenant')): ?>
                                                <button class="btn btn-info btn-sm" 
                                                        onclick="event.stopPropagation(); assignTenant(<?= $daire['id'] ?>)">
                                                    🔑 Kiracı Ata
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
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

    <!-- Dynamic Assignment Modal -->
    <div class="modal" id="assignmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="assignmentTitle">Atama</h3>
                <button class="modal-close" onclick="closeAssignModal()">×</button>
            </div>
            <div class="modal-body" id="assignmentContent">
                <div class="loading">Yükleniyor...</div>
            </div>
        </div>
    </div>

    <!-- Remove Resident Modal -->
    <div class="modal" id="removeResidentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Sakin Çıkar</h3>
                <button class="modal-close" onclick="closeModal('removeResidentModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="removeMessage">Bu sakini daireden çıkarmak istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz.
                </div>
                
                <form method="POST" id="removeResidentForm">
                    <input type="hidden" name="assignment_id" id="removeAssignmentId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('removeResidentModal')">
                            İptal
                        </button>
                        <button type="submit" name="remove_resident" class="btn btn-danger">
                            🗑️ Evet, Çıkar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Daire Modal -->
    <?php if (hasPermission('daireler.add') && $selected_site_id && $selected_blok_id): ?>
    <div class="modal" id="addDaireModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">➕ Yeni Daire Ekle</h3>
                <button class="modal-close" onclick="closeModal('addDaireModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addDaireForm">
                    <input type="hidden" name="site_id" value="<?= $selected_site_id ?>">
                    <input type="hidden" name="blok_id" value="<?= $selected_blok_id ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Daire No *</label>
                            <input type="text" name="daire_no" class="form-input" required placeholder="Örn: 1, 2A, 101">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kat *</label>
                            <input type="number" name="kat" class="form-input" min="0" max="50" required placeholder="Örn: 1">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Daire Tipi *</label>
                            <select name="oda_sayisi" class="form-select" required onchange="updateMetrekareSuggestion(this.value)">
                                <option value="">Seçin...</option>
                                <option value="0">Studio (1+0)</option>
                                <option value="1">1+1</option>
                                <option value="2">2+1</option>
                                <option value="3">3+1</option>
                                <option value="4">4+1</option>
                                <option value="5">5+1</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Metrekare</label>
                            <input type="number" name="metrekare" class="form-input" min="20" max="500" step="0.1" placeholder="Örn: 120">
                            <small class="form-hint" id="metrekareHint">Daire tipine göre önerilen değer gösterilecek</small>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addDaireModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_daire" class="btn btn-primary">
                            ➕ Daire Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Daire Modal -->
    <?php if (hasPermission('daireler.edit')): ?>
    <div class="modal" id="editDaireModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Daire Düzenle</h3>
                <button class="modal-close" onclick="closeModal('editDaireModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editDaireForm">
                    <input type="hidden" name="daire_id" id="editDaireId">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Daire No *</label>
                            <input type="text" name="daire_no" id="editDaireNo" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kat *</label>
                            <input type="number" name="kat" id="editKat" class="form-input" min="0" max="50" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Daire Tipi *</label>
                            <select name="oda_sayisi" id="editOdaSayisi" class="form-select" required onchange="updateEditMetrekareSuggestion(this.value)">
                                <option value="">Seçin...</option>
                                <option value="0">Studio (1+0)</option>
                                <option value="1">1+1</option>
                                <option value="2">2+1</option>
                                <option value="3">3+1</option>
                                <option value="4">4+1</option>
                                <option value="5">5+1</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Metrekare</label>
                            <input type="number" name="metrekare" id="editMetrekare" class="form-input" min="20" max="500" step="0.1">
                            <small class="form-hint" id="editMetrekareHint">Daire tipine göre önerilen değer gösterilecek</small>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('editDaireModal')">
                            İptal
                        </button>
                        <button type="submit" name="edit_daire" class="btn btn-success">
                            ✏️ Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Daire Detay Modal -->
    <div class="modal" id="daireDetayModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title" id="daireDetayTitle">📋 Daire Detayları</h3>
                <button class="modal-close" onclick="closeModal('daireDetayModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="daire-detay-container">
                    <!-- Daire Bilgileri -->
                    <div class="daire-info-section">
                        <div class="info-card">
                            <div class="info-header">
                                <div class="info-icon">🏠</div>
                                <div class="info-title">Daire Bilgileri</div>
                            </div>
                            <div class="info-content">
                                <div class="info-item">
                                    <span class="info-label">Daire No:</span>
                                    <span class="info-value" id="detayDaireNo">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Kat:</span>
                                    <span class="info-value" id="detayKat">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Daire Tipi:</span>
                                    <span class="info-value" id="detayDaireTipi">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Metrekare:</span>
                                    <span class="info-value" id="detayMetrekare">-</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Durum:</span>
                                    <span class="info-value" id="detayDurum">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sakinler Listesi -->
                    <div class="sakinler-section">
                        <div class="info-card">
                            <div class="info-header">
                                <div class="info-icon">👥</div>
                                <div class="info-title">Sakinler</div>
                                <div class="sakin-sayisi" id="sakinSayisi">0 Sakin</div>
                            </div>
                            <div class="sakinler-list" id="sakinlerList">
                                <div class="empty-sakinler">
                                    <div class="empty-icon">👤</div>
                                    <p>Bu dairede henüz sakin bulunmuyor</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('daireDetayModal')">
                    Kapat
                </button>
            </div>
        </div>
    </div>

    <!-- Sakin Detay Modal -->
    <div class="modal" id="sakinDetayModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">👤 Sakin Detayları</h3>
                <button class="modal-close" onclick="closeModal('sakinDetayModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="sakin-detay-container">
                    <div class="sakin-avatar-large" id="sakinAvatarLarge">
                        AK
                    </div>
                    <div class="sakin-bilgileri">
                        <h4 id="sakinAdSoyad">-</h4>
                        <span class="sakin-tip-badge" id="sakinTipBadge">-</span>
                    </div>
                </div>
                
                <div class="sakin-info-grid">
                    <div class="sakin-info-item">
                        <div class="sakin-info-label">📞 Telefon</div>
                        <div class="sakin-info-value" id="sakinTelefon">-</div>
                    </div>
                    <div class="sakin-info-item">
                        <div class="sakin-info-label">📧 E-mail</div>
                        <div class="sakin-info-value" id="sakinEmail">-</div>
                    </div>
                    <div class="sakin-info-item">
                        <div class="sakin-info-label">🏠 Daire</div>
                        <div class="sakin-info-value" id="sakinDaire">-</div>
                    </div>
                    <div class="sakin-info-item">
                        <div class="sakin-info-label">📅 Kayıt Tarihi</div>
                        <div class="sakin-info-value" id="sakinKayitTarihi">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeModal('sakinDetayModal')">
                    Kapat
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
        // Daire verilerini JavaScript'e aktar
        window.daireVerileri = <?= json_encode($daireler_json, JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Metrekare önerileri
        const metrekareOneri = {
            0: 45,  // Studio
            1: 65,  // 1+1
            2: 90,  // 2+1
            3: 120, // 3+1
            4: 150, // 4+1
            5: 180  // 5+1
        };
        

        // Aktif filtre durumu
let activeFilter = 'all';

// Daireleri filtrele
function filterDaireler(filterType) {
    const daireCards = document.querySelectorAll('.daire-card');
    const statCards = document.querySelectorAll('.stat-card');
    
    // Eski aktif kartı temizle
    statCards.forEach(card => card.classList.remove('active'));
    
    // Yeni aktif kartı işaretle
    let activeCardId = '';
    switch(filterType) {
        case 'all':
            activeCardId = 'totalCard';
            break;
        case 'dolu':
            activeCardId = 'doluCard';
            break;
        case 'bos':
            activeCardId = 'bosCard';
            break;
        case 'sakin':
            activeCardId = 'sakinCard';
            break;
    }
    
    if (activeCardId) {
        document.getElementById(activeCardId).classList.add('active');
    }
    
    activeFilter = filterType;
    
    // Daireleri filtrele
    daireCards.forEach(card => {
        const daireIndex = parseInt(card.dataset.daireIndex);
        const daireData = window.daireVerileri[daireIndex];
        
        let shouldShow = false;
        
        switch(filterType) {
            case 'all':
                shouldShow = true;
                break;
            case 'dolu':
                shouldShow = daireData.sakinler && daireData.sakinler.length > 0;
                break;
            case 'bos':
                shouldShow = !daireData.sakinler || daireData.sakinler.length === 0;
                break;
        }
        
        if (shouldShow) {
            card.style.display = '';
            card.classList.add('filter-show');
        } else {
            card.style.display = 'none';
            card.classList.remove('filter-show');
        }
    });
    
    // Filtreleme durumunu göster
    updateFilterStatus(filterType);
}

    // Sakinler sayfasına git
    function goToSakinler() {
        const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
        const currentBlokId = <?= $selected_blok_id ?? 'null' ?>;
        
        // Sakin kartını aktif yap
        document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
        document.getElementById('sakinCard').classList.add('active');
        
        // Kısa bir gecikme sonrası yönlendir (görsel feedback için)
        setTimeout(() => {
            if (currentSiteId && currentBlokId) {
                window.location.href = `sakinler.php?site_id=${currentSiteId}&blok_id=${currentBlokId}`;
            } else {
                window.location.href = 'sakinler.php';
            }
        }, 200);
    }

    // Filtre durumunu güncelle
    function updateFilterStatus(filterType) {
        const cardTitle = document.querySelector('.card-title');
        const totalCards = document.querySelectorAll('.daire-card').length;
        const visibleCards = document.querySelectorAll('.daire-card[style=""], .daire-card:not([style*="none"])').length;
        
        let statusText = '';
        switch(filterType) {
            case 'all':
                statusText = 'Tüm Daireler';
                break;
            case 'dolu':
                statusText = `Dolu Daireler (${visibleCards} adet)`;
                break;
            case 'bos':
                statusText = `Boş Daireler (${visibleCards} adet)`;
                break;
        }
        
        if (cardTitle) {
            cardTitle.innerHTML = `Daire Listesi ${statusText ? '- ' + statusText : ''}`;
        }
    }

    // Arama fonksiyonunu güncelle (mevcut filtreyi korumak için)
    function setupDaireSearch() {
        const searchInput = document.getElementById('daireSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const daireCards = document.querySelectorAll('.daire-card');
                
                daireCards.forEach(card => {
                    const searchData = card.dataset.search;
                    const passesSearch = searchData.includes(searchTerm);
                    const passesFilter = card.classList.contains('filter-show') || activeFilter === 'all';
                    
                    if (passesSearch && (passesFilter || activeFilter === 'all')) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
    }

    // Sayfa yüklendiğinde filtreleme sistemi hazırla
    document.addEventListener('DOMContentLoaded', function() {
        // İlk durumda tüm daireleri göster
        filterDaireler('all');
        
        // Arama sistemini kur
        setupDaireSearch();
        
        // Stat kartlarına hover efekti ekle
        document.querySelectorAll('.stat-card.clickable').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(-4px)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('active')) {
                    this.style.transform = 'translateY(0)';
                }
            });
        });
    });
        // Site seçim fonksiyonu
        function selectSite(siteId) {
            if (siteId === null) {
                window.location.href = 'daireler.php';
            } else {
                window.location.href = `daireler.php?site_id=${siteId}`;
            }
        }

        // Blok seçim fonksiyonu
        function selectBlok(blokId) {
            const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
            if (currentSiteId) {
                window.location.href = `daireler.php?site_id=${currentSiteId}&blok_id=${blokId}`;
            }
        }

        // Site değiştirme fonksiyonu
        function changeSite(siteId) {
            if (!siteId) return;
            window.location.href = `daireler.php?site_id=${siteId}`;
        }

        // Blok değiştirme fonksiyonu
        function changeBlok(blokId) {
            const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
            if (currentSiteId) {
                if (blokId) {
                    window.location.href = `daireler.php?site_id=${currentSiteId}&blok_id=${blokId}`;
                } else {
                    window.location.href = `daireler.php?site_id=${currentSiteId}`;
                }
            }
        }

        // Ev sahibi atama - YENİ AJAX VERSİYON
        function assignOwner(daireId) {
            document.getElementById('assignmentTitle').textContent = '🏠 Ev Sahibi Ata';
            document.getElementById('assignmentContent').innerHTML = '<div class="loading">Yükleniyor...</div>';
            openModal('assignmentModal');
            
            fetch(`../ajax/assign_owner.php?daire_id=${daireId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('assignmentContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Hata:', error);
                    document.getElementById('assignmentContent').innerHTML = '<div class="alert alert-error">Yükleme hatası oluştu.</div>';
                });
        }

        // Kiracı atama - YENİ AJAX VERSİYON (daireler.php içinde güncelleme)
        function assignTenant(daireId) {
            document.getElementById('assignmentTitle').textContent = '🔑 Kiracı Ata';
            document.getElementById('assignmentContent').innerHTML = '<div class="loading">Yükleniyor...</div>';
            openModal('assignmentModal');
            
            fetch(`../ajax/assign_tenant.php?daire_id=${daireId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('assignmentContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Hata:', error);
                    document.getElementById('assignmentContent').innerHTML = '<div class="alert alert-error">Yükleme hatası oluştu.</div>';
                });
        }

        // Global alert fonksiyonu - assign_owner.php'den çağırılabilir
        function showAlert(message, type) {
            // Eğer alert container yoksa oluştur
            let alertContainer = document.querySelector('.alert-container');
            if (!alertContainer) {
                alertContainer = document.createElement('div');
                alertContainer.className = 'alert-container';
                document.body.appendChild(alertContainer);
            }
            
            // Alert elementi oluştur
            const alertElement = document.createElement('div');
            alertElement.className = `alert alert-${type} alert-show`;
            alertElement.innerHTML = `
                <span>${message}</span>
                <button class="alert-close" onclick="this.parentElement.remove()">×</button>
            `;
            
            alertContainer.appendChild(alertElement);
            
            // 5 saniye sonra otomatik kaldır
            setTimeout(() => {
                if (alertElement.parentElement) {
                    alertElement.remove();
                }
            }, 5000);
        }

        // Kiracı atama - YENİ AJAX VERSİYON
        function assignTenant(daireId) {
            document.getElementById('assignmentTitle').textContent = '🔑 Kiracı Ata';
            document.getElementById('assignmentContent').innerHTML = '<div class="loading">Yükleniyor...</div>';
            openModal('assignmentModal');
            
            fetch(`../ajax/assign_tenant.php?daire_id=${daireId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('assignmentContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Hata:', error);
                    document.getElementById('assignmentContent').innerHTML = '<div class="alert alert-error">Yükleme hatası oluştu.</div>';
                });
        }

        // Tab değiştirme fonksiyonu - assign_owner.php için
        function switchTab(tabName) {
            // Tab butonlarını güncelle
            document.querySelectorAll('.assign-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            
            // Tab içeriklerini güncelle
            document.querySelectorAll('.assign-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        // Assignment modal kapatma - Include dosyalarından çağırılabilir
        function closeAssignModal() {
            closeModal('assignmentModal');
        }

        // Sakin çıkarma
        function removeResident(assignmentId, sakinAdi, daireNo) {
            document.getElementById('removeAssignmentId').value = assignmentId;
            document.getElementById('removeMessage').innerHTML = 
                `<strong>${sakinAdi}</strong> adlı sakini <strong>Daire ${daireNo}</strong>'dan çıkarmak istediğinizden emin misiniz?`;
            openModal('removeResidentModal');
        }

        // Metrekare önerisi güncelleme (yeni daire)
        function updateMetrekareSuggestion(odaSayisi) {
            const metrekareInput = document.querySelector('input[name="metrekare"]');
            const hintElement = document.getElementById('metrekareHint');
            
            if (odaSayisi && metrekareOneri[odaSayisi]) {
                const oneri = metrekareOneri[odaSayisi];
                hintElement.textContent = `Önerilen: ${oneri}m²`;
                if (!metrekareInput.value) {
                    metrekareInput.value = oneri;
                }
            } else {
                hintElement.textContent = 'Daire tipine göre önerilen değer gösterilecek';
            }
        }
        
        // Metrekare önerisi güncelleme (düzenleme)
        function updateEditMetrekareSuggestion(odaSayisi) {
            const metrekareInput = document.getElementById('editMetrekare');
            const hintElement = document.getElementById('editMetrekareHint');
            
            if (odaSayisi && metrekareOneri[odaSayisi]) {
                const oneri = metrekareOneri[odaSayisi];
                hintElement.textContent = `Önerilen: ${oneri}m²`;
            } else {
                hintElement.textContent = 'Daire tipine göre önerilen değer gösterilecek';
            }
        }

        // Daire düzenleme
        function editDaire(daireData) {
            document.getElementById('editDaireId').value = daireData.id;
            document.getElementById('editDaireNo').value = daireData.daire_no;
            document.getElementById('editKat').value = daireData.kat;
            document.getElementById('editOdaSayisi').value = daireData.oda_sayisi;
            document.getElementById('editMetrekare').value = daireData.metrekare || '';
            
            // Metrekare önerisini güncelle
            updateEditMetrekareSuggestion(daireData.oda_sayisi);
            
            openModal('editDaireModal');
        }

        // Daire detayını göster
        function showDaireDetay(daireData) {
            
            // Daire bilgilerini doldur
            document.getElementById('daireDetayTitle').textContent = `📋 Daire ${daireData.daire_no} Detayları`;
            document.getElementById('detayDaireNo').textContent = daireData.daire_no;
            document.getElementById('detayKat').textContent = daireData.kat + '. Kat';
            document.getElementById('detayDaireTipi').textContent = daireData.daire_tipi;
            document.getElementById('detayMetrekare').textContent = daireData.metrekare ? 
                parseFloat(daireData.metrekare).toFixed(0) + 'm²' : 'Belirtilmemiş';
            
            // Durum belirle
            const durum = daireData.sakinler && daireData.sakinler.length > 0 ? 'Dolu' : 'Boş';
            const durumBadge = durum === 'Dolu' ? 
                '<span class="badge badge-success">🏡 Dolu</span>' : 
                '<span class="badge badge-warning">🏠 Boş</span>';
            document.getElementById('detayDurum').innerHTML = durumBadge;
            
            // Sakin sayısını güncelle
            const sakinSayisi = daireData.sakinler ? daireData.sakinler.length : 0;
            document.getElementById('sakinSayisi').textContent = `${sakinSayisi} Sakin`;
            
            // Sakinler listesini doldur
            const sakinlerList = document.getElementById('sakinlerList');
            if (sakinSayisi === 0) {
                sakinlerList.innerHTML = `
                    <div class="empty-sakinler">
                        <div class="empty-icon">👤</div>
                        <p>Bu dairede henüz sakin bulunmuyor</p>
                    </div>
                `;
            } else {
                let sakinlerHTML = '';
                daireData.sakinler.forEach(sakin => {
                    const tipBadge = sakin.sakin_tipi === 'ev_sahibi' ? 
                        '<span class="badge badge-primary">🏠 Ev Sahibi</span>' : 
                        '<span class="badge badge-info">🔑 Kiracı</span>';
                    
                    sakinlerHTML += `
                        <div class="sakin-item-detay" onclick="showSakinDetay(${JSON.stringify(sakin).replace(/"/g, '&quot;')}, '${daireData.daire_no}')">
                            <div class="sakin-avatar-detay">
                                ${sakin.ad_soyad.substring(0, 2).toUpperCase()}
                            </div>
                            <div class="sakin-info-detay">
                                <div class="sakin-name-detay">${sakin.ad_soyad}</div>
                                <div class="sakin-type-detay">${tipBadge}</div>
                            </div>
                        </div>
                    `;
                });
                sakinlerList.innerHTML = sakinlerHTML;
            }
            
            openModal('daireDetayModal');
        }

        // Sakin detayını göster
        function showSakinDetay(sakinData, daireNo) {
            // Avatar harflerini ayarla
            const avatarText = sakinData.ad_soyad.substring(0, 2).toUpperCase();
            document.getElementById('sakinAvatarLarge').textContent = avatarText;
            
            // Sakin bilgilerini doldur
            document.getElementById('sakinAdSoyad').textContent = sakinData.ad_soyad;
            
            // Tip badge'ini ayarla
            const tipBadge = document.getElementById('sakinTipBadge');
            if (sakinData.sakin_tipi === 'ev_sahibi') {
                tipBadge.textContent = '🏠 Ev Sahibi';
                tipBadge.className = 'sakin-tip-badge ev-sahibi';
            } else {
                tipBadge.textContent = '🔑 Kiracı';
                tipBadge.className = 'sakin-tip-badge kiraci';
            }
            
            // İletişim bilgilerini doldur
            document.getElementById('sakinTelefon').textContent = sakinData.telefon || 'Belirtilmemiş';
            document.getElementById('sakinEmail').textContent = sakinData.email || 'Belirtilmemiş';
            document.getElementById('sakinDaire').textContent = `Daire ${daireNo}`;
            
            // Kayıt tarihi (eğer varsa)
            const kayitTarihi = sakinData.created_at ? 
                new Date(sakinData.created_at).toLocaleDateString('tr-TR') : 
                'Belirtilmemiş';
            document.getElementById('sakinKayitTarihi').textContent = kayitTarihi;
            
            openModal('sakinDetayModal');
        }

        // Daire arama
        const searchInput = document.getElementById('daireSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const daireCards = document.querySelectorAll('.daire-card');
                
                daireCards.forEach(card => {
                    const searchData = card.dataset.search;
                    if (searchData.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
        
        // Modal açma fonksiyonu
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
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
        
        // Daire kartına tıklama event'i ekle
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.daire-card').forEach((card, index) => {
                card.addEventListener('click', function(e) {
                    // Eğer tıklanan element bir buton ise, daire detayını açma
                    if (e.target.closest('button') || e.target.closest('.btn-icon')) {
                        return;
                    }
                    
                    const daireData = window.daireVerileri[index];
                    if (daireData) {
                        showDaireDetay(daireData);
                    }
                });
            });
        });
        
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
    </script>
    
    <style>
        /* Dinamik Stat Kartları için CSS stilleri - daireler.php style bölümüne ekleyin */

        /* Clickable stat cards */
        .stat-card.clickable {
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            user-select: none;
        }

        .stat-card.clickable::before {
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

        .stat-card.clickable:hover::before {
            opacity: 1;
        }

        .stat-card.clickable:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* Active state for stat cards */
        .stat-card.clickable.active {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            border: 2px solid transparent;
        }

        .stat-card.primary.clickable.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(37, 99, 235, 0.05));
        }

        .stat-card.success.clickable.active {
            border-color: var(--success-color);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
        }

        .stat-card.warning.clickable.active {
            border-color: var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
        }

        .stat-card.info.clickable.active {
            border-color: #0891b2;
            background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(8, 145, 178, 0.05));
        }

        /* Click animation */
        .stat-card.clickable:active {
            transform: translateY(-1px);
            transition: transform 0.1s ease;
        }

        /* Filtered daire cards animation */
        .daire-card.filter-show {
            animation: filterShow 0.3s ease-out;
        }

        @keyframes filterShow {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Enhanced hover effects for clickable cards */
        .stat-card.clickable:hover .stat-value {
            color: var(--text-primary);
            font-weight: 700;
        }

        .stat-card.primary.clickable:hover .stat-value {
            color: var(--primary-color);
        }

        .stat-card.success.clickable:hover .stat-value {
            color: var(--success-color);
        }

        .stat-card.warning.clickable:hover .stat-value {
            color: var(--warning-color);
        }

        .stat-card.info.clickable:hover .stat-value {
            color: #0891b2;
        }

        /* Click indicator */
        .stat-card.clickable::after {
            content: '👆';
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card.clickable:hover::after {
            opacity: 0.6;
        }

        .stat-card.clickable.active::after {
            content: '✨';
            opacity: 1;
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

        /* Loading animation for sakin redirect */
        .stat-card.info.clickable.loading {
            position: relative;
            pointer-events: none;
        }

        .stat-card.info.clickable.loading::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            border-radius: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card.info.clickable.loading::after {
            content: '⏳';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Enhanced card title for filter status */
        .card-title.filter-active {
            color: var(--primary-color);
            font-weight: 600;
        }
        /* Aksiyon butonları için */
        .daire-action-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: auto;
        }

        /* Önceki inline resident stilleri kaldırılabilir */
        .daire-residents-inline,
        .resident-inline,
        .resident-avatar-small,
        .resident-name-small,
        .badge-small,
        .no-residents-inline,
        .btn-icon-tiny,
        .resident-actions-inline {
            display: none;
        }
        /* Yeni inline residents stilleri */
        .daire-residents-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-left: auto;
            max-width: 400px;
        }

        .resident-inline {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--background-color);
            border-radius: 20px;
            padding: 6px 10px;
            border: 1px solid var(--border-color);
            font-size: 12px;
        }

        .resident-avatar-small {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 10px;
            flex-shrink: 0;
        }

        .resident-name-small {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-small {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-owner {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .badge-tenant {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .no-residents-inline {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 12px;
        }

        .btn-icon-tiny {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 10px;
        }

        .btn-icon-tiny.btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .btn-icon-tiny.btn-danger:hover {
            background: var(--danger-color);
            color: white;
        }

        .resident-actions-inline {
            display: flex;
            gap: 2px;
        }
        /* Loading stili */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            font-style: italic;
        }
        
        /* Modal Large */
        .modal-large {
            max-width: 800px;
            width: 90%;
        }
        
        /* Alert Error */
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 0;
        }

        /* Alert Container - Floating alerts için */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }

        .alert-show {
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-close {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            margin-left: 8px;
            font-size: 16px;
            font-weight: bold;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .form-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
            display: block;
        }

        .btn-icon.btn-info {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
        }

        .btn-icon.btn-info:hover {
            background: #0891b2;
            color: white;
        }

        @media (max-width: 968px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        /* Daire Grid - Liste formatında */
        .daire-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-top: 20px;
        }

        /* Daire Card - Liste item şeklinde */
        .daire-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            padding: 0;
            min-height: 80px;
            cursor: pointer;
        }

        .daire-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
            transform: translateX(4px);
        }

        /* Daire Header - Sol tarafta */
        .daire-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 4px;
            padding-bottom: 4px;
            padding-left: 20px;    /* Bu satırı ekle */
            padding-right: 20px;   /* Bu satırı ekle */
            background: var(--card-background);
            border-bottom: none;
            flex: 1;
            min-width: 0;
        }

        .daire-main {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }

        .daire-icon {
            font-size: 24px;
            width: 48px;
            height: 48px;
            background: var(--background-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border-color);
            flex-shrink: 0;
        }

        .daire-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
            flex: 1;
        }

        .daire-number {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
            white-space: nowrap;
        }

        .daire-type {
            font-size: 14px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .daire-status .badge {
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }

        /* Residents Section - Orta bölümde */
        .daire-residents {
            padding: 16px 20px;
            flex: 2;
            min-width: 0;
            border-left: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            max-height: none;
            overflow: visible;
        }

        .no-residents {
            text-align: center;
            padding: 8px;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
        }

        .resident-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .resident-item:last-child {
            border-bottom: none;
        }

        .resident-item:only-child {
            border-bottom: none;
        }

        .resident-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .resident-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }

        .resident-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }

        .resident-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .resident-type .badge {
            font-size: 11px;
        }

        .resident-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }

        .btn-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 12px;
        }

        .btn-icon.btn-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .btn-icon.btn-danger:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Daire Actions - Sağ tarafta */
        .daire-actions {
            padding: 16px 20px;
            background: var(--background-color);
            border-top: none;
            border-left: 1px solid var(--border-color);
            display: flex;
            gap: 8px;
            flex-direction: column;
            align-items: stretch;
            flex-shrink: 0;
            min-width: 160px;
        }

        .daire-actions .btn {
            font-size: 12px;
            padding: 8px 12px;
            white-space: nowrap;
        }

        /* Daire Detay Container */
        .daire-detay-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }

        /* Info Card */
        .info-card {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }

        .info-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: var(--card-background);
            border-bottom: 1px solid var(--border-color);
        }

        .info-icon {
            font-size: 20px;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .info-title {
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
        }

        .sakin-sayisi {
            font-size: 12px;
            color: var(--text-secondary);
            background: var(--background-color);
            padding: 4px 8px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .info-content {
            padding: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Sakinler List */
        .sakinler-list {
            padding: 16px 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .empty-sakinler {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        /* Empty Actions - Boş daire düzenleme butonu SAĞ TARAFA */
        .empty-actions {
            display: flex;
            justify-content: flex-end;
            padding: 8px 0;
            margin-top: 8px;
        }
        
        /* No Residents - Henüz sakin atanmamış yazısı */
        .no-residents {
            text-align: left;
            padding: 8px 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            flex: 1;
        }

        /* No Residents Container - Flexbox yapısı */
        .no-residents-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .no-residents-container .no-residents {
            text-align: left;
            padding: 0;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
            flex: 1;
        }
        
        .no-residents-container .empty-actions {
            padding: 0;
            margin: 0;
        }

        .sakin-item-detay {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 8px;
        }

        .sakin-item-detay:hover {
            background: var(--card-background);
            transform: translateX(4px);
        }

        .sakin-avatar-detay {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sakin-info-detay {
            flex: 1;
            min-width: 0;
        }

        .sakin-name-detay {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .sakin-type-detay .badge {
            font-size: 11px;
        }

        /* Sakin Detay Modal */
        .sakin-detay-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            padding: 20px;
            background: var(--background-color);
            border-radius: 12px;
        }

        .sakin-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 24px;
            flex-shrink: 0;
        }

        .sakin-bilgileri h4 {
            margin: 0 0 8px 0;
            color: var(--text-primary);
            font-size: 20px;
        }

        .sakin-tip-badge {
            padding: 6px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 600;
        }

        .sakin-tip-badge.ev-sahibi {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .sakin-tip-badge.kiraci {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        /* Sakin Info Grid */
        .sakin-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .sakin-info-item {
            background: var(--background-color);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .sakin-info-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .sakin-info-value {
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-all;
        }

        /* Badge Stilleri */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-primary {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
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

        .badge-info {
            background: rgba(6, 182, 212, 0.1);
            color: #0891b2;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        /* Form Info */
        .form-info {
            padding: 10px 12px;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Site Grid - Tekrar kullanım için */
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

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .breadcrumb-separator {
            margin: 0 4px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .daire-actions {
                min-width: 140px;
            }
            
            .daire-actions .btn {
                font-size: 11px;
                padding: 6px 10px;
            }
        }

        @media (max-width: 968px) {
            .daire-card {
                flex-direction: column;
                align-items: stretch;
            }
            
            .daire-header {
                border-bottom: 1px solid var(--border-color);
            }
            
            .daire-residents {
                border-left: none;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .daire-actions {
                border-left: none;
                flex-direction: row;
                min-width: auto;
                flex-wrap: wrap;
            }
            
            .daire-actions .btn {
                flex: 1;
                min-width: 120px;
            }

            .modal-large {
                width: 95%;
                max-width: none;
            }
            
            .daire-detay-container {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        @media (max-width: 768px) {
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

            .daire-header {
                padding: 16px;
            }

            .daire-residents {
                padding: 12px 16px;
            }

            .daire-actions {
                padding: 12px 16px;
            }

            .resident-item {
                padding: 8px 0;
            }

            .resident-avatar {
                width: 28px;
                height: 28px;
                font-size: 11px;
            }

            .daire-main {
                gap: 12px;
            }

            .daire-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .breadcrumb {
                flex-wrap: wrap;
            }
            
            .daire-actions .btn {
                font-size: 12px;
                padding: 8px 12px;
                min-width: 100px;
            }

            .sakin-info-grid {
                grid-template-columns: 1fr;
            }
            
            .sakin-detay-container {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Çok küçük ekranlar için */
        @media (max-width: 480px) {
            .daire-header .daire-main {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .daire-info {
                align-self: stretch;
            }
            
            .daire-number {
                font-size: 14px;
            }
            
            .daire-type {
                font-size: 12px;
            }
            
            .resident-name {
                font-size: 13px;
            }
            
            .daire-actions .btn {
                font-size: 11px;
                padding: 6px 8px;
                min-width: 80px;
            }
        }
    </style>
</body>
</html>