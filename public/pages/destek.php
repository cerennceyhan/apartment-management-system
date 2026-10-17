<?php
// pages/destek.php - Apartman Destek Sistemi
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
$page_title = 'Teknik Destek';

// Destek kategorilerini getir
try {
    $stmt = $db->prepare("SELECT * FROM destek_kategorileri WHERE status = 1 ORDER BY kategori_adi");
    $stmt->execute();
    $kategoriler = $stmt->fetchAll();
} catch (Exception $e) {
    $kategoriler = [];
}

// Yeni destek talebi oluşturma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_talep'])) {
    $kategori = sanitize($_POST['kategori']);
    $baslik = sanitize($_POST['baslik']);
    $aciklama = sanitize($_POST['aciklama']);
    $oncelik = sanitize($_POST['oncelik']);
    
    $errors = validateRequired([
        'kategori' => 'Kategori',
        'baslik' => 'Başlık',
        'aciklama' => 'Açıklama',
        'oncelik' => 'Öncelik'
    ], $_POST);
    
    if (empty($errors)) {
        $fotograf_path = null;
        
        // Fotoğraf yükleme
        if (isset($_FILES['fotograf']) && $_FILES['fotograf']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/destek/';
            
            // Upload klasörünü oluştur
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['fotograf']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = 'destek_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['fotograf']['tmp_name'], $file_path)) {
                    $fotograf_path = 'uploads/destek/' . $file_name;
                } else {
                    $errors[] = 'Fotoğraf yüklenirken hata oluştu';
                }
            } else {
                $errors[] = 'Sadece JPG, PNG ve GIF formatları kabul edilir';
            }
        }
        
        if (empty($errors)) {
            try {
                $sakin_id = $current_user['sakin_id'] ?? $current_user['id'];
                
                $stmt = $db->prepare("INSERT INTO destek_talepleri (sakin_id, kategori, baslik, aciklama, oncelik, fotograf) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sakin_id, $kategori, $baslik, $aciklama, $oncelik, $fotograf_path]);
                
                showAlert('Destek talebiniz başarıyla oluşturuldu. En kısa sürede değerlendirilecektir.', 'success');
                
                // Formu temizle
                $_POST = [];
                
            } catch (Exception $e) {
                showAlert('Destek talebi oluşturulurken hata oluştu', 'error');
            }
        } else {
            showAlert(implode('<br>', $errors), 'error');
        }
    }
}

// Talep güncelleme (yönetici için)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_talep']) && $current_user['role'] === 'yonetici') {
    $talep_id = (int)$_POST['talep_id'];
    $durum = sanitize($_POST['durum']);
    $yonetici_notu = sanitize($_POST['yonetici_notu']);
    
    try {
        if ($durum === 'çözüldü') {
            $stmt = $db->prepare("UPDATE destek_talepleri SET durum = ?, yonetici_notu = ?, 
                                cozum_tarihi = NOW(), updated_at = NOW() WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE destek_talepleri SET durum = ?, yonetici_notu = ?, 
                                updated_at = NOW() WHERE id = ?");
        }
        $stmt->execute([$durum, $yonetici_notu, $talep_id]);
        
        showAlert('Talep başarıyla güncellendi', 'success');
        
    } catch (Exception $e) {
        showAlert('Talep güncellenirken hata oluştu', 'error');
    }
}

