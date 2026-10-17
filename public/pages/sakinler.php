<?php
// pages/sakinler.php - Site/Blok destekli Modern Sakinler yönetimi sayfası
session_start();

// Pages klasöründe olduğumuzu belirt
define('IN_PAGES', true);
// App yüklendiğini belirt (sidebar.php için güvenlik)
define('APP_LOADED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions.php';
require_once __DIR__ . '/../../src/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Sayfa başlığını ayarla
$page_title = 'Sakinler';

// Sakinler görüntüleme yetkisi kontrolü
if (!hasPagePermission('sakinler')) {
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

// Sakin ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sakin'])) {
    if (!hasActionPermission('add_sakin')) {
        showAlert('Sakin ekleme yetkiniz yok', 'error');
    } else {
        $ad_soyad = sanitize($_POST['ad_soyad']);
        $daire_no = sanitize($_POST['daire_no']);
        $telefon = sanitize($_POST['telefon']);
        $email = sanitize($_POST['email']);
        
        // Site/blok bilgilerini al
        $sakin_site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : $selected_site_id;
        $sakin_blok_id = isset($_POST['blok_id']) ? (int)$_POST['blok_id'] : $selected_blok_id;
        
        $errors = validateRequired([
            'ad_soyad' => 'Ad Soyad',
            'daire_no' => 'Daire No',
            'telefon' => 'Telefon',
            'email' => 'Email'
        ], $_POST);
        
        // Site/blok seçimi kontrolü
        if (!$sakin_site_id) {
            $errors[] = 'Lütfen bir site seçin';
        }
        if (!$sakin_blok_id) {
            $errors[] = 'Lütfen bir blok seçin';
        }
        
        if (empty($errors)) {
            if (!validateEmail($email)) {
                $errors[] = 'Geçerli bir email adresi girin';
            }
            
            // Daire no benzersizlik kontrolü (aynı site/blok içinde)
            $stmt = $db->prepare("SELECT id FROM sakinler WHERE daire_no = ? AND site_id = ? AND blok_id = ? AND status = 1");
            $stmt->execute([$daire_no, $sakin_site_id, $sakin_blok_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Bu daire numarası bu blokta zaten kayıtlı';
            }
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Sakin grubunun ID'sini al
                $stmt = $db->prepare("SELECT id FROM yetki_gruplari WHERE grup_adi = 'Sakin' AND status = 1");
                $stmt->execute();
                $sakin_grup = $stmt->fetch();
                $grup_id = $sakin_grup ? $sakin_grup['id'] : null;
                
                // Sakini ekle
                $stmt = $db->prepare("
                    INSERT INTO sakinler (ad_soyad, daire_no, telefon, email, site_id, blok_id, grup_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ad_soyad, $daire_no, $telefon, $email, $sakin_site_id, $sakin_blok_id, $grup_id]);
                
                $db->commit();
                showAlert('Sakin başarıyla eklendi', 'success');
                
                // Formu temizle
                $_POST = [];
                
            } catch (Exception $e) {
                $db->rollback();
                showAlert('Sakin eklenirken hata oluştu: ' . $e->getMessage(), 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Sakin düzenleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sakin'])) {
    if (!hasActionPermission('add_sakin')) { 
        showAlert('Sakin düzenleme yetkiniz yok', 'error');
    } else {
        $sakin_id = (int)$_POST['sakin_id'];
        $ad_soyad = sanitize($_POST['ad_soyad']);
        $daire_no = sanitize($_POST['daire_no']);
        $telefon = sanitize($_POST['telefon']);
        $email = sanitize($_POST['email']);
        
        // Site/blok bilgilerini al
        $sakin_site_id = isset($_POST['site_id']) ? (int)$_POST['site_id'] : $selected_site_id;
        $sakin_blok_id = isset($_POST['blok_id']) ? (int)$_POST['blok_id'] : $selected_blok_id;
        
        $errors = validateRequired([
            'ad_soyad' => 'Ad Soyad',
            'daire_no' => 'Daire No',
            'telefon' => 'Telefon',
            'email' => 'Email'
        ], $_POST);
        
        if (empty($errors)) {
            if (!validateEmail($email)) {
                $errors[] = 'Geçerli bir email adresi girin';
            }
            
            // Daire no benzersizlik kontrolü (kendisi hariç, aynı site/blok içinde)
            $stmt = $db->prepare("SELECT id FROM sakinler WHERE daire_no = ? AND site_id = ? AND blok_id = ? AND status = 1 AND id != ?");
            $stmt->execute([$daire_no, $sakin_site_id, $sakin_blok_id, $sakin_id]);
            if ($stmt->fetch()) {
                $errors[] = 'Bu daire numarası bu blokta zaten kayıtlı';
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE sakinler 
                    SET ad_soyad = ?, daire_no = ?, telefon = ?, email = ?, site_id = ?, blok_id = ?, updated_at = NOW() 
                    WHERE id = ? AND status = 1
                ");
                $stmt->execute([$ad_soyad, $daire_no, $telefon, $email, $sakin_site_id, $sakin_blok_id, $sakin_id]);
                
                if ($stmt->rowCount() > 0) {
                    showAlert('Sakin bilgileri başarıyla güncellendi', 'success');
                } else {
                    showAlert('Sakin bulunamadı veya değişiklik yapılmadı', 'warning');
                }
                
            } catch (Exception $e) {
                showAlert('Sakin güncellenirken hata oluştu: ' . $e->getMessage(), 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Sakin silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sakin'])) {
    if (!hasActionPermission('delete_sakin')) {
        showAlert('Sakin silme yetkiniz yok', 'error');
    } else {
        $sakin_id = (int)$_POST['sakin_id'];
        
        try {
            $stmt = $db->prepare("UPDATE sakinler SET status = 0 WHERE id = ?");
            $stmt->execute([$sakin_id]);
            
            if ($stmt->rowCount() > 0) {
                showAlert('Sakin başarıyla silindi', 'success');
            } else {
                showAlert('Sakin bulunamadı', 'error');
            }
        } catch (Exception $e) {
            showAlert('Sakin silinirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Sakinleri getir (site/blok filtreli)
$sakinler = [];
if ($selected_site_id) {
    try {
        $where_conditions = ["s.status = 1"];
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
            SELECT s.id, s.ad_soyad, s.daire_no, s.telefon, s.email, s.site_id, s.blok_id,
                   CASE WHEN s.password IS NULL THEN 0 ELSE 1 END as password,
                   s.status, s.created_at,
                   st.site_adi, b.blok_adi
            FROM sakinler s
            LEFT JOIN siteler st ON s.site_id = st.id
            LEFT JOIN bloklar b ON s.blok_id = b.id
            WHERE $where_clause 
            ORDER BY s.ad_soyad
        ");
        $stmt->execute($params);
        $sakinler = $stmt->fetchAll();
    } catch (Exception $e) {
        $sakinler = [];
        showAlert('Sakinler yüklenirken hata oluştu: ' . $e->getMessage(), 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/../../src/views/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="welcome-section">
                    <h1 class="page-title">👥 Sakinler</h1>
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
                        <input type="text" class="search-input" placeholder="Sakin ara..." id="sakinSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <?php if (hasActionPermission('add_sakin')): ?>
                    <button class="btn btn-primary" onclick="openModal('addSakinModal')">
                        ➕ Yeni Sakin
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
                        <p class="card-subtitle">Sakinleri görüntülemek için önce bir site seçin</p>
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
                                    // Site için sakin sayısını al
                                    try {
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM sakinler WHERE site_id = ? AND status = 1");
                                        $stmt->execute([$site['id']]);
                                        $sakin_count = $stmt->fetch()['total'];
                                        
                                        // Site için blok sayısını al
                                        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bloklar WHERE site_id = ? AND status = 1");
                                        $stmt->execute([$site['id']]);
                                        $blok_count = $stmt->fetch()['total'];
                                    } catch (Exception $e) {
                                        $sakin_count = 0;
                                        $blok_count = 0;
                                    }
                                    ?>
                                    <div class="site-card" onclick="selectSite(<?= $site['id'] ?>)">
                                        <div class="site-card-header">
                                            <div class="site-icon">🏢</div>
                                            <div class="site-card-title"><?= htmlspecialchars($site['site_adi']) ?></div>
                                        </div>
                                        <div class="site-card-stats">
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $blok_count ?></span>
                                                <span class="stat-label">Blok</span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-number"><?= $sakin_count ?></span>
                                                <span class="stat-label">Sakin</span>
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
                    <div class="stat-card primary clickable" data-filter="all" onclick="filterByStatCard('all', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Sakin</div>
                        </div>
                        <div class="stat-value"><?= count($sakinler) ?></div>
                        <div class="stat-change positive">Aktif kayıtlar</div>
                    </div>
                    
                    <div class="stat-card success clickable" data-filter="password" onclick="filterByStatCard('password', this)">
                        <div class="stat-header">
                            <div class="stat-title">Şifre Oluşturan</div>
                        </div>
                        <div class="stat-value"><?= count(array_filter($sakinler, fn($s) => $s['password'])) ?></div>
                        <div class="stat-change positive">Sisteme giriş yapabilen</div>
                    </div>
                    
                    <div class="stat-card warning clickable" data-filter="no-password" onclick="filterByStatCard('no-password', this)">
                        <div class="stat-header">
                            <div class="stat-title">Şifre Bekleyen</div>
                        </div>
                        <div class="stat-value"><?= count(array_filter($sakinler, fn($s) => !$s['password'])) ?></div>
                        <div class="stat-change negative">İlk giriş bekliyor</div>
                    </div>
                    
                    <div class="stat-card info clickable" data-filter="this-month" onclick="filterByStatCard('this-month', this)">
                        <div class="stat-header">
                            <div class="stat-title">Bu Ay Eklenen</div>
                        </div>
                        <div class="stat-value">
                            <?= count(array_filter($sakinler, fn($s) => date('Y-m', strtotime($s['created_at'])) === date('Y-m'))) ?>
                        </div>
                        <div class="stat-change">Yeni kayıtlar</div>
                    </div>
                </div>

                <!-- Sakinler Listesi -->
                <!-- Sakinler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sakin Listesi</h3>
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
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Aktif Filtre Bilgisi -->
                        <div class="filter-info" id="filterInfo" style="display: none;">
                            <div class="filter-badge">
                                <span id="filterText">Tüm Sakinler</span>
                                <button onclick="clearAllFilters()" title="Filtreyi temizle">×</button>
                            </div>
                        </div>

                        <?php if (empty($sakinler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
                                <h3>
                                    <?php if ($selected_blok_id): ?>
                                        Bu blokta henüz sakin eklenmemiş
                                    <?php else: ?>
                                        Bu sitede henüz sakin eklenmemiş  
                                    <?php endif; ?>
                                </h3>
                                <p>İlk sakini eklemek için yukarıdaki "Yeni Sakin" butonunu kullanın.</p>
                                <?php if (hasActionPermission('add_sakin')): ?>
                                <button class="btn btn-primary" onclick="openModal('addSakinModal')" style="margin-top: 16px;">
                                    ➕ İlk Sakini Ekle
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="sakin-list">
                                <?php foreach ($sakinler as $sakin): ?>
                                <div class="sakin-item" 
                                    data-password="<?= $sakin['password'] ? 'password' : 'no-password' ?>" 
                                    data-month="<?= date('Y-m', strtotime($sakin['created_at'])) === date('Y-m') ? 'this-month' : 'other-month' ?>"
                                    data-search="<?= strtolower($sakin['ad_soyad'] . ' ' . $sakin['daire_no']) ?>"
                                    onclick="toggleSakinDetails(<?= $sakin['id'] ?>)">
                                    
                                    <div class="sakin-header">
                                        <div class="sakin-main">
                                            <div class="user-avatar">
                                                <?= substr($sakin['ad_soyad'], 0, 2) ?>
                                            </div>
                                            <div class="sakin-info">
                                                <div class="sakin-name"><?= htmlspecialchars($sakin['ad_soyad']) ?></div>
                                                <div class="sakin-apartment">
                                                    Daire <?= htmlspecialchars($sakin['daire_no']) ?>
                                                    <?php if ($sakin['blok_adi'] && !$selected_blok_id): ?>
                                                        - <?= htmlspecialchars($sakin['blok_adi']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="sakin-status">
                                            <?php if (hasActionPermission('add_sakin')): ?>
                                                <button class="btn btn-primary btn-sm" 
                                                        onclick="event.stopPropagation(); editSakin(<?= $sakin['id'] ?>, '<?= htmlspecialchars($sakin['ad_soyad']) ?>', '<?= htmlspecialchars($sakin['daire_no']) ?>', '<?= htmlspecialchars($sakin['telefon']) ?>', '<?= htmlspecialchars($sakin['email']) ?>', <?= $sakin['site_id'] ?>, <?= $sakin['blok_id'] ?>)"
                                                        title="Sakin Bilgilerini Düzenle">
                                                    ✏️ Düzenle
                                                </button>
                                            <?php endif; ?>
                                            
                                            <div class="expand-icon">
                                                <span class="chevron">▼</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="sakin-details" id="details-<?= $sakin['id'] ?>" style="display: none;">
                                        <div class="details-grid">
                                            <div class="detail-item">
                                                <div class="detail-label">📞 Telefon</div>
                                                <div class="detail-value">
                                                    <a href="tel:<?= htmlspecialchars($sakin['telefon']) ?>">
                                                        <?= htmlspecialchars($sakin['telefon']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <div class="detail-label">📧 Email</div>
                                                <div class="detail-value">
                                                    <a href="mailto:<?= htmlspecialchars($sakin['email']) ?>">
                                                        <?= htmlspecialchars($sakin['email']) ?>
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <div class="detail-label">🏢 Konum</div>
                                                <div class="detail-value">
                                                    <?= htmlspecialchars($sakin['site_adi']) ?>
                                                    <?php if ($sakin['blok_adi']): ?>
                                                        - <?= htmlspecialchars($sakin['blok_adi']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <div class="detail-label">🔐 Giriş Durumu</div>
                                                <div class="detail-value">
                                                    <?php if ($sakin['password']): ?>
                                                        <span style="color: #16a34a;">✅ Şifre oluşturulmuş</span>
                                                    <?php else: ?>
                                                        <span style="color: #d97706;">⏳ İlk giriş bekliyor</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <div class="detail-label">📅 Kayıt Tarihi</div>
                                                <div class="detail-value"><?= formatDate($sakin['created_at']) ?></div>
                                            </div>
                                        </div>
                                        
                                        <?php if (hasActionPermission('delete_sakin')): ?>
                                        <div class="sakin-actions">
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="event.stopPropagation(); deleteSakin(<?= $sakin['id'] ?>, '<?= htmlspecialchars($sakin['ad_soyad']) ?>')"
                                                    title="Sakini Sil">
                                                🗑️ Sakini Sil
                                            </button>
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

    <!-- Add Sakin Modal -->
    <?php if (hasActionPermission('add_sakin') && $selected_site_id): ?>
    <div class="modal" id="addSakinModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">👤 Yeni Sakin Ekle</h3>
                <button class="modal-close" onclick="closeModal('addSakinModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addSakinForm">
                    <input type="hidden" name="site_id" value="<?= $selected_site_id ?>">
                    <?php if ($selected_blok_id): ?>
                        <input type="hidden" name="blok_id" value="<?= $selected_blok_id ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" name="ad_soyad" class="form-input" 
                                   placeholder="Örn: Ahmet Yılmaz" required 
                                   value="<?= isset($_POST['ad_soyad']) ? htmlspecialchars($_POST['ad_soyad']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Daire No</label>
                            <input type="text" name="daire_no" class="form-input" 
                                   placeholder="Örn: A-101" required 
                                   value="<?= isset($_POST['daire_no']) ? htmlspecialchars($_POST['daire_no']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="telefon" class="form-input" 
                                   placeholder="0532-123-4567" required 
                                   value="<?= isset($_POST['telefon']) ? htmlspecialchars($_POST['telefon']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" 
                                   placeholder="ahmet@email.com" required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <?php if (!$selected_blok_id && count($site_bloklar) > 1): ?>
                    <div class="form-group">
                        <label class="form-label">Blok</label>
                        <select name="blok_id" class="form-select" required>
                            <option value="">Blok Seçin</option>
                            <?php foreach ($site_bloklar as $blok): ?>
                                <option value="<?= $blok['id'] ?>"><?= htmlspecialchars($blok['blok_adi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <strong>ℹ️ Bilgi:</strong> Sakin ilk girişinde kendi şifresini oluşturacak.
                        <br><strong>Konum:</strong> <?= htmlspecialchars($site_info['site_adi']) ?>
                        <?php if ($selected_blok_id && $blok_info): ?>
                            - <?= htmlspecialchars($blok_info['blok_adi']) ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addSakinModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_sakin" class="btn btn-primary">
                            👤 Sakin Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Sakin Modal -->
    <?php if (hasActionPermission('add_sakin') && $selected_site_id): ?>
    <div class="modal" id="editSakinModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Sakin Bilgilerini Düzenle</h3>
                <button class="modal-close" onclick="closeModal('editSakinModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editSakinForm">
                    <input type="hidden" name="sakin_id" id="editSakinId">
                    <input type="hidden" name="site_id" id="editSiteId">
                    <input type="hidden" name="blok_id" id="editBlokId">
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" name="ad_soyad" id="editAdSoyad" class="form-input" 
                                   placeholder="Örn: Ahmet Yılmaz" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Daire No</label>
                            <input type="text" name="daire_no" id="editDaireNo" class="form-input" 
                                   placeholder="Örn: A-101" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Telefon</label>
                            <input type="tel" name="telefon" id="editTelefon" class="form-input" 
                                   placeholder="0532-123-4567" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="editEmail" class="form-input" 
                                   placeholder="ahmet@email.com" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" style="margin-bottom: 20px;">
                        <strong>⚠️ Uyarı:</strong> Sakin bilgilerini değiştirirseniz, sakin bu değişikliklerden haberdar edilmelidir.
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('editSakinModal')">
                            İptal
                        </button>
                        <button type="submit" name="edit_sakin" class="btn btn-primary">
                            ✏️ Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteSakinModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Sakin Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteSakinModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu sakini silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz. Sakin ve tüm aidatları silinecek.
                </div>
                
                <form method="POST" id="deleteSakinForm">
                    <input type="hidden" name="sakin_id" id="deleteSakinId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteSakinModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_sakin" class="btn btn-danger">
                            🗑️ Evet, Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="../assets/js/app.js"></script>  
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
        // Global değişkenler
        let currentFilter = 'all';
        let currentSearchTerm = '';

        // Site seçim fonksiyonu
        function selectSite(siteId) {
            if (siteId === null) {
                window.location.href = 'sakinler.php';
            } else {
                window.location.href = `sakinler.php?site_id=${siteId}`;
            }
        }

        // Blok seçim fonksiyonu
        function selectBlok(blokId) {
            const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
            if (currentSiteId) {
                if (blokId === null) {
                    window.location.href = `sakinler.php?site_id=${currentSiteId}`;
                } else {
                    window.location.href = `sakinler.php?site_id=${currentSiteId}&blok_id=${blokId}`;
                }
            }
        }

        // Blok seçici toggle
        function toggleBlokSelector() {
            const content = document.getElementById('blokSelectorContent');
            const chevron = document.getElementById('blokSelectorChevron');
            
            if (content.style.display === 'none') {
                content.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                content.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Modal açma fonksiyonu
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
            }
        }

        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
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

        // Sakin detaylarını göster/gizle
        function toggleSakinDetails(sakinId) {
            const detailsDiv = document.getElementById('details-' + sakinId);
            const chevron = detailsDiv.parentElement.querySelector('.chevron');
            
            if (detailsDiv.style.display === 'none') {
                // Önce tüm açık detayları kapat
                document.querySelectorAll('.sakin-details').forEach(detail => {
                    detail.style.display = 'none';
                });
                document.querySelectorAll('.chevron').forEach(icon => {
                    icon.style.transform = 'rotate(0deg)';
                });
                
                // Seçili olanı aç
                detailsDiv.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                detailsDiv.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        // Stats kartına tıklayınca filtreleme
        function filterByStatCard(filterType, cardElement) {
            // Önceki aktif kartın stilini temizle
            document.querySelectorAll('.stat-card.clickable').forEach(card => {
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



        // Tüm filtreleri uygula
        function applyFilters() {
            const sakinItems = document.querySelectorAll('.sakin-item');
            
            sakinItems.forEach(item => {
                let showItem = true;
                
                // Filtre kontrolü
                if (currentFilter !== 'all') {
                    if (currentFilter === 'password' || currentFilter === 'no-password') {
                        showItem = item.dataset.password === currentFilter;
                    } else if (currentFilter === 'this-month') {
                        showItem = item.dataset.month === currentFilter;
                    }
                }
                
                // Arama kontrolü
                if (showItem && currentSearchTerm) {
                    const searchData = item.dataset.search;
                    showItem = searchData.includes(currentSearchTerm);
                }
                
                item.style.display = showItem ? '' : 'none';
            });
        }

        // Filtre bilgisini göster
        function showFilterInfo(filterType) {
            const filterInfo = document.getElementById('filterInfo');
            const filterText = document.getElementById('filterText');
            
            if (!filterInfo || !filterText) return;
            
            const filterTexts = {
                'all': 'Tüm Sakinler',
                'password': 'Şifre Oluşturanlar',
                'no-password': 'Şifre Bekleyenler',
                'this-month': 'Bu Ay Eklenenler'
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
                const searchInput = document.getElementById('sakinSearch');
                if (searchInput) {
                    searchInput.value = '';
                }
                
                // statusFilter kısmını silin - artık yok
                
                // Stat kartlarının aktif durumunu temizle
                document.querySelectorAll('.stat-card.clickable').forEach(card => {
                    card.classList.remove('active');
                });
                
                // Toplam sakin kartını aktif yap
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

        // Sakin arama
        const searchInput = document.getElementById('sakinSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                currentSearchTerm = e.target.value.toLowerCase();
                applyFilters();
            });
        }
        
        // Sakin düzenleme
        function editSakin(id, adSoyad, daireNo, telefon, email, siteId, blokId) {
            document.getElementById('editSakinId').value = id;
            document.getElementById('editAdSoyad').value = adSoyad;
            document.getElementById('editDaireNo').value = daireNo;
            document.getElementById('editTelefon').value = telefon;
            document.getElementById('editEmail').value = email;
            document.getElementById('editSiteId').value = siteId;
            document.getElementById('editBlokId').value = blokId;
            openModal('editSakinModal');
        }
        
        // Sakin silme
        function deleteSakin(id, name) {
            document.getElementById('deleteSakinId').value = id;
            document.getElementById('deleteMessage').textContent = 
                `"${name}" adlı sakini silmek istediğinizden emin misiniz?`;
            openModal('deleteSakinModal');
        }
        
        // Form temizleme
        function clearForm() {
            const addForm = document.getElementById('addSakinForm');
            if (addForm) {
                addForm.reset();
            }
            const editForm = document.getElementById('editSakinForm');
            if (editForm) {
                editForm.reset();
            }
        }
        
        // Event listeners
        document.addEventListener('click', function(e) {
            // Modal dışına tıklandığında kapat
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
                e.target.style.display = 'none';
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
            }
        });

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            const totalCard = document.querySelector('[data-filter="all"]');
            if (totalCard) {
                totalCard.classList.add('active');
            }
        });

        // Site değiştirme fonksiyonu
        function changeSite(siteId) {
            if (!siteId) return;
            window.location.href = `sakinler.php?site_id=${siteId}`;
        }

        // Blok değiştirme fonksiyonu
        function changeBlok(blokId) {
            const currentSiteId = <?= $selected_site_id ?? 'null' ?>;
            if (currentSiteId) {
                if (blokId) {
                    window.location.href = `sakinler.php?site_id=${currentSiteId}&blok_id=${blokId}`;
                } else {
                    window.location.href = `sakinler.php?site_id=${currentSiteId}`;
                }
            }
        }

        // Filtreleri temizle fonksiyonu - güncellenmiş
        function clearAllFilters() {
            currentFilter = 'all';
            currentSearchTerm = '';
            
            // Arama kutusunu temizle
            const searchInput = document.getElementById('sakinSearch');
            if (searchInput) {
                searchInput.value = '';
            }
            
            // Sadece status filter dropdown'ını temizle (site/blok dropdown'larına dokunma)
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.value = '';
            }
            
            // Stat kartlarının aktif durumunu temizle
            document.querySelectorAll('.stat-card.clickable').forEach(card => {
                card.classList.remove('active');
            });
            
            // Toplam sakin kartını aktif yap
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
    </script>
    
    <style>
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

        /* Compact Blok Selector */
        .blok-selector-card {
            margin-bottom: 24px;
        }

        .blok-selector {
            background: var(--card-background);
        }

        .blok-selector-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .blok-selector-header:hover {
            background: var(--background-color);
            border-color: var(--primary-color);
        }

        .selector-info {
            flex: 1;
        }

        .selector-title {
            margin: 0 0 4px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .selector-subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .selector-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chevron {
            font-size: 14px;
            color: var(--text-secondary);
            transition: transform 0.3s ease;
        }

        .blok-selector-content {
            padding: 16px 20px 20px;
            border-top: 1px solid var(--border-color);
            background: var(--background-color);
            animation: slideDown 0.3s ease;
        }

        .blok-grid-compact {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .blok-card-compact {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .blok-card-compact:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
        }

        .blok-card-compact.active {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .blok-compact-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .blok-compact-icon {
            font-size: 16px;
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .blok-compact-info {
            flex: 1;
        }

        .blok-compact-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .blok-compact-count {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Card subtitle */
        .card-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
            margin-top: 4px;
        }

        /* Clickable stat cards - Siteler teması */
        .stat-card.clickable {
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
            user-select: none;
            overflow: hidden;
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

        /* Enhanced hover effects for clickable cards */
        .stat-card.clickable:hover .stat-value {
            font-weight: 700;
            transform: scale(1.05);
            transition: all 0.3s ease;
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

        /* Click indicators */
        .stat-card.clickable::after {
            content: '👆';
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .stat-card.clickable:hover::after {
            opacity: 0.6;
        }

        .stat-card.clickable.active::after {
            content: '✨';
            opacity: 1;
            animation: sparkle 2s infinite;
        }

        @keyframes sparkle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Special indicators for navigation cards */
        .stat-card.success.clickable::after {
            content: '👥';
        }

        .stat-card.warning.clickable::after {
            content: '⏳';
        }

        .stat-card.info.clickable::after {
            content: '📅';
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
            .stat-card.clickable {
                animation: none;
                transition: none;
            }
            
            .stat-card.clickable:hover {
                transform: none;
            }
        }

        .clickable-stat.primary.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.25));
            border-color: rgba(59, 130, 246, 0.3);
        }

        .clickable-stat.success.active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.25));
            border-color: rgba(16, 185, 129, 0.3);
        }

        .clickable-stat.warning.active {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(245, 158, 11, 0.25));
            border-color: rgba(245, 158, 11, 0.3);
        }

        .clickable-stat.info.active {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(6, 182, 212, 0.25));
            border-color: rgba(6, 182, 212, 0.3);
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
            transition: background-color 0.3s ease;
        }

        .filter-badge button:hover {
            background: rgba(37, 99, 235, 0.2);
        }

        /* Card Actions Güncelleme */
        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Sakin liste stilleri */
        .sakin-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .sakin-item {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sakin-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
            border-color: var(--primary-color);
        }

        .sakin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
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

        .sakin-status {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .expand-icon {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .sakin-details {
            border-top: 1px solid var(--border-color);
            background: var(--background-color);
            padding: 20px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
                padding: 0 20px;
            }
            to {
                opacity: 1;
                max-height: 200px;
                padding: 20px;
            }
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .detail-value a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .detail-value a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .sakin-actions {
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
        }

        /* Button stilleri */
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

        .btn-danger {
            background: var(--danger-color);
            color: white;
            border: none;
        }

        .btn-danger:hover {
            background: #dc2626;
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

        /* Responsive */
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

            .blok-grid-compact {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .blok-selector-header {
                padding: 12px 16px;
            }

            .blok-selector-content {
                padding: 12px 16px 16px;
            }

            .sakin-header {
                padding: 12px 16px;
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

            .details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .sakin-details {
                padding: 16px;
            }

            .sakin-status {
                gap: 8px;
            }

            .btn-sm {
                padding: 4px 8px;
                font-size: 12px;
            }

            .clickable-stat:hover {
                transform: none;
            }

            .clickable-stat.active {
                transform: none;
            }

            .topbar-actions {
                gap: 8px;
            }

            .search-input {
                width: 150px;
            }
        }

        /* Site card özel efektler */
        .site-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
        }

        .site-card:hover {
            background: linear-gradient(145deg, #ffffff, #f1f5f9);
        }

        /* Stats grid responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
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

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
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
    </style>
</body>
</html>