<?php
// src/auth.php - Kimlik doğrulama fonksiyonları

// Oturum kontrolü
function isLoggedIn() {
    return isset($_SESSION['current_user']) && !empty($_SESSION['current_user']);
}

// Giriş yapmış kullanıcı bilgilerini al
function getCurrentUser() {
    return isLoggedIn() ? $_SESSION['current_user'] : null;
}

// Giriş kontrolü - giriş yapmamışsa login sayfasına yönlendir
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Sadece yönetici erişimi
function requireAdmin() {
    requireLogin();
    $user = getCurrentUser();
    if ($user['role'] !== 'yonetici') {
        header('Location: index.php?error=yetkisiz');
        exit();
    }
}

// Yeni fonksiyon: Yetki grubundan role belirle
function getUserRoleFromGroup($yetki_grup_id) {
    global $db;
    
    if (!$yetki_grup_id) {
        return 'uye'; // Default role
    }
    
    try {
        $stmt = $db->prepare("SELECT grup_adi FROM yetki_gruplari WHERE id = ?");
        $stmt->execute([$yetki_grup_id]);
        $grup = $stmt->fetch();
        
        if ($grup) {
            $grup_adi = strtolower($grup['grup_adi']);
            
            // Grup adına göre role mapping
            $role_mapping = [
                'yönetici' => 'yonetici',
                'yardımcı yönetici' => 'yardimci_yonetici', 
                'muhasebeci' => 'muhasebeci',
                'kapıcı' => 'kapici',
                'sakin' => 'sakin'
            ];
            
            foreach ($role_mapping as $grup_name => $role) {
                if (strpos($grup_adi, $grup_name) !== false) {
                    return $role;
                }
            }
        }
        
        // Default olarak yardımcı yönetici yap (güvenli)
        return 'yardimci_yonetici';
        
    } catch (Exception $e) {
        return 'yardimci_yonetici'; // Hata durumunda güvenli default
    }
}

// Güncellenmiş kullanıcı giriş işlemi - İKİ FARKLI GİRİŞ TİPİ
function loginUser($username, $password, $login_type = 'sakin') {
    global $db;
    
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Kullanıcı adı ve şifre gerekli'];
    }
    
    try {
        if ($login_type === 'sakin') {
            // Apartman sakini girişi - daire numarası veya ad soyad ile
            // Önce daire numarası ile dene
            $query = "SELECT * FROM sakinler WHERE daire_no = ? AND status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $sakin = $stmt->fetch();
            
            // Daire numarası ile bulunamazsa ad soyad ile dene
            if (!$sakin) {
                $query = "SELECT * FROM sakinler WHERE ad_soyad = ? AND status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                $sakin = $stmt->fetch();
            }
            
            if ($sakin) {
                if ($sakin['password'] && password_verify($password, $sakin['password'])) {
                    $found_user = [
                        'id' => $sakin['id'],
                        'username' => $sakin['daire_no'] ?: $sakin['ad_soyad'],
                        'role' => 'sakin',
                        'ad_soyad' => $sakin['ad_soyad'],
                        'sakin_id' => $sakin['id'],
                        'daire_no' => $sakin['daire_no'],
                        'kullanici_tipi' => 'sakin',
                        'status' => $sakin['status']
                    ];
                    $_SESSION['current_user'] = $found_user;
                    return ['success' => true, 'user' => $found_user];
                } elseif (!$sakin['password']) {
                    return ['success' => false, 'needs_password' => true, 'username' => $sakin['daire_no'] ?: $sakin['ad_soyad']];
                }
            }
        } else {
            // Üye girişi - users tablosundan ad soyad ile
            $query = "SELECT u.*, yg.grup_adi FROM users u 
                     LEFT JOIN yetki_gruplari yg ON u.yetki_grup_id = yg.id 
                     WHERE u.ad_soyad = ? AND u.status = 1 AND u.kullanici_tipi = 'uye'";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // İlk giriş kontrolü
                if (empty($user['password']) || $user['first_login']) {
                    return [
                        'success' => false, 
                        'needs_password' => true, 
                        'username' => $user['username']
                    ];
                }
                
                if (password_verify($password, $user['password'])) {
                    unset($user['password']);
                    
                    // ÖNEMLİ: Yetki grubuna göre role belirle
                    $user['role'] = getUserRoleFromGroup($user['yetki_grup_id']);
                    $user['kullanici_tipi'] = 'uye';
                    
                    $_SESSION['current_user'] = $user;
                    
                    // Son giriş zamanını güncelle
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    return ['success' => true, 'user' => $user];
                }
            }
            
            // Üye girişinde bulunamazsa, eski sistem users tablosunu da kontrol et (username ile)
            $query = "SELECT u.*, yg.grup_adi FROM users u 
                     LEFT JOIN yetki_gruplari yg ON u.yetki_grup_id = yg.id 
                     WHERE u.username = ? AND u.status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']);
                
                // ÖNEMLİ: Yetki grubuna göre role belirle
                $user['role'] = getUserRoleFromGroup($user['yetki_grup_id']);
                $user['kullanici_tipi'] = 'uye';
                
                $_SESSION['current_user'] = $user;
                return ['success' => true, 'user' => $user];
            }
        }
        
        return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Giriş işlemi sırasında hata oluştu: ' . $e->getMessage()];
    }
}

