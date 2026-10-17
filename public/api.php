<?php
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Veritabanı bağlantısı (paylaşılan sınıf)
require_once __DIR__ . '/../src/Database.php';

$database = new Database();
$db = $database->getConnection();

// Yetki kontrolü fonksiyonu
function checkPermission($db, $user, $requiredPermission) {
    // Debug için log ekle
    error_log("checkPermission called - User: " . json_encode($user) . " Permission: " . $requiredPermission);
    
    // Admin her şeyi yapabilir
    if ($user['role'] === 'yonetici') {
        error_log("Admin access granted");
        return true;
    }
    
    // Sakin kullanıcıları için özel kontrol
    if ($user['role'] === 'sakin') {
        error_log("Sakin user detected");
        
        // Sakinlerin grup_id'sini kontrol et
        if (!isset($user['sakin_id'])) {
            error_log("No sakin_id found in user");
            return false;
        }
        
        // Sakinler tablosundan grup_id al
        $query = "SELECT grup_id FROM sakinler WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user['sakin_id']]);
        $sakinInfo = $stmt->fetch();
        
        error_log("Sakin info: " . json_encode($sakinInfo));
        
        if (!$sakinInfo || !$sakinInfo['grup_id']) {
            error_log("No grup_id found for sakin");
            return false;
        }
        
        // Bu grupta bu yetki var mı kontrol et
        $query = "SELECT COUNT(*) as count FROM grup_yetkiler gy 
                  JOIN yetkiler y ON gy.yetki_id = y.id 
                  WHERE gy.grup_id = ? AND y.yetki_kodu = ? AND y.status = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([$sakinInfo['grup_id'], $requiredPermission]);
        $result = $stmt->fetch();
        
        error_log("Permission check result: " . json_encode($result));
        
        return $result['count'] > 0;
    }
    
    // Normal kullanıcılar için
    if (!$user || !isset($user['id'])) {
        error_log("No user ID found");
        return false;
    }
    
    // Kullanıcının grup ID'sini al
    $query = "SELECT grup_id FROM users WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user['id']]);
    $userInfo = $stmt->fetch();
    
    if (!$userInfo || !$userInfo['grup_id']) {
        error_log("No grup_id found for user");
        return false;
    }
    
    // Bu grupta bu yetki var mı kontrol et
    $query = "SELECT COUNT(*) as count FROM grup_yetkiler gy 
              JOIN yetkiler y ON gy.yetki_id = y.id 
              WHERE gy.grup_id = ? AND y.yetki_kodu = ? AND y.status = 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$userInfo['grup_id'], $requiredPermission]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
$data = json_decode(file_get_contents('php://input'), true);

