<?php
// pages/aidatlarim.php - Apartman Sakinlerine Özel Aidat Görüntüleme Sayfası
$page_title = 'Aidatlarım';
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

// Sadece sakinler bu sayfaya erişebilir
if ($current_user['role'] !== 'sakin') {
    header('Location: ../index.php?error=yetkisiz');
    exit();
}

// Sakin ID kontrolü
if (!isset($current_user['sakin_id']) || !$current_user['sakin_id']) {
    header('Location: ../index.php?error=sakin_bilgisi_eksik');
    exit();
}

// Aidat ödeme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_aidat'])) {
    $aidat_id = (int)$_POST['aidat_id'];
    
    try {
        // Aidatı kontrol et - sadece sakin kendi aidatını ödeyebilir
        $stmt = $db->prepare("SELECT * FROM aidatlar WHERE id = ? AND sakin_id = ? AND status = 1");
        $stmt->execute([$aidat_id, $current_user['sakin_id']]);
        $aidat = $stmt->fetch();
        
        if (!$aidat) {
            showAlert('Aidat bulunamadı veya bu aidatı ödeme yetkiniz yok', 'error');
        } elseif ($aidat['odendi']) {
            showAlert('Bu aidat zaten ödenmiş', 'error');
        } else {
            $stmt = $db->prepare("UPDATE aidatlar SET odendi = 1, odeme_tarihi = CURDATE() WHERE id = ?");
            $stmt->execute([$aidat_id]);
            
            showAlert('✅ Ödeme başarıyla tamamlandı!', 'success');
        }
    } catch (Exception $e) {
        showAlert('Ödeme işlemi sırasında hata oluştu', 'error');
    }
}

// Sakin bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT s.*, st.site_adi, b.blok_adi 
        FROM sakinler s 
        LEFT JOIN siteler st ON s.site_id = st.id 
        LEFT JOIN bloklar b ON s.blok_id = b.id 
        WHERE s.id = ? AND s.status = 1
    ");
    $stmt->execute([$current_user['sakin_id']]);
    $sakin_info = $stmt->fetch();
    
    if (!$sakin_info) {
        header('Location: ../index.php?error=sakin_bulunamadi');
        exit();
    }
} catch (Exception $e) {
    header('Location: ../index.php?error=veritabani_hatasi');
    exit();
}

// Sakin aidatlarını getir
$aidatlar = [];
$toplam_aidat = 0;
$odenen_aidat = 0;
$bekleyen_aidat = 0;
$odeme_orani = 0;
$aylar = [];

