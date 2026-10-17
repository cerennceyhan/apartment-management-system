<?php
// src/views/sidebar.php - Nav Badge'ler kaldırılmış versiyon + Çıkış butonu

// Bu dosyanın direkt çağırılmasını engelle
if (!defined('APP_LOADED')) {
    die('Bu dosyaya direkt erişim yasaktır.');
}

// Aktif sayfa tespiti için
$current_page = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['REQUEST_URI'];

// Aktif link fonksiyonu
function isActiveLink($href, $current_page, $current_path) {
    // Eğer index.php ise
    if ($href === 'index.php' && ($current_page === 'index.php' || $current_path === '/')) {
        return true;
    }
    
    // Diğer sayfalar için
    if (strpos($current_path, $href) !== false && $href !== 'index.php') {
        return true;
    }
    
    return false;
}

// Site/Blok seçim durumunu URL'den veya session'dan al
$selected_site_id = isset($_GET['site_id']) ? (int)$_GET['site_id'] : (isset($_SESSION['selected_site_id']) ? $_SESSION['selected_site_id'] : 1);
$selected_blok_id = isset($_GET['blok_id']) ? (int)$_GET['blok_id'] : (isset($_SESSION['selected_blok_id']) ? $_SESSION['selected_blok_id'] : null);

// Seçimi session'a kaydet
if (isset($_GET['site_id'])) {
    $_SESSION['selected_site_id'] = $selected_site_id;
}
if (isset($_GET['blok_id'])) {
    $_SESSION['selected_blok_id'] = $selected_blok_id;
}

// Site/blok bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM siteler WHERE id = ? AND status = 1");
    $stmt->execute([$selected_site_id]);
    $site_info = $stmt->fetch();
    
    if ($selected_blok_id) {
        $stmt = $db->prepare("SELECT * FROM bloklar WHERE id = ? AND status = 1");
        $stmt->execute([$selected_blok_id]);
        $blok_info = $stmt->fetch();
    } else {
        $blok_info = null;
    }
    
    // Seçili site için blokları al
    $stmt = $db->prepare("SELECT * FROM bloklar WHERE site_id = ? AND status = 1 ORDER BY blok_adi");
    $stmt->execute([$selected_site_id]);
    $site_bloklar = $stmt->fetchAll();
    
    // Tüm siteleri al (yönetici için)
    if ($current_user['role'] === 'yonetici') {
        $stmt = $db->prepare("SELECT * FROM siteler WHERE status = 1 ORDER BY site_adi");
        $stmt->execute();
        $all_sites = $stmt->fetchAll();
    } else {
        $all_sites = [$site_info];
    }
} catch (Exception $e) {
    $site_info = null;
    $blok_info = null;
    $site_bloklar = [];
    $all_sites = [];
}
?>

