<?php
// pages/yetkiler.php - Gelişmiş Yetki yönetimi sayfası
$page_title = 'Yetki Yönetimi';
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/functions.php';

define('IN_PAGES', true);
define('APP_LOADED', true);
// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Sadece yönetici erişebilir
if ($current_user['role'] !== 'yonetici') {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Kullanıcı atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_user'])) {
    $grup_id = (int)$_POST['grup_id'];
    $ad_soyad = trim($_POST['ad_soyad']);
    $telefon = trim($_POST['telefon']);
    $email = trim($_POST['email']);
    
    if ($grup_id && $ad_soyad) {
        try {
            $db->beginTransaction();
            
            // Önce grup bilgisini al ve sakin grubu kontrolü yap
            $stmt = $db->prepare("SELECT grup_adi FROM yetki_gruplari WHERE id = ? AND status = 1");
            $stmt->execute([$grup_id]);
            $grup_info = $stmt->fetch();
            
            if (!$grup_info) {
                showAlert('Geçersiz grup seçimi!', 'error');
                $db->rollback();
            } elseif (strtolower($grup_info['grup_adi']) === 'sakin') {
                showAlert('Sakin grubuna manuel kullanıcı ataması yapılamaz! Sakinler sistem tarafından otomatik yönetilir.', 'error');
                $db->rollback();
            } else {
                // Email benzersizlik kontrolü (eğer email girilmişse)
                if ($email) {
                    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND status = 1");
                    $stmt->execute([$email]);
                    if ($stmt->fetchColumn() > 0) {
                        showAlert('Bu email adresi zaten kullanılmakta!', 'error');
                        $db->rollback();
                    } else {
                        // Kullanıcı adı olarak ad soyad kullan (benzersizlik için sayaç ekle)
                        $username = strtolower(str_replace(' ', '_', $ad_soyad));
                        $original_username = $username;
                        $counter = 1;
                        
                        // Benzersiz kullanıcı adı bul
                        while (true) {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND status = 1");
                            $stmt->execute([$username]);
                            if ($stmt->fetchColumn() == 0) {
                                break;
                            }
                            $username = $original_username . '_' . $counter;
                            $counter++;
                        }
                        
                        // Kullanıcıyı ekle (şifre null - ilk giriş için)
                        $stmt = $db->prepare("
                            INSERT INTO users 
                            (username, ad_soyad, telefon, email, yetki_grup_id, kullanici_tipi, first_login, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, 'uye', 1, 1, NOW())
                        ");
                        $stmt->execute([$username, $ad_soyad, $telefon, $email, $grup_id]);
                        
                        $db->commit();
                        showAlert("Kullanıcı başarıyla eklendi! Kullanıcı adı: $username", 'success');
                    }
                } else {
                    // Email olmadan da kullanıcı ekle
                    $username = strtolower(str_replace(' ', '_', $ad_soyad));
                    $original_username = $username;
                    $counter = 1;
                    
                    // Benzersiz kullanıcı adı bul
                    while (true) {
                        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND status = 1");
                        $stmt->execute([$username]);
                        if ($stmt->fetchColumn() == 0) {
                            break;
                        }
                        $username = $original_username . '_' . $counter;
                        $counter++;
                    }
                    
                    // Kullanıcıyı ekle
                    $stmt = $db->prepare("
                        INSERT INTO users 
                        (username, ad_soyad, telefon, email, yetki_grup_id, kullanici_tipi, first_login, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'uye', 1, 1, NOW())
                    ");
                    $stmt->execute([$username, $ad_soyad, $telefon, $email, $grup_id]);
                    
                    $db->commit();
                    showAlert("Kullanıcı başarıyla eklendi! Kullanıcı adı: $username", 'success');
                }
            }
            
        } catch (Exception $e) {
            $db->rollback();
            showAlert('Kullanıcı eklenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    } else {
        showAlert('Grup seçimi ve ad soyad gerekli!', 'error');
    }
}

// Kullanıcı silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id) {
        try {
            // Kullanıcıyı soft delete yap
            $stmt = $db->prepare("UPDATE users SET status = 0 WHERE id = ?");
            $stmt->execute([$user_id]);
            
            showAlert('Kullanıcı başarıyla silindi', 'success');
            
        } catch (Exception $e) {
            showAlert('Kullanıcı silinirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Yeni yetki grubu ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grup'])) {
    $grup_adi = trim($_POST['grup_adi']);
    $aciklama = trim($_POST['aciklama']);
    $selected_yetkiler = $_POST['yetkiler'] ?? [];
    
    if ($grup_adi && !empty($selected_yetkiler)) {
        try {
            $db->beginTransaction();
            
            // Önce grup adının benzersiz olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) FROM yetki_gruplari WHERE grup_adi = ?");
            $stmt->execute([$grup_adi]);
            
            if ($stmt->fetchColumn() > 0) {
                showAlert('Bu grup adı zaten mevcut!', 'error');
            } else {
                // Yeni grubu ekle
                $stmt = $db->prepare("INSERT INTO yetki_gruplari (grup_adi, aciklama, status) VALUES (?, ?, 1)");
                $stmt->execute([$grup_adi, $aciklama]);
                $grup_id = $db->lastInsertId();
                
                // Seçili yetkiler için kayıt ekle
                $stmt = $db->prepare("INSERT INTO grup_yetkiler (grup_id, yetki_id) VALUES (?, ?)");
                foreach ($selected_yetkiler as $yetki_id) {
                    $stmt->execute([$grup_id, $yetki_id]);
                }
                
                $db->commit();
                showAlert('Yeni yetki grubu başarıyla eklendi', 'success');
                
                // Sayfayı yenile
                header('Location: yetkiler.php?grup_id=' . $grup_id);
                exit();
            }
            
        } catch (Exception $e) {
            $db->rollback();
            showAlert('Yetki grubu eklenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    } else {
        showAlert('Grup adı ve en az bir yetki seçmelisiniz!', 'error');
    }
}

// Yetki grubunu silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grup'])) {
    $grup_id = (int)$_POST['grup_id'];
    
    if ($grup_id) {
        try {
            $db->beginTransaction();
            
            // Önce bu grubun kullanıcıları olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE yetki_grup_id = ?");
            $stmt->execute([$grup_id]);
            $kullanici_sayisi = $stmt->fetchColumn();
            
            if ($kullanici_sayisi > 0) {
                showAlert('Bu grup silinemiyor! Gruba atanmış ' . $kullanici_sayisi . ' kullanıcı var.', 'error');
            } else {
                // Grup yetkilerini sil
                $stmt = $db->prepare("DELETE FROM grup_yetkiler WHERE grup_id = ?");
                $stmt->execute([$grup_id]);
                
                // Grubu sil (soft delete)
                $stmt = $db->prepare("UPDATE yetki_gruplari SET status = 0 WHERE id = ?");
                $stmt->execute([$grup_id]);
                
                $db->commit();
                showAlert('Yetki grubu başarıyla silindi', 'success');
                
                // Anasayfaya yönlendir
                header('Location: yetkiler.php');
                exit();
            }
            
        } catch (Exception $e) {
            $db->rollback();
            showAlert('Yetki grubu silinirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Grup yetkilerini güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grup_yetkiler'])) {
    $grup_id = (int)$_POST['grup_id'];
    $selected_yetkiler = $_POST['yetkiler'] ?? [];
    
    if ($grup_id) {
        try {
            $db->beginTransaction();
            
            // Önce bu grubun tüm yetkilerini sil
            $stmt = $db->prepare("DELETE FROM grup_yetkiler WHERE grup_id = ?");
            $stmt->execute([$grup_id]);
            
            // Seçili yetkiler için kayıt ekle
            if (!empty($selected_yetkiler)) {
                $stmt = $db->prepare("INSERT INTO grup_yetkiler (grup_id, yetki_id) VALUES (?, ?)");
                foreach ($selected_yetkiler as $yetki_id) {
                    $stmt->execute([$grup_id, $yetki_id]);
                }
            }
            
            $db->commit();
            showAlert('Grup yetkileri başarıyla güncellendi', 'success');
            
        } catch (Exception $e) {
            $db->rollback();
            showAlert('Yetki güncellenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Grup bilgilerini güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grup_info'])) {
    $grup_id = (int)$_POST['grup_id'];
    $grup_adi = trim($_POST['grup_adi']);
    $aciklama = trim($_POST['aciklama']);
    
    if ($grup_id && $grup_adi) {
        try {
            // Grup adının benzersiz olup olmadığını kontrol et (kendi dışında)
            $stmt = $db->prepare("SELECT COUNT(*) FROM yetki_gruplari WHERE grup_adi = ? AND id != ?");
            $stmt->execute([$grup_adi, $grup_id]);
            
            if ($stmt->fetchColumn() > 0) {
                showAlert('Bu grup adı zaten mevcut!', 'error');
            } else {
                $stmt = $db->prepare("UPDATE yetki_gruplari SET grup_adi = ?, aciklama = ? WHERE id = ?");
                $stmt->execute([$grup_adi, $aciklama, $grup_id]);
                
                showAlert('Grup bilgileri başarıyla güncellendi', 'success');
            }
            
        } catch (Exception $e) {
            showAlert('Grup bilgileri güncellenirken hata oluştu: ' . $e->getMessage(), 'error');
        }
    }
}

// Yetki gruplarını getir
try {
    $stmt = $db->prepare("SELECT * FROM yetki_gruplari WHERE status = 1 ORDER BY grup_adi");
    $stmt->execute();
    $gruplar = $stmt->fetchAll();
} catch (Exception $e) {
    $gruplar = [];
    showAlert('Yetki grupları yüklenirken hata oluştu', 'error');
}

// Tüm yetkileri getir
try {
    $stmt = $db->prepare("SELECT * FROM yetkiler WHERE status = 1 ORDER BY modul, yetki_adi");
    $stmt->execute();
    $all_yetkiler = $stmt->fetchAll();
} catch (Exception $e) {
    $all_yetkiler = [];
}

// Seçili grup için mevcut yetkileri getir
$selected_grup_id = isset($_GET['grup_id']) ? (int)$_GET['grup_id'] : 0;
// Hangi işlem yapılacağını belirle
$action = isset($_GET['action']) ? $_GET['action'] : '';
$grup_yetkiler = [];
$selected_grup_info = null;

if ($selected_grup_id) {
    try {
        // Grup bilgilerini getir
        $stmt = $db->prepare("SELECT * FROM yetki_gruplari WHERE id = ? AND status = 1");
        $stmt->execute([$selected_grup_id]);
        $selected_grup_info = $stmt->fetch();
        
        if ($selected_grup_info) {
            // Grup yetkilerini getir
            $stmt = $db->prepare("SELECT yetki_id FROM grup_yetkiler WHERE grup_id = ?");
            $stmt->execute([$selected_grup_id]);
            $grup_yetkiler = array_column($stmt->fetchAll(), 'yetki_id');
        }
    } catch (Exception $e) {
        $grup_yetkiler = [];
        $selected_grup_info = null;
    }
}

// Grup kullanıcılarını getir
$grup_kullanicilar = [];
if ($selected_grup_id) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM users 
            WHERE yetki_grup_id = ? AND status = 1 AND kullanici_tipi = 'uye'
            ORDER BY ad_soyad
        ");
        $stmt->execute([$selected_grup_id]);
        $grup_kullanicilar = $stmt->fetchAll();
    } catch (Exception $e) {
        $grup_kullanicilar = [];
    }
}

// Yetkileri modül bazında grupla
$yetkiler_by_module = [];
foreach ($all_yetkiler as $yetki) {
    $yetkiler_by_module[$yetki['modul']][] = $yetki;
}

// İstatistikleri hesapla
$toplam_yetki = count($all_yetkiler);
$toplam_grup = count($gruplar);

// Her grup için yetki sayılarını hesapla
$grup_yetki_sayilari = [];
foreach ($gruplar as $grup) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as toplam FROM grup_yetkiler WHERE grup_id = ?");
        $stmt->execute([$grup['id']]);
        $grup_yetki_sayilari[$grup['id']] = $stmt->fetch()['toplam'];
    } catch (Exception $e) {
        $grup_yetki_sayilari[$grup['id']] = 0;
    }
}

// Her grup için kullanıcı sayısını hesapla
$grup_kullanici_sayilari = [];
foreach ($gruplar as $grup) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as toplam FROM users WHERE yetki_grup_id = ? AND status = 1");
        $stmt->execute([$grup['id']]);
        $grup_kullanici_sayilari[$grup['id']] = $stmt->fetch()['toplam'];
    } catch (Exception $e) {
        $grup_kullanici_sayilari[$grup['id']] = 0;
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
                <h1 class="page-title">⚙️ Yetki Yönetimi</h1>
                
                <div class="topbar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Yetki ara..." id="yetkiSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="openAddGroupModal()">
                        ➕ Yeni Grup
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
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Yetki</div>
                            <div class="stat-icon">🔐</div>
                        </div>
                        <div class="stat-value"><?= $toplam_yetki ?></div>
                        <div class="stat-change">Sistem yetkisi</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <div class="stat-title">Yetki Grupları</div>
                            <div class="stat-icon">👥</div>
                        </div>
                        <div class="stat-value"><?= $toplam_grup ?></div>
                        <div class="stat-change">Aktif grup</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-header">
                            <div class="stat-title">Modüller</div>
                            <div class="stat-icon">📦</div>
                        </div>
                        <div class="stat-value"><?= count($yetkiler_by_module) ?></div>
                        <div class="stat-change">Farklı modül</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-header">
                            <div class="stat-title">Ortalama Yetki</div>
                            <div class="stat-icon">📊</div>
                        </div>
                        <div class="stat-value">
                            <?= $toplam_grup > 0 ? round(array_sum($grup_yetki_sayilari) / $toplam_grup, 1) : 0 ?>
                        </div>
                        <div class="stat-change">Grup başına</div>
                    </div>
                </div>

                <?php 
                // Hangi işlem yapılacağını belirle
                $action = isset($_GET['action']) ? $_GET['action'] : '';
                ?>

                <?php if ($selected_grup_id && $selected_grup_info && empty($action)): ?>
                <!-- İşlem Seçimi Kartı -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🎯 <?= htmlspecialchars($selected_grup_info['grup_adi']) ?> - Yapmak İstediğiniz İşlemi Seçin</h3>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons-grid">
                            <a href="?grup_id=<?= $selected_grup_id ?>&action=edit_info" class="action-button">
                                <div class="action-icon">📝</div>
                                <div class="action-title">Grup Bilgilerini Düzenle</div>
                                <div class="action-desc">Grup adı ve açıklamasını değiştir/Grubu Sil</div>
                            </a>
                            
                            <?php if (strtolower($selected_grup_info['grup_adi']) !== 'sakin'): ?>
                            <a href="?grup_id=<?= $selected_grup_id ?>&action=manage_users" class="action-button">
                                <div class="action-icon">👥</div>
                                <div class="action-title">Kullanıcıları Yönet</div>
                                <div class="action-desc">Gruba kullanıcı ekle/çıkar</div>
                            </a>
                            <?php endif; ?>
                            
                            <a href="?grup_id=<?= $selected_grup_id ?>&action=manage_permissions" class="action-button">
                                <div class="action-icon">⚙️</div>
                                <div class="action-title">Yetkileri Düzenle</div>
                                <div class="action-desc">Grup yetkilerini ayarla</div>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($selected_grup_id && $selected_grup_info && $action === 'edit_info'): ?>
                <!-- Grup Bilgi Düzenleme -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📝 Grup Bilgilerini Düzenle</h3>
                        <div class="card-actions">
                            <a href="?grup_id=<?= $selected_grup_id ?>" class="btn btn-ghost btn-sm">
                                ← Geri
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="grupInfoForm">
                            <input type="hidden" name="grup_id" value="<?= $selected_grup_id ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="grup_adi">Grup Adı</label>
                                    <input type="text" 
                                           name="grup_adi" 
                                           id="grup_adi" 
                                           value="<?= htmlspecialchars($selected_grup_info['grup_adi']) ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="aciklama">Açıklama</label>
                                    <textarea name="aciklama" 
                                              id="aciklama" 
                                              rows="2"><?= htmlspecialchars($selected_grup_info['aciklama'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                <button type="button" class="btn btn-danger" onclick="confirmDeleteGroup(<?= $selected_grup_id ?>)">
                                    🗑️ Grubu Sil
                                </button>
                                <button type="submit" name="update_grup_info" class="btn btn-success">
                                    💾 Bilgileri Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($selected_grup_id && $selected_grup_info && $action === 'manage_users' && strtolower($selected_grup_info['grup_adi']) !== 'sakin'): ?>
                <!-- Grup Kullanıcıları -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            👥 Grup Kullanıcıları: <?= htmlspecialchars($selected_grup_info['grup_adi']) ?>
                        </h3>
                        <div class="card-actions">
                            <a href="?grup_id=<?= $selected_grup_id ?>" class="btn btn-ghost btn-sm">
                                ← Geri
                            </a>
                            <button class="btn btn-success btn-sm" onclick="openAssignUserModal(<?= $selected_grup_id ?>, '<?= htmlspecialchars($selected_grup_info['grup_adi']) ?>')">
                                ➕ Kullanıcı Ekle
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($grup_kullanicilar)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">👤</div>
                                <h3>Henüz kullanıcı yok</h3>
                                <p>Bu gruba henüz hiç kullanıcı atanmamış. Yukarıdaki "Kullanıcı Ekle" butonunu kullanarak kullanıcı ekleyebilirsiniz.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>Kullanıcı Adı</th>
                                            <th>Telefon</th>
                                            <th>Email</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grup_kullanicilar as $kullanici): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                                            <?= substr($kullanici['ad_soyad'], 0, 2) ?>
                                                        </div>
                                                        <strong><?= htmlspecialchars($kullanici['ad_soyad']) ?></strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($kullanici['username']) ?></code>
                                                </td>
                                                <td>
                                                    <?= $kullanici['telefon'] ? htmlspecialchars($kullanici['telefon']) : '<span style="color: var(--text-secondary);">-</span>' ?>
                                                </td>
                                                <td>
                                                    <?= $kullanici['email'] ? htmlspecialchars($kullanici['email']) : '<span style="color: var(--text-secondary);">-</span>' ?>
                                                </td>
                                                <td>
                                                    <?php if ($kullanici['first_login']): ?>
                                                        <span class="badge badge-warning">İlk Giriş Bekliyor</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Aktif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; gap: 8px;">
                                                        <button class="btn btn-danger btn-sm" onclick="confirmDeleteUser(<?= $kullanici['id'] ?>, '<?= htmlspecialchars($kullanici['ad_soyad']) ?>')">
                                                            🗑️ Sil
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($selected_grup_id && $selected_grup_info && $action === 'manage_permissions'): ?>
                <!-- Yetki Düzenleme -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            ⚙️ Grup Yetkileri: <?= htmlspecialchars($selected_grup_info['grup_adi']) ?>
                        </h3>
                        <div class="card-actions">
                            <a href="?grup_id=<?= $selected_grup_id ?>" class="btn btn-ghost btn-sm">
                                ← Geri
                            </a>
                            <button type="button" class="btn btn-success btn-sm" onclick="selectAllPermissions()">
                                ✅ Tümünü Seç
                            </button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="deselectAllPermissions()">
                                ❌ Tümünü Kaldır
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="yetkiForm">
                            <input type="hidden" name="grup_id" value="<?= $selected_grup_id ?>">
                            
                            <div class="permissions-grid">
                                <?php foreach ($yetkiler_by_module as $modul => $yetkiler): ?>
                                    <div class="permission-module" data-module="<?= htmlspecialchars($modul) ?>">
                                        <div class="module-header">
                                            <div class="module-title" onclick="toggleModulePermissions('<?= htmlspecialchars($modul) ?>')">
                                                <span class="module-icon">
                                                    <?php
                                                    $module_icons = [
                                                        'Dashboard' => '📊',
                                                        'Sakinler' => '👥',
                                                        'Aidatlar' => '💰',
                                                        'Giderler' => '📋',
                                                        'Sistem' => '⚙️'
                                                    ];
                                                    echo $module_icons[$modul] ?? '📦';
                                                    ?>
                                                </span>
                                                <?= htmlspecialchars($modul) ?>
                                                <span class="module-count">
                                                    (<?= count($yetkiler) ?> yetki)
                                                </span>
                                            </div>
                                            <div class="module-progress">
                                                <?php
                                                $module_total = count($yetkiler);
                                                $module_selected = count(array_intersect(array_column($yetkiler, 'id'), $grup_yetkiler));
                                                $module_percentage = $module_total > 0 ? ($module_selected / $module_total) * 100 : 0;
                                                ?>
                                                <div class="progress">
                                                    <div class="progress-bar" style="width: <?= $module_percentage ?>%"></div>
                                                </div>
                                                <span class="progress-text"><?= $module_selected ?>/<?= $module_total ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="module-permissions">
                                            <?php foreach ($yetkiler as $yetki): ?>
                                                <div class="permission-item">
                                                    <label class="permission-label">
                                                        <input type="checkbox" 
                                                               name="yetkiler[]" 
                                                               value="<?= $yetki['id'] ?>"
                                                               data-module="<?= htmlspecialchars($modul) ?>"
                                                               <?= in_array($yetki['id'], $grup_yetkiler) ? 'checked' : '' ?>
                                                               onchange="updateModuleProgress('<?= htmlspecialchars($modul) ?>')">
                                                        <span class="permission-name">
                                                            <?= htmlspecialchars($yetki['yetki_adi']) ?>
                                                        </span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border-color);">
                                <button type="button" class="btn btn-ghost" onclick="resetPermissions()">
                                    🔄 Sıfırla
                                </button>
                                <button type="submit" name="update_grup_yetkiler" class="btn btn-primary">
                                    💾 Yetkileri Kaydet
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Grup Özeti -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📊 Yetki Grupları Özeti</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Grup Adı</th>
                                        <th>Açıklama</th>
                                        <th>Kullanıcı Sayısı</th>
                                        <th>Yetki Dağılımı</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gruplar as $grup): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                    <div class="user-avatar" style="width: 32px; height: 32px; font-size: 12px;">
                                                        <?= substr($grup['grup_adi'], 0, 2) ?>
                                                    </div>
                                                    <strong><?= htmlspecialchars($grup['grup_adi']) ?></strong>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($grup['aciklama'] ?? 'Açıklama yok') ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?= $grup_kullanici_sayilari[$grup['id']] ?> kullanıcı
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="margin-bottom: 4px;">
                                                    <?php 
                                                    $yetki_yuzdesi = $toplam_yetki > 0 ? ($grup_yetki_sayilari[$grup['id']] / $toplam_yetki) * 100 : 0;
                                                    ?>
                                                    <div class="progress-bar" style="width: <?= $yetki_yuzdesi ?>%"></div>
                                                </div>
                                                <small style="color: var(--text-secondary);">
                                                    %<?= round($yetki_yuzdesi, 1) ?> (<?= $grup_yetki_sayilari[$grup['id']] ?>/<?= $toplam_yetki ?>)
                                                </small>
                                            </td>
                                            <td>
                                                <a href="?grup_id=<?= $grup['id'] ?>" class="btn btn-primary btn-sm">
                                                    ⚙️ Düzenle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

    <!-- Yeni Grup Ekleme Modal -->
    <div class="modal" id="addGroupModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Yeni Yetki Grubu Ekle</h3>
                <button class="modal-close" onclick="closeAddGroupModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addGroupForm">
                    <div class="form-group">
                        <label for="new_grup_adi">Grup Adı *</label>
                        <input type="text" name="grup_adi" id="new_grup_adi" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_aciklama">Açıklama</label>
                        <textarea name="aciklama" id="new_aciklama" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Yetkiler *</label>
                        <div class="permissions-grid">
                            <?php foreach ($yetkiler_by_module as $modul => $yetkiler): ?>
                                <div class="permission-module" data-module="<?= htmlspecialchars($modul) ?>">
                                    <div class="module-header">
                                        <div class="module-title" onclick="toggleNewModulePermissions('<?= htmlspecialchars($modul) ?>')">
                                            <span class="module-icon">
                                                <?php
                                                $module_icons = [
                                                    'Dashboard' => '📊',
                                                    'Sakinler' => '👥',
                                                    'Aidatlar' => '💰',
                                                    'Giderler' => '📋',
                                                    'Sistem' => '⚙️'
                                                ];
                                                echo $module_icons[$modul] ?? '📦';
                                                ?>
                                            </span>
                                            <?= htmlspecialchars($modul) ?>
                                            <span class="module-count">
                                                (<?= count($yetkiler) ?> yetki)
                                            </span>
                                        </div>
                                        <div class="module-progress">
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <span class="progress-text">0/<?= count($yetkiler) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="module-permissions">
                                        <?php foreach ($yetkiler as $yetki): ?>
                                            <div class="permission-item">
                                                <label class="permission-label">
                                                    <input type="checkbox" 
                                                           name="yetkiler[]" 
                                                           value="<?= $yetki['id'] ?>"
                                                           data-module="<?= htmlspecialchars($modul) ?>"
                                                           onchange="updateNewModuleProgress('<?= htmlspecialchars($modul) ?>')">
                                                    <span class="permission-name">
                                                        <?= htmlspecialchars($yetki['yetki_adi']) ?>
                                                    </span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeAddGroupModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="add_grup" class="btn btn-primary">
                            ➕ Grubu Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Atama Modal -->
    <div class="modal" id="assignUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👤 Kullanıcı Ata</h3>
                <button class="modal-close" onclick="closeAssignUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="assignUserForm">
                    <input type="hidden" name="grup_id" id="assign_grup_id">
                    
                    <div class="form-group">
                        <label>Grup</label>
                        <input type="text" id="assign_grup_name" readonly style="background: var(--background-color); color: var(--text-secondary);">
                    </div>
                    
                    <div class="form-group">
                        <label for="assign_ad_soyad">Ad Soyad *</label>
                        <input type="text" name="ad_soyad" id="assign_ad_soyad" required placeholder="Kullanıcının tam adını girin">
                    </div>
                    
                    <div class="form-group">
                        <label for="assign_telefon">Telefon</label>
                        <input type="tel" name="telefon" id="assign_telefon" placeholder="0550-123-4567">
                    </div>
                    
                    <div class="form-group">
                        <label for="assign_email">Email</label>
                        <input type="email" name="email" id="assign_email" placeholder="ornek@email.com">
                    </div>
                    
                    <div class="info-box" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 8px; padding: 16px; margin: 20px 0;">
                        <h4 style="margin: 0 0 8px 0; color: var(--primary-color);">ℹ️ Bilgilendirme</h4>
                        <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); font-size: 14px;">
                            <li>Kullanıcı adı otomatik olarak ad soyaddan oluşturulacak</li>
                            <li>Kullanıcı ilk giriş yaptığında şifre oluşturmaya yönlendirilecek</li>
                            <li>Giriş yapmak için "Üye Girişi" sekmesini kullanacak</li>
                        </ul>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeAssignUserModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="assign_user" class="btn btn-success">
                            👤 Kullanıcı Ata
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Silme Onay Modal -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Kullanıcı Sil</h3>
                <button class="modal-close" onclick="closeDeleteUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Bu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                <p><strong id="deleteUserName"></strong> kullanıcısı sistemden kaldırılacak.</p>
                <p><strong>Bu işlem geri alınamaz!</strong></p>
                
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeDeleteUserModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="delete_user" class="btn btn-danger">
                            🗑️ Evet, Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Grup Silme Onay Modal -->
    <div class="modal" id="deleteConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Grubu Sil</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Bu yetki grubunu silmek istediğinizden emin misiniz?</p>
                <p><strong>Bu işlem geri alınamaz!</strong></p>
                
                <form method="POST" id="deleteGroupForm">
                    <input type="hidden" name="grup_id" id="delete_grup_id">
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="delete_grup" class="btn btn-danger">
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
        // Modal işlevleri
        function openAddGroupModal() {
            document.getElementById('addGroupModal').style.display = 'flex';
            document.getElementById('new_grup_adi').focus();
        }

        function closeAddGroupModal() {
            document.getElementById('addGroupModal').style.display = 'none';
            document.getElementById('addGroupForm').reset();
            
            // Tüm checkbox'ları temizle
            document.querySelectorAll('#addGroupModal input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Progress bar'ları sıfırla
            document.querySelectorAll('#addGroupModal .progress-bar').forEach(bar => {
                bar.style.width = '0%';
            });
            
            document.querySelectorAll('#addGroupModal .progress-text').forEach(text => {
                const total = text.textContent.split('/')[1];
                text.textContent = '0/' + total;
            });
        }

        function confirmDeleteGroup(grupId) {
            document.getElementById('delete_grup_id').value = grupId;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
        }

        // Kullanıcı atama modal işlevleri
        function openAssignUserModal(grupId, grupAdi) {
            // Sakin grubu kontrolü
            if (grupAdi.toLowerCase() === 'sakin') {
                alert('Sakin grubuna manuel kullanıcı ataması yapılamaz!\nSakinler sistem tarafından otomatik yönetilir.');
                return;
            }
            
            document.getElementById('assign_grup_id').value = grupId;
            document.getElementById('assign_grup_name').value = grupAdi;
            document.getElementById('assignUserModal').style.display = 'flex';
            document.getElementById('assign_ad_soyad').focus();
        }

        function closeAssignUserModal() {
            document.getElementById('assignUserModal').style.display = 'none';
            document.getElementById('assignUserForm').reset();
        }

        // Kullanıcı silme modal işlevleri
        function confirmDeleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            document.getElementById('deleteUserModal').style.display = 'flex';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }

        // Yeni grup modal için modül bazında yetki seçimi
        function toggleNewModulePermissions(module) {
            const moduleDiv = document.querySelector(`#addGroupModal [data-module="${module}"]`);
            const checkboxes = moduleDiv.querySelectorAll('input[type="checkbox"]');
            const checkedCount = moduleDiv.querySelectorAll('input[type="checkbox"]:checked').length;
            const shouldCheck = checkedCount < checkboxes.length;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = shouldCheck;
            });
            
            updateNewModuleProgress(module);
        }

        // Yeni grup modal için modül progress güncelleme
        function updateNewModuleProgress(module) {
            const moduleDiv = document.querySelector(`#addGroupModal [data-module="${module}"]`);
            const checkboxes = moduleDiv.querySelectorAll('input[type="checkbox"]');
            const checkedBoxes = moduleDiv.querySelectorAll('input[type="checkbox"]:checked');
            
            const total = checkboxes.length;
            const selected = checkedBoxes.length;
            const percentage = total > 0 ? (selected / total) * 100 : 0;
            
            const progressBar = moduleDiv.querySelector('.progress-bar');
            const progressText = moduleDiv.querySelector('.progress-text');
            
            if (progressBar) {
                progressBar.style.width = percentage + '%';
            }
            
            if (progressText) {
                progressText.textContent = `${selected}/${total}`;
            }
            
            // Progress bar rengini güncelle
            if (progressBar) {
                if (percentage === 100) {
                    progressBar.style.background = 'var(--success-color)';
                } else if (percentage > 50) {
                    progressBar.style.background = 'var(--warning-color)';
                } else {
                    progressBar.style.background = 'var(--primary-color)';
                }
            }
        }

        // Mevcut grup için modül bazında yetki seçimi
        function toggleModulePermissions(module) {
            const moduleDiv = document.querySelector(`#yetkiForm [data-module="${module}"]`);
            const checkboxes = moduleDiv.querySelectorAll('input[type="checkbox"]');
            const checkedCount = moduleDiv.querySelectorAll('input[type="checkbox"]:checked').length;
            const shouldCheck = checkedCount < checkboxes.length;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = shouldCheck;
            });
            
            updateModuleProgress(module);
        }

        // Mevcut grup için modül progress güncelleme
        function updateModuleProgress(module) {
            const moduleDiv = document.querySelector(`#yetkiForm [data-module="${module}"]`);
            const checkboxes = moduleDiv.querySelectorAll('input[type="checkbox"]');
            const checkedBoxes = moduleDiv.querySelectorAll('input[type="checkbox"]:checked');
            
            const total = checkboxes.length;
            const selected = checkedBoxes.length;
            const percentage = total > 0 ? (selected / total) * 100 : 0;
            
            const progressBar = moduleDiv.querySelector('.progress-bar');
            const progressText = moduleDiv.querySelector('.progress-text');
            
            if (progressBar) {
                progressBar.style.width = percentage + '%';
            }
            
            if (progressText) {
                progressText.textContent = `${selected}/${total}`;
            }
            
            // Progress bar rengini güncelle
            if (progressBar) {
                if (percentage === 100) {
                    progressBar.style.background = 'var(--success-color)';
                } else if (percentage > 50) {
                    progressBar.style.background = 'var(--warning-color)';
                } else {
                    progressBar.style.background = 'var(--primary-color)';
                }
            }
        }

        // Tüm yetkileri seç (mevcut grup)
        function selectAllPermissions() {
            document.querySelectorAll('#yetkiForm input[type="checkbox"][name="yetkiler[]"]').forEach(checkbox => {
                checkbox.checked = true;
            });
            
            // Tüm modül progresslarını güncelle
            document.querySelectorAll('#yetkiForm [data-module]').forEach(moduleDiv => {
                const module = moduleDiv.getAttribute('data-module');
                updateModuleProgress(module);
            });
        }

        // Tüm yetkileri kaldır (mevcut grup)
        function deselectAllPermissions() {
            document.querySelectorAll('#yetkiForm input[type="checkbox"][name="yetkiler[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Tüm modül progresslarını güncelle
            document.querySelectorAll('#yetkiForm [data-module]').forEach(moduleDiv => {
                const module = moduleDiv.getAttribute('data-module');
                updateModuleProgress(module);
            });
        }

        // Yetkileri sıfırla (orijinal haline getir)
        function resetPermissions() {
            window.location.reload();
        }

        // Yetki arama
        document.getElementById('yetkiSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const permissionItems = document.querySelectorAll('.permission-item');
            
            permissionItems.forEach(item => {
                const permissionName = item.querySelector('.permission-name').textContent.toLowerCase();
                
                if (permissionName.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Boş modülleri gizle
            document.querySelectorAll('.permission-module').forEach(module => {
                const visibleItems = module.querySelectorAll('.permission-item[style=""], .permission-item:not([style])');
                if (visibleItems.length === 0 && searchTerm.length > 0) {
                    module.style.display = 'none';
                } else {
                    module.style.display = '';
                }
            });
        });

        // Form doğrulama
        document.getElementById('yetkiForm')?.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('#yetkiForm input[type="checkbox"][name="yetkiler[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('En az bir yetki seçmelisiniz!');
                return false;
            }
            
            // Onay sorusu
            if (!confirm(`${checkedBoxes.length} yetki seçtiniz. Değişiklikleri kaydetmek istediğinizden emin misiniz?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Yeni grup form doğrulama
        document.getElementById('addGroupForm').addEventListener('submit', function(e) {
            const grupAdi = document.getElementById('new_grup_adi').value.trim();
            const checkedBoxes = document.querySelectorAll('#addGroupModal input[type="checkbox"][name="yetkiler[]"]:checked');
            
            if (!grupAdi) {
                e.preventDefault();
                alert('Grup adı boş olamaz!');
                document.getElementById('new_grup_adi').focus();
                return false;
            }
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('En az bir yetki seçmelisiniz!');
                return false;
            }
            
            // Onay sorusu
            if (!confirm(`"${grupAdi}" adlı grup ${checkedBoxes.length} yetki ile oluşturulacak. Onaylıyor musunuz?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', function() {
            // Tüm modül progresslarını başlangıçta güncelle
            document.querySelectorAll('#yetkiForm [data-module]').forEach(moduleDiv => {
                const module = moduleDiv.getAttribute('data-module');
                updateModuleProgress(module);
            });
            
            // Modül başlıklarına hover efekti ekle
            document.querySelectorAll('.module-title').forEach(title => {
                title.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'var(--background-color)';
                    this.style.cursor = 'pointer';
                });
                
                title.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {

            if (e.key === 'Escape') {
                closeAddGroupModal();
                closeDeleteModal();
                closeAssignUserModal();
                closeDeleteUserModal();
            }
            
            // Ctrl + N - Yeni grup modal
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddGroupModal();
            }
            
            // Ctrl + U - Kullanıcı atama modal (eğer grup seçiliyse)
            if (e.ctrlKey && e.key === 'u' && <?= $selected_grup_id ?>) {
                e.preventDefault();
                openAssignUserModal(<?= $selected_grup_id ?>, '<?= htmlspecialchars($selected_grup_info['grup_adi'] ?? '') ?>');
            }
            
            // Ctrl + A - Tümünü seç (sadece yetki formunda)
            if (e.ctrlKey && e.key === 'a' && document.getElementById('yetkiForm')) {
                e.preventDefault();
                selectAllPermissions();
            }
            
            // Ctrl + D - Tümünü kaldır (sadece yetki formunda)
            if (e.ctrlKey && e.key === 'd' && document.getElementById('yetkiForm')) {
                e.preventDefault();
                deselectAllPermissions();
            }
            
            // Ctrl + S - Kaydet
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (document.getElementById('yetkiForm')) {
                    document.getElementById('yetkiForm').submit();
                }
            }
        });

        // Modal dışına tıklama ile kapatma
        document.getElementById('addGroupModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddGroupModal();
            }
        });

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.getElementById('assignUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssignUserModal();
            }
        });

        document.getElementById('deleteUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteUserModal();
            }
        });

        // Progress animation
        function animateProgress() {
            document.querySelectorAll('.progress-bar').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'width 0.5s ease';
                    bar.style.width = width;
                }, 100);
            });
        }

        // Sayfa yüklendiğinde animasyonu başlat
        setTimeout(animateProgress, 500);
    </script>
    
    <style>
        /* Mevcut stiller korunarak modal stilleri eklendi */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card-background);
            border-radius: 12px;
            max-width: 90vw;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: slideInFromTop 0.3s ease;
        }

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 24px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        /* Form stilleri */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--card-background);
            color: var(--text-primary);
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        /* Badge stilleri */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
        }

        .badge-info {
            background: rgba(59,130,246,0.1);
            color: var(--primary-color);
        }

        .badge-warning {
            background: rgba(251,191,36,0.1);
            color: #d97706;
        }

        .badge-success {
            background: rgba(34,197,94,0.1);
            color: #16a34a;
        }

        /* Info box */
        .info-box {
            margin: 16px 0;
        }

        .info-box h4 {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-box ul {
            list-style-type: disc;
        }

        .info-box li {
            margin-bottom: 4px;
        }

        /* Yetki yönetimi için özel stiller */
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 24px;
            margin: 24px 0;
        }

        .permission-module {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .permission-module:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .module-header {
            background: var(--background-color);
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .module-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .module-title:hover {
            background: rgba(59,130,246,0.1);
        }

        .module-icon {
            font-size: 20px;
        }

        .module-count {
            margin-left: auto;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .module-progress {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .progress {
            flex: 1;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            color: var(--text-secondary);
            min-width: 40px;
            text-align: right;
        }

        .module-permissions {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .permission-item {
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }

        .permission-item:hover {
            transform: translateX(4px);
        }

        .permission-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .permission-label:hover {
            background: var(--background-color);
        }

        .permission-label input[type="checkbox"] {
            margin: 0;
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            cursor: pointer;
        }

        .permission-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .permissions-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .module-permissions {
                max-height: 300px;
            }
            
            .permission-label {
                padding: 8px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                max-width: 95vw;
                margin: 20px;
            }
        }

        /* Animasyonlar */
        @keyframes slideInFromLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .permission-module {
            animation: slideInFromLeft 0.3s ease forwards;
        }

        .permission-module:nth-child(1) { animation-delay: 0.1s; }
        .permission-module:nth-child(2) { animation-delay: 0.2s; }
        .permission-module:nth-child(3) { animation-delay: 0.3s; }
        .permission-module:nth-child(4) { animation-delay: 0.4s; }
        .permission-module:nth-child(5) { animation-delay: 0.5s; }

        /* Checkbox özelleştirme */
        input[type="checkbox"] {
            appearance: none;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background: var(--card-background);
            position: relative;
            transition: all 0.3s ease;
        }

        input[type="checkbox"]:checked {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        input[type="checkbox"]:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        /* Yetki seviyesi göstergeleri */
        .permission-level-high {
            border-left: 4px solid var(--danger-color);
        }

        .permission-level-medium {
            border-left: 4px solid var(--warning-color);
        }

        .permission-level-low {
            border-left: 4px solid var(--success-color);
        }
        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .action-button {
            display: block;
            padding: 24px;
            background: var(--card-background);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }

        .action-button:hover {
            border-color: var(--primary-color);
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .action-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .action-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .action-desc {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
</body>
</html>