// Güncellenmiş şifre oluşturma işlemi
function createPassword($username, $password, $confirm_password) {
    global $db;
    
    if (empty($username) || empty($password) || empty($confirm_password)) {
        return ['success' => false, 'message' => 'Tüm alanlar gerekli'];
    }
    
    if ($password !== $confirm_password) {
        return ['success' => false, 'message' => 'Şifreler eşleşmiyor'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Şifre en az 6 karakter olmalı'];
    }
    
    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Önce users tablosunu kontrol et 
        $query = "UPDATE users SET password = ?, first_login = 0, updated_at = NOW() 
                  WHERE username = ? AND status = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$hashed_password, $username]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Şifre başarıyla oluşturuldu'];
        }
        
        // Bulunamazsa sakinler tablosunu kontrol et
        $query = "UPDATE sakinler SET password = ?, updated_at = NOW() 
                  WHERE (ad_soyad = ? OR daire_no = ?) AND password IS NULL AND status = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$hashed_password, $username, $username]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Şifre başarıyla oluşturuldu'];
        } else {
            return ['success' => false, 'message' => 'Kullanıcı bulunamadı veya zaten şifre oluşturulmuş'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Şifre oluşturulurken hata oluştu: ' . $e->getMessage()];
    }
}

// Çıkış işlemi
function logoutUser() {
    unset($_SESSION['current_user']);
    session_destroy();
}

// Kullanıcı rolü metinini al
function getRoleText($role) {
    $roles = [
        'sakin' => 'Apartman Sakini',
        'muhasebeci' => 'Muhasebeci',
        'yonetici' => 'Yönetici',
        'kapici' => 'Kapıcı',
        'yardimci_yonetici' => 'Yardımcı Yönetici'
    ];
    return $roles[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

// Kullanıcının belirli bir yetkisi olup olmadığını kontrol et
function hasPermission($permission) {
    global $db;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_user = getCurrentUser();
    
    // Yöneticiler her şeye erişebilir
    if ($current_user['role'] === 'yonetici') {
        return true;
    }
    
    // Grup ID'si yoksa erişim yok
    $grup_id = $current_user['yetki_grup_id'] ?? $current_user['grup_id'] ?? null;
    if (!$grup_id) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM grup_yetkiler gy
            INNER JOIN yetkiler y ON gy.yetki_id = y.id
            WHERE gy.grup_id = ? AND y.yetki_kodu = ? AND y.status = 1
        ");
        $stmt->execute([$grup_id, $permission]);
        
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Kullanıcının yetki gruplarını getir
function getUserPermissions() {
    global $db;
    
    if (!isLoggedIn()) {
        return [];
    }
    
    $current_user = getCurrentUser();
    
    // Yöneticiler tüm yetkilere sahip
    if ($current_user['role'] === 'yonetici') {
        try {
            $stmt = $db->prepare("SELECT yetki_kodu FROM yetkiler WHERE status = 1");
            $stmt->execute();
            return array_column($stmt->fetchAll(), 'yetki_kodu');
        } catch (Exception $e) {
            return [];
        }
    }
    
    // Grup ID'si yoksa boş array
    $grup_id = $current_user['yetki_grup_id'] ?? $current_user['grup_id'] ?? null;
    if (!$grup_id) {
        return [];
    }
    
    try {
        $stmt = $db->prepare("
            SELECT y.yetki_kodu 
            FROM grup_yetkiler gy
            INNER JOIN yetkiler y ON gy.yetki_id = y.id
            WHERE gy.grup_id = ? AND y.status = 1
        ");
        $stmt->execute([$grup_id]);
        
        return array_column($stmt->fetchAll(), 'yetki_kodu');
    } catch (Exception $e) {
        return [];
    }
}

// Kullanıcı aktivite logunu kaydet
function logUserActivity($action, $details = '') {
    global $db;
    
    if (!isLoggedIn()) {
        return false;
    }
    
    try {
        $current_user = getCurrentUser();
        $user_id = $current_user['id'];
        
        // Eğer user_activity_log tablosu varsa kaydet
        $stmt = $db->prepare("
            INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$user_id, $action, $details, $ip_address, $user_agent]);
        
        return true;
    } catch (Exception $e) {
        // Tablo yoksa hata verme, sadece false dön
        return false;
    }
}

// Session güvenlik kontrolü
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Session timeout kontrolü (24 saat)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
        logoutUser();
        return false;
    }
    
    // Son aktivite zamanını güncelle
    $_SESSION['last_activity'] = time();
    
    return true;
}
?>