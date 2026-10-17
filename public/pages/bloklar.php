<?php
// pages/bloklar.php - Blok Yönetimi Sayfası
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

// Sadece yönetici erişebilir
if ($current_user['role'] !== 'yonetici') {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Site ID'sini al
$site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : (isset($_SESSION['selected_site_id']) ? $_SESSION['selected_site_id'] : 1);

// Site bilgisini al
try {
    $stmt = $db->prepare("SELECT * FROM siteler WHERE id = ? AND status = 1");
    $stmt->execute([$site_id]);
    $site_info = $stmt->fetch();
    
    if (!$site_info) {
        header('Location: siteler.php?error=site_bulunamadi');
        exit();
    }
} catch (Exception $e) {
    header('Location: siteler.php?error=site_hatasi');
    exit();
}

// Sayfa başlığını ayarla
$page_title = $site_info['site_adi'] . ' - Blok Yönetimi';

// Daire tipi text fonksiyonu
function getDaireTipiText($oda_sayisi) {
    switch($oda_sayisi) {
        case 1: return '1+0 (Studio)';
        case 2: return '1+1';
        case 3: return '2+1';
        case 4: return '3+1';
        case 5: return '4+1';
        case 6: return '5+1';
        default: return $oda_sayisi . ' Odalı';
    }
}

// Blok ekleme işlemi - YENİ GELİŞMİŞ VERSİYON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_blok'])) {
    $blok_adi = sanitize($_POST['blok_adi']);
    $blok_kodu = sanitize($_POST['blok_kodu']);
    $kat_sayisi = (int)$_POST['kat_sayisi'];
    $daire_sayisi = (int)$_POST['daire_sayisi'];
    $aciklama = sanitize($_POST['aciklama']);
    
    // Yeni daire ayarları
    $default_oda_sayisi = (int)($_POST['default_oda_sayisi'] ?? 3);
    $default_metrekare = (float)($_POST['default_metrekare'] ?? 120.00);
    $daire_per_kat = (int)($_POST['daire_per_kat'] ?? 4);
    $numbering_format = $_POST['numbering_format'] ?? 'sequential';
    $mixed_types = isset($_POST['mixed_types']) ? true : false;
    
    $errors = validateRequired([
        'blok_adi' => 'Blok Adı',
        'blok_kodu' => 'Blok Kodu'
    ], $_POST);
    
    if (empty($errors)) {
        // Blok adı benzersizlik kontrolü (aynı site içinde)
        $stmt = $db->prepare("SELECT id FROM bloklar WHERE blok_adi = ? AND site_id = ? AND status = 1");
        $stmt->execute([$blok_adi, $site_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu blok adı bu sitede zaten kayıtlı';
        }
        
        // Blok kodu benzersizlik kontrolü (aynı site içinde)
        $stmt = $db->prepare("SELECT id FROM bloklar WHERE blok_kodu = ? AND site_id = ? AND status = 1");
        $stmt->execute([$blok_kodu, $site_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu blok kodu bu sitede zaten kayıtlı';
        }
    }
    
    if (empty($errors)) {
        try {
            // Transaction başlat
            $db->beginTransaction();
            
            // Önce bloğu ekle
            $stmt = $db->prepare("
                INSERT INTO bloklar (site_id, blok_adi, blok_kodu, kat_sayisi, daire_sayisi, aciklama) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$site_id, $blok_adi, $blok_kodu, $kat_sayisi, $daire_sayisi, $aciklama]);
            
            // Yeni eklenen blok ID'sini al
            $new_blok_id = $db->lastInsertId();
            
            // Otomatik daire oluşturma
            if ($daire_sayisi > 0) {
                // Kat başına daire sayısını hesapla
                if ($daire_per_kat > 0) {
                    $calculated_daire_per_kat = $daire_per_kat;
                } else {
                    $calculated_daire_per_kat = $kat_sayisi > 0 ? ceil($daire_sayisi / $kat_sayisi) : 4;
                }
                
                for ($i = 1; $i <= $daire_sayisi; $i++) {
                    // Daire numarası formatını belirle
                    if ($numbering_format === 'floor_based') {
                        // Kat bazlı: 101, 102, 201, 202...
                        $kat = ceil($i / $calculated_daire_per_kat);
                        $kat_daire_no = (($i - 1) % $calculated_daire_per_kat) + 1;
                        $daire_no = $kat . sprintf('%02d', $kat_daire_no);
                    } else {
                        // Sıralı: 1, 2, 3, 4...
                        $daire_no = (string)$i;
                        $kat = ceil($i / $calculated_daire_per_kat);
                    }
                    
                    // Karışık tipler seçilirse, farklı katlarda farklı daire tipleri
                    if ($mixed_types) {
                        // 1. kat: Studio ve 1+1
                        // 2-3. kat: 2+1
                        // 4+ kat: 3+1 ve 4+1
                        if ($kat == 1) {
                            $oda_sayisi = ($i % 2 == 1) ? 1 : 2; // 1+0, 1+1 karışık
                            $metrekare = ($oda_sayisi == 1) ? 60.00 : 80.00;
                        } elseif ($kat <= 3) {
                            $oda_sayisi = 3; // 2+1
                            $metrekare = 120.00;
                        } else {
                            $oda_sayisi = ($i % 2 == 1) ? 4 : 5; // 3+1, 4+1 karışık
                            $metrekare = ($oda_sayisi == 4) ? 150.00 : 180.00;
                        }
                    } else {
                        // Aynı tip
                        $oda_sayisi = $default_oda_sayisi;
                        $metrekare = $default_metrekare;
                    }
                    
                    // Daireyi ekle
                    $stmt_daire = $db->prepare("
                        INSERT INTO daireler (site_id, blok_id, daire_no, kat, oda_sayisi, metrekare, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt_daire->execute([
                        $site_id, 
                        $new_blok_id, 
                        $daire_no, 
                        $kat, 
                        $oda_sayisi, 
                        $metrekare
                    ]);
                }
            }
            
            // Transaction'ı onayla
            $db->commit();
            
            if ($mixed_types) {
                showAlert("Blok ve {$daire_sayisi} adet karışık tipte daire başarıyla oluşturuldu", 'success');
            } else {
                $daire_tipi_text = getDaireTipiText($default_oda_sayisi);
                showAlert("Blok ve {$daire_sayisi} adet {$daire_tipi_text} daire başarıyla oluşturuldu", 'success');
            }
            $_POST = [];
            
        } catch (Exception $e) {
            // Hata durumunda geri al
            $db->rollback();
            showAlert('Blok eklenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    } else {
        showAlert(implode('<br>', $errors), 'error');
    }
}

// Blok silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blok'])) {
    $blok_id = (int)$_POST['blok_id'];
    
    try {
        // Blokta sakinler var mı kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) as sakin_count FROM sakinler WHERE blok_id = ? AND status = 1");
        $stmt->execute([$blok_id]);
        $sakin_count = $stmt->fetch()['sakin_count'];
        
        if ($sakin_count > 0) {
            showAlert('Bu blokta sakinler bulunduğu için silinemez', 'error');
        } else {
            $stmt = $db->prepare("UPDATE bloklar SET status = 0 WHERE id = ? AND site_id = ?");
            $stmt->execute([$blok_id, $site_id]);
            
            if ($stmt->rowCount() > 0) {
                showAlert('Blok başarıyla silindi', 'success');
            } else {
                showAlert('Blok bulunamadı', 'error');
            }
        }
    } catch (Exception $e) {
        showAlert('Blok silinirken hata oluştu', 'error');
    }
}

// Blokları getir
try {
    $stmt = $db->prepare("
        SELECT b.*, 
               (SELECT COUNT(*) FROM sakinler WHERE blok_id = b.id AND status = 1) as sakin_sayisi,
               (SELECT COUNT(*) FROM aidatlar WHERE blok_id = b.id AND status = 1) as aidat_sayisi
        FROM bloklar b 
        WHERE b.site_id = ? AND b.status = 1 
        ORDER BY b.blok_adi
    ");
    $stmt->execute([$site_id]);
    $bloklar = $stmt->fetchAll();
} catch (Exception $e) {
    $bloklar = [];
    showAlert('Bloklar yüklenirken hata oluştu', 'error');
}

// Tüm siteleri al (site seçimi için)
try {
    $stmt = $db->prepare("SELECT * FROM siteler WHERE status = 1 ORDER BY site_adi");
    $stmt->execute();
    $all_sites = $stmt->fetchAll();
} catch (Exception $e) {
    $all_sites = [];
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
                    <h1 class="page-title">🏢 <?= htmlspecialchars($site_info['site_adi']) ?> - Bloklar</h1>
                    <nav class="breadcrumb">
                        <a href="siteler.php">Siteler</a> 
                        <span>/</span> 
                        <span><?= htmlspecialchars($site_info['site_adi']) ?></span>
                    </nav>
                </div>
                
                <div class="topbar-actions">
                    <!-- Site Seçim Dropdown'u -->
                    <?php if (count($all_sites) > 1): ?>
                    <select class="form-select" onchange="changeSite(this.value)" style="margin-right: 12px;">
                        <?php foreach ($all_sites as $site): ?>
                            <option value="<?= $site['id'] ?>" <?= $site['id'] == $site_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($site['site_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary" onclick="openModal('addBlokModal')">
                        ➕ Yeni Blok Ekle
                    </button>
                    
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


                <!-- Stats Cards -->
                <div class="stats-grid">
                    <?php
                    $toplam_blok = count($bloklar);
                    $toplam_sakin = array_sum(array_column($bloklar, 'sakin_sayisi'));
                    $toplam_daire = array_sum(array_column($bloklar, 'daire_sayisi'));
                    $occupancy = $toplam_daire > 0 ? round(($toplam_sakin / $toplam_daire) * 100) : 0;
                    
                    // Dolu ve boş blok sayıları
                    $dolu_blok = 0;
                    $bos_blok = 0;
                    foreach ($bloklar as $blok) {
                        if ($blok['sakin_sayisi'] > 0) {
                            $dolu_blok++;
                        } else {
                            $bos_blok++;
                        }
                    }
                    ?>
                    
                    <div class="stat-card primary clickable" onclick="filterBloklar('all')" id="totalBlokCard" title="Tüm blokları göster">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Blok</div>
                        </div>
                        <div class="stat-value"><?= $toplam_blok ?></div>
                        <div class="stat-change positive">Bu sitede</div>
                    </div>
                    
                    <div class="stat-card success clickable" onclick="goToSakinler()" id="sakinCard" title="Sakinler sayfasına git">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Sakin</div>
                        </div>
                        <div class="stat-value"><?= $toplam_sakin ?></div>
                        <div class="stat-change positive">Tüm bloklar</div>
                    </div>
                    
                    <div class="stat-card info clickable" onclick="goToDaireler()" id="daireCard" title="Daireler sayfasına git">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Daire</div>
                        </div>
                        <div class="stat-value"><?= $toplam_daire ?></div>
                        <div class="stat-change positive">Planlanan</div>
                    </div>
                    
                    <div class="stat-card warning clickable" onclick="filterBloklar('doluluk')" id="dolulukCard" title="Doluluk oranına göre sırala">
                        <div class="stat-header">
                            <div class="stat-title">Doluluk Oranı</div>
                        </div>
                        <div class="stat-value"><?= $occupancy ?>%</div>
                        <div class="stat-change">
                            <?= $dolu_blok ?> dolu, <?= $bos_blok ?> boş blok
                        </div>
                    </div>
                </div>

                <!-- Bloklar Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Blok Listesi</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bloklar)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏢</div>
                                <h3>Bu sitede henüz blok eklenmemiş</h3>
                                <p>İlk bloğu eklemek için yukarıdaki "Yeni Blok Ekle" butonunu kullanın.</p>
                                <button class="btn btn-primary" onclick="openModal('addBlokModal')" style="margin-top: 16px;">
                                    ➕ İlk Bloğu Ekle
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="bloks-grid">
                                <?php foreach ($bloklar as $blok): ?>
                                <div class="blok-card">
                                    <div class="blok-header">
                                        <div class="blok-icon">🏢</div>
                                        <div class="blok-title">
                                            <h4><?= htmlspecialchars($blok['blok_adi']) ?></h4>
                                            <p class="blok-code">Kod: <?= htmlspecialchars($blok['blok_kodu']) ?></p>
                                        </div>
                                        <div class="blok-actions">
                                            <a href="sakinler.php?site_id=<?= $site_id ?>&blok_id=<?= $blok['id'] ?>" 
                                               class="btn btn-primary btn-sm">
                                                👥 Sakinleri Görüntüle
                                            </a>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteBlok(<?= $blok['id'] ?>, '<?= htmlspecialchars($blok['blok_adi']) ?>')"
                                                    title="Bloğu Sil">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="blok-stats">
                                        <div class="stat-item">
                                            <span class="stat-label">Katlar</span>
                                            <span class="stat-value"><?= $blok['kat_sayisi'] ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Daireler</span>
                                            <span class="stat-value"><?= $blok['daire_sayisi'] ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Sakinler</span>
                                            <span class="stat-value"><?= $blok['sakin_sayisi'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($blok['aciklama']): ?>
                                    <div class="blok-description">
                                        <p><?= htmlspecialchars($blok['aciklama']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="blok-footer">
                                        <span class="creation-date">
                                            📅 <?= formatDate($blok['created_at']) ?>
                                        </span>
                                        <div class="occupancy-bar">
                                            <?php
                                            $blok_occupancy = $blok['daire_sayisi'] > 0 ? 
                                                round(($blok['sakin_sayisi'] / $blok['daire_sayisi']) * 100) : 0;
                                            ?>
                                            <div class="occupancy-fill" style="width: <?= $blok_occupancy ?>%"></div>
                                            <span class="occupancy-text"><?= $blok_occupancy ?>% dolu</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Blok Modal -->
<!-- Add Blok Modal - GÜNCELLENMİŞ VERSİYON -->
    <div class="modal" id="addBlokModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">🏢 Yeni Blok Ekle</h3>
                <button class="modal-close" onclick="closeModal('addBlokModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addBlokForm">
                    <!-- Blok Temel Bilgileri -->
                    <div class="form-section">
                        <h4 class="section-title">📋 Blok Bilgileri</h4>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label class="form-label">Blok Adı</label>
                                <input type="text" name="blok_adi" class="form-input" 
                                       placeholder="Örn: G Blok" required 
                                       value="<?= isset($_POST['blok_adi']) ? htmlspecialchars($_POST['blok_adi']) : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Blok Kodu</label>
                                <input type="text" name="blok_kodu" class="form-input" 
                                       placeholder="Örn: G" required 
                                       value="<?= isset($_POST['blok_kodu']) ? htmlspecialchars($_POST['blok_kodu']) : '' ?>">
                            </div>
                        </div>
                        
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label class="form-label">Kat Sayısı</label>
                                <input type="number" name="kat_sayisi" class="form-input" 
                                       placeholder="Örn: 5" min="1" max="50" 
                                       value="<?= isset($_POST['kat_sayisi']) ? (int)$_POST['kat_sayisi'] : '5' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Toplam Daire Sayısı</label>
                                <input type="number" name="daire_sayisi" class="form-input" 
                                       placeholder="Örn: 20" min="1" max="200" required
                                       value="<?= isset($_POST['daire_sayisi']) ? (int)$_POST['daire_sayisi'] : '' ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Daire Varsayılan Ayarları -->
                    <div class="form-section">
                        <h4 class="section-title">🏠 Varsayılan Daire Ayarları</h4>
                        <div class="grid grid-3">
                            <div class="form-group">
                                <label class="form-label">Daire Tipi</label>
                                <select name="default_oda_sayisi" class="form-select">
                                    <option value="1">1+0 (Studio)</option>
                                    <option value="2">1+1</option>
                                    <option value="3" selected>2+1</option>
                                    <option value="4">3+1</option>
                                    <option value="5">4+1</option>
                                    <option value="6">5+1</option>
                                </select>
                                <small class="form-hint">Sonradan her daire için ayrı ayrı değiştirilebilir</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Varsayılan Metrekare</label>
                                <input type="number" name="default_metrekare" class="form-input" 
                                       placeholder="120" min="30" max="500" step="0.01"
                                       value="120.00">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Kat Başına Daire</label>
                                <input type="number" name="daire_per_kat" class="form-input" 
                                       placeholder="4" min="1" max="20"
                                       value="4">
                                <small class="form-hint">Otomatik hesaplanır</small>
                            </div>
                        </div>
                    </div>

                    <!-- Gelişmiş Ayarlar (İsteğe Bağlı) -->
                    <div class="form-section">
                        <h4 class="section-title">⚙️ Gelişmiş Ayarlar</h4>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="mixed_types" name="mixed_types" value="1">
                                <label for="mixed_types">Karışık daire tipleri (Farklı katlarda farklı tipler)</label>
                            </div>
                        </div>

                        <div id="mixed_options" style="display: none;">
                            <div class="alert alert-info">
                                <strong>💡 Bilgi:</strong> Bu seçeneği işaretlerseniz, blok oluşturulduktan sonra daireler sayfasından her daireyi ayrı ayrı düzenleyebilirsiniz.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Daire Numarası Formatı</label>
                            <select name="numbering_format" class="form-select">
                                <option value="sequential">Sıralı (1, 2, 3, 4...)</option>
                                <option value="floor_based">Kat Bazlı (101, 102, 201, 202...)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Açıklama (Opsiyonel)</label>
                            <textarea name="aciklama" class="form-input" rows="2" 
                                      placeholder="Blok hakkında ek bilgiler..."><?= isset($_POST['aciklama']) ? htmlspecialchars($_POST['aciklama']) : '' ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addBlokModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_blok" class="btn btn-primary">
                            🏢 Blok ve Daireleri Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteBlokModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Blok Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteBlokModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu bloğu silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz. Blok ve tüm verileri silinecek.
                </div>
                
                <form method="POST" id="deleteBlokForm">
                    <input type="hidden" name="blok_id" id="deleteBlokId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteBlokModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_blok" class="btn btn-danger">
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
        // Aktif filtre durumu
        let activeFilter = 'all';

        // Blok verilerini JavaScript'e aktar (PHP'den)
        window.blokVerileri = <?= json_encode($bloklar) ?>;
        window.siteId = <?= $site_id ?>;

        // Blokları filtrele
        function filterBloklar(filterType) {
            const blokCards = document.querySelectorAll('.blok-card');
            const statCards = document.querySelectorAll('.stat-card.clickable');
            
            // Eski aktif kartı temizle
            statCards.forEach(card => card.classList.remove('active'));
            
            // Yeni aktif kartı işaretle
            let activeCardId = '';
            switch(filterType) {
                case 'all':
                    activeCardId = 'totalBlokCard';
                    break;
                case 'dolu':
                    activeCardId = 'doluBlokCard';
                    break;
                case 'bos':
                    activeCardId = 'bosBlokCard';
                    break;
                case 'doluluk':
                    activeCardId = 'dolulukCard';
                    break;
            }
            
            if (activeCardId && document.getElementById(activeCardId)) {
                document.getElementById(activeCardId).classList.add('active');
            }
            
            activeFilter = filterType;
            
            // Blokları filtrele ve sırala
            const blokContainer = document.querySelector('.bloks-grid');
            const blokCardsArray = Array.from(blokCards);
            
            if (filterType === 'doluluk') {
                // Doluluk oranına göre sırala (yüksekten düşüğe)
                blokCardsArray.sort((a, b) => {
                    const aData = window.blokVerileri.find(blok => blok.id == a.dataset.blokId);
                    const bData = window.blokVerileri.find(blok => blok.id == b.dataset.blokId);
                    
                    const aOccupancy = aData.daire_sayisi > 0 ? (aData.sakin_sayisi / aData.daire_sayisi) * 100 : 0;
                    const bOccupancy = bData.daire_sayisi > 0 ? (bData.sakin_sayisi / bData.daire_sayisi) * 100 : 0;
                    
                    return bOccupancy - aOccupancy;
                });
                
                // Sıralanmış kartları yeniden yerleştir
                blokCardsArray.forEach(card => {
                    blokContainer.appendChild(card);
                    card.style.display = '';
                    card.classList.add('filter-show');
                });
                
                // Doluluk animasyonu ekle
                blokCards.forEach(card => {
                    const occupancyBar = card.querySelector('.occupancy-fill');
                    if (occupancyBar) {
                        const width = occupancyBar.style.width;
                        occupancyBar.style.width = '0%';
                        setTimeout(() => {
                            occupancyBar.style.width = width;
                        }, 100);
                    }
                });
                
            } else {
                // Normal filtreleme
                blokCards.forEach(card => {
                    const blokData = window.blokVerileri.find(blok => blok.id == card.dataset.blokId);
                    let shouldShow = false;
                    
                    switch(filterType) {
                        case 'all':
                            shouldShow = true;
                            break;
                        case 'dolu':
                            shouldShow = blokData && blokData.sakin_sayisi > 0;
                            break;
                        case 'bos':
                            shouldShow = blokData && blokData.sakin_sayisi === 0;
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
            }
            
            // Filtreleme durumunu göster
            updateFilterStatus(filterType);
        }

        // Sakinler sayfasına git
        function goToSakinler() {
            const sakinCard = document.getElementById('sakinCard');
    
            // Kartı aktif yap ama loading animasyonu ekleme
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
            sakinCard.classList.add('active');
            
            // Derhal yönlendir (gecikme olmadan)
            window.location.href = `sakinler.php?site_id=${window.siteId}`;
        }

        // Daireler sayfasına git
        function goToDaireler() {
            const daireCard = document.getElementById('daireCard');
    
            // Kartı aktif yap ama loading animasyonu ekleme
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
            daireCard.classList.add('active');
            
            // Derhal yönlendir (gecikme olmadan)
            window.location.href = `daireler.php?site_id=${window.siteId}`;
        }

        // Blok kartına data attribute ekleme fonksiyonu
        function addBlokDataAttributes() {
            const blokCards = document.querySelectorAll('.blok-card');
            blokCards.forEach((card, index) => {
                const blokData = window.blokVerileri[index];
                if (blokData) {
                    card.dataset.blokId = blokData.id;
                    card.dataset.sakinSayisi = blokData.sakin_sayisi;
                    card.dataset.daireSayisi = blokData.daire_sayisi;
                    card.dataset.search = `${blokData.blok_adi} ${blokData.blok_kodu}`.toLowerCase();
                }
            });
        }

        // Filtre durumunu güncelle
        function updateFilterStatus(filterType) {
            const cardTitle = document.querySelector('.card-title');
            const totalCards = document.querySelectorAll('.blok-card').length;
            const visibleCards = document.querySelectorAll('.blok-card[style=""], .blok-card:not([style*="none"])').length;
            
            let statusText = '';
            switch(filterType) {
                case 'all':
                    statusText = 'Tüm Bloklar';
                    break;
                case 'dolu':
                    statusText = `Dolu Bloklar (${visibleCards} adet)`;
                    break;
                case 'bos':
                    statusText = `Boş Bloklar (${visibleCards} adet)`;
                    break;
                case 'doluluk':
                    statusText = 'Doluluk Oranına Göre Sıralandı';
                    break;
            }
            
            if (cardTitle) {
                const originalText = 'Blok Listesi';
                cardTitle.innerHTML = `${originalText} ${statusText ? '- ' + statusText : ''}`;
                
                if (filterType !== 'all') {
                    cardTitle.classList.add('filter-active');
                } else {
                    cardTitle.classList.remove('filter-active');
                }
            }
        }

        // Arama fonksiyonunu ekle (opsiyonel)
        function setupBlokSearch() {
            // Eğer arama input'u varsa
            const searchInput = document.getElementById('blokSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const blokCards = document.querySelectorAll('.blok-card');
                    
                    blokCards.forEach(card => {
                        const searchData = card.dataset.search;
                        const passesSearch = searchData && searchData.includes(searchTerm);
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

        // Blok kartlarına hover animasyonu ekle
        function setupBlokCardAnimations() {
            const blokCards = document.querySelectorAll('.blok-card');
            
            blokCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    // Doluluk barını animasyon ile göster
                    const occupancyBar = this.querySelector('.occupancy-fill');
                    if (occupancyBar) {
                        const currentWidth = occupancyBar.style.width;
                        occupancyBar.style.transition = 'width 0.3s ease';
                        occupancyBar.style.width = currentWidth;
                    }
                });
            });
        }

        // Sayfa yüklendiğinde filtreleme sistemi hazırla
        document.addEventListener('DOMContentLoaded', function() {
            // Blok data attribute'larını ekle
            addBlokDataAttributes();
            
            // İlk durumda tüm blokları göster
            filterBloklar('all');
            
            // Arama sistemini kur
            setupBlokSearch();
            
            // Animasyonları kur
            setupBlokCardAnimations();
            
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
                
                // Click animasyonu
                card.addEventListener('mousedown', function() {
                    this.style.transform = 'translateY(-1px)';
                });
                
                card.addEventListener('mouseup', function() {
                    if (this.classList.contains('active')) {
                        this.style.transform = 'translateY(-2px)';
                    } else {
                        this.style.transform = 'translateY(-4px)';
                    }
                });
            });
        });

        // Klavye kısayolları (opsiyonel)
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        filterBloklar('all');
                        break;
                    case '2':
                        e.preventDefault();
                        goToSakinler();
                        break;
                    case '3':
                        e.preventDefault();
                        goToDaireler();
                        break;
                    case '4':
                        e.preventDefault();
                        filterBloklar('doluluk');
                        break;
                }
            }
        });
        // Modal fonksiyonları
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
        }

        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function changeSite(siteId) {
            window.location.href = `bloklar.php?site_id=${siteId}`;
        }

        function deleteBlok(id, name) {
            document.getElementById('deleteBlokId').value = id;
            document.getElementById('deleteMessage').textContent = 
                `"${name}" adlı bloğu silmek istediğinizden emin misiniz?`;
            openModal('deleteBlokModal');
        }

        // Modal dışına tıklandığında kapat
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
                e.target.style.display = 'none';
            }
            
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
            }
        });

        // Karışık tipler checkbox'ı için event listener - MEVCUT SCRIPT'İN SONUNA EKLEYİN
        document.addEventListener('DOMContentLoaded', function() {
            const mixedTypesCheckbox = document.getElementById('mixed_types');
            const mixedOptions = document.getElementById('mixed_options');
            
            if (mixedTypesCheckbox && mixedOptions) {
                mixedTypesCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        mixedOptions.style.display = 'block';
                    } else {
                        mixedOptions.style.display = 'none';
                    }
                });
            }
        });

        // Form validation (opsiyonel)
        function validateBlokForm() {
            const blokAdi = document.querySelector('input[name="blok_adi"]').value;
            const blokKodu = document.querySelector('input[name="blok_kodu"]').value;
            const daireSayisi = document.querySelector('input[name="daire_sayisi"]').value;
            
            if (!blokAdi.trim()) {
                alert('Blok adı zorunludur');
                return false;
            }
            
            if (!blokKodu.trim()) {
                alert('Blok kodu zorunludur');
                return false;
            }
            
            if (!daireSayisi || daireSayisi < 1) {
                alert('Daire sayısı en az 1 olmalıdır');
                return false;
            }
            
            return true;
        }

        // Form submit event'i - mevcut addBlokForm varsa önce kontrol edin
        const addBlokForm = document.getElementById('addBlokForm');
        if (addBlokForm) {
            addBlokForm.addEventListener('submit', function(e) {
                if (!validateBlokForm()) {
                    e.preventDefault();
                }
            });
        }
    </script>
    
    <style>
        /* Clickable stat cards */
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

        /* Loading animation for navigation cards */
        .stat-card.clickable.loading {
            position: relative;
            pointer-events: none;
        }

        .stat-card.clickable.loading::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            border-radius: inherit;
            opacity: 1;
            z-index: 1;
        }

        .stat-card.clickable.loading::after {
            content: '⏳';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            animation: loadingPulse 1s infinite;
            z-index: 2;
        }

        @keyframes loadingPulse {
            0%, 100% { 
                opacity: 1; 
                transform: translate(-50%, -50%) scale(1);
            }
            50% { 
                opacity: 0.5; 
                transform: translate(-50%, -50%) scale(1.1);
            }
        }

        /* Filtered blok cards animation */
        .blok-card.filter-show {
            animation: blokFilterShow 0.4s ease-out;
        }

        @keyframes blokFilterShow {
            from {
                opacity: 0;
                transform: translateY(15px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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

        .stat-card.info.clickable::after {
            content: '🏠';
        }

        .stat-card.warning.clickable::after {
            content: '📊';
        }

        /* Enhanced card title for filter status */
        .card-title.filter-active {
            color: var(--primary-color);
            font-weight: 600;
            position: relative;
        }

        .card-title.filter-active::before {
            content: '🔍';
            margin-right: 8px;
        }

        /* Occupancy bar enhanced animation */
        .occupancy-bar {
            position: relative;
            overflow: visible;
        }

        .occupancy-fill {
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .blok-card:hover .occupancy-fill {
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
        }

        /* Stagger animation for multiple cards */
        .blok-card.filter-show:nth-child(1) { animation-delay: 0.1s; }
        .blok-card.filter-show:nth-child(2) { animation-delay: 0.2s; }
        .blok-card.filter-show:nth-child(3) { animation-delay: 0.3s; }
        .blok-card.filter-show:nth-child(4) { animation-delay: 0.4s; }
        .blok-card.filter-show:nth-child(5) { animation-delay: 0.5s; }

        /* Keyboard shortcuts hint (optional) */
        .keyboard-hint {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 12px;
            font-size: 12px;
            color: var(--text-secondary);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .keyboard-hint.show {
            opacity: 0.8;
        }

        .keyboard-hint kbd {
            background: var(--border-color);
            border-radius: 4px;
            padding: 2px 6px;
            font-family: monospace;
            font-size: 11px;
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
                display: none; /* Hide click indicators on mobile */
            }
            
            .keyboard-hint {
                display: none; /* Hide keyboard shortcuts on mobile */
            }
            
            /* Better touch targets */
            .stat-card.clickable {
                min-height: 100px;
            }
            
            /* Reduce animation intensity on mobile */
            .blok-card.filter-show {
                animation-duration: 0.2s;
            }
        }

        /* Dark mode adjustments */
        @media (prefers-color-scheme: dark) {
            .stat-card.clickable.loading::before {
                background: rgba(0, 0, 0, 0.8);
            }
            
            .keyboard-hint {
                background: #1f2937;
                border-color: #374151;
            }
            
            .keyboard-hint kbd {
                background: #374151;
                color: #d1d5db;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .stat-card.clickable.active {
                border-width: 3px;
            }
            
            .stat-card.clickable:hover {
                outline: 2px solid;
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
            .blok-card.filter-show,
            .occupancy-fill {
                animation: none;
                transition: none;
            }
            
            .stat-card.clickable:hover {
                transform: none;
            }
        }
        /* Blok kartları için özel stiller */
        .bloks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .blok-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .blok-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .blok-header {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .blok-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            border-radius: 8px;
            flex-shrink: 0;
        }

        .blok-title {
            flex: 1;
        }

        .blok-title h4 {
            margin: 0 0 4px 0;
            font-size: 16px;
            color: var(--text-primary);
        }

        .blok-code {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .blok-actions {
            display: flex;
            gap: 6px;
            align-items: flex-start;
        }

        .blok-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .blok-stats .stat-item {
            text-align: center;
        }

        .blok-stats .stat-label {
            display: block;
            font-size: 11px;
            color: var(--text-secondary);
            margin-bottom: 2px;
        }

        .blok-stats .stat-value {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .blok-description {
            margin-bottom: 12px;
        }

        .blok-description p {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .blok-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .creation-date {
            color: var(--text-secondary);
        }

        .occupancy-bar {
            position: relative;
            width: 100px;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .occupancy-fill {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: var(--success-color);
            transition: width 0.3s ease;
        }

        .occupancy-text {
            position: absolute;
            top: -18px;
            right: 0;
            font-size: 10px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        /* Site info grid */
        .site-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
            min-width: 80px;
        }

        .info-value {
            color: var(--text-primary);
        }

        .info-value a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .bloks-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .blok-header {
                flex-direction: column;
                gap: 12px;
            }

            .blok-actions {
                align-self: stretch;
                justify-content: space-between;
            }

            .blok-stats {
                justify-content: space-around;
            }

            .site-info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .info-label {
                min-width: auto;
            }

            .blok-footer {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }

            .occupancy-bar {
                width: 100%;
            }
        }
        /* Modal Large - MEVCUT CSS'İN SONUNA EKLEYİN */
        .modal-large {
            max-width: 900px;
            width: 90%;
        }

        /* Form Sections */
        .form-section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-hint {
            font-size: 11px;
            color: var(--text-secondary);
            margin-top: 4px;
            display: block;
        }

        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
        }

        .checkbox-group label {
            font-size: 14px;
            color: var(--text-primary);
            cursor: pointer;
        }

        /* Grid 3 */
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        /* Alert Info */
        .alert.alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: #1e40af;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
        }

        /* Form Select */
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-background);
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Modal Large Responsive */
        @media (max-width: 768px) {
            .modal-large {
                width: 95%;
                max-width: none;
            }
            
            .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 14px;
            }
            
            .form-section {
                margin-bottom: 20px;
                padding-bottom: 16px;
            }
        }

        @media (max-width: 480px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>