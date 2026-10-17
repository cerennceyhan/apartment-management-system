<?php
// pages/carihareketler.php - Cari Hareketler ve Parçalı Ödeme sistemi + Gider Bilgilendirmeleri
$page_title = 'Cari Hareketler';
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

// Yetki kontrolü - Sadece sakinler ve yöneticiler görebilir
if ($current_user['role'] === 'sakin') {
    // Sakinler sadece kendi cari hareketlerini görebilir
    $can_view = true;
} elseif (hasPagePermission('aidatlar')) {
    // Yönetici/muhasebeci tüm cari hareketleri görebilir
    $can_view = true;
} else {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Parçalı ödeme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parcali_odeme'])) {
    if ($current_user['role'] !== 'sakin') {
        showAlert('Ödeme yetkisi sadece sakinlere aittir', 'error');
    } else {
        $aidat_id = (int)$_POST['aidat_id'];
        $odeme_miktari = (float)$_POST['odeme_miktari'];
        $aciklama = sanitize($_POST['aciklama'] ?? '');
        
        $errors = [];
        
        if (!$aidat_id) $errors[] = 'Geçersiz aidat seçimi';
        if (!validateNumeric($odeme_miktari, 0.01)) $errors[] = 'Geçerli bir ödeme miktarı girin';
        
        // Aidat kontrolü
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT * FROM aidatlar WHERE id = ? AND sakin_id = ? AND status = 1");
            $stmt->execute([$aidat_id, $current_user['sakin_id']]);
            $aidat = $stmt->fetch();
            
            if (!$aidat) {
                $errors[] = 'Aidat bulunamadı veya erişim yetkiniz yok';
            } elseif ($aidat['kalan_borc'] <= 0) {
                $errors[] = 'Bu aidatın borcu bulunmuyor';
            } elseif ($odeme_miktari > $aidat['kalan_borc']) {
                $errors[] = 'Ödeme miktarı kalan borçtan fazla olamaz';
            }
        }
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Cari harekete kaydet
                $stmt = $db->prepare("INSERT INTO cari_hareketler (sakin_id, aidat_id, odeme_miktari, odeme_tarihi, odeme_aciklamasi) VALUES (?, ?, ?, CURDATE(), ?)");
                $stmt->execute([$current_user['sakin_id'], $aidat_id, $odeme_miktari, $aciklama]);
                
                // Aidat tablosunu güncelle
                $yeni_toplam_odeme = $aidat['toplam_odeme'] + $odeme_miktari;
                $yeni_kalan_borc = $aidat['miktar'] - $yeni_toplam_odeme;
                $odendi = ($yeni_kalan_borc <= 0) ? 1 : 0;
                
                // Eğer tam ödeme yapıldıysa ödeme tarihini güncelle
                if ($odendi) {
                    $stmt = $db->prepare("UPDATE aidatlar SET toplam_odeme = ?, kalan_borc = 0.00, odendi = 1, odeme_tarihi = CURDATE() WHERE id = ?");
                    $stmt->execute([$yeni_toplam_odeme, $aidat_id]);
                } else {
                    $stmt = $db->prepare("UPDATE aidatlar SET toplam_odeme = ?, kalan_borc = ? WHERE id = ?");
                    $stmt->execute([$yeni_toplam_odeme, $yeni_kalan_borc, $aidat_id]);
                }
                
                $db->commit();
                
                if ($odendi) {
                    showAlert('Ödeme tamamlandı! Aidat tam olarak ödendi.', 'success');
                } else {
                    showAlert('Parçalı ödeme başarıyla yapıldı. Kalan borç: ' . formatMoney($yeni_kalan_borc), 'success');
                }
                
                $_POST = []; // Formu temizle
                
            } catch (Exception $e) {
                $db->rollBack();
                showAlert('Ödeme işlemi sırasında hata oluştu', 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Cari hareketleri getir
try {
    if ($current_user['role'] === 'sakin') {
        // Sakin sadece kendi hareketlerini görsün
        $stmt = $db->prepare("
            SELECT ch.*, a.miktar as aidat_miktari, a.ay, a.toplam_odeme, a.kalan_borc,
                   s.ad_soyad, s.daire_no
            FROM cari_hareketler ch
            JOIN aidatlar a ON ch.aidat_id = a.id
            JOIN sakinler s ON ch.sakin_id = s.id
            WHERE ch.sakin_id = ? AND ch.status = 1
            ORDER BY ch.odeme_tarihi DESC, ch.id DESC
        ");
        $stmt->execute([$current_user['sakin_id']]);
    } else {
        // Yönetici tüm hareketleri görsün
        $stmt = $db->prepare("
            SELECT ch.*, a.miktar as aidat_miktari, a.ay, a.toplam_odeme, a.kalan_borc,
                   s.ad_soyad, s.daire_no
            FROM cari_hareketler ch
            JOIN aidatlar a ON ch.aidat_id = a.id
            JOIN sakinler s ON ch.sakin_id = s.id
            WHERE ch.status = 1
            ORDER BY ch.odeme_tarihi DESC, ch.id DESC
        ");
        $stmt->execute();
    }
    $cari_hareketler = $stmt->fetchAll();
} catch (Exception $e) {
    $cari_hareketler = [];
    showAlert('Cari hareketler yüklenirken hata oluştu', 'error');
}

// Gider bilgilendirmelerini getir
try {
    if ($current_user['role'] === 'sakin') {
        // Sakinler tüm giderleri görebilir (şeffaflık için)
        $stmt = $db->prepare("
            SELECT g.*, gk.icon, 'gider' as tip
            FROM giderler g 
            LEFT JOIN gider_kategorileri gk ON g.kategori = gk.kategori_adi 
            WHERE g.status = 1 
            ORDER BY g.tarih DESC, g.id DESC
        ");
        $stmt->execute();
    } else {
        // Yönetici tüm giderleri görsün
        $stmt = $db->prepare("
            SELECT g.*, gk.icon, 'gider' as tip
            FROM giderler g 
            LEFT JOIN gider_kategorileri gk ON g.kategori = gk.kategori_adi 
            WHERE g.status = 1 
            ORDER BY g.tarih DESC, g.id DESC
        ");
        $stmt->execute();
    }
    $gider_bilgilendirmeleri = $stmt->fetchAll();
} catch (Exception $e) {
    $gider_bilgilendirmeleri = [];
}

// Tüm hareketleri birleştir ve tarih sırasına göre sırala
$tumun_hareketler = [];

// Cari hareketleri ekle
foreach ($cari_hareketler as $hareket) {
    $hareket['tip'] = 'odeme';
    $hareket['tarih_sira'] = $hareket['odeme_tarihi'];
    $hareket['zaman_damgasi'] = strtotime($hareket['created_at']);
    $tumun_hareketler[] = $hareket;
}

// Gider bilgilendirmelerini ekle
foreach ($gider_bilgilendirmeleri as $gider) {
    $gider['tip'] = 'gider';
    $gider['tarih_sira'] = $gider['tarih'];
    $gider['zaman_damgasi'] = strtotime($gider['created_at']);
    $tumun_hareketler[] = $gider;
}

// Tarihe göre sırala (en yeni en üstte)
usort($tumun_hareketler, function($a, $b) {
    $tarih_karsilastirma = strcmp($b['tarih_sira'], $a['tarih_sira']);
    if ($tarih_karsilastirma === 0) {
        return $b['zaman_damgasi'] - $a['zaman_damgasi'];
    }
    return $tarih_karsilastirma;
});

// Borçlu aidatları getir (parçalı ödeme için)
$borclu_aidatlar = [];
if ($current_user['role'] === 'sakin') {
    try {
        $stmt = $db->prepare("
            SELECT a.*, s.ad_soyad, s.daire_no 
            FROM aidatlar a
            JOIN sakinler s ON a.sakin_id = s.id
            WHERE a.sakin_id = ? AND a.kalan_borc > 0 AND a.status = 1
            ORDER BY a.ay ASC
        ");
        $stmt->execute([$current_user['sakin_id']]);
        $borclu_aidatlar = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hata log'la
    }
}

// İstatistikleri hesapla
$toplam_odeme = array_sum(array_column($cari_hareketler, 'odeme_miktari'));
$toplam_islem = count($cari_hareketler);
$toplam_gider = array_sum(array_column($gider_bilgilendirmeleri, 'miktar'));
$bu_ay_odeme = 0;
$bu_ay = date('Y-m');

foreach ($cari_hareketler as $hareket) {
    if (date('Y-m', strtotime($hareket['odeme_tarihi'])) === $bu_ay) {
        $bu_ay_odeme += $hareket['odeme_miktari'];
    }
}

// Toplam borç hesapla
$toplam_borc = 0;
if ($current_user['role'] === 'sakin') {
    foreach ($borclu_aidatlar as $aidat) {
        $toplam_borc += $aidat['kalan_borc'];
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
        <?php include __DIR__ . '/../../src/views/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="topbar">
                <h1 class="page-title">📋 Cari Hareketler</h1>
                
                <div class="topbar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Hareket ara..." id="hareketSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <?php if ($current_user['role'] === 'sakin' && !empty($borclu_aidatlar)): ?>
                    <button class="btn btn-primary" onclick="openModal('parcaliOdemeModal')">
                        💳 Ödeme Yap
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

                <!-- Stats Cards - Tıklanabilir ve Filtreleyici -->
                <div class="stats-grid">
                    <div class="stat-card success clickable-stat" data-filter="toplam-odeme" onclick="filterByStatCard('toplam-odeme', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Ödeme</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_odeme) ?></div>
                        <div class="stat-change positive"><?= $toplam_islem ?> işlem</div>
                    </div>
                    
                    <div class="stat-card info clickable-stat" data-filter="this-month" onclick="filterByStatCard('this-month', this)">
                        <div class="stat-header">
                            <div class="stat-title">Bu Ay Ödeme</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($bu_ay_odeme) ?></div>
                        <div class="stat-change"><?= date('F Y') ?></div>
                    </div>
                    
                    <?php if ($current_user['role'] === 'sakin'): ?>
                    <div class="stat-card <?= $toplam_borc > 0 ? 'danger' : 'success' ?> clickable-stat" data-filter="pending-payments" onclick="filterByStatCard('pending-payments', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Borç</div>
                            <div class="stat-icon"><?= $toplam_borc > 0 ? '⚠️' : '✅' ?></div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_borc) ?></div>
                        <div class="stat-change <?= $toplam_borc > 0 ? 'negative' : 'positive' ?>">
                            <?= count($borclu_aidatlar) ?> borçlu aidat
                        </div>
                    </div>
                    
                    <div class="stat-card warning clickable-stat" data-filter="gider" onclick="filterByStatCard('gider', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Gider</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_gider) ?></div>
                        <div class="stat-change"><?= count($gider_bilgilendirmeleri) ?> gider</div>
                    </div>
                    <?php else: ?>
                    <div class="stat-card warning clickable-stat" data-filter="gider" onclick="filterByStatCard('gider', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Gider</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_gider) ?></div>
                        <div class="stat-change"><?= count($gider_bilgilendirmeleri) ?> gider</div>
                    </div>
                    
                    <div class="stat-card primary clickable-stat" data-filter="all" onclick="filterByStatCard('all', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Hareket</div>
                        </div>
                        <div class="stat-value"><?= count($tumun_hareketler) ?></div>
                        <div class="stat-change">Ödeme + Gider</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Borçlu Aidatlar (Sadece Sakinler için) -->
                <?php if ($current_user['role'] === 'sakin' && !empty($borclu_aidatlar)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">💸 Borçlu Aidatlar</h3>
                        <span class="card-subtitle">Parçalı ödeme yapabileceğiniz aidatlar</span>
                    </div>
                    <div class="card-body">
                        <div class="borclu-aidatlar-grid">
                            <?php foreach ($borclu_aidatlar as $aidat): ?>
                            <div class="borclu-aidat-card">
                                <div class="aidat-period"><?= formatMonth($aidat['ay']) ?></div>
                                <div class="aidat-amounts">
                                    <div class="amount-item">
                                        <span class="amount-label">Toplam:</span>
                                        <span class="amount-value total"><?= formatMoney($aidat['miktar']) ?></span>
                                    </div>
                                    <div class="amount-item">
                                        <span class="amount-label">Ödenen:</span>
                                        <span class="amount-value paid"><?= formatMoney($aidat['toplam_odeme']) ?></span>
                                    </div>
                                    <div class="amount-item">
                                        <span class="amount-label">Kalan:</span>
                                        <span class="amount-value remaining"><?= formatMoney($aidat['kalan_borc']) ?></span>
                                    </div>
                                </div>
                                <div class="aidat-progress">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $aidat['miktar'] > 0 ? ($aidat['toplam_odeme'] / $aidat['miktar']) * 100 : 0 ?>%"></div>
                                    </div>
                                    <div class="progress-text">
                                        %<?= $aidat['miktar'] > 0 ? round(($aidat['toplam_odeme'] / $aidat['miktar']) * 100, 1) : 0 ?> ödendi
                                    </div>
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="openParcaliOdeme(<?= $aidat['id'] ?>, '<?= formatMonth($aidat['ay']) ?>', <?= $aidat['kalan_borc'] ?>)">
                                    💳 Ödeme Yap
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tüm Hareketler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📋 Tüm Hareketler</h3>
                        <div class="card-actions" style="display: none;">
                            <!-- Aktif Filtre Bilgisi -->
                            <div class="filter-info" id="filterInfo" style="display: none;">
                                <div class="filter-badge">
                                    <span id="filterText">Tüm Hareketler</span>
                                    <button onclick="clearAllFilters()" title="Filtreyi temizle">×</button>
                                </div>
                            </div>
                            
                            <select class="form-select" id="tipFilter" onchange="filterHareketler()">
                                <option value="">Tüm Hareketler</option>
                                <option value="odeme">Sadece Ödemeler</option>
                                <option value="gider">Sadece Giderler</option>
                            </select>
                            <select class="form-select" id="monthFilter" onchange="filterHareketler()">
                                <option value="">Tüm Aylar</option>
                                <?php
                                $aylar = array_unique(array_map(function($h) {
                                    return date('Y-m', strtotime($h['tarih_sira']));
                                }, $tumun_hareketler));
                                rsort($aylar);
                                foreach ($aylar as $ay):
                                ?>
                                    <option value="<?= $ay ?>"><?= formatMonth($ay) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-ghost btn-sm" onclick="clearAllFilters()" title="Tüm filtreleri temizle">
                                🔄 Temizle
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tumun_hareketler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                                <h3>Henüz hareket kaydı yok</h3>
                                <p>
                                    <?php if ($current_user['role'] === 'sakin'): ?>
                                        İlk ödemenizi yapmak için "Ödeme Yap" butonunu kullanın.
                                    <?php else: ?>
                                        Sakinler ödeme yaptıkça ve gider ekledikçe burada görünecek.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="hareketler-timeline">
                                <?php 
                                $current_date = '';
                                foreach ($tumun_hareketler as $hareket): 
                                    $hareket_date = formatDate($hareket['tarih_sira']);
                                    if ($current_date !== $hareket_date):
                                        $current_date = $hareket_date;
                                ?>
                                    <div class="timeline-date"><?= $current_date ?></div>
                                <?php endif; ?>
                                
                                <?php if ($hareket['tip'] === 'odeme'): ?>
                                <!-- Ödeme Hareketi -->
                                <div class="timeline-item hareket-odeme"
                                     data-tip="odeme"
                                     data-month="<?= date('Y-m', strtotime($hareket['odeme_tarihi'])) ?>"
                                     data-search="<?= strtolower($hareket['ad_soyad'] . ' ' . $hareket['daire_no'] . ' ' . formatMonth($hareket['ay']) . ' ' . $hareket['odeme_aciklamasi']) ?>"
                                     data-pending="<?= $hareket['kalan_borc'] > 0 ? 'true' : 'false' ?>">
                                    
                                    <div class="timeline-marker odeme-marker">💰</div>
                                    
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <?php if ($current_user['role'] !== 'sakin'): ?>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?= substr($hareket['ad_soyad'], 0, 2) ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?= htmlspecialchars($hareket['ad_soyad']) ?></div>
                                                    <div class="user-daire">Daire <?= htmlspecialchars($hareket['daire_no']) ?></div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="hareket-summary">
                                                <div class="hareket-amount success"><?= formatMoney($hareket['odeme_miktari']) ?></div>
                                                <div class="hareket-time"><?= date('H:i', strtotime($hareket['created_at'])) ?></div>
                                                <div class="hareket-type">✅ Ödeme</div>
                                            </div>
                                        </div>
                                        
                                        <div class="timeline-details">
                                            <div class="detail-grid">
                                                <div class="detail-item">
                                                    <span class="detail-label">📅 Dönem:</span>
                                                    <span class="detail-value"><?= formatMonth($hareket['ay']) ?></span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">💰 Aidat Tutarı:</span>
                                                    <span class="detail-value"><?= formatMoney($hareket['aidat_miktari']) ?></span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">💳 Toplam Ödenen:</span>
                                                    <span class="detail-value"><?= formatMoney($hareket['toplam_odeme']) ?></span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">💸 Kalan Borç:</span>
                                                    <span class="detail-value <?= $hareket['kalan_borc'] > 0 ? 'text-danger' : 'text-success' ?>">
                                                        <?= formatMoney($hareket['kalan_borc']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($hareket['odeme_aciklamasi'])): ?>
                                            <div class="hareket-note">
                                                <strong>📝 Not:</strong> <?= htmlspecialchars($hareket['odeme_aciklamasi']) ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="progress-info">
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?= $hareket['aidat_miktari'] > 0 ? ($hareket['toplam_odeme'] / $hareket['aidat_miktari']) * 100 : 0 ?>%"></div>
                                                </div>
                                                <div class="progress-text">
                                                    %<?= $hareket['aidat_miktari'] > 0 ? round(($hareket['toplam_odeme'] / $hareket['aidat_miktari']) * 100, 1) : 0 ?> tamamlandı
                                                    <?php if ($hareket['kalan_borc'] <= 0): ?>
                                                        <span class="status-completed">✅ Tam ödendi</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php else: ?>
                                <!-- Gider Bilgilendirmesi -->
                                <div class="timeline-item hareket-gider"
                                     data-tip="gider"
                                     data-month="<?= date('Y-m', strtotime($hareket['tarih'])) ?>"
                                     data-search="<?= strtolower($hareket['aciklama'] . ' ' . $hareket['kategori']) ?>"
                                     onclick="window.location.href='giderler.php'"
                                     style="cursor: pointer;">
                                    
                                    <div class="timeline-marker gider-marker">
                                        <?= $hareket['icon'] ?? '📋' ?>
                                    </div>
                                    
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <div class="gider-info">
                                                <div class="gider-kategori">
                                                    <span class="kategori-icon"><?= $hareket['icon'] ?? '📋' ?></span>
                                                    <span class="kategori-name"><?= htmlspecialchars($hareket['kategori']) ?></span>
                                                </div>
                                                <div class="gider-aciklama"><?= htmlspecialchars($hareket['aciklama']) ?></div>
                                            </div>
                                            
                                            <div class="hareket-summary">
                                                <div class="hareket-amount danger"><?= formatMoney($hareket['miktar']) ?></div>
                                                <div class="hareket-time"><?= date('H:i', strtotime($hareket['created_at'])) ?></div>
                                                <div class="hareket-type">📊 Gider</div>
                                            </div>
                                        </div>
                                        
                                        <div class="timeline-details gider-details">
                                            <div class="gider-summary">
                                                <div class="summary-item">
                                                    <span class="summary-icon">📅</span>
                                                    <span class="summary-text">
                                                        <strong><?= formatDate($hareket['tarih']) ?></strong> tarihinde 
                                                        <strong><?= htmlspecialchars($hareket['kategori']) ?></strong> kategorisinde 
                                                        <strong><?= formatMoney($hareket['miktar']) ?></strong> tutarında gider eklendi
                                                    </span>
                                                </div>
                                                <div class="summary-item">
                                                    <span class="summary-icon">📝</span>
                                                    <span class="summary-text"><?= htmlspecialchars($hareket['aciklama']) ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="gider-action">
                                                <div class="action-hint">
                                                    <span class="hint-icon">👆</span>
                                                    <span class="hint-text">Detayları görmek için tıklayın</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parçalı Ödeme Modal -->
    <?php if ($current_user['role'] === 'sakin'): ?>
    <div class="modal" id="parcaliOdemeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">💳 Parçalı Ödeme</h3>
                <button class="modal-close" onclick="closeModal('parcaliOdemeModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="parcaliOdemeForm">
                    <div class="form-group">
                        <label class="form-label">Ödeme Yapılacak Aidat</label>
                        <select name="aidat_id" id="aidatSelect" class="form-select" required onchange="updateOdemeInfo()">
                            <option value="">Aidat seçin...</option>
                            <?php foreach ($borclu_aidatlar as $aidat): ?>
                                <option value="<?= $aidat['id'] ?>" 
                                        data-miktar="<?= $aidat['miktar'] ?>"
                                        data-odenen="<?= $aidat['toplam_odeme'] ?>"
                                        data-kalan="<?= $aidat['kalan_borc'] ?>">
                                    <?= formatMonth($aidat['ay']) ?> - Kalan: <?= formatMoney($aidat['kalan_borc']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="aidat-info" id="aidatInfo" style="display: none;">
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">💰 Toplam Aidat:</span>
                                <span class="info-value" id="toplamTutar">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">✅ Ödenen:</span>
                                <span class="info-value" id="odenenTutar">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">💸 Kalan Borç:</span>
                                <span class="info-value text-danger" id="kalanTutar">-</span>
                            </div>
                        </div>
                        
                        <div class="progress-section">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                            </div>
                            <div class="progress-text" id="progressText">%0 ödendi</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Ödeme Miktarı (₺)</label>
                        <input type="number" name="odeme_miktari" id="odemeMiktari" class="form-input" 
                               placeholder="0.00" min="0.01" step="0.01" required 
                               onchange="validateOdeme()">
                        <div class="form-help">
                            <button type="button" class="quick-amount" onclick="setQuickAmount(50)">50₺</button>
                            <button type="button" class="quick-amount" onclick="setQuickAmount(100)">100₺</button>
                            <button type="button" class="quick-amount" onclick="setQuickAmount(200)">200₺</button>
                            <button type="button" class="quick-amount" onclick="setFullAmount()">Tam Ödeme</button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Açıklama (İsteğe bağlı)</label>
                        <textarea name="aciklama" class="form-textarea" rows="3" 
                                  placeholder="Ödeme ile ilgili not ekleyebilirsiniz..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>ℹ️ Bilgi:</strong> Parçalı ödeme yapabilirsiniz. Kalan miktar için daha sonra tekrar ödeme yapabilirsiniz.
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('parcaliOdemeModal')">
                            İptal
                        </button>
                        <button type="submit" name="parcali_odeme" class="btn btn-primary" id="odemeBtn" disabled>
                            💳 Ödeme Yap
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/sidebar.js"></script>  
    <script>
// Global değişkenler
    let currentFilter = 'all';
    let currentSearchTerm = '';
    let currentTipFilter = '';
    let currentMonthFilter = '';

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
        
        // Dropdown'ları temizle
        document.getElementById('tipFilter').value = '';
        document.getElementById('monthFilter').value = '';
        currentTipFilter = '';
        currentMonthFilter = '';
        
        // Eğer bu ay ödemesi seçiliyse, ay filtresini otomatik ayarla
        if (filterType === 'this-month') {
            const thisMonth = new Date().toISOString().slice(0, 7); // YYYY-MM formatı
            document.getElementById('monthFilter').value = thisMonth;
            currentMonthFilter = thisMonth;
            // Bu ay ödemesi için tip filtresini de ödeme olarak ayarla
            document.getElementById('tipFilter').value = 'odeme';
            currentTipFilter = 'odeme';
        }
        
        // Eğer toplam ödeme seçiliyse, sadece ödemeleri göster
        if (filterType === 'toplam-odeme') {
            // Toplam ödeme kartı sadece ödeme hareketlerini göstersin
            document.getElementById('tipFilter').value = 'odeme';
            currentTipFilter = 'odeme';
        }
        
        // Eğer toplam hareket seçiliyse, tüm hareketleri göster
        if (filterType === 'all') {
            // Tüm hareketler (ödeme + gider)
            // currentTipFilter boş bırakılır, böylece tüm tipler görünür
        }
        
        // Eğer sadece giderler seçiliyse, tip filtresini ayarla
        if (filterType === 'gider') {
            document.getElementById('tipFilter').value = 'gider';
            currentTipFilter = 'gider';
        }
        
        // Eğer sadece ödemeler seçiliyse, tip filtresini ayarla
        if (filterType === 'odeme') {
            document.getElementById('tipFilter').value = 'odeme';
            currentTipFilter = 'odeme';
        }
        
        applyAllFilters();
        showFilterInfo(filterType);
    }

    // Dropdown filtreleme
    function filterHareketler() {
        currentTipFilter = document.getElementById('tipFilter').value;
        currentMonthFilter = document.getElementById('monthFilter').value;
        
        // Tüm stat kartlarının aktif durumunu temizle
        document.querySelectorAll('.clickable-stat').forEach(card => {
            card.classList.remove('active');
        });
        
        // Eğer belirli bir filtre kombinasyonu varsa ilgili kartı aktif yap
        if (currentTipFilter === 'gider' && !currentMonthFilter) {
            const giderCard = document.querySelector('[data-filter="gider"]');
            if (giderCard) giderCard.classList.add('active');
            currentFilter = 'gider';
        } else if (currentTipFilter === 'odeme' && !currentMonthFilter) {
            const odemeCard = document.querySelector('[data-filter="odeme"]');
            if (odemeCard) odemeCard.classList.add('active');
            currentFilter = 'odeme';
        } else if (currentMonthFilter && currentTipFilter === 'odeme') {
            const thisMonth = new Date().toISOString().slice(0, 7);
            if (currentMonthFilter === thisMonth) {
                const thisMonthCard = document.querySelector('[data-filter="this-month"]');
                if (thisMonthCard) thisMonthCard.classList.add('active');
                currentFilter = 'this-month';
            } else {
                currentFilter = 'custom';
            }
        } else if (!currentTipFilter && !currentMonthFilter) {
            // Hiçbir filtre yoksa toplam hareket kartını aktif yap (tüm hareketler)
            const allCard = document.querySelector('[data-filter="all"]');
            if (allCard) allCard.classList.add('active');
            currentFilter = 'all';
            // Toplam hareket için tip filtresi uygulanmaz
        } else {
            currentFilter = 'custom';
        }
        
        applyAllFilters();
        showFilterInfo(currentFilter);
    }

    // Tüm filtreleri uygula
    function applyAllFilters() {
        const timelineItems = document.querySelectorAll('.timeline-item');
        
        timelineItems.forEach(item => {
            let showItem = true;
            
            // Tip filtresi
            if (currentTipFilter) {
                showItem = showItem && item.dataset.tip === currentTipFilter;
            }
            
            // Ay filtresi
            if (currentMonthFilter) {
                showItem = showItem && item.dataset.month === currentMonthFilter;
            }
            
            // Özel stat kart filtreleri
            if (currentFilter === 'pending-payments') {
                // Sadece henüz tam ödenmemiş ödeme hareketleri
                showItem = showItem && item.dataset.tip === 'odeme' && item.dataset.pending === 'true';
            }
            
            // Toplam ödeme ve bu ay ödeme kartları için sadece ödeme hareketleri
            if (currentFilter === 'toplam-odeme' || currentFilter === 'this-month') {
                showItem = showItem && item.dataset.tip === 'odeme';
            }
            
            // Toplam hareket kartı (all) için tüm hareketler görünsün - özel bir kısıtlama yok
            
            // Arama kontrolü
            if (showItem && currentSearchTerm) {
                const searchData = item.dataset.search || '';
                showItem = searchData.includes(currentSearchTerm);
            }
            
            item.style.display = showItem ? '' : 'none';
        });
        
        // Tarih başlıklarını güncelle
        updateDateHeaders();
    }

    // Filtre bilgisini göster
    function showFilterInfo(filterType) {
        const filterInfo = document.getElementById('filterInfo');
        const filterText = document.getElementById('filterText');
        
        const filterTexts = {
            'all': 'Tüm Hareketler', // Değiştirildi - toplam hareket kartı
            'toplam-odeme': 'Toplam Ödemeler', // Yeni eklendi
            'odeme': 'Sadece Ödemeler',
            'gider': 'Sadece Giderler',
            'this-month': 'Bu Ay Ödemeleri',
            'pending-payments': 'Borçlu Aidatlar',
            'custom': 'Özel Filtre'
        };
        
        if (filterType !== 'all' || currentTipFilter || currentMonthFilter) {
            filterText.textContent = filterTexts[filterType] || 'Özel Filtre';
            filterInfo.style.display = 'inline-block';
        } else {
            filterInfo.style.display = 'none';
        }
    }

    // Tüm filtreleri temizle
    function clearAllFilters() {
        currentFilter = 'all';
        currentSearchTerm = '';
        currentTipFilter = ''; // Varsayılan olarak tüm tipler gösterilsin
        currentMonthFilter = '';
        
        // Arama kutusunu temizle
        document.getElementById('hareketSearch').value = '';
        
        // Dropdown'ları temizle
        document.getElementById('tipFilter').value = '';
        document.getElementById('monthFilter').value = '';
        
        // Stat kartlarının aktif durumunu temizle
        document.querySelectorAll('.clickable-stat').forEach(card => {
            card.classList.remove('active');
        });
        
        // Toplam hareket kartını aktif yap
        const totalCard = document.querySelector('[data-filter="all"]');
        if (totalCard) {
            totalCard.classList.add('active');
        }
        
        // Filtreleri uygula
        applyAllFilters();
        
        // Filtre bilgisini gizle
        document.getElementById('filterInfo').style.display = 'none';
    }

    // Hareket arama
    document.getElementById('hareketSearch').addEventListener('input', function(e) {
        currentSearchTerm = e.target.value.toLowerCase();
        applyAllFilters();
    });

    // Tarih başlıklarını güncelle
    function updateDateHeaders() {
        const dateHeaders = document.querySelectorAll('.timeline-date');
        
        dateHeaders.forEach(header => {
            const nextItem = header.nextElementSibling;
            let hasVisibleItems = false;
            
            let currentElement = nextItem;
            while (currentElement && !currentElement.classList.contains('timeline-date')) {
                if (currentElement.classList.contains('timeline-item') && 
                    currentElement.style.display !== 'none') {
                    hasVisibleItems = true;
                    break;
                }
                currentElement = currentElement.nextElementSibling;
            }
            
            header.style.display = hasVisibleItems ? '' : 'none';
        });
    }

    // Parçalı ödeme modal açma
    function openParcaliOdeme(aidatId, ay, kalanBorc) {
        const select = document.getElementById('aidatSelect');
        select.value = aidatId;
        updateOdemeInfo();
        openModal('parcaliOdemeModal');
    }

    // Aidat bilgilerini güncelle
    function updateOdemeInfo() {
        const select = document.getElementById('aidatSelect');
        const selectedOption = select.options[select.selectedIndex];
        
        if (select.value) {
            const miktar = parseFloat(selectedOption.dataset.miktar);
            const odenen = parseFloat(selectedOption.dataset.odenen);
            const kalan = parseFloat(selectedOption.dataset.kalan);
            const oran = miktar > 0 ? (odenen / miktar) * 100 : 0;
            
            document.getElementById('toplamTutar').textContent = formatMoney(miktar);
            document.getElementById('odenenTutar').textContent = formatMoney(odenen);
            document.getElementById('kalanTutar').textContent = formatMoney(kalan);
            document.getElementById('progressFill').style.width = oran + '%';
            document.getElementById('progressText').textContent = '%' + oran.toFixed(1) + ' ödendi';
            document.getElementById('aidatInfo').style.display = 'block';
            
            // Maksimum ödeme miktarını ayarla
            const odemeInput = document.getElementById('odemeMiktari');
            odemeInput.max = kalan;
            odemeInput.placeholder = 'Max: ' + formatMoney(kalan);
            
            validateOdeme();
        } else {
            document.getElementById('aidatInfo').style.display = 'none';
            document.getElementById('odemeBtn').disabled = true;
        }
    }

    // Ödeme miktarını doğrula
    function validateOdeme() {
        const select = document.getElementById('aidatSelect');
        const odemeInput = document.getElementById('odemeMiktari');
        const odemeBtn = document.getElementById('odemeBtn');
        
        if (select.value && odemeInput.value) {
            const selectedOption = select.options[select.selectedIndex];
            const kalan = parseFloat(selectedOption.dataset.kalan);
            const odeme = parseFloat(odemeInput.value);
            
            if (odeme > 0 && odeme <= kalan) {
                odemeBtn.disabled = false;
                odemeInput.style.borderColor = '';
            } else {
                odemeBtn.disabled = true;
                odemeInput.style.borderColor = 'red';
            }
        } else {
            odemeBtn.disabled = true;
        }
    }

    // Hızlı miktar ayarlama
    function setQuickAmount(amount) {
        const select = document.getElementById('aidatSelect');
        const odemeInput = document.getElementById('odemeMiktari');
        
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const kalan = parseFloat(selectedOption.dataset.kalan);
            
            odemeInput.value = Math.min(amount, kalan).toFixed(2);
            validateOdeme();
        }
    }

    // Tam ödeme ayarlama
    function setFullAmount() {
        const select = document.getElementById('aidatSelect');
        const odemeInput = document.getElementById('odemeMiktari');
        
        if (select.value) {
            const selectedOption = select.options[select.selectedIndex];
            const kalan = parseFloat(selectedOption.dataset.kalan);
            
            odemeInput.value = kalan.toFixed(2);
            validateOdeme();
        }
    }

    // Para formatı
    function formatMoney(amount) {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY'
        }).format(amount);
    }

    // Form temizleme
    function clearParcaliOdemeForm() {
        document.getElementById('parcaliOdemeForm').reset();
        document.getElementById('aidatInfo').style.display = 'none';
        document.getElementById('odemeBtn').disabled = true;
    }

    // Modal kapatıldığında form temizle
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-close') || 
            (e.target.classList.contains('modal') && e.target.id === 'parcaliOdemeModal')) {
            clearParcaliOdemeForm();
        }
    });

    // Sayfa yüklendiğinde toplam hareket kartını aktif yap ve tüm hareketleri göster
    document.addEventListener('DOMContentLoaded', function() {
        const totalCard = document.querySelector('[data-filter="all"]');
        if (totalCard) {
            totalCard.classList.add('active');
        }
        
        // Varsayılan olarak tüm hareketleri göster
        currentFilter = 'all';
        currentTipFilter = ''; // Boş bırak, tüm tipler görünsün
        applyAllFilters();
    });
    </script>
    
    <style>
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

        .stat-card.danger.clickable-stat.active {
            border-color: var(--danger-color);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
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

        .stat-card.danger.clickable-stat:hover .stat-value {
            color: var(--danger-color);
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
            content: '💰';
        }

        .stat-card.info.clickable-stat::after {
            content: '📅';
        }

        .stat-card.warning.clickable-stat::after {
            content: '📊';
        }

        .stat-card.danger.clickable-stat::after {
            content: '⚠️';
        }

        /* GERIYE KALAN TÜM STİLLER AYNI KALIYOR - SADECE STAT CARD'LAR DEĞİŞTİ */

        /* Filtre Bilgi Badge */
        .filter-info {
            display: inline-block;
            margin-right: 12px;
            animation: slideDown 0.3s ease;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-badge button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 14px;
            cursor: pointer;
            padding: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .filter-badge button:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* Card Actions Güncelleme */
        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Gider Timeline Stilleri */
        .hareket-gider {
            border-left: 4px solid var(--warning-color);
            transition: all 0.3s ease;
        }

        .hareket-gider:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
            transform: translateX(2px);
        }

        .hareket-odeme {
            border-left: 4px solid var(--success-color);
        }

        .timeline-marker.gider-marker {
            background: var(--warning-color);
            color: white;
            font-size: 18px;
        }

        .timeline-marker.odeme-marker {
            background: var(--success-color);
            color: white;
        }

        .gider-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .gider-kategori {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kategori-icon {
            font-size: 20px;
        }

        .kategori-name {
            font-weight: 600;
            color: var(--warning-color);
            font-size: 14px;
        }

        .gider-aciklama {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .hareket-amount.danger {
            color: var(--danger-color);
        }

        .hareket-amount.success {
            color: var(--success-color);
        }

        .hareket-type {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .gider-details {
            background: linear-gradient(135deg, var(--background-color), #fff9e6);
            border-left: 4px solid var(--warning-color);
        }

        .gider-summary {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .summary-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .summary-icon {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .summary-text {
            line-height: 1.5;
            color: var(--text-primary);
        }

        .gider-action {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .action-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            padding: 8px;
            background: var(--card-background);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .hint-icon {
            font-size: 16px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-5px);
            }
            60% {
                transform: translateY(-3px);
            }
        }

        /* Borçlu Aidatlar Grid */
        .borclu-aidatlar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .borclu-aidat-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .borclu-aidat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .aidat-period {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            text-align: center;
        }

        .aidat-amounts {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .amount-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .amount-value {
            font-weight: 600;
        }

        .amount-value.total {
            color: var(--text-primary);
        }

        .amount-value.paid {
            color: var(--success-color);
        }

        .amount-value.remaining {
            color: var(--danger-color);
        }

        .aidat-progress {
            margin-bottom: 16px;
        }

        .progress-bar {
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
        }

        /* Timeline Styles */
        .hareketler-timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .timeline-date {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--background-color);
            padding: 8px 16px;
            border-radius: 20px;
            margin: 16px 0 8px 0;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .timeline-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .timeline-item:hover {
            box-shadow: var(--shadow-sm);
        }

        .timeline-marker {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
            min-width: 0;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-daire {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .hareket-summary {
            text-align: right;
        }

        .hareket-amount {
            font-size: 18px;
            font-weight: 700;
        }

        .hareket-time {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .timeline-details {
            background: var(--background-color);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .hareket-note {
            margin-top: 12px;
            padding: 12px;
            background: var(--card-background);
            border-radius: 6px;
            border-left: 4px solid var(--info-color);
            font-size: 14px;
        }

        .progress-info {
            margin-top: 12px;
        }

        .progress-info .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .status-completed {
            color: var(--success-color);
            font-weight: 600;
            font-size: 12px;
        }

        /* Modal Styles */
        .aidat-info {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-section {
            margin-top: 16px;
        }

        .form-help {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .quick-amount {
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--card-background);
            color: var(--text-primary);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-amount:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-value {
                font-size: 28px;
            }
            
            .stat-card.clickable-stat:hover {
                transform: translateY(-2px);
            }
            
            .stat-card.clickable-stat.active {
                transform: translateY(-1px);
            }
            
            .stat-card.clickable-stat::after {
                display: none;
            }

            .borclu-aidatlar-grid {
                grid-template-columns: 1fr;
            }

            .timeline-item {
                padding: 12px;
                gap: 12px;
            }

            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .hareket-summary {
                text-align: left;
                align-self: flex-end;
            }

            .detail-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .user-info {
                gap: 8px;
            }

            .timeline-marker {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .form-help {
                gap: 4px;
            }

            .quick-amount {
                flex: 1;
                text-align: center;
            }

            .gider-info {
                margin-bottom: 8px;
            }

            .summary-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .summary-icon {
                align-self: flex-start;
            }

            .card-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .filter-info {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }

        /* Utility Classes */
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
        /* Clickable Stats Card Stilleri - Sakinler sayfasından adapt edildi */
        .clickable-stat {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .clickable-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .clickable-stat:hover::before {
            left: 100%;
        }

        .clickable-stat:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .clickable-stat.active {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .clickable-stat.danger.active {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.25));
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Filtre Bilgi Badge */
        .filter-info {
            display: inline-block;
            margin-right: 12px;
            animation: slideDown 0.3s ease;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-badge button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 14px;
            cursor: pointer;
            padding: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .filter-badge button:hover {
            background: rgba(59, 130, 246, 0.2);
        }

        /* Card Actions Güncelleme */
        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Gider Timeline Stilleri */
        .hareket-gider {
            border-left: 4px solid var(--warning-color);
            transition: all 0.3s ease;
        }

        .hareket-gider:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary-color);
            transform: translateX(2px);
        }

        .hareket-odeme {
            border-left: 4px solid var(--success-color);
        }

        .timeline-marker.gider-marker {
            background: var(--warning-color);
            color: white;
            font-size: 18px;
        }

        .timeline-marker.odeme-marker {
            background: var(--success-color);
            color: white;
        }

        .gider-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .gider-kategori {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kategori-icon {
            font-size: 20px;
        }

        .kategori-name {
            font-weight: 600;
            color: var(--warning-color);
            font-size: 14px;
        }

        .gider-aciklama {
            font-size: 16px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .hareket-amount.danger {
            color: var(--danger-color);
        }

        .hareket-amount.success {
            color: var(--success-color);
        }

        .hareket-type {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .gider-details {
            background: linear-gradient(135deg, var(--background-color), #fff9e6);
            border-left: 4px solid var(--warning-color);
        }

        .gider-summary {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .summary-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .summary-icon {
            font-size: 16px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .summary-text {
            line-height: 1.5;
            color: var(--text-primary);
        }

        .gider-action {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .action-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            padding: 8px;
            background: var(--card-background);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .hint-icon {
            font-size: 16px;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-5px);
            }
            60% {
                transform: translateY(-3px);
            }
        }

        /* Borçlu Aidatlar Grid */
        .borclu-aidatlar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }

        .borclu-aidat-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .borclu-aidat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .aidat-period {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 16px;
            text-align: center;
        }

        .aidat-amounts {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .amount-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .amount-value {
            font-weight: 600;
        }

        .amount-value.total {
            color: var(--text-primary);
        }

        .amount-value.paid {
            color: var(--success-color);
        }

        .amount-value.remaining {
            color: var(--danger-color);
        }

        .aidat-progress {
            margin-bottom: 16px;
        }

        .progress-bar {
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
        }

        /* Timeline Styles */
        .hareketler-timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .timeline-date {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            background: var(--background-color);
            padding: 8px 16px;
            border-radius: 20px;
            margin: 16px 0 8px 0;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .timeline-item {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .timeline-item:hover {
            box-shadow: var(--shadow-sm);
        }

        .timeline-marker {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .timeline-content {
            flex: 1;
            min-width: 0;
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-daire {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .hareket-summary {
            text-align: right;
        }

        .hareket-amount {
            font-size: 18px;
            font-weight: 700;
        }

        .hareket-time {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .timeline-details {
            background: var(--background-color);
            padding: 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .detail-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        .hareket-note {
            margin-top: 12px;
            padding: 12px;
            background: var(--card-background);
            border-radius: 6px;
            border-left: 4px solid var(--info-color);
            font-size: 14px;
        }

        .progress-info {
            margin-top: 12px;
        }

        .progress-info .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .status-completed {
            color: var(--success-color);
            font-weight: 600;
            font-size: 12px;
        }

        /* Modal Styles */
        .aidat-info {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress-section {
            margin-top: 16px;
        }

        .form-help {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .quick-amount {
            padding: 4px 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--card-background);
            color: var(--text-primary);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quick-amount:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .borclu-aidatlar-grid {
                grid-template-columns: 1fr;
            }

            .timeline-item {
                padding: 12px;
                gap: 12px;
            }

            .timeline-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .hareket-summary {
                text-align: left;
                align-self: flex-end;
            }

            .detail-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .user-info {
                gap: 8px;
            }

            .timeline-marker {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .form-help {
                gap: 4px;
            }

            .quick-amount {
                flex: 1;
                text-align: center;
            }

            .gider-info {
                margin-bottom: 8px;
            }

            .summary-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .summary-icon {
                align-self: flex-start;
            }

            .clickable-stat:hover {
                transform: none;
            }

            .clickable-stat.active {
                transform: none;
            }

            .card-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .filter-info {
                margin-right: 0;
                margin-bottom: 8px;
            }
        }

        /* Utility Classes */
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</body>
</html>