<?php
// public/ajax/set_selection.php - Site/Blok seçimini session'a kaydet

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/auth.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Giriş kontrolü
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Unauthorized');
}

// JSON input'u al
$input = json_decode(file_get_contents('php://input'), true);
$site_id = intval($input['site_id'] ?? 0);
$blok_id = intval($input['blok_id'] ?? 0);

try {
    // Site seçimini kaydet
    if ($site_id > 0) {
        // Site var mı kontrol et
        $stmt = $db->prepare("SELECT id FROM siteler WHERE id = ? AND status = 1");
        $stmt->execute([$site_id]);
        
        if ($stmt->fetch()) {
            $_SESSION['selected_site_id'] = $site_id;
            
            // Blok seçimi varsa onu da kaydet
            if ($blok_id > 0) {
                // Blok bu siteye ait mi kontrol et
                $stmt = $db->prepare("SELECT id FROM bloklar WHERE id = ? AND site_id = ? AND status = 1");
                $stmt->execute([$blok_id, $site_id]);
                
                if ($stmt->fetch()) {
                    $_SESSION['selected_blok_id'] = $blok_id;
                } else {
                    $_SESSION['selected_blok_id'] = null;
                }
            } else {
                $_SESSION['selected_blok_id'] = null;
            }
            
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Geçersiz site']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Site ID gerekli']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Sunucu hatası']);
}
?>