<!-- Modern Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <span id="toggle-icon">‹</span>
    </button>
    
    <div class="sidebar-header">
        <div class="logo">🏢</div>
        <div class="brand-text">Armiya Tech.</div>
    </div>
    
    <nav class="nav-menu">
        <!-- Ana Menü -->
        <div class="nav-section">
            <div class="nav-section-title">Site & Blok</div>
            
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? '../index.php' : 'index.php' ?>" 
                   class="nav-link <?= isActiveLink('index.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">📊</div>
                    <span class="nav-text">Ana Sayfa</span>
                    <div class="nav-tooltip">Dashboard</div>
                </a>
            </div>
            
            <!-- Siteler (sadece yönetici için) -->
            <?php if ($current_user['role'] === 'yonetici'): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'siteler.php' : 'pages/siteler.php' ?>" 
                   class="nav-link <?= isActiveLink('siteler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">🏙️</div>
                    <span class="nav-text">Siteler</span>
                    <div class="nav-tooltip">Site Yönetimi</div>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'bloklar.php' : 'pages/bloklar.php' ?>" 
                   class="nav-link <?= isActiveLink('bloklar.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">🏢</div>
                    <span class="nav-text">Bloklar</span>
                    <div class="nav-tooltip">Blok Yönetimi</div>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- DAİRELER -->
            <?php if (hasPermission('daireler.view')): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'daireler.php' : 'pages/daireler.php' ?>" 
                   class="nav-link <?= isActiveLink('daireler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">🏠</div>
                    <span class="nav-text">Daireler</span>
                    <div class="nav-tooltip">Daire Yönetimi</div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (hasPagePermission('sakinler')): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'sakinler.php' : 'pages/sakinler.php' ?>" 
                   class="nav-link <?= isActiveLink('sakinler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">👥</div>
                    <span class="nav-text">Sakinler</span>
                    <div class="nav-tooltip">Sakinler</div>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Finansal Yönetim -->
        <div class="nav-section">
            <div class="nav-section-title">Finansal</div>
            
            <!-- AIDATLAR - Yönetici ve Muhasebeci için -->
            <?php if ($current_user['role'] === 'yonetici' || $current_user['role'] === 'muhasebeci'): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'aidatlar.php' : 'pages/aidatlar.php' ?>" 
                   class="nav-link <?= isActiveLink('aidatlar.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">💰</div>
                    <span class="nav-text">Aidatlar</span>
                    <div class="nav-tooltip">Tüm Aidatlar</div>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- AİDATLARIM - Sadece Sakinler için -->
            <?php if ($current_user['role'] === 'sakin'): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'aidatlarim.php' : 'pages/aidatlarim.php' ?>" 
                   class="nav-link <?= isActiveLink('aidatlarim.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">💳</div>
                    <span class="nav-text">Aidatlarım</span>
                    <div class="nav-tooltip">Benim Aidatlarım</div>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- CARİ HAREKETLER -->
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'carihareketler.php' : 'pages/carihareketler.php' ?>" 
                   class="nav-link <?= isActiveLink('carihareketler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">📋</div>
                    <span class="nav-text">Cari Hareketler</span>
                    <div class="nav-tooltip">Cari Hareketler</div>
                </a>
            </div>
            
            <?php if (hasPagePermission('giderler')): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'giderler.php' : 'pages/giderler.php' ?>" 
                   class="nav-link <?= isActiveLink('giderler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">📉</div>
                    <span class="nav-text">Giderler</span>
                    <div class="nav-tooltip">Giderler</div>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Destek & Yönetim -->
        <div class="nav-section">
            <div class="nav-section-title">Destek & Yönetim</div>
            
            <!-- TEKNİK DESTEK -->
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'destek.php' : 'pages/destek.php' ?>" 
                   class="nav-link <?= isActiveLink('destek.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">🛠️</div>
                    <span class="nav-text">Teknik Destek</span>
                    <div class="nav-tooltip">Teknik Destek</div>
                </a>
            </div>

            <?php if ($current_user['role'] === 'yonetici'): ?>
            <div class="nav-item">
                <a href="<?= defined('IN_PAGES') ? 'yetkiler.php' : 'pages/yetkiler.php' ?>" 
                class="nav-link <?= isActiveLink('yetkiler.php', $current_page, $current_path) ? 'active' : '' ?>">
                    <div class="nav-icon">⚙️</div>
                    <span class="nav-text">Yetki Yönetimi</span>
                    <div class="nav-tooltip">Yetki Yönetimi</div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </nav>
    
    <!-- Sidebar Footer - Çıkış Butonu -->
    <div class="sidebar-footer">

        
        <!-- Çıkış Butonu -->
        <div class="nav-item logout-item">
            <a href="<?= defined('IN_PAGES') ? '../logout.php' : 'logout.php' ?>" 
               class="nav-link logout-link" 
               onclick="return confirm('Çıkış yapmak istediğinizden emin misiniz?')">
                <div class="nav-icon logout-icon">🚪</div>
                <span class="nav-text">Çıkış Yap</span>
                <div class="nav-tooltip">Sistemden Çık</div>
            </a>
        </div>
    </div>
</div>

<!-- Site/Blok değişim JavaScript'i -->
<script>
function changeSite(siteId) {
    if (!siteId) return;
    
    // Sayfa yenile ile basit çözüm
    const url = new URL(window.location);
    url.searchParams.set('site_id', siteId);
    window.location = url.toString();
}

function changeBlok(blokId) {
    // Sayfa yenile ile basit çözüm
    const url = new URL(window.location);
    if (blokId) {
        url.searchParams.set('blok_id', blokId);
    } else {
        url.searchParams.delete('blok_id');
    }
    window.location = url.toString();
}

// URL parametrelerinden site/blok seçimini al
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const siteId = urlParams.get('site_id');
    const blokId = urlParams.get('blok_id');
    
    if (siteId) {
        // Site seçimini session'a kaydet
        fetch('<?= defined('IN_PAGES') ? '../' : '' ?>ajax/set_selection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                site_id: siteId,
                blok_id: blokId 
            })
        });
    }
});
</script>