<?php
// public/pages/kullanicilar.php - Kullanıcı yönetimi sayfası
$page_title = 'Kullanıcı Yönetimi';
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Sadece yönetici erişebilir
if ($current_user['role'] !== 'yonetici') {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Yeni kullanıcı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $ad_soyad = trim($_POST['ad_soyad']);
    $email = trim($_POST['email']);
    $telefon = trim($_POST['telefon']);
    $password = $_POST['password'];
    $yetki_grup_id = (int)$_POST['yetki_grup_id'];
    
    $result = createUser($username, $ad_soyad, $email, $telefon, $password, $yetki_grup_id);
    
    if ($result['success']) {
        showAlert($result['message'], 'success');
    } else {
        showAlert($result['message'], 'error');
    }
}

// Kullanıcı güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $username = trim($_POST['username']);
    $ad_soyad = trim($_POST['ad_soyad']);
    $email = trim($_POST['email']);
    $telefon = trim($_POST['telefon']);
    $yetki_grup_id = (int)$_POST['yetki_grup_id'];
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    
    $result = updateUser($user_id, $username, $ad_soyad, $email, $telefon, $yetki_grup_id, $password);
    
    if ($result['success']) {
        showAlert($result['message'], 'success');
    } else {
        showAlert($result['message'], 'error');
    }
}

// Kullanıcı silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $result = deleteUser($user_id);
    
    if ($result['success']) {
        showAlert($result['message'], 'success');
    } else {
        showAlert($result['message'], 'error');
    }
}

// Kullanıcıları getir
$users = getUsers();

// Yetki gruplarını getir
$permission_groups = getActivePermissionGroups();

// Seçili kullanıcı bilgilerini getir (düzenleme için)
$selected_user = null;
if (isset($_GET['edit_user'])) {
    $edit_user_id = (int)$_GET['edit_user'];
    foreach ($users as $user) {
        if ($user['id'] == $edit_user_id) {
            $selected_user = $user;
            break;
        }
    }
}

