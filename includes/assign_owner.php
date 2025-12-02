<?php
// includes/assign_owner.php - Ev sahibi atama sayfası
session_start();

define('IN_PAGES', true);
define('APP_LOADED', true);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Yetki kontrolü
if (!hasPermission('daireler.assign_owner')) {
    http_response_code(403);
    exit('Bu işlem için yetkiniz yok');
}

// Daire ID'sini al
$daire_id = isset($_GET['daire_id']) ? (int)$_GET['daire_id'] : 0;

if (!$daire_id) {
    echo '<div class="alert alert-error">Geçersiz daire ID</div>';
    exit();
}

// Daire bilgisini al
try {
    $stmt = $db->prepare("
        SELECT d.*, s.site_adi, b.blok_adi 
        FROM daireler d 
        JOIN siteler s ON d.site_id = s.id 
        JOIN bloklar b ON d.blok_id = b.id 
        WHERE d.id = ? AND d.status = 1
    ");
    $stmt->execute([$daire_id]);
    $daire = $stmt->fetch();
    
    if (!$daire) {
        echo '<div class="alert alert-error">Daire bulunamadı</div>';
        exit();
    }
} catch (Exception $e) {
    echo '<div class="alert alert-error">Daire bilgisi alınamadı</div>';
    exit();
}

// Bu dairede zaten ev sahibi var mı kontrol et
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM daire_sakinler ds 
        WHERE ds.daire_id = ? AND ds.sakin_tipi = 'ev_sahibi' AND ds.status = 1
    ");
    $stmt->execute([$daire_id]);
    $ev_sahibi_count = $stmt->fetch()['count'];
    
    if ($ev_sahibi_count > 0) {
        echo '<div class="alert alert-warning">Bu dairede zaten bir ev sahibi bulunmaktadır. Önce mevcut ev sahibini çıkarmanız gerekir.</div>';
        exit();
    }
} catch (Exception $e) {
    echo '<div class="alert alert-error">Ev sahibi kontrolü yapılamadı</div>';
    exit();
}