try {
    switch ($endpoint) {
        case 'login':
            if ($method === 'POST') {
                $username = isset($data['username']) ? trim($data['username']) : '';
                $password = isset($data['password']) ? $data['password'] : '';
                
                if (empty($username) || empty($password)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Kullanici adi ve sifre gerekli'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Önce admin/muhasebe kullanıcılarını kontrol et
                $query = "SELECT u.*, yg.grup_adi FROM users u 
                         LEFT JOIN yetki_gruplari yg ON u.grup_id = yg.id 
                         WHERE u.username = ? AND u.status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    unset($user['password']);
                    $_SESSION['current_user'] = $user;
                    echo json_encode(['success' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Sakin kullanıcılarını kontrol et
                $query = "SELECT * FROM sakinler WHERE ad_soyad = ? AND status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$username]);
                $sakin = $stmt->fetch();
                
                if ($sakin) {
                    if ($sakin['password'] && password_verify($password, $sakin['password'])) {
                        $found_user = [
                            'id' => $sakin['id'],
                            'username' => $sakin['ad_soyad'],
                            'role' => 'sakin',
                            'ad_soyad' => $sakin['ad_soyad'],
                            'sakin_id' => $sakin['id'],
                            'daire_no' => $sakin['daire_no'],
                            'status' => $sakin['status']
                        ];
                        $_SESSION['current_user'] = $found_user;
                        echo json_encode(['success' => true, 'user' => $found_user], JSON_UNESCAPED_UNICODE);
                    } elseif (!$sakin['password']) {
                        echo json_encode(['needs_password' => true, 'username' => $username], JSON_UNESCAPED_UNICODE);
                    } else {
                        http_response_code(401);
                        echo json_encode(['error' => 'Kullanici adi veya sifre yanlis'], JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    http_response_code(401);
                    echo json_encode(['error' => 'Kullanici adi veya sifre yanlis'], JSON_UNESCAPED_UNICODE);
                }
            }
            break;
            
        case 'yetki-gruplari':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT * FROM yetki_gruplari WHERE status = 1 ORDER BY grup_adi";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $gruplar = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $gruplar], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'yetkiler':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT * FROM yetkiler WHERE status = 1 ORDER BY modul, yetki_adi";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $yetkiler = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $yetkiler], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'grup-yetkiler':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // URL parametresini doğru şekilde al
            $grup_id = 0;
            if (isset($_GET['grup_id'])) {
                $grup_id = (int)$_GET['grup_id'];
            } elseif (isset($data['grup_id'])) {
                $grup_id = (int)$data['grup_id'];
            }
            
            // Debug için log ekle
            error_log("Grup ID: " . $grup_id);
            error_log("GET params: " . print_r($_GET, true));
            
            if (!$grup_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Grup ID gerekli. Gelen: ' . $grup_id], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Önce grubun var olup olmadığını kontrol et
            $checkQuery = "SELECT id, grup_adi FROM yetki_gruplari WHERE id = ? AND status = 1";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$grup_id]);
            $grup = $checkStmt->fetch();
            
            if (!$grup) {
                http_response_code(404);
                echo json_encode(['error' => 'Grup bulunamadi (ID: ' . $grup_id . ')'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT y.*, 
                        CASE WHEN gy.id IS NOT NULL THEN 1 ELSE 0 END as aktif
                      FROM yetkiler y
                      LEFT JOIN grup_yetkiler gy ON y.id = gy.yetki_id AND gy.grup_id = ?
                      WHERE y.status = 1
                      ORDER BY y.modul, y.yetki_adi";
            $stmt = $db->prepare($query);
            $stmt->execute([$grup_id]);
            $yetkiler = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'data' => $yetkiler,
                'grup_info' => $grup,
                'debug' => ['grup_id' => $grup_id, 'yetki_sayisi' => count($yetkiler)]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update-grup-yetkiler':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $grup_id = isset($data['grup_id']) ? (int)$data['grup_id'] : 0;
            $yetkiler = isset($data['yetkiler']) ? $data['yetkiler'] : [];
            
            if (!$grup_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Grup ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            try {
                $db->beginTransaction();
                
                // Önce bu grubun tüm yetkilerini sil
                $query = "DELETE FROM grup_yetkiler WHERE grup_id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$grup_id]);
                
                // Seçili yetkiler için kayıt ekle
                foreach ($yetkiler as $yetki_id) {
                    $query = "INSERT INTO grup_yetkiler (grup_id, yetki_id) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$grup_id, $yetki_id]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Yetkiler basariyla guncellendi'], JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
            break;
            
        case 'kullanicilar':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT u.*, yg.grup_adi FROM users u 
                     LEFT JOIN yetki_gruplari yg ON u.grup_id = yg.id 
                     WHERE u.status = 1 ORDER BY u.username";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $kullanicilar = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $kullanicilar], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'update-kullanici-grup':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if (!checkPermission($db, $_SESSION['current_user'], 'yetki.management')) {
                http_response_code(403);
                echo json_encode(['error' => 'Yetkisiz erisim'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
            $grup_id = isset($data['grup_id']) ? (int)$data['grup_id'] : null;
            
            if (!$user_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Kullanici ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "UPDATE users SET grup_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$grup_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Kullanici grubu guncellendi'], JSON_UNESCAPED_UNICODE);
            break;

        // Mevcut endpoint'ler...
        case 'create-password':
            if ($method === 'POST') {
                $username = isset($data['username']) ? trim($data['username']) : '';
                $password = isset($data['password']) ? $data['password'] : '';
                $confirm_password = isset($data['confirm_password']) ? $data['confirm_password'] : '';
                
                if (empty($username) || empty($password) || empty($confirm_password)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tum alanlar gerekli'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                if ($password !== $confirm_password) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Sifreler eslesmiyor'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                if (strlen($password) < 6) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Sifre en az 6 karakter olmali'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE sakinler SET password = ? WHERE ad_soyad = ? AND password IS NULL AND status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$hashed_password, $username]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Sifre basariyla olusturuldu'], JSON_UNESCAPED_UNICODE);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Kullanici bulunamadi veya zaten sifre olusturulmus'], JSON_UNESCAPED_UNICODE);
                }
            }
            break;
            
        case 'logout':
            unset($_SESSION['current_user']);
            echo json_encode(['success' => true, 'message' => 'Cikis yapildi'], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'current-user':
            if (isset($_SESSION['current_user'])) {
                echo json_encode(['success' => true, 'user' => $_SESSION['current_user']], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'dashboard':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            
            // Dashboard verilerini görebilme yetkisi kontrolü
            if ($user['role'] !== 'sakin' && !checkPermission($db, $user, 'dashboard.view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Dashboard goruntulemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $istatistikler = [];
            
            if ($user['role'] === 'sakin') {
                // Sakin sadece kendi aidatlarını görsün
                $sakin_id = $user['sakin_id'];
                
                // Sakin için özel aidat istatistikleri
                $query = "SELECT 
                            SUM(miktar) as toplam_aidat,
                            SUM(CASE WHEN odendi = 1 THEN miktar ELSE 0 END) as odenen_aidat,
                            SUM(CASE WHEN odendi = 0 THEN miktar ELSE 0 END) as bekleyen_aidat
                          FROM aidatlar WHERE sakin_id = ? AND status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute([$sakin_id]);
                $aidat_stats = $stmt->fetch();
                
                $istatistikler = [
                    'toplam_aidat' => (float)($aidat_stats['toplam_aidat'] ?: 0),
                    'odenen_aidat' => (float)($aidat_stats['odenen_aidat'] ?: 0),
                    'bekleyen_aidat' => (float)($aidat_stats['bekleyen_aidat'] ?: 0)
                ];
                
                // Sakin giderleri de görebilir (şeffaflık için)
                $query = "SELECT SUM(miktar) as toplam_gider FROM giderler WHERE status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $gider_stats = $stmt->fetch();
                
                $istatistikler['toplam_gider'] = (float)($gider_stats['toplam_gider'] ?: 0);
                
            } else {
                // Yönetici/Muhasebeci için tüm apartman istatistikleri
                
                // Toplam sakin sayısı
                $query = "SELECT COUNT(*) as toplam FROM sakinler WHERE status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $toplam_sakin = $stmt->fetch()['toplam'];
                
                // Aidat istatistikleri
                $query = "SELECT 
                            SUM(miktar) as toplam_aidat,
                            SUM(CASE WHEN odendi = 1 THEN miktar ELSE 0 END) as odenen_aidat,
                            SUM(CASE WHEN odendi = 0 THEN miktar ELSE 0 END) as bekleyen_aidat
                          FROM aidatlar WHERE status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $aidat_stats = $stmt->fetch();
                
                $istatistikler = [
                    'toplam_sakin' => (int)$toplam_sakin,
                    'toplam_aidat' => (float)($aidat_stats['toplam_aidat'] ?: 0),
                    'odenen_aidat' => (float)($aidat_stats['odenen_aidat'] ?: 0),
                    'bekleyen_aidat' => (float)($aidat_stats['bekleyen_aidat'] ?: 0)
                ];
                
                if ($user['role'] === 'yonetici' || checkPermission($db, $user, 'giderler.view')) {
                    $query = "SELECT SUM(miktar) as toplam_gider FROM giderler WHERE status = 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $gider_stats = $stmt->fetch();
                    
                    $istatistikler['toplam_gider'] = (float)($gider_stats['toplam_gider'] ?: 0);
                    $istatistikler['net_gelir'] = $istatistikler['odenen_aidat'] - $istatistikler['toplam_gider'];
                }
            }
            
            echo json_encode(['success' => true, 'data' => $istatistikler], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'sakinler':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'sakinler.view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sakinleri goruntulemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT id, ad_soyad, daire_no, telefon, email, 
                             CASE WHEN password IS NULL THEN 0 ELSE 1 END as password,
                             status, created_at FROM sakinler WHERE status = 1 ORDER BY id";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $sakinler = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $sakinler], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'add-sakin':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'sakinler.add')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sakin eklemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $ad_soyad = isset($data['ad_soyad']) ? trim($data['ad_soyad']) : '';
            $daire_no = isset($data['daire_no']) ? trim($data['daire_no']) : '';
            $telefon = isset($data['telefon']) ? trim($data['telefon']) : '';
            $email = isset($data['email']) ? trim($data['email']) : '';
            
            if (empty($ad_soyad) || empty($daire_no) || empty($telefon) || empty($email)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tum alanlar gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Daire no benzersizlik kontrolü
            $query = "SELECT id FROM sakinler WHERE daire_no = ? AND status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$daire_no]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Bu daire no zaten kayitli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            try {
                $db->beginTransaction();
                
                // Sakin grubunun ID'sini al
                $query = "SELECT id FROM yetki_gruplari WHERE grup_adi = 'Sakin' AND status = 1";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $sakin_grup = $stmt->fetch();
                $grup_id = $sakin_grup ? $sakin_grup['id'] : null;
                
                // Sakini ekle - grup_id ile birlikte
                $query = "INSERT INTO sakinler (ad_soyad, daire_no, telefon, email, grup_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$ad_soyad, $daire_no, $telefon, $email, $grup_id]);
                
                $new_id = $db->lastInsertId();
                
                $db->commit();
                
                $yeni_sakin = [
                    'id' => $new_id,
                    'ad_soyad' => $ad_soyad,
                    'daire_no' => $daire_no,
                    'telefon' => $telefon,
                    'email' => $email,
                    'grup_id' => $grup_id,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                echo json_encode(['success' => true, 'message' => 'Sakin basariyla eklendi', 'data' => $yeni_sakin], JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                $db->rollback();
                http_response_code(500);
                echo json_encode(['error' => 'Sakin eklenirken hata olustu: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'aidatlar':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            
            if ($user['role'] === 'sakin') {
                // Sakin sadece kendi aidatlarını görebilir
                if (isset($user['sakin_id'])) {
                    $query = "SELECT a.*, s.ad_soyad as sakin_adi, s.daire_no 
                             FROM aidatlar a 
                             JOIN sakinler s ON a.sakin_id = s.id 
                             WHERE a.sakin_id = ? AND a.status = 1 
                             ORDER BY a.id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$user['sakin_id']]);
                }
            } else {
                // Yetki kontrolü
                if (!checkPermission($db, $user, 'aidatlar.view')) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Aidatlari goruntulemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                $query = "SELECT a.*, s.ad_soyad as sakin_adi, s.daire_no 
                         FROM aidatlar a 
                         JOIN sakinler s ON a.sakin_id = s.id 
                         WHERE a.status = 1 
                         ORDER BY a.id";
                $stmt = $db->prepare($query);
                $stmt->execute();
            }
            
            $aidatlar = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $aidatlar], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'add-aidat':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'aidatlar.add')) {
                http_response_code(403);
                echo json_encode(['error' => 'Aidat eklemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $sakin_id = isset($data['sakin_id']) ? (int)$data['sakin_id'] : 0;
            $ay = isset($data['ay']) ? trim($data['ay']) : '';
            $miktar = isset($data['miktar']) ? (float)$data['miktar'] : 0;
            
            if (!$sakin_id || empty($ay) || $miktar <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tum alanlar gerekli ve gecerli olmali'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Sakin kontrol
            $query = "SELECT * FROM sakinler WHERE id = ? AND status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$sakin_id]);
            $sakin = $stmt->fetch();
            
            if (!$sakin) {
                http_response_code(404);
                echo json_encode(['error' => 'Sakin bulunamadi'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Duplicate kontrol
            $query = "SELECT id FROM aidatlar WHERE sakin_id = ? AND ay = ? AND status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$sakin_id, $ay]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'Bu sakin icin bu ay zaten aidat kaydi var'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "INSERT INTO aidatlar (sakin_id, ay, miktar) VALUES (?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$sakin_id, $ay, $miktar]);
            
            $new_id = $db->lastInsertId();
            $yeni_aidat = [
                'id' => $new_id,
                'sakin_id' => $sakin_id,
                'sakin_adi' => $sakin['ad_soyad'],
                'daire_no' => $sakin['daire_no'],
                'ay' => $ay,
                'miktar' => $miktar,
                'odendi' => false,
                'odeme_tarihi' => null,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode(['success' => true, 'message' => 'Aidat basariyla eklendi', 'data' => $yeni_aidat], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'giderler':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            
            // Sakinler de yetki kontrolü ile giderleri görebilir
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'giderler.view')) {
                http_response_code(403);
                echo json_encode(['error' => 'Giderleri goruntulemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "SELECT * FROM giderler WHERE status = 1 ORDER BY id";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $giderler = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $giderler], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'add-gider':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            
            // Debug için user bilgisini logla
            error_log("add-gider called by user: " . json_encode($user));
            
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'giderler.add')) {
                error_log("add-gider permission denied for user: " . $user['role']);
                http_response_code(403);
                echo json_encode(['error' => 'Gider eklemek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            error_log("add-gider permission granted");
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $tarih = isset($data['tarih']) ? trim($data['tarih']) : '';
            $aciklama = isset($data['aciklama']) ? trim($data['aciklama']) : '';
            $kategori = isset($data['kategori']) ? trim($data['kategori']) : '';
            $miktar = isset($data['miktar']) ? (float)$data['miktar'] : 0;
            
            if (empty($tarih) || empty($aciklama) || empty($kategori) || $miktar <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Tum alanlar gerekli ve gecerli olmali'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $query = "INSERT INTO giderler (tarih, aciklama, kategori, miktar) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$tarih, $aciklama, $kategori, $miktar]);
            
            $new_id = $db->lastInsertId();
            $yeni_gider = [
                'id' => $new_id,
                'tarih' => $tarih,
                'aciklama' => $aciklama,
                'kategori' => $kategori,
                'miktar' => $miktar,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode(['success' => true, 'message' => 'Gider basariyla eklendi', 'data' => $yeni_gider], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'odeme-yap':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $user = $_SESSION['current_user'];
            $aidat_id = isset($data['aidat_id']) ? (int)$data['aidat_id'] : 0;
            
            if (!$aidat_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Aidat ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Aidatı kontrol et
            $query = "SELECT * FROM aidatlar WHERE id = ? AND status = 1";
            $stmt = $db->prepare($query);
            $stmt->execute([$aidat_id]);
            $aidat = $stmt->fetch();
            
            if (!$aidat) {
                http_response_code(404);
                echo json_encode(['error' => 'Aidat bulunamadi'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Sakin sadece kendi aidatını ödeyebilir
            if ($user['role'] === 'sakin' && $user['sakin_id'] != $aidat['sakin_id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Bu aidati odeme yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Yönetici/muhasebeci ise aidat ödeme yetkisi kontrol et
            if ($user['role'] !== 'sakin' && $user['role'] !== 'yonetici' && !checkPermission($db, $user, 'aidatlar.payment')) {
                http_response_code(403);
                echo json_encode(['error' => 'Aidat odemesi yapmak icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            if ($aidat['odendi']) {
                http_response_code(400);
                echo json_encode(['error' => 'Bu aidat zaten odenmis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            // Aidatı ödenmiş olarak işaretle
            $query = "UPDATE aidatlar SET odendi = 1, odeme_tarihi = CURDATE() WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$aidat_id]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Odeme basariyla tamamlandi',
                'odeme_tarihi' => date('Y-m-d'),
                'aidat_id' => $aidat_id
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'test':
            echo json_encode(['success' => true, 'message' => 'MySQL API calisiyor', 'time' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
            break;
        
        case 'delete-sakin':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'sakinler.delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Sakin silmek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $sakin_id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$sakin_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Sakin ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $query = "UPDATE sakinler SET status = 0 WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$sakin_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Sakin basariyla pasif hale getirildi', 'id' => $sakin_id], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Sakin bulunamadi veya zaten pasif'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'delete-aidat':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'aidatlar.delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Aidat silmek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $aidat_id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$aidat_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Aidat ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $query = "UPDATE aidatlar SET status = 0 WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$aidat_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Aidat basariyla pasif hale getirildi', 'id' => $aidat_id], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Aidat bulunamadi veya zaten pasif'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'delete-gider':
            if (!isset($_SESSION['current_user'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Oturum acilmamis'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $user = $_SESSION['current_user'];
            if ($user['role'] !== 'yonetici' && !checkPermission($db, $user, 'giderler.delete')) {
                http_response_code(403);
                echo json_encode(['error' => 'Gider silmek icin yetkiniz yok'], JSON_UNESCAPED_UNICODE);
                break;
            }
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Sadece POST desteklenir'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $gider_id = isset($data['id']) ? (int)$data['id'] : 0;
            if (!$gider_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Gider ID gerekli'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $query = "UPDATE giderler SET status = 0 WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$gider_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Gider basariyla pasif hale getirildi', 'id' => $gider_id], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Gider bulunamadi veya zaten pasif'], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Gecersiz endpoint: ' . $endpoint], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server hatasi: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>