// Destek taleplerini getir
try {
    if ($current_user['role'] === 'yonetici') {
        // Yönetici tüm talepleri görebilir
        $stmt = $db->prepare("SELECT dt.*, s.ad_soyad, s.daire_no 
                            FROM destek_talepleri dt 
                            LEFT JOIN sakinler s ON dt.sakin_id = s.id 
                            WHERE dt.status = 1 
                            ORDER BY 
                                CASE dt.durum 
                                    WHEN 'açık' THEN 1 
                                    WHEN 'devam_ediyor' THEN 2 
                                    WHEN 'çözüldü' THEN 3 
                                    WHEN 'reddedildi' THEN 4 
                                END,
                                CASE dt.oncelik 
                                    WHEN 'acil' THEN 1 
                                    WHEN 'yüksek' THEN 2 
                                    WHEN 'orta' THEN 3 
                                    WHEN 'düşük' THEN 4 
                                END,
                                dt.created_at DESC");
        $stmt->execute();
    } else {
        // Sakin sadece kendi taleplerini görebilir
        $sakin_id = $current_user['sakin_id'] ?? $current_user['id'];
        $stmt = $db->prepare("SELECT dt.*, s.ad_soyad, s.daire_no 
                            FROM destek_talepleri dt 
                            LEFT JOIN sakinler s ON dt.sakin_id = s.id 
                            WHERE dt.sakin_id = ? AND dt.status = 1 
                            ORDER BY dt.created_at DESC");
        $stmt->execute([$sakin_id]);
    }
    
    $talepler = $stmt->fetchAll();
} catch (Exception $e) {
    $talepler = [];
    showAlert('Talepler yüklenirken hata oluştu', 'error');
}

// İstatistikler
$stats = [
    'toplam' => count($talepler),
    'acik' => count(array_filter($talepler, fn($t) => $t['durum'] === 'açık')),
    'devam_ediyor' => count(array_filter($talepler, fn($t) => $t['durum'] === 'devam_ediyor')),
    'cozuldu' => count(array_filter($talepler, fn($t) => $t['durum'] === 'çözüldü'))
];
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

        <div class="main-content">
            <div class="topbar">
                <div class="welcome-section">
                    <h1 class="page-title">🛠️ Teknik Destek</h1>
                    <p class="page-subtitle">Apartmanınızdaki teknik sorunları bildirin ve takip edin</p>
                </div>
                
                <div class="topbar-actions">
                    <button class="btn btn-primary" onclick="openModal('createTalepModal')">
                        ➕ Yeni Talep
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
                            <a href="../logout.php" class="dropdown-item" style="color: #ef4444;">🚪 Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <?php 
                $alert = getAlert();
                if ($alert): 
                ?>
                    <div class="alert alert-<?= $alert['type'] ?>">
                        <?= htmlspecialchars($alert['message']) ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Talep</div>
                        </div>
                        <div class="stat-value"><?= $stats['toplam'] ?></div>
                        <div class="stat-change">Tüm talepler</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-header">
                            <div class="stat-title">Bekleyen</div>
                        </div>
                        <div class="stat-value"><?= $stats['acik'] ?></div>
                        <div class="stat-change negative">Değerlendirilmedi</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-header">
                            <div class="stat-title">İşlemde</div>
                        </div>
                        <div class="stat-value"><?= $stats['devam_ediyor'] ?></div>
                        <div class="stat-change">Çözüm aşamasında</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <div class="stat-title">Çözüldü</div>
                        </div>
                        <div class="stat-value"><?= $stats['cozuldu'] ?></div>
                        <div class="stat-change positive">Tamamlandı</div>
                    </div>
                </div>

                <!-- Talepler Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Destek Talepleri</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($talepler)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">🛠️</div>
                                <h3>Henüz destek talebi bulunmuyor</h3>
                                <p>Apartmanınızda bir teknik sorun olduğunda buradan bildirebilirsiniz.</p>
                                <button class="btn btn-primary" onclick="openModal('createTalepModal')" style="margin-top: 16px;">
                                    ➕ İlk Talebi Oluştur
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="talep-list">
                                <?php foreach ($talepler as $talep): ?>
                                <div class="talep-item" onclick="toggleTalepDetails(<?= $talep['id'] ?>)">
                                    <div class="talep-header">
                                        <div class="talep-main">
                                            <div class="talep-info">
                                                <div class="talep-baslik"><?= htmlspecialchars($talep['baslik']) ?></div>
                                                <div class="talep-meta">
                                                    <span class="kategori"><?= htmlspecialchars($talep['kategori']) ?></span>
                                                    <?php if ($current_user['role'] === 'yonetici'): ?>
                                                        <span class="sakin-info">
                                                            <?= htmlspecialchars($talep['ad_soyad']) ?> - Daire <?= htmlspecialchars($talep['daire_no']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="talep-badges">
                                            <span class="durum-badge durum-<?= str_replace(['ö', 'ü'], ['o', 'u'], $talep['durum']) ?>">
                                                <?= ucfirst($talep['durum']) ?>
                                            </span>
                                            <span class="oncelik-badge oncelik-<?= str_replace(['ö', 'ü'], ['o', 'u'], $talep['oncelik']) ?>">
                                                <?= ucfirst($talep['oncelik']) ?>
                                            </span>
                                            <div class="expand-icon">
                                                <span class="chevron">▼</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="talep-details" id="details-<?= $talep['id'] ?>" style="display: none;">
                                        <div class="details-content">
                                            <div class="aciklama-section">
                                                <h4>📝 Açıklama</h4>
                                                <p><?= nl2br(htmlspecialchars($talep['aciklama'])) ?></p>
                                            </div>
                                            
                                            <?php if ($talep['fotograf']): ?>
                                            <div class="fotograf-section">
                                                <h4>📷 Fotoğraf</h4>
                                                <div class="fotograf-container">
                                                    <img src="../<?= htmlspecialchars($talep['fotograf']) ?>" 
                                                         alt="Talep fotoğrafı" 
                                                         class="talep-fotograf"
                                                         onclick="openImageModal('../<?= htmlspecialchars($talep['fotograf']) ?>')">
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($talep['yonetici_notu']): ?>
                                            <div class="yonetici-notu-section">
                                                <h4>💬 Yönetici Notu</h4>
                                                <p><?= nl2br(htmlspecialchars($talep['yonetici_notu'])) ?></p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="talep-timeline">
                                                <div class="timeline-item">
                                                    <span class="timeline-date">📅 Oluşturulma:</span>
                                                    <span><?= formatDate($talep['created_at']) ?></span>
                                                </div>
                                                <?php if ($talep['updated_at'] !== $talep['created_at']): ?>
                                                <div class="timeline-item">
                                                    <span class="timeline-date">🔄 Son Güncelleme:</span>
                                                    <span><?= formatDate($talep['updated_at']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                                <?php if ($talep['cozum_tarihi']): ?>
                                                <div class="timeline-item">
                                                    <span class="timeline-date">✅ Çözüm Tarihi:</span>
                                                    <span><?= formatDate($talep['cozum_tarihi']) ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($current_user['role'] === 'yonetici'): ?>
                                        <div class="talep-actions">
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="event.stopPropagation(); updateTalep(<?= $talep['id'] ?>, '<?= $talep['durum'] ?>', '<?= htmlspecialchars($talep['yonetici_notu'] ?? '') ?>')"
                                                    title="Talebi Güncelle">
                                                ✏️ Güncelle
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
            </div>
        </div>
    </div>

    <!-- Yeni Talep Modal -->
    <div class="modal" id="createTalepModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">🛠️ Yeni Destek Talebi</h3>
                <button class="modal-close" onclick="closeModal('createTalepModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="createTalepForm">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" class="form-select" required>
                                <option value="">Kategori Seçin</option>
                                <?php foreach ($kategoriler as $kategori): ?>
                                    <option value="<?= htmlspecialchars($kategori['kategori_adi']) ?>">
                                        <?= htmlspecialchars($kategori['kategori_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Öncelik</label>
                            <select name="oncelik" class="form-select" required>
                                <option value="düşük">🟢 Düşük</option>
                                <option value="orta" selected>🟡 Orta</option>
                                <option value="yüksek">🟠 Yüksek</option>
                                <option value="acil">🔴 Acil</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="baslik" class="form-input" 
                               placeholder="Örn: 3. kat ışıkları çalışmıyor" required 
                               maxlength="255">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Açıklama</label>
                        <textarea name="aciklama" class="form-textarea" rows="4" 
                                  placeholder="Problemi detaylı olarak açıklayın..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Fotoğraf (Opsiyonel)</label>
                        <input type="file" name="fotograf" id="fotografInput" 
                               accept="image/*" class="form-input">
                        <small class="form-hint">JPG, PNG, GIF formatları kabul edilir (Max 5MB)</small>
                    </div>
                    
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <strong>ℹ️ Bilgi:</strong> Talebiniz oluşturulduktan sonra yönetici tarafından değerlendirilecek ve size bilgi verilecektir.
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('createTalepModal')">
                            İptal
                        </button>
                        <button type="submit" name="create_talep" class="btn btn-primary">
                            🛠️ Talep Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Talep Güncelleme Modal (Yönetici için) -->
    <?php if ($current_user['role'] === 'yonetici'): ?>
    <div class="modal" id="updateTalepModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Talep Güncelle</h3>
                <button class="modal-close" onclick="closeModal('updateTalepModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateTalepForm">
                    <input type="hidden" name="talep_id" id="updateTalepId">
                    
                    <div class="form-group">
                        <label class="form-label">Durum</label>
                        <select name="durum" id="updateDurum" class="form-select" required>
                            <option value="açık">🟡 Bekliyor</option>
                            <option value="devam_ediyor">🔄 İşlemde</option>
                            <option value="çözüldü">✅ Çözüldü</option>
                            <option value="reddedildi">❌ Reddedildi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Yönetici Notu</label>
                        <textarea name="yonetici_notu" id="updateYoneticiNotu" class="form-textarea" rows="4" 
                                  placeholder="Talep hakkında not ekleyin..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('updateTalepModal')">
                            İptal
                        </button>
                        <button type="submit" name="update_talep" class="btn btn-primary">
                            ✏️ Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Fotoğraf Görüntüleme Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content modal-image">
            <div class="modal-header">
                <h3 class="modal-title">📷 Fotoğraf</h3>
                <button class="modal-close" onclick="closeModal('imageModal')">×</button>
            </div>
            <div class="modal-body">
                <img id="modalImage" src="" alt="Talep fotoğrafı" class="modal-full-image">
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>  
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
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

        function toggleTalepDetails(talepId) {
            const detailsDiv = document.getElementById('details-' + talepId);
            const chevron = detailsDiv.parentElement.querySelector('.chevron');
            
            if (detailsDiv.style.display === 'none') {
                document.querySelectorAll('.talep-details').forEach(detail => {
                    detail.style.display = 'none';
                });
                document.querySelectorAll('.chevron').forEach(icon => {
                    icon.style.transform = 'rotate(0deg)';
                });
                
                detailsDiv.style.display = 'block';
                chevron.style.transform = 'rotate(180deg)';
            } else {
                detailsDiv.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }
        
        function updateTalep(id, durum, yoneticiNotu) {
            document.getElementById('updateTalepId').value = id;
            document.getElementById('updateDurum').value = durum;
            document.getElementById('updateYoneticiNotu').value = yoneticiNotu;
            openModal('updateTalepModal');
        }

        function openImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            openModal('imageModal');
        }

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
        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 4px 0 0 0;
            font-weight: 400;
        }

        .modal-lg {
            max-width: 800px;
        }

        .modal-image {
            max-width: 90vw;
            max-height: 90vh;
        }

        .modal-full-image {
            max-width: 100%;
            max-height: 70vh;
            object-fit: contain;
        }

        .talep-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .talep-item {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .talep-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
            border-color: var(--primary-color);
        }

        .talep-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
        }

        .talep-main {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .talep-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .talep-baslik {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 16px;
        }

        .talep-meta {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .kategori {
            background: var(--background-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 500;
        }

        .sakin-info {
            color: var(--text-secondary);
        }

        .talep-badges {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .durum-badge, .oncelik-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .durum-acik {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .durum-devam_ediyor {
            background: rgba(59, 130, 246, 0.1);
            color: #2563eb;
        }

        .durum-cozuldu {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }

        .durum-reddedildi {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .oncelik-dusuk {
            background: rgba(34, 197, 94, 0.1);
            color: #16a34a;
        }

        .oncelik-orta {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .oncelik-yuksek {
            background: rgba(249, 115, 22, 0.1);
            color: #ea580c;
        }

        .oncelik-acil {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
        }

        .expand-icon {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .chevron {
            transition: transform 0.3s ease;
        }

        .talep-details {
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
                max-height: 800px;
                padding: 20px;
            }
        }

        .details-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .aciklama-section h4,
        .fotograf-section h4,
        .yonetici-notu-section h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .aciklama-section p,
        .yonetici-notu-section p {
            margin: 0;
            line-height: 1.5;
            color: var(--text-secondary);
        }

        .fotograf-container {
            display: flex;
            justify-content: flex-start;
        }

        .talep-fotograf {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
            object-fit: cover;
        }

        .talep-fotograf:hover {
            transform: scale(1.05);
        }

        .talep-timeline {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .timeline-item {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 13px;
        }

        .timeline-date {
            color: var(--text-secondary);
            font-weight: 500;
            min-width: 120px;
        }

        .talep-actions {
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            margin-top: 16px;
        }

        .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--card-background);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
        }

        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea::placeholder {
            color: var(--text-secondary);
        }

        .grid {
            display: grid;
            gap: 16px;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-ghost {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-ghost:hover {
            background: var(--background-color);
            color: var(--text-primary);
            border-color: var(--primary-color);
        }

        .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--card-background);
            color: var(--text-primary);
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-hint {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin: 16px 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .empty-state p {
            margin: 0 0 16px 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background: var(--card-background);
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.95);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 20px 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
            padding-bottom: 16px;
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
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 0 20px 20px 20px;
        }

        .alert.alert-info {
            background: rgba(59, 130, 246, 0.1);
            border-color: rgba(59, 130, 246, 0.2);
            color: #1e40af;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .talep-header {
                padding: 12px 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .talep-main {
                gap: 8px;
                width: 100%;
            }

            .talep-baslik {
                font-size: 14px;
            }

            .talep-badges {
                gap: 6px;
                flex-wrap: wrap;
            }

            .durum-badge, .oncelik-badge {
                font-size: 10px;
                padding: 2px 6px;
            }

            .details-content {
                gap: 16px;
            }

            .talep-details {
                padding: 16px;
            }

            .talep-fotograf {
                max-width: 150px;
                max-height: 150px;
            }

            .modal-content {
                width: 95%;
                margin: 20px;
                max-height: calc(100vh - 40px);
            }
            
            .modal-header {
                padding: 16px 16px 0 16px;
            }
            
            .modal-body {
                padding: 0 16px 16px 16px;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>