try {
    $stmt = $db->prepare("
        SELECT a.*, 
               CASE WHEN a.odeme_tarihi IS NOT NULL THEN 'Ödendi' ELSE 'Beklemede' END as durum,
               CASE WHEN a.ay = ? THEN 1 ELSE 0 END as bu_ay
        FROM aidatlar a
        WHERE a.sakin_id = ? AND a.status = 1 
        ORDER BY a.ay DESC
    ");
    $stmt->execute([date('Y-m'), $current_user['sakin_id']]);
    $aidatlar = $stmt->fetchAll();
    
    // İstatistikleri hesapla
    foreach ($aidatlar as $aidat) {
        $toplam_aidat += $aidat['miktar'];
        if ($aidat['odendi']) {
            $odenen_aidat += $aidat['miktar'];
        } else {
            $bekleyen_aidat += $aidat['miktar'];
        }
    }
    
    if ($toplam_aidat > 0) {
        $odeme_orani = ($odenen_aidat / $toplam_aidat) * 100;
    }
    
    // Benzersiz ayları al
    $aylar = array_unique(array_column($aidatlar, 'ay'));
    sort($aylar);
    $aylar = array_reverse($aylar); // En yeni aylar önce
    
} catch (Exception $e) {
    $aidatlar = [];
    showAlert('Aidatlar yüklenirken hata oluştu', 'error');
}

// Bu ay aidatları
$bu_ay_aidatlar = array_filter($aidatlar, fn($a) => $a['ay'] === date('Y-m'));
$bu_ay_toplam = array_sum(array_column($bu_ay_aidatlar, 'miktar'));
$bu_ay_odenen = array_sum(array_column(array_filter($bu_ay_aidatlar, fn($a) => $a['odendi']), 'miktar'));
$bu_ay_bekleyen = $bu_ay_toplam - $bu_ay_odenen;

// Son 6 ay aidatları (grafik için)
$son_6_ay = [];
for ($i = 5; $i >= 0; $i--) {
    $ay = date('Y-m', strtotime("-$i months"));
    $ay_aidatlar = array_filter($aidatlar, fn($a) => $a['ay'] === $ay);
    $son_6_ay[] = [
        'ay' => $ay,
        'ay_text' => formatMonth($ay),
        'toplam' => array_sum(array_column($ay_aidatlar, 'miktar')),
        'odenen' => array_sum(array_column(array_filter($ay_aidatlar, fn($a) => $a['odendi']), 'miktar')),
        'adet' => count($ay_aidatlar)
    ];
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
                <div class="welcome-section">
                    <h1 class="page-title">💳 Aidatlarım</h1>
                    <p class="page-subtitle">
                        <strong><?= htmlspecialchars($sakin_info['ad_soyad']) ?></strong> - 
                        Daire <?= htmlspecialchars($sakin_info['daire_no']) ?> 
                        (<?= htmlspecialchars($sakin_info['site_adi']) ?>
                        <?php if ($sakin_info['blok_adi']): ?>
                            - <?= htmlspecialchars($sakin_info['blok_adi']) ?>
                        <?php endif; ?>)
                    </p>
                </div>
                
                <div class="topbar-actions">
                    <div class="search-box">
                        <input type="text" class="search-input" placeholder="Ay ara..." id="aidatSearch">
                        <div class="search-icon">🔍</div>
                    </div>
                    
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
                        <?= $alert['message'] ?>
                    </div>
                <?php endif; ?>

                <!-- Özet Kartlar -->
                <div class="stats-grid">
                    <div class="stat-card primary clickable-stat" data-filter="all" onclick="filterByStatCard('all', this)">
                        <div class="stat-header">
                            <div class="stat-title">Toplam Aidat</div>
                            <div class="stat-icon">💰</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($toplam_aidat) ?></div>
                        <div class="stat-change"><?= count($aidatlar) ?> kayıt</div>
                    </div>
                    
                    <div class="stat-card success clickable-stat" data-filter="paid" onclick="filterByStatCard('paid', this)">
                        <div class="stat-header">
                            <div class="stat-title">Ödenen Aidatlar</div>
                            <div class="stat-icon">✅</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($odenen_aidat) ?></div>
                        <div class="stat-change positive">%<?= number_format($odeme_orani, 1) ?> ödeme oranı</div>
                    </div>
                    
                    <div class="stat-card danger clickable-stat" data-filter="unpaid" onclick="filterByStatCard('unpaid', this)">
                        <div class="stat-header">
                            <div class="stat-title">Bekleyen Aidatlar</div>
                            <div class="stat-icon">⏳</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($bekleyen_aidat) ?></div>
                        <div class="stat-change negative"><?= count(array_filter($aidatlar, fn($a) => !$a['odendi'])) ?> bekleyen</div>
                    </div>
                    
                    <div class="stat-card info clickable-stat" data-filter="this-month" onclick="filterByStatCard('this-month', this)">
                        <div class="stat-header">
                            <div class="stat-title">Bu Ay</div>
                            <div class="stat-icon">📅</div>
                        </div>
                        <div class="stat-value"><?= formatMoney($bu_ay_toplam) ?></div>
                        <div class="stat-change"><?= count($bu_ay_aidatlar) ?> aidat</div>
                    </div>
                </div>

                <!-- Grafik ve Liste Grid -->
                <div class="content-grid">
                    <!-- Aidat Grafiği -->
                    <div class="card chart-card">
                        <div class="card-header">
                            <h3 class="card-title">📊 Son 6 Ay Ödeme Durumu</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="aidatChart" width="400" height="200"></canvas>
                            </div>
                            <div class="chart-legend">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--success-color);"></div>
                                    <span>Ödenen</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: var(--danger-color);"></div>
                                    <span>Bekleyen</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hızlı Özet -->
                    <div class="card summary-card">
                        <div class="card-header">
                            <h3 class="card-title">🏠 Daire Bilgileri</h3>
                        </div>
                        <div class="card-body">
                            <div class="summary-item">
                                <div class="summary-label">👤 Sakin</div>
                                <div class="summary-value"><?= htmlspecialchars($sakin_info['ad_soyad']) ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">🏠 Daire</div>
                                <div class="summary-value">No: <?= htmlspecialchars($sakin_info['daire_no']) ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">🏢 Site/Blok</div>
                                <div class="summary-value">
                                    <?= htmlspecialchars($sakin_info['site_adi']) ?>
                                    <?php if ($sakin_info['blok_adi']): ?>
                                        <br><small><?= htmlspecialchars($sakin_info['blok_adi']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">📞 İletişim</div>
                                <div class="summary-value">
                                    <?= htmlspecialchars($sakin_info['telefon']) ?><br>
                                    <small><?= htmlspecialchars($sakin_info['email']) ?></small>
                                </div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">📊 Ödeme Oranım</div>
                                <div class="summary-value">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $odeme_orani ?>%"></div>
                                    </div>
                                    <span class="progress-text">%<?= number_format($odeme_orani, 1) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aidatlar Listesi -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">💳 Aidat Listesi</h3>
                        <div class="card-actions">
                            <?php if (!empty($aylar)): ?>
                            <select class="form-select" id="ayFilter" onchange="filterAidatlar()">
                                <option value="">Tüm Aylar</option>
                                <?php foreach ($aylar as $ay): ?>
                                    <option value="<?= $ay ?>"><?= formatMonth($ay) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <select class="form-select" id="statusFilter" onchange="filterAidatlar()">
                                <option value="">Tüm Durumlar</option>
                                <option value="paid">Ödenen</option>
                                <option value="unpaid">Bekleyen</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Aktif Filtre Bilgisi -->
                        <div class="filter-info" id="filterInfo" style="display: none;">
                            <div class="filter-badge">
                                <span id="filterText">Tüm Aidatlar</span>
                                <button onclick="clearAllFilters()" title="Filtreyi temizle">×</button>
                            </div>
                        </div>

                        <?php if (empty($aidatlar)): ?>
                            <div class="empty-state">
                                <div style="font-size: 48px; margin-bottom: 16px;">💳</div>
                                <h3>Henüz aidat kaydınız yok</h3>
                                <p>Apartman yöneticisi tarafından aidat eklendiğinde burada görüntülenecektir.</p>
                            </div>
                        <?php else: ?>
                            <div class="aidatlar-list">
                                <?php foreach ($aidatlar as $aidat): ?>
                                <div class="aidat-item" 
                                     data-ay="<?= $aidat['ay'] ?>" 
                                     data-status="<?= $aidat['odendi'] ? 'paid' : 'unpaid' ?>"
                                     data-month="<?= date('Y-m', strtotime($aidat['ay'])) === date('Y-m') ? 'this-month' : 'other-month' ?>"
                                     data-search="<?= strtolower(formatMonth($aidat['ay'])) ?>">
                                    
                                    <div class="aidat-main">
                                        <div class="aidat-period">
                                            <div class="period-icon">
                                                <?php if ($aidat['ay'] === date('Y-m')): ?>
                                                    📅
                                                <?php elseif ($aidat['odendi']): ?>
                                                    ✅
                                                <?php else: ?>
                                                    ⏳
                                                <?php endif; ?>
                                            </div>
                                            <div class="period-info">
                                                <div class="period-text"><?= formatMonth($aidat['ay']) ?></div>
                                                <div class="period-meta">
                                                    <?php if ($aidat['ay'] === date('Y-m')): ?>
                                                        <span class="current-month">Bu Ay</span>
                                                    <?php endif; ?>
                                                    <?php if ($aidat['odendi'] && $aidat['odeme_tarihi']): ?>
                                                        <span class="payment-date">Ödendi: <?= formatDate($aidat['odeme_tarihi']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="aidat-amount">
                                            <div class="amount-value"><?= formatMoney($aidat['miktar']) ?></div>
                                            <div class="amount-status">
                                                <?php if ($aidat['odendi']): ?>
                                                    <span class="badge badge-success">✅ Ödendi</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">⏳ Bekliyor</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="aidat-actions">
                                            <?php if (!$aidat['odendi']): ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="payAidat(<?= $aidat['id'] ?>, '<?= formatMonth($aidat['ay']) ?>', <?= $aidat['miktar'] ?>)"
                                                        title="Ödeme Yap">
                                                    💳 Öde
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-ghost btn-sm" disabled>
                                                    ✅ Ödendi
                                                </button>
                                            <?php endif; ?>
                                        </div>
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

    <!-- Pay Aidat Modal -->
    <div class="modal" id="payAidatModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">💳 Aidat Ödemesi</h3>
                <button class="modal-close" onclick="closeModal('payAidatModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="payment-summary">
                    <div class="summary-row">
                        <span class="summary-label">📅 Ödeme Dönemi:</span>
                        <span class="summary-value" id="paymentPeriod"></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">💰 Tutar:</span>
                        <span class="summary-value amount" id="paymentAmount"></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">🏠 Daire:</span>
                        <span class="summary-value"><?= htmlspecialchars($sakin_info['daire_no']) ?></span>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <strong>ℹ️ Bilgi:</strong> Ödeme onayladığınızda aidat "ödendi" olarak işaretlenecektir. 
                    Bu işlem geri alınamaz.
                </div>
                
                <form method="POST" id="payAidatForm">
                    <input type="hidden" name="aidat_id" id="payAidatId">
                    
                    <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-ghost" onclick="closeModal('payAidatModal')">
                            İptal
                        </button>
                        <button type="submit" name="pay_aidat" class="btn btn-success">
                            💳 Ödemeyi Onayla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Global değişkenler
        let currentFilter = 'all';
        let currentSearchTerm = '';
        let aidatChart;

        // Chart verilerini hazırla
        const chartData = <?= json_encode($son_6_ay) ?>;
        
        // Chart'ı başlat
        function initChart() {
            const ctx = document.getElementById('aidatChart');
            if (!ctx) return;

            aidatChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartData.map(item => item.ay_text),
                    datasets: [
                        {
                            label: 'Ödenen',
                            data: chartData.map(item => item.odenen),
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Bekleyen',
                            data: chartData.map(item => item.toplam - item.odenen),
                            backgroundColor: 'rgba(239, 68, 68, 0.8)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₺' + value.toLocaleString('tr-TR');
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ₺' + context.raw.toLocaleString('tr-TR');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Stats kartına tıklayınca filtreleme
        function filterByStatCard(filterType, cardElement) {
            // Önceki aktif kartın stilini temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // Tıklanan kartı aktif yap
            cardElement.classList.add('active');
            
            // Filtreyi uygula
            currentFilter = filterType;
            applyFilters();
            
            // Dropdown'ı da güncelle
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                if (filterType === 'all') {
                    statusFilter.value = '';
                } else if (filterType === 'paid' || filterType === 'unpaid') {
                    statusFilter.value = filterType;
                } else {
                    statusFilter.value = '';
                }
            }
            
            // Filtre bilgisini göster
            showFilterInfo(filterType);
        }

        // Dropdown filtreleme
        function filterAidatlar() {
            const ayFilter = document.getElementById('ayFilter') ? document.getElementById('ayFilter').value : '';
            const statusFilter = document.getElementById('statusFilter').value;
            
            currentFilter = statusFilter || 'all';
            
            // Tüm stat kartlarının aktif durumunu temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // İlgili stat kartını aktif yap
            const targetCard = document.querySelector(`[data-filter="${currentFilter}"]`);
            if (targetCard) {
                targetCard.classList.add('active');
            }
            
            applyFilters();
            showFilterInfo(currentFilter);
        }

        // Tüm filtreleri uygula
        function applyFilters() {
            const ayFilter = document.getElementById('ayFilter') ? document.getElementById('ayFilter').value : '';
            const aidatItems = document.querySelectorAll('.aidat-item');
            
            aidatItems.forEach(item => {
                let showItem = true;
                
                // Ay filtresi
                if (ayFilter && item.dataset.ay !== ayFilter) {
                    showItem = false;
                }
                
                // Durum filtresi
                if (currentFilter !== 'all') {
                    if (currentFilter === 'paid' || currentFilter === 'unpaid') {
                        showItem = showItem && item.dataset.status === currentFilter;
                    } else if (currentFilter === 'this-month') {
                        showItem = showItem && item.dataset.month === currentFilter;
                    }
                }
                
                // Arama filtresi
                if (showItem && currentSearchTerm) {
                    const searchData = item.dataset.search;
                    showItem = searchData.includes(currentSearchTerm);
                }
                
                if (showItem) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Filtre bilgisini göster
        function showFilterInfo(filterType) {
            const filterInfo = document.getElementById('filterInfo');
            const filterText = document.getElementById('filterText');
            
            if (!filterInfo || !filterText) return;
            
            const filterTexts = {
                'all': 'Tüm Aidatlar',
                'paid': 'Ödenen Aidatlar',
                'unpaid': 'Bekleyen Aidatlar', 
                'this-month': 'Bu Ay Aidatları'
            };
            
            if (filterType !== 'all') {
                filterText.textContent = filterTexts[filterType];
                filterInfo.style.display = 'block';
            } else {
                filterInfo.style.display = 'none';
            }
        }

        // Tüm filtreleri temizle
        function clearAllFilters() {
            currentFilter = 'all';
            currentSearchTerm = '';
            
            // Arama kutusunu temizle
            const searchInput = document.getElementById('aidatSearch');
            if (searchInput) {
                searchInput.value = '';
            }
            
            // Dropdown'ları temizle
            if (document.getElementById('ayFilter')) {
                document.getElementById('ayFilter').value = '';
            }
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.value = '';
            }
            
            // Stat kartlarının aktif durumunu temizle
            document.querySelectorAll('.clickable-stat').forEach(card => {
                card.classList.remove('active');
            });
            
            // Toplam aidat kartını aktif yap
            const totalCard = document.querySelector('[data-filter="all"]');
            if (totalCard) {
                totalCard.classList.add('active');
            }
            
            // Filtreleri uygula
            applyFilters();
            
            // Filtre bilgisini gizle
            const filterInfo = document.getElementById('filterInfo');
            if (filterInfo) {
                filterInfo.style.display = 'none';
            }
        }

        // Aidat arama
        const searchInput = document.getElementById('aidatSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                currentSearchTerm = e.target.value.toLowerCase();
                applyFilters();
            });
        }
        
        // Aidat ödeme
        function payAidat(id, ay, miktar) {
            document.getElementById('payAidatId').value = id;
            document.getElementById('paymentPeriod').textContent = ay;
            document.getElementById('paymentAmount').textContent = '₺' + miktar.toLocaleString('tr-TR');
            openModal('payAidatModal');
        }
        
        // Modal açma fonksiyonu
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        // Modal kapatma fonksiyonu
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Dropdown toggle fonksiyonu
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.toggle('show');
                
                // Diğer dropdown'ları kapat
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => {
                    if (d.id !== dropdownId) {
                        d.classList.remove('show');
                    }
                });
            }
        }
        
        // Event listeners
        document.addEventListener('click', function(e) {
            // Modal dışına tıklandığında kapat
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
                e.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
            
            // Dropdown dışına tıklandığında kapat
            if (!e.target.closest('.dropdown')) {
                const allDropdowns = document.querySelectorAll('.dropdown-menu');
                allDropdowns.forEach(d => d.classList.remove('show'));
            }
        });

        // ESC tuşu ile modal kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // Sayfa yüklendiğinde 
        document.addEventListener('DOMContentLoaded', function() {
            // Chart'ı başlat
            initChart();
            
            // Toplam aidat kartını aktif yap
            const totalCard = document.querySelector('[data-filter="all"]');
            if (totalCard) {
                totalCard.classList.add('active');
            }
        });
    </script>
    
    <style>
        /* İçerik Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Chart Card */
        .chart-card {
            min-height: 350px;
        }

        .chart-container {
            height: 250px;
            margin-bottom: 16px;
        }

        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 24px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }

        /* Summary Card */
        .summary-card .card-body {
            padding: 16px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .summary-value {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }

        .summary-value small {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* Progress Bar */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Aidatlar Listesi */
        .aidatlar-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .aidat-item {
            background: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .aidat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.05), transparent);
            transition: left 0.5s ease;
        }

        .aidat-item:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }

        .aidat-item:hover::before {
            left: 100%;
        }

        .aidat-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .aidat-period {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }

        .period-icon {
            font-size: 24px;
            width: 48px;
            height: 48px;
            background: var(--background-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border-color);
        }

        .period-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .period-text {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .period-meta {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .current-month {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payment-date {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .aidat-amount {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }

        .amount-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .amount-status {
            display: flex;
            gap: 8px;
        }

        .aidat-actions {
            display: flex;
            gap: 8px;
        }

        /* Payment Summary Modal */
        .payment-summary {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .summary-row .summary-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .summary-row .summary-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .summary-row .summary-value.amount {
            font-size: 18px;
            color: var(--primary-color);
        }

        /* Badge Stilleri */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        /* Clickable Stats */
        .clickable-stat {
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .clickable-stat::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .clickable-stat:hover::before {
            left: 100%;
        }

        .clickable-stat:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .clickable-stat.active {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .clickable-stat.primary.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(59, 130, 246, 0.25));
            border-color: rgba(59, 130, 246, 0.3);
        }

        .clickable-stat.success.active {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.25));
            border-color: rgba(16, 185, 129, 0.3);
        }

        .clickable-stat.danger.active {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.25));
            border-color: rgba(239, 68, 68, 0.3);
        }

        .clickable-stat.info.active {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(6, 182, 212, 0.25));
            border-color: rgba(6, 182, 212, 0.3);
        }

        /* Stat Card Icon */
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-icon {
            font-size: 20px;
            opacity: 0.7;
        }

        /* Filtre Bilgi Badge */
        .filter-info {
            margin-bottom: 16px;
            animation: slideDown 0.3s ease;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .filter-badge button {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            opacity: 0.7;
        }

        .filter-badge button:hover {
            background: rgba(59, 130, 246, 0.1);
            opacity: 1;
        }

        /* Card Actions */
        .card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* Button Stilleri */
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
            border: none;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-ghost:hover {
            background: var(--background-color);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-ghost:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-ghost:disabled:hover {
            background: transparent;
            border-color: var(--border-color);
            color: var(--text-secondary);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            margin-bottom: 16px;
        }

        /* Modal stilleri */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 500px;
            }
        }

        .modal-header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            cursor: pointer;
            color: var(--text-secondary);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--background-color);
            color: var(--text-primary);
        }

        .modal-body {
            padding: 24px;
        }

        /* Alert stilleri */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fed7aa;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }

        /* Form stilleri */
        .form-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .aidat-main {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }

            .aidat-period {
                gap: 12px;
            }

            .period-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }

            .aidat-amount {
                align-items: center;
                text-align: center;
            }

            .amount-value {
                font-size: 20px;
            }

            .aidat-actions {
                justify-content: center;
            }

            .clickable-stat:hover {
                transform: none;
            }

            .clickable-stat.active {
                transform: none;
            }

            .chart-container {
                height: 200px;
            }

            .summary-value {
                max-width: 50%;
                font-size: 13px;
            }

            .topbar-actions {
                gap: 8px;
            }

            .search-input {
                width: 120px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-value {
                font-size: 18px;
            }

            .stat-change {
                font-size: 11px;
            }

            .aidat-item {
                padding: 16px;
            }

            .period-text {
                font-size: 14px;
            }

            .amount-value {
                font-size: 18px;
            }

            .modal-content {
                width: 95%;
                margin: 10px;
            }

            .modal-body {
                padding: 20px;
            }
        }
    </style>
</body>
</html>