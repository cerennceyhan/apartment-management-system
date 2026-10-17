<?php
// pages/siteler.php - Site Yönetimi Sayfası
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

// Sayfa başlığını ayarla
$page_title = 'Site Yönetimi';

// Site ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    $site_adi = sanitize($_POST['site_adi']);
    $adres = sanitize($_POST['adres']);
    $telefon = sanitize($_POST['telefon']);
    $email = sanitize($_POST['email']);
    $yonetici_adi = sanitize($_POST['yonetici_adi']);
    $yonetici_telefon = sanitize($_POST['yonetici_telefon']);
    
    $errors = validateRequired([
        'site_adi' => 'Site Adı',
        'adres' => 'Adres',
        'telefon' => 'Telefon',
        'email' => 'Email',
        'yonetici_adi' => 'Yönetici Adı'
    ], $_POST);
    
    if (empty($errors)) {
        if (!validateEmail($email)) {
            $errors[] = 'Geçerli bir email adresi girin';
        }
        
        // Site adı benzersizlik kontrolü
        $stmt = $db->prepare("SELECT id FROM siteler WHERE site_adi = ? AND status = 1");
        $stmt->execute([$site_adi]);
        if ($stmt->fetch()) {
            $errors[] = 'Bu site adı zaten kayıtlı';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO siteler (site_adi, adres, telefon, email, yonetici_adi, yonetici_telefon) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$site_adi, $adres, $telefon, $email, $yonetici_adi, $yonetici_telefon]);
            
            showAlert('Site başarıyla eklendi', 'success');
            $_POST = [];
            
        } catch (Exception $e) {
            showAlert('Site eklenirken hata oluştu', 'error');
        }
    } else {
        showAlert(implode('<br>', $errors), 'error');
    }
}

// Site silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_site'])) {
    $site_id = (int)$_POST['site_id'];
    
    try {
        // Sitenin sakinleri var mı kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) as sakin_count FROM sakinler WHERE site_id = ? AND status = 1");
        $stmt->execute([$site_id]);
        $sakin_count = $stmt->fetch()['sakin_count'];
        
        if ($sakin_count > 0) {
            showAlert('Bu sitede sakinler bulunduğu için silinemez', 'error');
        } else {
            $stmt = $db->prepare("UPDATE siteler SET status = 0 WHERE id = ?");
            $stmt->execute([$site_id]);
            
            if ($stmt->rowCount() > 0) {
                showAlert('Site başarıyla silindi', 'success');
            } else {
                showAlert('Site bulunamadı', 'error');
            }
        }
    } catch (Exception $e) {
        showAlert('Site silinirken hata oluştu', 'error');
    }
}

