<?php
// pages/giderler.php - Modern Gider yönetimi sayfası
$page_title = 'Giderler';
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

// Gider görüntüleme yetkisi kontrolü
if (!hasPagePermission('giderler')) {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Kategori ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_kategori'])) {
    if (!hasActionPermission('add_gider')) {
        showAlert('Kategori ekleme yetkiniz yok', 'error');
    } else {
        $kategori_adi = sanitize($_POST['kategori_adi']);
        $icon = sanitize($_POST['icon']);
        
        // Boş icon kontrolü - default değer ata
        if (empty($icon)) {
            $icon = '📋';
        }
        
        $errors = validateRequired([
            'kategori_adi' => 'Kategori Adı'
        ], $_POST);
        
        if (empty($errors)) {
            try {
                // Aynı kategorinin var olup olmadığını kontrol et
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM gider_kategorileri WHERE kategori_adi = ? AND status = 1");
                $check_stmt->execute([$kategori_adi]);
                
                if ($check_stmt->fetchColumn() > 0) {
                    showAlert('Bu kategori zaten mevcut', 'error');
                } else {
                    $stmt = $db->prepare("INSERT INTO gider_kategorileri (kategori_adi, icon) VALUES (?, ?)");
                    $stmt->execute([$kategori_adi, $icon]);
                    
                    showAlert('Kategori başarıyla eklendi', 'success');
                    $_POST = [];
                }
            } catch (Exception $e) {
                showAlert('Kategori eklenirken hata oluştu', 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Kategori silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_kategori'])) {
    if (!hasActionPermission('delete_gider')) {
        showAlert('Kategori silme yetkiniz yok', 'error');
    } else {
        $kategori_id = (int)$_POST['kategori_id'];
        
        try {
            // Önce bu kategoriye ait gider var mı kontrol et
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM giderler WHERE kategori = (SELECT kategori_adi FROM gider_kategorileri WHERE id = ?) AND status = 1");
            $check_stmt->execute([$kategori_id]);
            
            if ($check_stmt->fetchColumn() > 0) {
                showAlert('Bu kategoriye ait giderler olduğu için silinemez', 'error');
            } else {
                $stmt = $db->prepare("UPDATE gider_kategorileri SET status = 0 WHERE id = ?");
                $stmt->execute([$kategori_id]);
                
                if ($stmt->rowCount() > 0) {
                    showAlert('Kategori başarıyla silindi', 'success');
                } else {
                    showAlert('Kategori bulunamadı', 'error');
                }
            }
        } catch (Exception $e) {
            showAlert('Kategori silinirken hata oluştu', 'error');
        }
    }
}

// Gider ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_gider'])) {
    if (!hasActionPermission('add_gider')) {
        showAlert('Gider ekleme yetkiniz yok', 'error');
    } else {
        $tarih = sanitize($_POST['tarih']);
        $aciklama = sanitize($_POST['aciklama']);
        $kategori = sanitize($_POST['kategori']);
        $miktar = (float)$_POST['miktar'];
        
        $errors = validateRequired([
            'tarih' => 'Tarih',
            'aciklama' => 'Açıklama',
            'kategori' => 'Kategori',
            'miktar' => 'Miktar'
        ], $_POST);
        
        if (!validateNumeric($miktar, 1)) {
            $errors[] = 'Geçerli bir miktar girin';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("INSERT INTO giderler (tarih, aciklama, kategori, miktar) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tarih, $aciklama, $kategori, $miktar]);
                
                showAlert('Gider başarıyla eklendi', 'success');
                $_POST = [];
                
            } catch (Exception $e) {
                showAlert('Gider eklenirken hata oluştu', 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Gider silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_gider'])) {
    if (!hasActionPermission('delete_gider')) {
        showAlert('Gider silme yetkiniz yok', 'error');
    } else {
        $gider_id = (int)$_POST['gider_id'];
        
        try {
            $stmt = $db->prepare("UPDATE giderler SET status = 0 WHERE id = ?");
            $stmt->execute([$gider_id]);
            
            if ($stmt->rowCount() > 0) {
                showAlert('Gider başarıyla silindi', 'success');
            } else {
                showAlert('Gider bulunamadı', 'error');
            }
        } catch (Exception $e) {
            showAlert('Gider silinirken hata oluştu', 'error');
        }
    }
}

// Kategorileri getir
try {
    $kategori_stmt = $db->prepare("SELECT * FROM gider_kategorileri WHERE status = 1 ORDER BY kategori_adi");
    $kategori_stmt->execute();
    $kategoriler_db = $kategori_stmt->fetchAll();
} catch (Exception $e) {
    $kategoriler_db = [];
}

// Giderleri getir
try {
    $stmt = $db->prepare("SELECT g.*, gk.icon FROM giderler g LEFT JOIN gider_kategorileri gk ON g.kategori = gk.kategori_adi WHERE g.status = 1 ORDER BY g.tarih DESC, g.id DESC");
    $stmt->execute();
    $giderler = $stmt->fetchAll();
} catch (Exception $e) {
    $giderler = [];
    showAlert('Giderler yüklenirken hata oluştu', 'error');
}

// İstatistikleri hesapla
$toplam_gider = array_sum(array_column($giderler, 'miktar'));
$bu_ay_giderler = array_filter($giderler, fn($g) => date('Y-m', strtotime($g['tarih'])) === date('Y-m'));
$bu_ay_toplam = array_sum(array_column($bu_ay_giderler, 'miktar'));
$gecen_ay_giderler = array_filter($giderler, fn($g) => date('Y-m', strtotime($g['tarih'])) === date('Y-m', strtotime('-1 month')));
$gecen_ay_toplam = array_sum(array_column($gecen_ay_giderler, 'miktar'));

// Kategori analizleri
$kategoriler = [];
foreach ($giderler as $gider) {
    if (!isset($kategoriler[$gider['kategori']])) {
        $kategoriler[$gider['kategori']] = ['miktar' => 0, 'adet' => 0];
    }
    $kategoriler[$gider['kategori']]['miktar'] += $gider['miktar'];
    $kategoriler[$gider['kategori']]['adet']++;
}

// En yüksek kategoriler
arsort($kategoriler);
$top_kategoriler = array_slice($kategoriler, 0, 3, true);

// Aylık trend hesaplama
$aylik_trend = $gecen_ay_toplam > 0 ? (($bu_ay_toplam - $gecen_ay_toplam) / $gecen_ay_toplam) * 100 : 0;

// Filtreleme için kategorileri getir
$kategori_listesi = array_unique(array_column($giderler, 'kategori'));
sort($kategori_listesi);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .icon-selector {
            position: relative;
            width: 100%;
        }
        
        .selected-icon {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--background-color);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .selected-icon:hover {
            border-color: var(--primary-color);
        }
        
        .selected-icon span:first-child {
            font-size: 20px;
        }
        
        .icon-arrow {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .icon-picker {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            /* max-height kaldırıldı - widget istediği kadar uzayacak */
        }
        
        .icon-picker {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            z-index: 1000;
            /* Normal genişlik - değişiklik yok */
        }
        
        .icon-categories {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            padding: 8px;
            gap: 4px;
            background: var(--background-color);
        }
        
        .icon-category-btn {
            padding: 6px 12px;
            border: none;
            background: transparent;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .icon-category-btn:hover {
            background: var(--hover-color);
        }
        
        .icon-category-btn.active {
            background: var(--primary-color);
            color: white;
        }
        
        .icon-grid {
            padding: 12px;
            /* Normal padding */
        }
        
        .icon-category-content {
            display: grid;
            grid-template-columns: repeat(8, 1fr); /* Normal 8 sütun */
            gap: 8px; /* Normal gap */
            padding-bottom: 8px;
            /* TÜM EMOJİLERİ GÖSTER - yükseklik sınırı yok */
        }
        
        .icon-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px; /* Normal boyut */
            height: 40px; /* Normal boyut */
            border: 1px solid var(--border-color);
            border-radius: 6px;
            cursor: pointer;
            font-size: 20px; /* Normal emoji boyutu */
            transition: all 0.2s;
            background: var(--background-color);
        }
        
        .icon-item:hover {
            background: var(--hover-color);
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        .icon-item.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.05);
        }
        
        /* Responsive düzenlemeler */
        @media (max-width: 768px) {
            .icon-category-content {
                grid-template-columns: repeat(6, 1fr);
            }
            
            .icon-item {
                width: 35px;
                height: 35px;
                font-size: 18px;
            }
            
            .icon-categories {
                padding: 6px;
                gap: 2px;
                overflow-x: auto;
            }
            
            .icon-category-btn {
                padding: 4px 8px;
                font-size: 11px;
            }
        }
        
        @media (max-width: 480px) {
            .icon-category-content {
                grid-template-columns: repeat(5, 1fr);
            }
            
            .icon-item {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../src/views/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <h1 class="page-title">📋 Giderler</h1>
                
                <div class="topbar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Gider ara..." id="giderSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <?php if (hasActionPermission('add_gider')): ?>
                    <button class="btn btn-secondary" onclick="openModal('addKategoriModal')" title="Kategori Yönetimi">
                        🏷️ Kategoriler
                    </button>
                    <button class="btn btn-primary" onclick="openModal('addGiderModal')">
                        ➕ Yeni Gider
                    </button>
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

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card warning">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Gider</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_gider) ?></div>
                        <div class="stat-change"><?= count($giderler) ?> kayıt</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-header">
                            <div class="stat-title">Bu Ay</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($bu_ay_toplam) ?></div>
                        <div class="stat-change <?= $aylik_trend >= 0 ? 'negative' : 'positive' ?>">
                            <?= $aylik_trend >= 0 ? '↗️' : '↘️' ?> %<?= abs(number_format($aylik_trend, 1)) ?> önceki aya göre
                        </div>
                    </div>
                    
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <div class="stat-title">Ortalama Gider</div>
                        </div>
                        <div class="stat-value">
                            <?= count($giderler) > 0 ? formatMoney($toplam_gider / count($giderler)) : formatMoney(0) ?>
                        </div>
                        <div class="stat-change">Gider başına</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <div class="stat-title">En Yüksek Kategori</div>
                        </div>
                        <div class="stat-value">
                            <?php 
                            $top_kategori = !empty($top_kategoriler) ? array_key_first($top_kategoriler) : 'Yok';
                            echo $top_kategori !== 'Yok' ? formatMoney($top_kategoriler[$top_kategori]['miktar']) : formatMoney(0);
                            ?>
                        </div>
                        <div class="stat-change"><?= $top_kategori ?></div>
                    </div>
                </div>

                <?php if ($current_user['role'] === 'sakin'): ?>
                <!-- Sakin için şeffaflık bilgisi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔍 Gider Şeffaflığı</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>📊 Apartman Giderlerimiz:</strong> Apartmanımızın tüm giderlerini şeffaflık adına burada görüntüleyebilirsiniz. 
                            Bu sayede aidat ödemelerinizin nereye harcandığını takip edebilirsiniz.
                        </div>
                        
                        <div class="grid grid-3">
                            <div style="text-align: center; padding: 16px; background: var(--background-color); border-radius: 8px;">
                                <div style="font-size: 24px; margin-bottom: 8px;">📈</div>
                                <strong>Bu Ay Toplam</strong>
                                <div style="color: var(--warning-color); font-weight: bold; margin-top: 4px;">
                                    <?= formatMoney($bu_ay_toplam) ?>
                                </div>
                            </div>
                            
                            <div style="text-align: center; padding: 16px; background: var(--background-color); border-radius: 8px;">
                                <div style="font-size: 24px; margin-bottom: 8px;">📋</div>
                                <strong>Gider Sayısı</strong>
                                <div style="color: var(--info-color); font-weight: bold; margin-top: 4px;">
                                    <?= count($bu_ay_giderler) ?> adet
                                </div>
                            </div>
                            
                            <div style="text-align: center; padding: 16px; background: var(--background-color); border-radius: 8px;">
                                <div style="font-size: 24px; margin-bottom: 8px;">🏆</div>
                                <strong>En Fazla</strong>
                                <div style="color: var(--success-color); font-weight: bold; margin-top: 4px;">
                                    <?= $top_kategori ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Kategori Analizi -->
                <?php if (!empty($top_kategoriler) && $current_user['role'] !== 'sakin'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📈 Kategori Analizi</h3>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-3">
                            <?php foreach ($top_kategoriler as $kategori => $data): ?>
                            <div style="text-align: center; padding: 20px; background: var(--background-color); border-radius: 12px; border-left: 4px solid var(--primary-color);">
                                <div style="font-size: 32px; margin-bottom: 12px;">
                                    <?php
                                    // Veritabanından icon'u al
                                    $kategori_icon = '📋';
                                    foreach ($kategoriler_db as $kat) {
                                        if ($kat['kategori_adi'] === $kategori) {
                                            $kategori_icon = $kat['icon'];
                                            break;
                                        }
                                    }
                                    echo $kategori_icon;
                                    ?>
                                </div>
                                <h4 style="margin-bottom: 8px; color: var(--text-primary);"><?= htmlspecialchars($kategori) ?></h4>
                                <div style="font-size: 20px; font-weight: bold; color: var(--primary-color); margin-bottom: 4px;">
                                    <?= formatMoney($data['miktar']) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-secondary);">
                                    <?= $data['adet'] ?> gider
                                </div>
                                <div style="margin-top: 8px;">
                                    <div style="background: var(--border-color); border-radius: 10px; height: 6px; overflow: hidden;">
                                        <div style="background: var(--primary-color); height: 100%; width: <?= ($data['miktar'] / $toplam_gider) * 100 ?>%; transition: width 0.3s ease;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Giderler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Gider Listesi</h3>
                        <div class="card-actions">
                            <?php if (!empty($kategori_listesi)): ?>
                            <select class="form-select" id="kategoriFilter" onchange="filterGiderler()">
                                <option value="">Tüm Kategoriler</option>
                                <?php foreach ($kategori_listesi as $kategori): ?>
                                    <option value="<?= htmlspecialchars($kategori) ?>"><?= htmlspecialchars($kategori) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            
                            <select class="form-select" id="ayFilter" onchange="filterGiderler()">
                                <option value="">Tüm Aylar</option>
                                <option value="bu-ay">Bu Ay</option>
                                <option value="gecen-ay">Geçen Ay</option>
                                <option value="son-3-ay">Son 3 Ay</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($giderler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                                <h3>Henüz gider eklenmemiş</h3>
                                <p>İlk gideri eklemek için yukarıdaki "Yeni Gider" butonunu kullanın.</p>
                                <?php if (hasActionPermission('add_gider')): ?>
                                <button class="btn btn-primary" onclick="openModal('addGiderModal')" style="margin-top: 16px;">
                                    ➕ İlk Gideri Ekle
                                </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" id="giderlerTable">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Açıklama</th>
                                            <th>Kategori</th>
                                            <th>Miktar</th>
                                            <?php if (hasActionPermission('delete_gider')): ?>
                                                <th>İşlemler</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($giderler as $gider): ?>
                                        <tr class="gider-row" 
                                            data-kategori="<?= htmlspecialchars($gider['kategori']) ?>"
                                            data-tarih="<?= $gider['tarih'] ?>"
                                            data-search="<?= strtolower($gider['aciklama'] . ' ' . $gider['kategori'] . ' ' . formatDate($gider['tarih'])) ?>">
                                            <td><?= formatDate($gider['tarih']) ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div style="font-size: 20px;">
                                                        <?= $gider['icon'] ?? '📋' ?>
                                                    </div>
                                                    <?= htmlspecialchars($gider['aciklama']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= htmlspecialchars($gider['kategori']) ?></span>
                                            </td>
                                            <td><strong><?= formatMoney($gider['miktar']) ?></strong></td>
                                            <?php if (hasActionPermission('delete_gider')): ?>
                                            <td>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="deleteGider(<?= $gider['id'] ?>, '<?= htmlspecialchars($gider['aciklama']) ?>')"
                                                        title="Gideri Sil">
                                                    🗑️
                                                </button>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Kategori Modal -->
    <?php if (hasActionPermission('add_gider')): ?>
    <div class="modal" id="addKategoriModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🏷️ Yeni Kategori Ekle</h3>
                <button class="modal-close" onclick="closeModal('addKategoriModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addKategoriForm">
                    <div class="form-group">
                        <label class="form-label">Kategori Adı</label>
                        <input type="text" name="kategori_adi" class="form-input" 
                               placeholder="Örn: Peyzaj" required 
                               value="<?= isset($_POST['kategori_adi']) ? htmlspecialchars($_POST['kategori_adi']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Icon Seç</label>
                        <div class="icon-selector">
                            <!-- Seçilen Icon -->
                            <div class="selected-icon" onclick="toggleIconPicker()">
                                <span id="selectedIcon">📋</span>
                                <span class="icon-arrow">▼</span>
                            </div>
                            
                            <!-- Icon Picker -->
                            <div class="icon-picker" id="iconPicker" style="display: none;">
                                <div class="icon-categories">
                                    <button type="button" class="icon-category-btn active" onclick="showIconCategory('general')">Genel</button>
                                    <button type="button" class="icon-category-btn" onclick="showIconCategory('building')">Bina</button>
                                    <button type="button" class="icon-category-btn" onclick="showIconCategory('service')">Hizmet</button>
                                    <button type="button" class="icon-category-btn" onclick="showIconCategory('nature')">Doğa</button>
                                </div>
                                
                                <div class="icon-grid">
                                    <!-- Genel Icons -->
                                    <div class="icon-category-content" id="general">
                                        <div class="icon-item" onclick="selectIcon('📋')">📋</div>
                                        <div class="icon-item" onclick="selectIcon('📦')">📦</div>
                                        <div class="icon-item" onclick="selectIcon('📄')">📄</div>
                                        <div class="icon-item" onclick="selectIcon('📊')">📊</div>
                                        <div class="icon-item" onclick="selectIcon('💰')">💰</div>
                                        <div class="icon-item" onclick="selectIcon('💸')">💸</div>
                                        <div class="icon-item" onclick="selectIcon('📝')">📝</div>
                                        <div class="icon-item" onclick="selectIcon('🎯')">🎯</div>
                                        <div class="icon-item" onclick="selectIcon('📱')">📱</div>
                                        <div class="icon-item" onclick="selectIcon('💻')">💻</div>
                                        <div class="icon-item" onclick="selectIcon('📞')">📞</div>
                                        <div class="icon-item" onclick="selectIcon('📧')">📧</div>
                                        <div class="icon-item" onclick="selectIcon('🎉')">🎉</div>
                                        <div class="icon-item" onclick="selectIcon('🎁')">🎁</div>
                                        <div class="icon-item" onclick="selectIcon('🏆')">🏆</div>
                                        <div class="icon-item" onclick="selectIcon('⭐')">⭐</div>
                                    </div>
                                    
                                    <!-- Bina Icons -->
                                    <div class="icon-category-content" id="building" style="display: none;">
                                        <div class="icon-item" onclick="selectIcon('⚡')">⚡</div>
                                        <div class="icon-item" onclick="selectIcon('💡')">💡</div>
                                        <div class="icon-item" onclick="selectIcon('🔥')">🔥</div>
                                        <div class="icon-item" onclick="selectIcon('💧')">💧</div>
                                        <div class="icon-item" onclick="selectIcon('🏗️')">🏗️</div>
                                        <div class="icon-item" onclick="selectIcon('🔧')">🔧</div>
                                        <div class="icon-item" onclick="selectIcon('🔨')">🔨</div>
                                        <div class="icon-item" onclick="selectIcon('🛠️')">🛠️</div>
                                        <div class="icon-item" onclick="selectIcon('🏠')">🏠</div>
                                        <div class="icon-item" onclick="selectIcon('🏢')">🏢</div>
                                        <div class="icon-item" onclick="selectIcon('🚪')">🚪</div>
                                        <div class="icon-item" onclick="selectIcon('🪟')">🪟</div>
                                        <div class="icon-item" onclick="selectIcon('🏗️')">🏗️</div>
                                        <div class="icon-item" onclick="selectIcon('🪜')">🪜</div>
                                        <div class="icon-item" onclick="selectIcon('🔩')">🔩</div>
                                        <div class="icon-item" onclick="selectIcon('⚙️')">⚙️</div>
                                    </div>
                                    
                                    <!-- Hizmet Icons -->
                                    <div class="icon-category-content" id="service" style="display: none;">
                                        <div class="icon-item" onclick="selectIcon('🧹')">🧹</div>
                                        <div class="icon-item" onclick="selectIcon('🧽')">🧽</div>
                                        <div class="icon-item" onclick="selectIcon('🧴')">🧴</div>
                                        <div class="icon-item" onclick="selectIcon('🛡️')">🛡️</div>
                                        <div class="icon-item" onclick="selectIcon('🔒')">🔒</div>
                                        <div class="icon-item" onclick="selectIcon('🚗')">🚗</div>
                                        <div class="icon-item" onclick="selectIcon('🚕')">🚕</div>
                                        <div class="icon-item" onclick="selectIcon('🛞')">🛞</div>
                                        <div class="icon-item" onclick="selectIcon('⛽')">⛽</div>
                                        <div class="icon-item" onclick="selectIcon('🧰')">🧰</div>
                                        <div class="icon-item" onclick="selectIcon('🔐')">🔐</div>
                                        <div class="icon-item" onclick="selectIcon('🛎️')">🛎️</div>
                                        <div class="icon-item" onclick="selectIcon('📹')">📹</div>
                                        <div class="icon-item" onclick="selectIcon('🚨')">🚨</div>
                                        <div class="icon-item" onclick="selectIcon('🧯')">🧯</div>
                                        <div class="icon-item" onclick="selectIcon('🚿')">🚿</div>
                                    </div>
                                    
                                    <!-- Doğa Icons -->
                                    <div class="icon-category-content" id="nature" style="display: none;">
                                        <div class="icon-item" onclick="selectIcon('🌿')">🌿</div>
                                        <div class="icon-item" onclick="selectIcon('🌱')">🌱</div>
                                        <div class="icon-item" onclick="selectIcon('🌳')">🌳</div>
                                        <div class="icon-item" onclick="selectIcon('🌺')">🌺</div>
                                        <div class="icon-item" onclick="selectIcon('🌸')">🌸</div>
                                        <div class="icon-item" onclick="selectIcon('🍃')">🍃</div>
                                        <div class="icon-item" onclick="selectIcon('🌾')">🌾</div>
                                        <div class="icon-item" onclick="selectIcon('🌴')">🌴</div>
                                        <div class="icon-item" onclick="selectIcon('🌲')">🌲</div>
                                        <div class="icon-item" onclick="selectIcon('🌵')">🌵</div>
                                        <div class="icon-item" onclick="selectIcon('🌻')">🌻</div>
                                        <div class="icon-item" onclick="selectIcon('🌷')">🌷</div>
                                        <div class="icon-item" onclick="selectIcon('🌹')">🌹</div>
                                        <div class="icon-item" onclick="selectIcon('🌼')">🌼</div>
                                        <div class="icon-item" onclick="selectIcon('🍀')">🍀</div>
                                        <div class="icon-item" onclick="selectIcon('🌿')">🌿</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="icon" id="iconInput" value="📋">
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addKategoriModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_kategori" class="btn btn-primary">
                            ➕ Kategori Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Add Gider Modal -->
    <?php if (hasActionPermission('add_gider')): ?>
    <div class="modal" id="addGiderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">📋 Yeni Gider Ekle</h3>
                <button class="modal-close" onclick="closeModal('addGiderModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addGiderForm">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Tarih</label>
                            <input type="date" name="tarih" class="form-input" required 
                                   value="<?= isset($_POST['tarih']) ? htmlspecialchars($_POST['tarih']) : date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-select" required>
                                <option value="">Kategori seçin...</option>
                                <?php foreach ($kategoriler_db as $kategori): ?>
                                    <option value="<?= htmlspecialchars($kategori['kategori_adi']) ?>" 
                                            <?= (isset($_POST['kategori']) && $_POST['kategori'] == $kategori['kategori_adi']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kategori['icon']) ?> <?= htmlspecialchars($kategori['kategori_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Açıklama</label>
                        <input type="text" name="aciklama" class="form-input" 
                               placeholder="Örn: Elektrik Faturası" required 
                               value="<?= isset($_POST['aciklama']) ? htmlspecialchars($_POST['aciklama']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Miktar (₺)</label>
                        <input type="number" name="miktar" class="form-input" 
                               placeholder="1200" min="1" step="0.01" required 
                               value="<?= isset($_POST['miktar']) ? htmlspecialchars($_POST['miktar']) : '' ?>">
                    </div>
                    
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <strong>ℹ️ Bilgi:</strong> Gider eklendikten sonra tüm sakinler tarafından görülebilir.
                        <?php if (empty($kategoriler_db)): ?>
                            <br><strong>⚠️ Uyarı:</strong> Önce kategori eklemelisiniz.
                        <?php endif; ?>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addGiderModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_gider" class="btn btn-primary" <?= empty($kategoriler_db) ? 'disabled' : '' ?>>
                            📋 Gider Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Delete Gider Modal -->
    <div class="modal" id="deleteGiderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Gider Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteGiderModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu gideri silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz.
                </div>
                
                <form method="POST" id="deleteGiderForm">
                    <input type="hidden" name="gider_id" id="deleteGiderId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteGiderModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_gider" class="btn btn-danger">
                            🗑️ Evet, Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Kategori Modal -->
    <div class="modal" id="deleteKategoriModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Kategori Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteKategoriModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteKategoriMessage">Bu kategoriyi silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu kategoriye ait giderler varsa kategori silinemez.
                </div>
                
                <form method="POST" id="deleteKategoriForm">
                    <input type="hidden" name="kategori_id" id="deleteKategoriId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteKategoriModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_kategori" class="btn btn-danger">
                            🗑️ Evet, Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        let currentSelectedIcon = '📋';
        
        function toggleIconPicker() {
            const picker = document.getElementById('iconPicker');
            const modal = document.getElementById('addKategoriModal');
            const modalContent = modal.querySelector('.modal-content');
            const isVisible = picker.style.display === 'block';
            
            if (isVisible) {
                picker.style.display = 'none';
                // Modal'ı normale döndür
                modalContent.style.height = 'auto';
                modalContent.style.maxHeight = 'none';
            } else {
                picker.style.display = 'block';
                // Modal'ı sadece emoji'ler sığacak kadar büyüt
                modalContent.style.height = 'auto';
                modalContent.style.maxHeight = '600px'; // Emoji'ler için yeterli
                modalContent.style.overflowY = 'visible';
            }
        }
        
        function showIconCategory(category) {
            // Tüm kategori içeriklerini gizle
            document.querySelectorAll('.icon-category-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Seçilen kategoriyi göster
            const targetCategory = document.getElementById(category);
            if (targetCategory) {
                targetCategory.style.display = 'grid';
            }
            
            // Buton durumlarını güncelle
            document.querySelectorAll('.icon-category-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        function selectIcon(icon) {
            currentSelectedIcon = icon;
            document.getElementById('selectedIcon').textContent = icon;
            document.getElementById('iconInput').value = icon;
            
            // Seçilen icon'u vurgula
            document.querySelectorAll('.icon-item').forEach(item => {
                item.classList.remove('selected');
            });
            event.target.classList.add('selected');
            
            // Picker'ı kapat
            document.getElementById('iconPicker').style.display = 'none';
        }
        
        // Dışarı tıklandığında picker'ı kapat
        document.addEventListener('click', function(event) {
            const iconSelector = document.querySelector('.icon-selector');
            const picker = document.getElementById('iconPicker');
            
            if (iconSelector && !iconSelector.contains(event.target) && picker) {
                picker.style.display = 'none';
            }
        });
        
        // Keyboard desteği ekle
        document.addEventListener('keydown', function(e) {
            const picker = document.getElementById('iconPicker');
            if (picker && picker.style.display === 'block') {
                // Escape ile picker'ı kapat
                if (e.key === 'Escape') {
                    picker.style.display = 'none';
                    e.preventDefault();
                }
                
                // Arrow keys ile kategori değiştirme
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    const activeBtn = document.querySelector('.icon-category-btn.active');
                    const buttons = Array.from(document.querySelectorAll('.icon-category-btn'));
                    const currentIndex = buttons.indexOf(activeBtn);
                    
                    let newIndex;
                    if (e.key === 'ArrowLeft') {
                        newIndex = currentIndex > 0 ? currentIndex - 1 : buttons.length - 1;
                    } else {
                        newIndex = currentIndex < buttons.length - 1 ? currentIndex + 1 : 0;
                    }
                    
                    buttons[newIndex].click();
                    e.preventDefault();
                }
            }
        });
        
        // Kategori adı girildiğinde otomatik icon önerisi (geliştirilmiş)
        document.querySelector('input[name="kategori_adi"]')?.addEventListener('input', function(e) {
            const kategoriAdi = e.target.value.toLowerCase();
            let suggestedIcon = '📋';
            
            // Geliştirilmiş otomatik icon önerileri
            const iconSuggestions = {
                'elektrik': '⚡',
                'fatura': '⚡',
                'temizlik': '🧹',
                'temiz': '🧹',
                'bakım': '🔧',
                'tamir': '🔧',
                'onarım': '🔧',
                'güvenlik': '🛡️',
                'güven': '🛡️',
                'asansör': '🏗️',
                'elevator': '🏗️',
                'bahçe': '🌿',
                'peyzaj': '🌿',
                'çiçek': '🌺',
                'bitki': '🌱',
                'su': '💧',
                'water': '💧',
                'ısı': '🔥',
                'ısıtma': '🔥',
                'heat': '🔥',
                'park': '🚗',
                'oto': '🚗',
                'araç': '🚗',
                'internet': '📞',
                'wifi': '📞',
                'telefon': '📞',
                'gaz': '🔥',
                'yakıt': '⛽',
                'benzin': '⛽',
                'sigort': '🛡️',
                'vergi': '📄',
                'mali': '💰',
                'muhaseb': '📊',
                'kırtasiye': '📝',
                'ofis': '📋',
                'toplantı': '👥',
                'eğitim': '📚',
                'sağlık': '🏥'
            };
            
            // Kategori adında geçen kelimeler için icon önerisi
            for (const [keyword, icon] of Object.entries(iconSuggestions)) {
                if (kategoriAdi.includes(keyword)) {
                    suggestedIcon = icon;
                    break;
                }
            }
            
            // Sadece default icon seçiliyse önerilen icon'u ata
            if (currentSelectedIcon === '📋') {
                selectIcon(suggestedIcon);
            }
        });
        
        // Form validation
        document.getElementById('addKategoriForm')?.addEventListener('submit', function(e) {
            const kategoriAdi = document.querySelector('input[name="kategori_adi"]').value.trim();
            const iconInput = document.getElementById('iconInput');
            
            if (!kategoriAdi) {
                e.preventDefault();
                alert('Kategori adı gereklidir!');
                return;
            }
            
            if (!iconInput.value) {
                iconInput.value = '📋'; // Default icon
            }
        });
        
        // Modal açma fonksiyonu (geliştirilmiş)
        function openModal(modalId) {
            if (modalId === 'addKategoriModal') {
                // Form'u sıfırla
                document.getElementById('addKategoriForm').reset();
                selectIcon('📋');
                
                // İlk input'a odaklan
                setTimeout(() => {
                    const firstInput = document.querySelector('#addKategoriModal input[name="kategori_adi"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                }, 100);
            }
            
            // Modal göster
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Gider arama
        document.getElementById('giderSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.gider-row');
            
            rows.forEach(row => {
                const searchData = row.dataset.search;
                if (searchData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // Filtreleme
        function filterGiderler() {
            const kategoriFilter = document.getElementById('kategoriFilter') ? document.getElementById('kategoriFilter').value : '';
            const ayFilter = document.getElementById('ayFilter').value;
            const rows = document.querySelectorAll('.gider-row');
            
            const now = new Date();
            const currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
            const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastMonthStr = lastMonth.getFullYear() + '-' + String(lastMonth.getMonth() + 1).padStart(2, '0');
            const threeMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 3, 1);
            
            rows.forEach(row => {
                const kategoriMatch = !kategoriFilter || row.dataset.kategori === kategoriFilter;
                let ayMatch = true;
                
                if (ayFilter) {
                    const giderTarih = new Date(row.dataset.tarih);
                    const giderMonth = giderTarih.getFullYear() + '-' + String(giderTarih.getMonth() + 1).padStart(2, '0');
                    
                    switch (ayFilter) {
                        case 'bu-ay':
                            ayMatch = giderMonth === currentMonth;
                            break;
                        case 'gecen-ay':
                            ayMatch = giderMonth === lastMonthStr;
                            break;
                        case 'son-3-ay':
                            ayMatch = giderTarih >= threeMonthsAgo;
                            break;
                    }
                }
                
                if (kategoriMatch && ayMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Filtreleme sonrası toplam hesapla
            updateFilteredStats();
        }
        
        // Filtrelenmiş istatistikleri güncelle
        function updateFilteredStats() {
            const visibleRows = document.querySelectorAll('.gider-row[style=""], .gider-row:not([style])');
            let toplam = 0;
            
            visibleRows.forEach(row => {
                const miktar = row.querySelector('td:nth-child(4)').textContent;
                const numericValue = parseFloat(miktar.replace(/[^0-9,.-]/g, '').replace(',', '.'));
                if (!isNaN(numericValue)) {
                    toplam += numericValue;
                }
            });
            
            // Filtreleme bilgisini göster
            const existingInfo = document.getElementById('filter-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            if (visibleRows.length !== document.querySelectorAll('.gider-row').length) {
                const info = document.createElement('div');
                info.id = 'filter-info';
                info.className = 'alert alert-info';
                info.style.marginBottom = '20px';
                info.innerHTML = `
                    <strong>📊 Filtreleme Sonucu:</strong> 
                    ${visibleRows.length} gider gösteriliyor - 
                    Toplam: <strong>${new Intl.NumberFormat('tr-TR', {style: 'currency', currency: 'TRY'}).format(toplam)}</strong>
                `;
                
                document.querySelector('.table-responsive').parentNode.insertBefore(info, document.querySelector('.table-responsive'));
            }
        }
        
        // Gider silme
        function deleteGider(id, aciklama) {
            document.getElementById('deleteGiderId').value = id;
            document.getElementById('deleteMessage').innerHTML = 
                `<strong>"${aciklama}"</strong> adlı gideri silmek istediğinizden emin misiniz?`;
            openModal('deleteGiderModal');
        }
        
        // Kategori silme
        function deleteKategori(id, kategoriAdi) {
            document.getElementById('deleteKategoriId').value = id;
            document.getElementById('deleteKategoriMessage').innerHTML = 
                `<strong>"${kategoriAdi}"</strong> kategorisini silmek istediğinizden emin misiniz?`;
            openModal('deleteKategoriModal');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + E - Export
            if (e.ctrlKey && e.key === 'e') {
                e.preventDefault();
                exportGiderler();
            }
            
            // Ctrl + N - Yeni gider
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('addGiderModal');
            }
            
            // Ctrl + K - Kategori yönetimi
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                openModal('addKategoriModal');
            }
            
            // Escape - Modal kapat
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal[style*="flex"]');
                openModals.forEach(modal => {
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // Export fonksiyonu
        function exportGiderler() {
            const visibleRows = document.querySelectorAll('.gider-row[style=""], .gider-row:not([style])');
            let csvContent = "Tarih,Açıklama,Kategori,Miktar\n";
            
            visibleRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const tarih = cells[0].textContent.trim();
                const aciklama = cells[1].textContent.trim().replace(/[^\w\s]/g, '');
                const kategori = cells[2].textContent.trim();
                const miktar = cells[3].textContent.trim();
                
                csvContent += `"${tarih}","${aciklama}","${kategori}","${miktar}"\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `giderler_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }
        
        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Kategori sayısını kontrol et
            const kategoriCount = <?= count($kategoriler_db) ?>;
            const giderButton = document.querySelector('button[onclick="openModal(\'addGiderModal\')"]');
            
            if (kategoriCount === 0 && giderButton) {
                giderButton.style.opacity = '0.6';
                giderButton.title = 'Önce kategori eklemelisiniz';
            }
        });
    </script>
</body>
</html>