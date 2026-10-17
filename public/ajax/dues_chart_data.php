<?php
// public/ajax/dues_chart_data.php - Aidat pasta grafik verilerini getir
session_start();

// APP_LOADED sabitini tanımla
define('APP_LOADED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';

// Giriş kontrolü
requireLogin();
$current_user = getCurrentUser();

// Yetki kontrolü
if ($current_user['role'] === 'sakin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
    exit();
}

// Ay parametresini al
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

try {
    // Aidat durumu verilerini al
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN odendi = 1 THEN miktar ELSE 0 END) as odenen_toplam,
            SUM(CASE WHEN odendi = 0 THEN miktar ELSE 0 END) as odenmesi_gereken_toplam,
            COUNT(CASE WHEN odendi = 1 THEN 1 END) as odenen_adet,
            COUNT(CASE WHEN odendi = 0 THEN 1 END) as odenmesi_gereken_adet,
            COUNT(*) as toplam_daire
        FROM aidatlar 
        WHERE status = 1 
        AND ay = ?
    ");
    $stmt->execute([$selected_month]);
    $aidat_durumu = $stmt->fetch();
    
    // Tahsilat oranını hesapla
    $toplam_tutar = $aidat_durumu['odenen_toplam'] + $aidat_durumu['odenmesi_gereken_toplam'];
    $tahsilat_orani = $toplam_tutar > 0 ? ($aidat_durumu['odenen_toplam'] / $toplam_tutar) * 100 : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'odenen_toplam' => floatval($aidat_durumu['odenen_toplam']),
            'odenmesi_gereken_toplam' => floatval($aidat_durumu['odenmesi_gereken_toplam']),
            'odenen_adet' => intval($aidat_durumu['odenen_adet']),
            'odenmesi_gereken_adet' => intval($aidat_durumu['odenmesi_gereken_adet']),
            'toplam_daire' => intval($aidat_durumu['toplam_daire']),
            'tahsilat_orani' => number_format($tahsilat_orani, 1),
            'selected_month' => $selected_month
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Veri alınırken hata oluştu: ' . $e->getMessage()
    ]);
}
?>