// Mevcut sakinleri al (sitedeki tüm sakinler - sadece ev sahibi olmayanlar)
try {
    $stmt = $db->prepare("
        SELECT DISTINCT s.id, s.ad_soyad, s.telefon, s.email,
               d.daire_no, b.blok_adi,
               ds.sakin_tipi,
               CASE WHEN ds.sakin_tipi = 'ev_sahibi' THEN 1 ELSE 0 END as is_owner
        FROM sakinler s
        LEFT JOIN daire_sakinler ds ON s.id = ds.sakin_id AND ds.status = 1
        LEFT JOIN daireler d ON ds.daire_id = d.id
        LEFT JOIN bloklar b ON d.blok_id = b.id
        WHERE s.site_id = ? AND s.status = 1
        AND s.id NOT IN (
            SELECT ds2.sakin_id 
            FROM daire_sakinler ds2 
            WHERE ds2.sakin_tipi = 'ev_sahibi' AND ds2.status = 1
        )
        ORDER BY s.ad_soyad
    ");
    $stmt->execute([$daire['site_id']]);
    $mevcut_sakinler = $stmt->fetchAll();
} catch (Exception $e) {
    $mevcut_sakinler = [];
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        if (isset($_POST['assign_existing'])) {
            // Mevcut sakin atama
            $sakin_id = (int)$_POST['sakin_id'];
            
            if (!$sakin_id) {
                throw new Exception('Sakin seçmelisiniz');
            }
            
            // Sakinin zaten ev sahibi olup olmadığını kontrol et
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM daire_sakinler 
                WHERE sakin_id = ? AND sakin_tipi = 'ev_sahibi' AND status = 1
            ");
            $stmt->execute([$sakin_id]);
            if ($stmt->fetch()['count'] > 0) {
                throw new Exception('Bu sakin zaten başka bir dairenin ev sahibi');
            }
            
            // Mevcut atamayı iptal et (eğer varsa)
            $stmt = $db->prepare("
                UPDATE daire_sakinler 
                SET status = 0 
                WHERE sakin_id = ? AND status = 1
            ");
            $stmt->execute([$sakin_id]);
            
            // Yeni ev sahibi ataması yap
            $stmt = $db->prepare("
                INSERT INTO daire_sakinler (daire_id, sakin_id, sakin_tipi, baslangic_tarihi) 
                VALUES (?, ?, 'ev_sahibi', CURDATE())
            ");
            $stmt->execute([$daire_id, $sakin_id]);
            
            // Sakin tablosunda daire bilgilerini güncelle
            $stmt = $db->prepare("
                UPDATE sakinler 
                SET site_id = ?, blok_id = ?, daire_no = ? 
                WHERE id = ?
            ");
            $stmt->execute([$daire['site_id'], $daire['blok_id'], $daire['daire_no'], $sakin_id]);
            
        } elseif (isset($_POST['add_new'])) {
            // Yeni sakin ekleme
            $ad_soyad = trim($_POST['ad_soyad']);
            $telefon = trim($_POST['telefon']);
            $email = trim($_POST['email']);
            
            if (empty($ad_soyad) || empty($telefon) || empty($email)) {
                throw new Exception('Tüm alanları doldurun');
            }
            
            // Email kontrolü
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Geçerli bir email adresi girin');
            }
            
            // Aynı isimde sakin var mı kontrol et
            $stmt = $db->prepare("SELECT id FROM sakinler WHERE ad_soyad = ? AND status = 1");
            $stmt->execute([$ad_soyad]);
            if ($stmt->fetch()) {
                throw new Exception('Bu isimde bir sakin zaten mevcut');
            }
            
            // Email kontrolü
            $stmt = $db->prepare("SELECT id FROM sakinler WHERE email = ? AND status = 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Bu email adresi zaten kullanılıyor');
            }
            
            // Yeni sakin ekle (şifresiz - ilk giriş için)
            $stmt = $db->prepare("
                INSERT INTO sakinler (site_id, blok_id, ad_soyad, daire_no, telefon, email, password, yetki_grup_id, first_login) 
                VALUES (?, ?, ?, ?, ?, ?, NULL, 3, 1)
            ");
            $stmt->execute([
                $daire['site_id'], 
                $daire['blok_id'], 
                $ad_soyad, 
                $daire['daire_no'], 
                $telefon, 
                $email
            ]);
                        
            $new_sakin_id = $db->lastInsertId();
            
            // Ev sahibi olarak ata
            $stmt = $db->prepare("
                INSERT INTO daire_sakinler (daire_id, sakin_id, sakin_tipi, baslangic_tarihi) 
                VALUES (?, ?, 'ev_sahibi', CURDATE())
            ");
            $stmt->execute([$daire_id, $new_sakin_id]);
        }
        
        $db->commit();
        
        echo '<script>
            parent.showAlert("Ev sahibi başarıyla atandı", "success");
            parent.closeAssignModal();
            parent.location.reload();
        </script>';
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        echo '<div class="alert alert-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>

<div class="assign-container">
    <!-- Daire Bilgisi -->
    <div class="assign-info">
        <div class="assign-daire-info">
            <div class="assign-daire-icon">🏠</div>
            <div class="assign-daire-details">
                <h4>Daire <?= htmlspecialchars($daire['daire_no']) ?></h4>
                <p><?= htmlspecialchars($daire['site_adi']) ?> - <?= htmlspecialchars($daire['blok_adi']) ?></p>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="assign-tabs">
        <button class="assign-tab active" data-tab="existing" onclick="switchTab('existing')">
            👥 Mevcut Sakinlerden Seç
        </button>
        <button class="assign-tab" data-tab="new" onclick="switchTab('new')">
            ➕ Yeni Sakin Ekle
        </button>
    </div>

    <!-- Mevcut Sakinler Tab -->
    <div class="assign-tab-content active" id="existing-tab">
        <?php if (empty($mevcut_sakinler)): ?>
            <div class="assign-empty">
                <div class="assign-empty-icon">👤</div>
                <h4>Uygun sakin bulunamadı</h4>
                <p>Sitede henüz ev sahibi olmayan sakin bulunmuyor. Yeni sakin ekleyebilirsiniz.</p>
            </div>
        <?php else: ?>
            <form method="POST" id="existingForm">
                <div class="assign-search">
                    <input type="text" placeholder="Sakin ara..." id="sakinSearch" class="assign-search-input">
                </div>
                
                <div class="assign-sakin-list">
                    <?php foreach ($mevcut_sakinler as $sakin): ?>
                        <div class="assign-sakin-item" data-search="<?= strtolower($sakin['ad_soyad']) ?>">
                            <label class="assign-sakin-label">
                                <input type="radio" name="sakin_id" value="<?= $sakin['id'] ?>" required>
                                <div class="assign-sakin-card">
                                    <div class="assign-sakin-avatar">
                                        <?= substr($sakin['ad_soyad'], 0, 2) ?>
                                    </div>
                                    <div class="assign-sakin-info">
                                        <div class="assign-sakin-name"><?= htmlspecialchars($sakin['ad_soyad']) ?></div>
                                        <div class="assign-sakin-contact">
                                            📞 <?= htmlspecialchars($sakin['telefon']) ?>
                                        </div>
                                        <div class="assign-sakin-email">
                                            📧 <?= htmlspecialchars($sakin['email']) ?>
                                        </div>
                                        <?php if ($sakin['daire_no']): ?>
                                            <div class="assign-sakin-current">
                                                🏠 <?= htmlspecialchars($sakin['blok_adi']) ?> - Daire <?= htmlspecialchars($sakin['daire_no']) ?>
                                                <?php if ($sakin['sakin_tipi'] === 'kiraci'): ?>
                                                    <span class="assign-badge assign-badge-tenant">Kiracı</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="assign-sakin-status">
                                                <span class="assign-badge assign-badge-unassigned">Atanmamış</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="assign-actions">
                    <button type="button" class="btn btn-ghost" onclick="parent.closeAssignModal()">
                        İptal
                    </button>
                    <button type="submit" name="assign_existing" class="btn btn-primary">
                        🏠 Ev Sahibi Olarak Ata
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Yeni Sakin Tab -->
    <div class="assign-tab-content" id="new-tab">
        <form method="POST" id="newSakinForm">
            <div class="assign-form">
                <div class="assign-form-group">
                    <label class="assign-form-label">Ad Soyad *</label>
                    <input type="text" name="ad_soyad" class="assign-form-input" required placeholder="Örn: Ahmet Yılmaz">
                </div>
                
                <div class="assign-form-group">
                    <label class="assign-form-label">Telefon *</label>
                    <input type="tel" name="telefon" class="assign-form-input" required placeholder="Örn: 0555-123-4567">
                </div>
                
                <div class="assign-form-group">
                    <label class="assign-form-label">Email *</label>
                    <input type="email" name="email" class="assign-form-input" required placeholder="Örn: ahmet@gmail.com">
                </div>
                
                <div class="assign-form-info">
                    <div class="assign-info-card">
                        <div class="assign-info-icon">ℹ️</div>
                        <div class="assign-info-text">
                            <strong>Bilgi:</strong> Yeni sakin otomatik olarak sisteme kaydedilecek ve bu daireye ev sahibi olarak atanacaktır. 
                             Sakin ilk giriş yaparken kendi şifresini oluşturacaktır.
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="assign-actions">
                <button type="button" class="btn btn-ghost" onclick="parent.closeAssignModal()">
                    İptal
                </button>
                <button type="submit" name="add_new" class="btn btn-success">
                    ➕ Ekle ve Ev Sahibi Yap
                </button>
            </div>
        </form>
    </div>
</div>

<script>


// Sakin arama
document.getElementById('sakinSearch')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const sakinItems = document.querySelectorAll('.assign-sakin-item');
    
    sakinItems.forEach(item => {
        const searchData = item.dataset.search;
        if (searchData.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});

// Form submit önleme ve AJAX gönderimi
document.getElementById('existingForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('assign_existing', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('<script>')) {
            // Script içeriyorsa direkt çalıştır
            const scriptMatch = data.match(/<script>([\s\S]*?)<\/script>/);
            if (scriptMatch) {
                eval(scriptMatch[1]);
            }
        } else {
            // HTML içeriyorsa modalı güncelle
            document.querySelector('#assignmentContent').innerHTML = data;
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        parent.showAlert('Bir hata oluştu', 'error');
    });
});

document.getElementById('newSakinForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('add_new', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('<script>')) {
            // Script içeriyorsa direkt çalıştır
            const scriptMatch = data.match(/<script>([\s\S]*?)<\/script>/);
            if (scriptMatch) {
                eval(scriptMatch[1]);
            }
        } else {
            // HTML içeriyorsa modalı güncelle
            document.querySelector('#assignmentContent').innerHTML = data;
        }
    })
    .catch(error => {
        console.error('Hata:', error);
        parent.showAlert('Bir hata oluştu', 'error');
    });
});
</script>

