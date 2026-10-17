<?php
// src/permissions.php - Yetki kontrol fonksiyonları

// Yetki kontrolü fonksiyonu
function checkPermission($user, $requiredPermission) {
    global $db;
    
    // Admin her şeyi yapabilir
    if ($user['role'] === 'yonetici') {
        return true;
    }
    
    // Sakin kullanıcıları için özel kontrol
    if ($user['role'] === 'sakin') {
        if (!isset($user['sakin_id'])) {
            return false;
        }
        
        // Sakinler tablosundan grup_id al
        $query = "SELECT grup_id FROM sakinler WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user['sakin_id']]);
        $sakinInfo = $stmt->fetch();
        
        if (!$sakinInfo || !$sakinInfo['grup_id']) {
            return false;
        }
        
        // Bu grupta bu yetki var mı kontrol et
        $query = "SELECT COUNT(*) as count FROM grup_yetkiler gy 
                  JOIN yetkiler y ON gy.yetki_id = y.id 
                  WHERE gy.grup_id = ? AND y.yetki_kodu = ? AND y.status = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$sakinInfo['grup_id'], $requiredPermission]);
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    }
    
    // Normal kullanıcılar için (YENİ SİSTEM - yetki_grup_id kullan)
    if (!$user || !isset($user['id'])) {
        return false;
    }
    
    // Kullanıcının yetki grup ID'sini al
    $grup_id = $user['yetki_grup_id'] ?? $user['grup_id'] ?? null;
    
    if (!$grup_id) {
        // Eğer session'da grup_id yoksa veritabanından al
        $query = "SELECT yetki_grup_id, grup_id FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user['id']]);
        $userInfo = $stmt->fetch();
        
        $grup_id = $userInfo['yetki_grup_id'] ?? $userInfo['grup_id'] ?? null;
        
        if (!$grup_id) {
            return false;
        }
    }
    
    // Bu grupta bu yetki var mı kontrol et
    $query = "SELECT COUNT(*) as count FROM grup_yetkiler gy 
              JOIN yetkiler y ON gy.yetki_id = y.id 
              WHERE gy.grup_id = ? AND y.yetki_kodu = ? AND y.status = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$grup_id, $requiredPermission]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

// Yetki kontrolü - yetkisi yoksa hata döndür
function requirePermission($permission) {
    $user = getCurrentUser();
    if (!checkPermission($user, $permission)) {
        header('Location: index.php?error=yetkisiz');
        exit();
    }
}

// Sayfa için yetki kontrolü (GÜNCELLENMİŞ)
function hasPagePermission($page) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Yöneticiler her sayfaya erişebilir
    if ($user['role'] === 'yonetici') {
        return true;
    }
    
    // Apartman sakini ise sadece kendi sayfalarına erişebilir
    if ($user['role'] === 'sakin' || $user['kullanici_tipi'] === 'sakin') {
        $allowed_pages = ['dashboard', 'carihareketler', 'profil'];
        return in_array($page, $allowed_pages);
    }
    
    // Sayfa yetki haritası
    $pagePermissions = [
        'dashboard' => 'dashboard.view',
        'sakinler' => 'sakinler.view',
        'aidatlar' => 'aidatlar.view_own',
        'giderler' => 'giderler.view',
        'yetkiler' => 'yetki.management'
    ];
    
    if (!isset($pagePermissions[$page])) {
        return false;
    }
    
    return checkPermission($user, $pagePermissions[$page]);
}

// Aksiyon için yetki kontrolü
function hasActionPermission($action) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Aksiyon yetki haritası
    $actionPermissions = [
        'add_sakin' => 'sakinler.add',
        'delete_sakin' => 'sakinler.delete',
        'add_aidat' => 'aidatlar.add',
        'delete_aidat' => 'aidatlar.delete',
        'pay_aidat' => 'aidatlar.payment',
        'add_gider' => 'giderler.add',
        'delete_gider' => 'giderler.delete',
        'manage_permissions' => 'yetki.management'
    ];
    
    if (!isset($actionPermissions[$action])) {
        return false;
    }
    
    return checkPermission($user, $actionPermissions[$action]);
}

// Navigasyon menüsü için yetki kontrolü
function getNavigationItems() {
    $user = getCurrentUser();
    if (!$user) return [];
    
    $items = [
        'dashboard' => [
            'title' => '📊 Dashboard',
            'url' => 'index.php',
            'permission' => 'dashboard.view'
        ]
    ];
    
    // Sakinler modülü
    if (hasPagePermission('sakinler')) {
        $items['sakinler'] = [
            'title' => '👥 Sakinler',
            'url' => 'pages/sakinler.php',
            'permission' => 'sakinler.view'
        ];
    }
    
    // Aidatlar modülü
    if (hasPagePermission('aidatlar')) {
        $items['aidatlar'] = [
            'title' => '💰 Aidatlar',
            'url' => 'pages/aidatlar.php',
            'permission' => 'aidatlar.view'
        ];
    }

    
    // Cari Hareketler modülü
    if (hasPagePermission('aidatlar') || $user['role'] === 'sakin') {
        $items['carihareketler'] = [
            'title' => '📋 Cari Hareketler',
            'url' => 'pages/carihareketler.php',
            'permission' => 'aidatlar.view'
        ];
    }
    
    // Giderler modülü
    if (hasPagePermission('giderler')) {
        $items['giderler'] = [
            'title' => '📋 Giderler',
            'url' => 'pages/giderler.php',
            'permission' => 'giderler.view'
        ];
    }
    
    // Yetki yönetimi (sadece yönetici)
    if ($user['role'] === 'yonetici') {
        $items['yetkiler'] = [
            'title' => '⚙️ Yetki Yönetimi',
            'url' => 'pages/yetkiler.php',
            'permission' => 'yetki.management'
        ];
    }
    
    return $items;
}
