<?php
// src/functions.php - Genel yardımcı fonksiyonlar

// Veri temizleme
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Para formatı
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.') . ' ₺';
}

// Tarih formatı
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

// Ay formatı
function formatMonth($monthString) {
    $months = [
        '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
        '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
        '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
    ];
    
    $parts = explode('-', $monthString);
    if (count($parts) >= 2) {
        $year = $parts[0];
        $month = $parts[1];
        return $months[$month] . ' ' . $year;
    }
    
    return $monthString;
}

// Alert gösterme
function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Alert alma ve temizleme
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Dashboard istatistiklerini al

function getDashboardStats($selected_month = null) {
    global $db, $current_user;
    
    if (!$selected_month) {
        $selected_month = date('Y-m');
    }
    
    $stats = [
        'odenen_aidat' => 0,
        'bekleyen_aidat' => 0,
        'toplam_gider' => 0,
        'net_gelir' => 0,
        'tahsilat_orani' => 0
    ];
    
    try {
        if ($current_user['role'] === 'sakin') {
            // SAKİN İÇİN İSTATİSTİKLER
            
            // Sakin'in tüm bekleyen aidatları
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as bekleyen_aidat 
                FROM aidatlar 
                WHERE sakin_id = ? AND odendi = 0 AND status = 1
            ");
            $stmt->execute([$current_user['sakin_id']]);
            $result = $stmt->fetch();
            $stats['bekleyen_aidat'] = $result['bekleyen_aidat'];
            
            // Sakin'in ödenen aidatları (bu ay)
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as odenen_aidat 
                FROM aidatlar 
                WHERE sakin_id = ? AND odendi = 1 AND status = 1 AND ay = ?
            ");
            $stmt->execute([$current_user['sakin_id'], $selected_month]);
            $result = $stmt->fetch();
            $stats['odenen_aidat'] = $result['odenen_aidat'];
            
            // Sakin'in toplam aidat (bu ay)
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as toplam_aidat 
                FROM aidatlar 
                WHERE sakin_id = ? AND status = 1 AND ay = ?
            ");
            $stmt->execute([$current_user['sakin_id'], $selected_month]);
            $result = $stmt->fetch();
            $toplam_aidat = $result['toplam_aidat'];
            
            // Tahsilat oranı (sakin için)
            if ($toplam_aidat > 0) {
                $stats['tahsilat_orani'] = ($stats['odenen_aidat'] / $toplam_aidat) * 100;
            }
            
            // Net gelir sakin için önemli değil
            $stats['net_gelir'] = 0;
            $stats['toplam_gider'] = 0;
            
        } else {
            // YÖNETİCİ/MUHASEBECI İÇİN İSTATİSTİKLER
            
            // Ödenen aidatlar (seçili ay)
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as odenen_aidat 
                FROM aidatlar 
                WHERE odendi = 1 AND status = 1 AND ay = ?
            ");
            $stmt->execute([$selected_month]);
            $result = $stmt->fetch();
            $stats['odenen_aidat'] = $result['odenen_aidat'];
            
            // Bekleyen aidatlar (tüm aylar)
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as bekleyen_aidat 
                FROM aidatlar 
                WHERE odendi = 0 AND status = 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['bekleyen_aidat'] = $result['bekleyen_aidat'];
            
            // Toplam giderler (seçili ay)
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as toplam_gider 
                FROM giderler 
                WHERE status = 1 AND DATE_FORMAT(tarih, '%Y-%m') = ?
            ");
            $stmt->execute([$selected_month]);
            $result = $stmt->fetch();
            $stats['toplam_gider'] = $result['toplam_gider'];
            
            // Net gelir hesaplama
            $stats['net_gelir'] = $stats['odenen_aidat'] - $stats['toplam_gider'];
            
            // Tahsilat oranı hesaplama
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(miktar), 0) as toplam_aidat 
                FROM aidatlar 
                WHERE status = 1 AND ay = ?
            ");
            $stmt->execute([$selected_month]);
            $result = $stmt->fetch();
            $toplam_aidat = $result['toplam_aidat'];
            
            if ($toplam_aidat > 0) {
                $stats['tahsilat_orani'] = ($stats['odenen_aidat'] / $toplam_aidat) * 100;
            }
        }
        
    } catch (Exception $e) {
        // Hata durumunda default değerler dön
        error_log("Dashboard stats hatası: " . $e->getMessage());
    }
    
    return $stats;
}