// İstatistikler
$total_users = count($users);
$groups_stats = [];
foreach ($permission_groups as $group) {
    $count = 0;
    foreach ($users as $user) {
        if ($user['yetki_grup_id'] == $group['id']) {
            $count++;
        }
    }
    $groups_stats[$group['id']] = $count;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Apartman Yönetimi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <button class="sidebar-toggle" onclick="toggleSidebar()">
                <span id="toggle-icon">‹</span>
            </button>
            
            <div class="sidebar-header">
                <div class="logo">🏢</div>
                <div class="brand-text">Armiya Tech.</div>
            </div>
            
            <nav class="nav-menu">
                <div class="nav-item">
                    <a href="../index.php" class="nav-link">
                        <div class="nav-icon">📊</div>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="sakinler.php" class="nav-link">
                        <div class="nav-icon">👥</div>
                        <span class="nav-text">Sakinler</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="aidatlar.php" class="nav-link">
                        <div class="nav-icon">💰</div>
                        <span class="nav-text">Aidatlar</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="giderler.php" class="nav-link">
                        <div class="nav-icon">📋</div>
                        <span class="nav-text">Giderler</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="yetkiler.php" class="nav-link">
                        <div class="nav-icon">⚙️</div>
                        <span class="nav-text">Yetki Yönetimi</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="kullanicilar.php" class="nav-link active">
                        <div class="nav-icon">👤</div>
                        <span class="nav-text">Kullanıcı Yönetimi</span>
                    </a>
                </div>
            </nav>
        </div>

        <div class="main-content">
     
            <div class="topbar">
                <h1 class="page-title">👤 Kullanıcı Yönetimi</h1>
                
                <div class="topbar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Kullanıcı ara..." id="userSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
                    <button class="btn btn-primary" onclick="openAddUserModal()">
                        ➕ Yeni Kullanıcı
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
                            <span>▼</span>
                        </div>
                        <div class="dropdown-menu" id="userMenu">
                            <a href="#" class="dropdown-item">👤 Profil</a>
                            <a href="#" class="dropdown-item">⚙️ Ayarlar</a>
                            <a href="../logout.php" class="dropdown-item" style="color: #ef4444;">🚪 Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>

          
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

           
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Kullanıcı</div>
                            <div class="stat-icon">👥</div>
                        </div>
                        <div class="stat-value"><?= $total_users ?></div>
                        <div class="stat-change">Aktif kullanıcı</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <div class="stat-title">Yetki Grupları</div>
                            <div class="stat-icon">🔐</div>
                        </div>
                        <div class="stat-value"><?= count($permission_groups) ?></div>
                        <div class="stat-change">Farklı grup</div>
                    </div>
                    
                    <div class="stat-card info">
                        <div class="stat-header">
                            <div class="stat-title">En Çok Kullanılan</div>
                            <div class="stat-icon">📊</div>
                        </div>
                        <div class="stat-value">
                            <?php 
                            if (!empty($groups_stats)) {
                                $max_group_id = array_keys($groups_stats, max($groups_stats))[0] ?? 0;
                                $max_group_name = 'Yok';
                                foreach ($permission_groups as $group) {
                                    if ($group['id'] == $max_group_id) {
                                        $max_group_name = $group['grup_adi'];
                                        break;
                                    }
                                }
                                echo htmlspecialchars($max_group_name);
                            } else {
                                echo 'Yok';
                            }
                            ?>
                        </div>
                        <div class="stat-change"><?= !empty($groups_stats) ? max($groups_stats) : 0 ?> kullanıcı</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-header">
                            <div class="stat-title">Son 30 Gün</div>
                            <div class="stat-icon">📅</div>
                        </div>
                        <div class="stat-value">
                            <?php
                            $recent_users = 0;
                            foreach ($users as $user) {
                                if (isset($user['created_at']) && strtotime($user['created_at']) > strtotime('-30 days')) {
                                    $recent_users++;
                                }
                            }
                            echo $recent_users;
                            ?>
                        </div>
                        <div class="stat-change">Yeni eklenen</div>
                    </div>
                </div>

          
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">👥 Kullanıcı Listesi</h3>
                        <div class="card-actions">
                            <select id="groupFilter" class="form-select">
                                <option value="">Tüm Gruplar</option>
                                <?php foreach ($permission_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['grup_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Kullanıcı</th>
                                        <th>İletişim</th>
                                        <th>Yetki Grubu</th>
                                        <th>Kayıt Tarihi</th>
                                        <th>Son Giriş</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr data-group-id="<?= $user['yetki_grup_id'] ?>">
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div class="user-avatar">
                                                        <?= substr($user['ad_soyad'] ?? $user['username'], 0, 2) ?>
                                                    </div>
                                                    <div>
                                                        <div class="user-name"><?= htmlspecialchars($user['ad_soyad'] ?? $user['username']) ?></div>
                                                        <div class="user-username">@<?= htmlspecialchars($user['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php if (isset($user['email']) && $user['email']): ?>
                                                        <div>📧 <?= htmlspecialchars($user['email']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (isset($user['telefon']) && $user['telefon']): ?>
                                                        <div>📱 <?= htmlspecialchars($user['telefon']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $user['yetki_grup_id'] == 1 ? 'primary' : ($user['yetki_grup_id'] == 2 ? 'success' : 'info') ?>">
                                                    <?= htmlspecialchars($user['grup_adi'] ?? 'Belirtilmemiş') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= isset($user['created_at']) ? date('d.m.Y H:i', strtotime($user['created_at'])) : 'Bilinmiyor' ?>
                                            </td>
                                            <td>
                                                <?= isset($user['last_login']) && $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Hiç giriş yapmadı' ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px;">
                                                    <button class="btn btn-sm btn-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                        ✏️ Düzenle
                                                    </button>
                                                    <?php if ($user['id'] != $current_user['id']): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                            🗑️ Sil
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">📊 Yetki Grubu Dağılımı</h3>
                    </div>
                    <div class="card-body">
                        <div class="permissions-stats">
                            <?php foreach ($permission_groups as $group): ?>
                                <div class="permission-stat">
                                    <div class="stat-info">
                                        <div class="stat-name"><?= htmlspecialchars($group['grup_adi']) ?></div>
                                        <div class="stat-count"><?= $groups_stats[$group['id']] ?> kullanıcı</div>
                                    </div>
                                    <div class="stat-progress">
                                        <?php $percentage = $total_users > 0 ? ($groups_stats[$group['id']] / $total_users) * 100 : 0; ?>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                                        </div>
                                        <span class="percentage"><?= round($percentage, 1) ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Yeni Kullanıcı Ekle</h3>
                <button class="modal-close" onclick="closeAddUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addUserForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_username">Kullanıcı Adı *</label>
                            <input type="text" name="username" id="new_username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_ad_soyad">Ad Soyad *</label>
                            <input type="text" name="ad_soyad" id="new_ad_soyad" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_email">Email</label>
                            <input type="email" name="email" id="new_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_telefon">Telefon</label>
                            <input type="tel" name="telefon" id="new_telefon">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Şifre *</label>
                            <input type="password" name="password" id="new_password" required minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_yetki_grup_id">Yetki Grubu *</label>
                            <select name="yetki_grup_id" id="new_yetki_grup_id" required>
                                <option value="">Yetki Grubu Seçin</option>
                                <?php foreach ($permission_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['grup_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeAddUserModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            ➕ Kullanıcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Kullanıcı Düzenleme Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Kullanıcı Düzenle</h3>
                <button class="modal-close" onclick="closeEditUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_username">Kullanıcı Adı *</label>
                            <input type="text" name="username" id="edit_username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_ad_soyad">Ad Soyad *</label>
                            <input type="text" name="ad_soyad" id="edit_ad_soyad" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" name="email" id="edit_email">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_telefon">Telefon</label>
                            <input type="tel" name="telefon" id="edit_telefon">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_password">Yeni Şifre (Boş bırakın değişmez)</label>
                            <input type="password" name="password" id="edit_password" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_yetki_grup_id">Yetki Grubu *</label>
                            <select name="yetki_grup_id" id="edit_yetki_grup_id" required>
                                <option value="">Yetki Grubu Seçin</option>
                                <?php foreach ($permission_groups as $group): ?>
                                    <option value="<?= $group['id'] ?>"><?= htmlspecialchars($group['grup_adi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-ghost" onclick="closeEditUserModal()">
                            ❌ İptal
                        </button>
                        <button type="submit" name="update_user" class="btn btn-primary">
                            💾 Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Kullanıcı Sil</h3>
                <button class="modal-close" onclick="closeDeleteUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Bu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                <p><strong id="deleteUserName"></strong></p>
                <p><em>Bu işlem geri alınamaz!</em></p>
                
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

    <script src="../assets/js/app.js"></script>
    <script>
        // Kullanıcı verilerini JS'ye aktar
        const users = <?= json_encode($users) ?>;

        // Modal işlevleri
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            document.getElementById('new_username').focus();
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }

        function editUser(userId) {
            const user = users.find(u => u.id == userId);
            if (!user) return;

            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_ad_soyad').value = user.ad_soyad || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_telefon').value = user.telefon || '';
            document.getElementById('edit_yetki_grup_id').value = user.yetki_grup_id || '';
            document.getElementById('edit_password').value = '';

            document.getElementById('editUserModal').style.display = 'flex';
        }

        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
            document.getElementById('editUserForm').reset();
        }

        function confirmDeleteUser(userId, username) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('deleteUserModal').style.display = 'flex';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').style.display = 'none';
        }

        // Kullanıcı arama
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const username = row.querySelector('.user-name').textContent.toLowerCase();
                const email = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const group = row.querySelector('.badge').textContent.toLowerCase();
                
                if (username.includes(searchTerm) || email.includes(searchTerm) || group.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Grup filtresi
        document.getElementById('groupFilter').addEventListener('change', function(e) {
            const selectedGroup = e.target.value;
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                if (!selectedGroup || row.getAttribute('data-group-id') === selectedGroup) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Modal kapatma - dışarı tıklama
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddUserModal();
            }
        });

        // Form validasyonu
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            const username = document.getElementById('new_username').value.trim();
            const ad_soyad = document.getElementById('new_ad_soyad').value.trim();
            const password = document.getElementById('new_password').value;
            const yetki_grup_id = document.getElementById('new_yetki_grup_id').value;
            
            if (!username || !ad_soyad || !password || !yetki_grup_id) {
                e.preventDefault();
                alert('Lütfen tüm zorunlu alanları doldurun!');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Şifre en az 6 karakter olmalıdır!');
                return;
            }
        });

        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            const username = document.getElementById('edit_username').value.trim();
            const ad_soyad = document.getElementById('edit_ad_soyad').value.trim();
            const password = document.getElementById('edit_password').value;
            const yetki_grup_id = document.getElementById('edit_yetki_grup_id').value;
            
            if (!username || !ad_soyad || !yetki_grup_id) {
                e.preventDefault();
                alert('Lütfen tüm zorunlu alanları doldurun!');
                return;
            }
            
            if (password && password.length < 6) {
                e.preventDefault();
                alert('Yeni şifre en az 6 karakter olmalıdır!');
                return;
            }
        });

        // Sayfa yüklendiğinde animasyon
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('#usersTable tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
    
    <style>
        /* Modal stilleri */
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

        /* Kullanıcı tablosu özel stilleri */
        .user-username {
            color: var(--text-secondary);
            font-size: 12px;
        }

        .permissions-stats {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .permission-stat {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
        }

        .stat-info {
            flex: 1;
        }

        .stat-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-count {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .stat-progress {
            flex: 2;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .percentage {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            min-width: 50px;
        }

        /* Form stilleri */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--card-background);
            color: var(--text-primary);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }

        .form-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--card-background);
            color: var(--text-primary);
        }

        /* Badge stilleri */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background: rgba(59,130,246,0.1);
            color: var(--primary-color);
        }

        .badge-success {
            background: rgba(34,197,94,0.1);
            color: var(--success-color);
        }

        .badge-info {
            background: rgba(14,165,233,0.1);
            color: var(--info-color);
        }

        /* Progress bar stilleri */
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
            transition: width 0.3s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .modal-content {
                max-width: 95vw;
                margin: 20px;
            }
        }
    </style>
</body>
</html>