<style>
/* Assign Container */
.assign-container {
    padding: 0;
}

/* Daire Bilgisi */
.assign-info {
    background: var(--background-color);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.assign-daire-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.assign-daire-icon {
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

.assign-daire-details h4 {
    margin: 0 0 4px 0;
    color: var(--text-primary);
    font-size: 18px;
}

.assign-daire-details p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 14px;
}

/* Tabs */
.assign-tabs {
    display: flex;
    gap: 4px;
    background: var(--background-color);
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 20px;
}

.assign-tab {
    flex: 1;
    padding: 12px 16px;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.assign-tab.active {
    background: var(--primary-color);
    color: white;
}

.assign-tab:hover:not(.active) {
    background: var(--card-background);
    color: var(--text-primary);
}

/* Tab Content */
.assign-tab-content {
    display: none;
}

.assign-tab-content.active {
    display: block;
}

/* Search */
.assign-search {
    margin-bottom: 16px;
}

.assign-search-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    background: var(--card-background);
    color: var(--text-primary);
}

.assign-search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Sakin List */
.assign-sakin-list {
    max-height: 400px;
    overflow-y: auto;
    margin-bottom: 20px;
}

.assign-sakin-item {
    margin-bottom: 8px;
}

.assign-sakin-label {
    display: block;
    cursor: pointer;
}

.assign-sakin-label input[type="radio"] {
    display: none;
}

.assign-sakin-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    background: var(--card-background);
    transition: all 0.3s ease;
}