// Sakin için özel dashboard bilgileri
function getSakinDashboardInfo() {
    global $db, $current_user;
    
    if ($current_user['role'] !== 'sakin') {
        return null;
    }
    
    try {
        // Sakin bilgileri
        $stmt = $db->prepare("
            SELECT s.*, st.site_adi, b.blok_adi 
            FROM sakinler s 
            LEFT JOIN siteler st ON s.site_id = st.id 
            LEFT JOIN bloklar b ON s.blok_id = b.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$current_user['sakin_id']]);
        $sakin_info = $stmt->fetch();
        
        // En yakın ödeme tarihi
        $stmt = $db->prepare("
            SELECT ay, miktar 
            FROM aidatlar 
            WHERE sakin_id = ? AND odendi = 0 AND status = 1 
            ORDER BY ay ASC 
            LIMIT 1
        ");
        $stmt->execute([$current_user['sakin_id']]);
        $next_payment = $stmt->fetch();
        
        // Son ödemeler
        $stmt = $db->prepare("
            SELECT ay, miktar, odeme_tarihi 
            FROM aidatlar 
            WHERE sakin_id = ? AND odendi = 1 AND status = 1 
            ORDER BY odeme_tarihi DESC 
            LIMIT 3
        ");
        $stmt->execute([$current_user['sakin_id']]);
        $recent_payments = $stmt->fetchAll();
        
        return [
            'sakin_info' => $sakin_info,
            'next_payment' => $next_payment,
            'recent_payments' => $recent_payments
        ];
        
    } catch (Exception $e) {
        error_log("Sakin dashboard info hatası: " . $e->getMessage());
        return null;
    }
}

function addPartialPayment($sakin_id, $odeme_miktari, $aciklama = '') {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO cari_hareketler (sakin_id, odeme_miktari, aciklama, odeme_tarihi) 
            VALUES (?, ?, ?, CURDATE())
        ");
        $stmt->execute([$sakin_id, $odeme_miktari, $aciklama]);
        
        return [
            'success' => true,
            'message' => 'Parçalı ödeme başarıyla kaydedildi'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Parçalı ödeme kaydedilemedi: ' . $e->getMessage()
        ];
    }
}

// Mevcut ayları getir (son 12 ay + gelecek 3 ay)
function getAvailableMonths() {
    global $db;
    
    $months = [];
    
    try {
        // Son 12 ay + gelecek 3 ay oluştur
        for ($i = -12; $i <= 3; $i++) {
            $date = new DateTime();
            $date->modify("$i months");
            $month_key = $date->format('Y-m');
            $month_name = formatMonthName($date->format('Y-m'));
            
            $months[$month_key] = $month_name;
        }
        
        // Mevcut verileri kontrol et ve sadece veri olan ayları işaretle
        $stmt = $db->prepare("
            SELECT DISTINCT DATE_FORMAT(ay, '%Y-%m') as ay_key
            FROM aidatlar 
            WHERE status = 1
            UNION
            SELECT DISTINCT DATE_FORMAT(tarih, '%Y-%m') as ay_key
            FROM giderler 
            WHERE status = 1
            UNION  
            SELECT DISTINCT DATE_FORMAT(odeme_tarihi, '%Y-%m') as ay_key
            FROM cari_hareketler 
            WHERE status = 1
        ");
        $stmt->execute();
        $data_months = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Sadece veri olan ayları ve bu ay + gelecek ayları göster
        $current_month = date('Y-m');
        $filtered_months = [];
        
        foreach ($months as $key => $name) {
            if (in_array($key, $data_months) || $key >= $current_month) {
                $filtered_months[$key] = $name;
            }
        }
        
        return $filtered_months;
        
    } catch (Exception $e) {
        error_log("Get available months error: " . $e->getMessage());
        return [date('Y-m') => formatMonthName(date('Y-m'))];
    }
}

// Ay formatı düzenleme
function formatMonthName($month_key) {
    $months_tr = [
        '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
        '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
        '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
    ];
    
    list($year, $month) = explode('-', $month_key);
    return $months_tr[$month] . ' ' . $year;
}

// Aktif sayfa kontrolü
function isActivePage($page) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    return $current_page === $page;
}


function validateRequired($fields, $data) {
    $errors = [];
    
    foreach ($fields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = $label . ' gereklidir';
        }
    }
    
    return $errors;
}


function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}


function validateNumeric($value, $min = null, $max = null) {
    if (!is_numeric($value)) {
        return false;
    }
    
    if ($min !== null && $value < $min) {
        return false;
    }
    
    if ($max !== null && $value > $max) {
        return false;
    }
    
    return true;
}


function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}


function getPaginationData($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}


function debug($data) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}