// Siteleri getir
try {
    $stmt = $db->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM bloklar WHERE site_id = s.id AND status = 1) as blok_sayisi,
               (SELECT COUNT(*) FROM sakinler WHERE site_id = s.id AND status = 1) as sakin_sayisi
        FROM siteler s 
        WHERE s.status = 1 
        ORDER BY s.site_adi
    ");
    $stmt->execute();
    $siteler = $stmt->fetchAll();
} catch (Exception $e) {
    $siteler = [];
    showAlert('Siteler yüklenirken hata oluştu', 'error');
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
                    <h1 class="page-title">🏙️ Site Yönetimi</h1>
                </div>
                
                <div class="topbar-actions">
                    <button class="btn btn-primary" onclick="openModal('addSiteModal')">
                        ➕ Yeni Site Ekle
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
                    $toplam_site = count($siteler);
                    $toplam_blok = array_sum(array_column($siteler, 'blok_sayisi'));
                    $toplam_sakin = array_sum(array_column($siteler, 'sakin_sayisi'));
                    
                    // Büyük ve küçük siteleri hesapla (blok sayısına göre)
                    $buyuk_site = 0;
                    $kucuk_site = 0;
                    foreach ($siteler as $site) {
                        if ($site['blok_sayisi'] >= 3) {
                            $buyuk_site++;
                        } else {
                            $kucuk_site++;
                        }
                    }
                    
                    $avg_occupancy = $toplam_blok > 0 ? round(($toplam_sakin / $toplam_blok), 1) : 0;
                    ?>
                    
                    <div class="stat-card primary clickable" onclick="filterSiteler('all')" id="totalSiteCard" title="Tüm siteleri göster">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Site</div>
                        </div>
                        <div class="stat-value"><?= $toplam_site ?></div>
                        <div class="stat-change positive">Aktif siteler</div>
                    </div>
                    
                    <div class="stat-card success clickable" onclick="goToBloklar()" id="blokCard" title="Tüm blokları görüntüle">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Blok</div>
                        </div>
                        <div class="stat-value"><?= $toplam_blok ?></div>
                        <div class="stat-change positive">Tüm siteler</div>
                    </div>
                    
                    <div class="stat-card info clickable" onclick="goToSakinler()" id="sakinCard" title="Tüm sakinleri görüntüle">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Sakin</div>
                        </div>
                        <div class="stat-value"><?= $toplam_sakin ?></div>
                        <div class="stat-change positive">Tüm siteler</div>
                    </div>
                    
                    <div class="stat-card warning clickable" onclick="filterSiteler('buyukluk')" id="buyuklukCard" title="Büyüklüğe göre sırala">
                        <div class="stat-header">
                            <div class="stat-title">Ortalama Doluluk</div>
                        </div>
                        <div class="stat-value"><?= $avg_occupancy ?></div>
                        <div class="stat-change">
                            <?= $buyuk_site ?> büyük, <?= $kucuk_site ?> küçük site
                        </div>
                    </div>
                </div>

                <!-- Siteler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Site Listesi</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($siteler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🏙️</div>
                                <h3>Henüz site eklenmemiş</h3>
                                <p>İlk siteyi eklemek için yukarıdaki "Yeni Site Ekle" butonunu kullanın.</p>
                                <button class="btn btn-primary" onclick="openModal('addSiteModal')" style="margin-top: 16px;">
                                    ➕ İlk Siteyi Ekle
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="sites-grid">
                                <?php foreach ($siteler as $site): ?>
                                <div class="site-card">
                                    <div class="site-header">
                                        <div class="site-icon">🏙️</div>
                                        <div class="site-title">
                                            <h4><?= htmlspecialchars($site['site_adi']) ?></h4>
                                            <p class="site-address"><?= htmlspecialchars($site['adres']) ?></p>
                                        </div>
                                        <div class="site-actions">
                                            <a href="bloklar.php?site_id=<?= $site['id'] ?>" class="btn btn-primary btn-sm">
                                                🏢 Blokları Görüntüle
                                            </a>
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="deleteSite(<?= $site['id'] ?>, '<?= htmlspecialchars($site['site_adi']) ?>')"
                                                    title="Siteyi Sil">
                                                🗑️
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="site-stats">
                                        <div class="stat-item">
                                            <span class="stat-label">Bloklar</span>
                                            <span class="stat-value"><?= $site['blok_sayisi'] ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Sakinler</span>
                                            <span class="stat-value"><?= $site['sakin_sayisi'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="site-details">
                                        <div class="detail-row">
                                            <span class="detail-label">📞 Telefon:</span>
                                            <span class="detail-value">
                                                <a href="tel:<?= htmlspecialchars($site['telefon']) ?>">
                                                    <?= htmlspecialchars($site['telefon']) ?>
                                                </a>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">📧 Email:</span>
                                            <span class="detail-value">
                                                <a href="mailto:<?= htmlspecialchars($site['email']) ?>">
                                                    <?= htmlspecialchars($site['email']) ?>
                                                </a>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">👤 Yönetici:</span>
                                            <span class="detail-value"><?= htmlspecialchars($site['yonetici_adi']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">📅 Oluşturulma:</span>
                                            <span class="detail-value"><?= formatDate($site['created_at']) ?></span>
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

    <!-- Add Site Modal -->
    <div class="modal" id="addSiteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">🏙️ Yeni Site Ekle</h3>
                <button class="modal-close" onclick="closeModal('addSiteModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addSiteForm">
                    <div class="form-group">
                        <label class="form-label">Site Adı</label>
                        <input type="text" name="site_adi" class="form-input" 
                               placeholder="Örn: Beykent Sitesi" required 
                               value="<?= isset($_POST['site_adi']) ? htmlspecialchars($_POST['site_adi']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Adres</label>
                        <textarea name="adres" class="form-input" rows="3" 
                                  placeholder="Site adresi" required><?= isset($_POST['adres']) ? htmlspecialchars($_POST['adres']) : '' ?></textarea>
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
                                   placeholder="yonetim@site.com" required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Yönetici Adı</label>
                            <input type="text" name="yonetici_adi" class="form-input" 
                                   placeholder="Yönetici Adı Soyadı" required 
                                   value="<?= isset($_POST['yonetici_adi']) ? htmlspecialchars($_POST['yonetici_adi']) : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Yönetici Telefon</label>
                            <input type="tel" name="yonetici_telefon" class="form-input" 
                                   placeholder="0532-123-4567" 
                                   value="<?= isset($_POST['yonetici_telefon']) ? htmlspecialchars($_POST['yonetici_telefon']) : '' ?>">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('addSiteModal')">
                            İptal
                        </button>
                        <button type="submit" name="add_site" class="btn btn-primary">
                            🏙️ Site Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteSiteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">⚠️ Site Sil</h3>
                <button class="modal-close" onclick="closeModal('deleteSiteModal')">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Bu siteyi silmek istediğinizden emin misiniz?</p>
                <div class="alert alert-warning">
                    <strong>⚠️ Uyarı:</strong> Bu işlem geri alınamaz. Site, blokları ve tüm verileri silinecek.
                </div>
                
                <form method="POST" id="deleteSiteForm">
                    <input type="hidden" name="site_id" id="deleteSiteId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteSiteModal')">
                            İptal
                        </button>
                        <button type="submit" name="delete_site" class="btn btn-danger">
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

        window.siteVerileri = <?= json_encode($siteler) ?>;

        // Aktif filtre durumu
        let activeFilter = 'all';

        // Siteleri filtrele
        function filterSiteler(filterType) {
            const siteCards = document.querySelectorAll('.site-card');
            const statCards = document.querySelectorAll('.stat-card.clickable');
            
            // Eski aktif kartı temizle
            statCards.forEach(card => card.classList.remove('active'));
            
            // Yeni aktif kartı işaretle
            let activeCardId = '';
            switch(filterType) {
                case 'all':
                    activeCardId = 'totalSiteCard';
                    break;
                case 'buyuk':
                    activeCardId = 'buyukSiteCard';
                    break;
                case 'kucuk':
                    activeCardId = 'kucukSiteCard';
                    break;
                case 'buyukluk':
                    activeCardId = 'buyuklukCard';
                    break;
            }
            
            if (activeCardId && document.getElementById(activeCardId)) {
                document.getElementById(activeCardId).classList.add('active');
            }
            
            activeFilter = filterType;
            
            // Siteleri filtrele ve sırala
            const siteContainer = document.querySelector('.sites-grid');
            const siteCardsArray = Array.from(siteCards);
            
            if (filterType === 'buyukluk') {
                // Blok sayısına göre sırala (büyükten küçüğe)
                siteCardsArray.sort((a, b) => {
                    const aData = window.siteVerileri.find(site => site.id == a.dataset.siteId);
                    const bData = window.siteVerileri.find(site => site.id == b.dataset.siteId);
                    
                    return (bData.blok_sayisi + bData.sakin_sayisi) - (aData.blok_sayisi + aData.sakin_sayisi);
                });
                
                // Sıralanmış kartları yeniden yerleştir
                siteCardsArray.forEach(card => {
                    siteContainer.appendChild(card);
                    card.style.display = '';
                    card.classList.add('filter-show');
                });
                
            } else {
                // Normal filtreleme
                siteCards.forEach(card => {
                    const siteData = window.siteVerileri.find(site => site.id == card.dataset.siteId);
                    let shouldShow = false;
                    
                    switch(filterType) {
                        case 'all':
                            shouldShow = true;
                            break;
                        case 'buyuk':
                            shouldShow = siteData && siteData.blok_sayisi >= 3;
                            break;
                        case 'kucuk':
                            shouldShow = siteData && siteData.blok_sayisi < 3;
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

        // Bloklar sayfasına git (genel)
        function goToBloklar() {
            // Derhal yönlendir
            window.location.href = 'bloklar.php';
        }

        // Sakinler sayfasına git (genel)
        function goToSakinler() {
            // Derhal yönlendir
            window.location.href = 'sakinler.php';
        }

        // Belirli bir site için bloklar sayfasına git
        function goToSiteBloklar(siteId) {
            const siteCard = event.currentTarget.closest('.site-card');
            
            // Kartı highlight et
            siteCard.style.transform = 'scale(0.98)';
            siteCard.style.transition = 'transform 0.1s ease';
            
            setTimeout(() => {
                window.location.href = `bloklar.php?site_id=${siteId}`;
            }, 100);
        }

        // Site kartına data attribute ekleme fonksiyonu
        function addSiteDataAttributes() {
            const siteCards = document.querySelectorAll('.site-card');
            siteCards.forEach((card, index) => {
                const siteData = window.siteVerileri[index];
                if (siteData) {
                    card.dataset.siteId = siteData.id;
                    card.dataset.blokSayisi = siteData.blok_sayisi;
                    card.dataset.sakinSayisi = siteData.sakin_sayisi;
                    card.dataset.search = `${siteData.site_adi} ${siteData.adres} ${siteData.yonetici_adi}`.toLowerCase();
                }
            });
        }

        // Filtre durumunu güncelle
        function updateFilterStatus(filterType) {
            const cardTitle = document.querySelector('.card-title');
            const totalCards = document.querySelectorAll('.site-card').length;
            const visibleCards = document.querySelectorAll('.site-card[style=""], .site-card:not([style*="none"])').length;
            
            let statusText = '';
            switch(filterType) {
                case 'all':
                    statusText = 'Tüm Siteler';
                    break;
                case 'buyuk':
                    statusText = `Büyük Siteler (${visibleCards} adet)`;
                    break;
                case 'kucuk':
                    statusText = `Küçük Siteler (${visibleCards} adet)`;
                    break;
                case 'buyukluk':
                    statusText = 'Büyüklüğe Göre Sıralandı';
                    break;
            }
            
            if (cardTitle) {
                const originalText = 'Site Listesi';
                cardTitle.innerHTML = `${originalText} ${statusText ? '- ' + statusText : ''}`;
                
                if (filterType !== 'all') {
                    cardTitle.classList.add('filter-active');
                } else {
                    cardTitle.classList.remove('filter-active');
                }
            }
        }

        // Arama fonksiyonunu ekle
        function setupSiteSearch() {
            const searchInput = document.getElementById('siteSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const siteCards = document.querySelectorAll('.site-card');
                    
                    siteCards.forEach(card => {
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

        // Site kartlarına hover animasyonu ekle
        function setupSiteCardAnimations() {
            const siteCards = document.querySelectorAll('.site-card');
            
            siteCards.forEach(card => {
                // Blokları Görüntüle butonuna özel işlev
                const blokButton = card.querySelector('a[href*="bloklar.php"]');
                if (blokButton) {
                    blokButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Kartı highlight et
                        card.style.transform = 'scale(0.98)';
                        card.style.transition = 'transform 0.1s ease';
                        
                        // URL'i al ve yönlendir
                        const url = this.href;
                        setTimeout(() => {
                            window.location.href = url;
                        }, 100);
                    });
                }
            });
        }

        // Site istatistiklerini güncelle (dinamik)
        function updateSiteStats() {
            const visibleCards = document.querySelectorAll('.site-card:not([style*="none"])');
            let totalBloks = 0;
            let totalResidents = 0;
            
            visibleCards.forEach(card => {
                const siteData = window.siteVerileri.find(site => site.id == card.dataset.siteId);
                if (siteData) {
                    totalBloks += parseInt(siteData.blok_sayisi);
                    totalResidents += parseInt(siteData.sakin_sayisi);
                }
            });
            
            // Stats kartlarını güncelle (filtreleme durumunda)
            if (activeFilter !== 'all') {
                const blokCard = document.getElementById('blokCard');
                const sakinCard = document.getElementById('sakinCard');
                
                if (blokCard) {
                    const blokValue = blokCard.querySelector('.stat-value');
                    const blokChange = blokCard.querySelector('.stat-change');
                    blokValue.textContent = totalBloks;
                    blokChange.textContent = `Filtrelenmiş siteler`;
                }
                
                if (sakinCard) {
                    const sakinValue = sakinCard.querySelector('.stat-value');
                    const sakinChange = sakinCard.querySelector('.stat-change');
                    sakinValue.textContent = totalResidents;
                    sakinChange.textContent = `Filtrelenmiş siteler`;
                }
            }
        }

        // Sayfa yüklendiğinde filtreleme sistemi hazırla
        document.addEventListener('DOMContentLoaded', function() {
            // Site data attribute'larını ekle
            addSiteDataAttributes();
            
            // İlk durumda tüm siteleri göster
            filterSiteler('all');
            
            // Arama sistemini kur
            setupSiteSearch();
            
            // Animasyonları kur
            setupSiteCardAnimations();
            
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

        // Klavye kısayolları
        document.addEventListener('keydown', function(e) {
            if (e.altKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        filterSiteler('all');
                        break;
                    case '2':
                        e.preventDefault();
                        goToBloklar();
                        break;
                    case '3':
                        e.preventDefault();
                        goToSakinler();
                        break;
                    case '4':
                        e.preventDefault();
                        filterSiteler('buyukluk');
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

        function deleteSite(id, name) {
            document.getElementById('deleteSiteId').value = id;
            document.getElementById('deleteMessage').textContent = 
                `"${name}" adlı siteyi silmek istediğinizden emin misiniz?`;
            openModal('deleteSiteModal');
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
    </script>
    
    <style>

        /* Clickable stat cards - Siteler için */
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
            content: '🏢';
        }

        .stat-card.info.clickable::after {
            content: '👥';
        }

        .stat-card.warning.clickable::after {
            content: '📊';
        }

        /* Filtered site cards animation */
        .site-card.filter-show {
            animation: siteFilterShow 0.4s ease-out;
        }

        @keyframes siteFilterShow {
            from {
                opacity: 0;
                transform: translateY(15px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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

        /* Site card enhancements */
        .site-card {
            position: relative;
            overflow: hidden;
        }

        .site-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .site-card:hover::before {
            opacity: 1;
        }

        .site-card.filter-show::before {
            opacity: 0.7;
        }

        /* Stagger animation for multiple cards */
        .site-card.filter-show:nth-child(1) { animation-delay: 0.1s; }
        .site-card.filter-show:nth-child(2) { animation-delay: 0.2s; }
        .site-card.filter-show:nth-child(3) { animation-delay: 0.3s; }
        .site-card.filter-show:nth-child(4) { animation-delay: 0.4s; }
        .site-card.filter-show:nth-child(5) { animation-delay: 0.5s; }

        /* Interactive site stats */
        .site-stats .stat-item:hover {
            transform: scale(1.1);
            transition: transform 0.2s ease;
        }

        .site-stats .stat-value {
            transition: color 0.3s ease;
        }

        .site-card:hover .site-stats .stat-value {
            color: var(--success-color);
        }

        /* Enhanced button interactions */
        .site-actions .btn {
            position: relative;
            overflow: hidden;
        }

        .site-actions .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.3s ease, height 0.3s ease;
        }

        .site-actions .btn:hover::before {
            width: 100%;
            height: 100%;
        }

        /* Detail rows hover effect */
        .detail-row {
            padding: 4px 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .detail-row:hover {
            background: rgba(59, 130, 246, 0.1);
        }

        .detail-row .detail-value a {
            transition: color 0.2s ease;
        }

        .detail-row:hover .detail-value a {
            color: var(--primary-color);
            font-weight: 500;
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
            
            .site-card.filter-show {
                animation-duration: 0.2s;
            }
            
            .site-stats .stat-item:hover {
                transform: none;
            }
            
            .detail-row:hover {
                background: none;
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
            .site-card.filter-show,
            .site-stats .stat-item,
            .detail-row {
                animation: none;
                transition: none;
            }
            
            .stat-card.clickable:hover {
                transform: none;
            }
        }

        /* Dark mode adjustments */
        @media (prefers-color-scheme: dark) {
            .detail-row:hover {
                background: rgba(59, 130, 246, 0.15);
            }
            
            .site-card::before {
                background: linear-gradient(90deg, #3b82f6, #10b981);
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
            
            .site-card.filter-show::before {
                opacity: 1;
                height: 6px;
            }
        }
        /* Site kartları için özel stiller */
        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
        }

        .site-card {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .site-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .site-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 16px;
        }

        .site-icon {
            font-size: 32px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
            border-radius: 12px;
            flex-shrink: 0;
        }

        .site-title {
            flex: 1;
        }

        .site-title h4 {
            margin: 0 0 4px 0;
            font-size: 18px;
            color: var(--text-primary);
        }

        .site-address {
            margin: 0;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .site-actions {
            display: flex;
            gap: 8px;
            align-items: flex-start;
        }

        .site-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
            padding: 12px 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            display: block;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
        }

        .site-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .detail-value a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .detail-value a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sites-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .site-header {
                flex-direction: column;
                gap: 12px;
            }

            .site-actions {
                align-self: stretch;
                justify-content: space-between;
            }

            .site-stats {
                justify-content: space-around;Z
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .detail-label {
                min-width: auto;
            }
        }
    </style>
</body>
</html>