.assign-sakin-label:hover .assign-sakin-card {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.assign-sakin-label input[type="radio"]:checked + .assign-sakin-card {
    border-color: var(--primary-color);
    background: rgba(37, 99, 235, 0.05);
}

.assign-sakin-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.assign-sakin-info {
    flex: 1;
    min-width: 0;
}

.assign-sakin-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    font-size: 16px;
}

.assign-sakin-contact,
.assign-sakin-email,
.assign-sakin-current {
    font-size: 13px;
    color: var(--text-secondary);
    margin-bottom: 2px;
}

.assign-sakin-status {
    margin-top: 4px;
}

/* Badges */
.assign-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.assign-badge-tenant {
    background: rgba(6, 182, 212, 0.1);
    color: #0891b2;
    border: 1px solid rgba(6, 182, 212, 0.2);
}

.assign-badge-unassigned {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

/* Empty State */
.assign-empty {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.assign-empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
}

.assign-empty h4 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
}

.assign-empty p {
    margin: 0;
    font-size: 14px;
}

/* Form */
.assign-form {
    margin-bottom: 20px;
}

.assign-form-group {
    margin-bottom: 16px;
}

.assign-form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.assign-form-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    background: var(--card-background);
    color: var(--text-primary);
    transition: border-color 0.3s ease;
}

.assign-form-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Info Card */
.assign-form-info {
    margin-top: 16px;
}

.assign-info-card {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    background: rgba(37, 99, 235, 0.05);
    border: 1px solid rgba(37, 99, 235, 0.1);
    border-radius: 8px;
}

.assign-info-icon {
    font-size: 16px;
    flex-shrink: 0;
}

.assign-info-text {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.assign-info-text code {
    background: rgba(0, 0, 0, 0.1);
    padding: 2px 4px;
    border-radius: 4px;
    font-size: 12px;
}

/* Actions */
.assign-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    border-top: 1px solid var(--border-color);
    padding-top: 16px;
    margin-top: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .assign-tabs {
        flex-direction: column;
    }
    
    .assign-tab {
        flex: none;
    }
    
    .assign-sakin-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .assign-actions {
        flex-direction: column;
    }
    
    .assign-actions .btn {
        width: 100%;
    }
}
</style>