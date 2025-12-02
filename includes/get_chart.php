<?php
// includes/get_chart.php - Yeniden Kullanılabilir Grafik Bileşeni

// Bu dosyanın doğrudan erişimini engelle
if (!defined('IN_PAGES')) {
    exit('Direct access not allowed');
}

/**
 * Modal içinde sakin grafiği render eder
 */
function renderSakinChartModal() {
    ?>
    <!-- Sakin Chart Modal -->
    <div class="modal" id="sakinChartModal">
        <div class="modal-content chart-modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="chartModalTitle">📊 Aidat Grafiği</h3>
                <button class="modal-close" onclick="closeModal('sakinChartModal')">×</button>
            </div>
            <div class="modal-body">                
                <div class="chart-container-modal">
                    <canvas id="sakinChart" width="400" height="300"></canvas>
                </div>
                
                <div class="chart-legend-modal">
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
    </div>

    <!-- Sakin Document Modal -->
    <div class="modal" id="sakinDocumentModal">
        <div class="modal-content document-modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="documentModalTitle">📄 Aidat Dokümanı</h3>
                <button class="modal-close" onclick="closeModal('sakinDocumentModal')">×</button>
            </div>
            <div class="modal-body">
                <div class="document-table-container">
                    <table class="document-table" id="documentTable">
                        <thead>
                            <tr>
                                <th>Dönem</th>
                                <th>Tutar</th>
                                <th>Durum</th>
                                <th>Ödeme Tarihi</th>
                            </tr>
                        </thead>
                        <tbody id="documentTableBody">
                            <!-- JavaScript ile doldurulacak -->
                        </tbody>
                    </table>
                </div>
                
                <div class="document-summary" id="documentSummary">
                    <!-- JavaScript ile doldurulacak -->
                </div>
                
                <div class="document-actions">
                    <button class="btn btn-ghost" onclick="closeModal('sakinDocumentModal')">
                        Kapat
                    </button>
                    <button class="btn btn-primary" onclick="printDocument()">
                        🖨️ Yazdır
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Chart Modal */
        .chart-modal-content {
            max-width: 800px;
            width: 95%;
        }

        .chart-container-modal {
            height: 400px;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid var(--border-color);
        }

        .chart-legend-modal {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 16px;
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

        /* Chart Button */
        .btn-chart {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 12px;
        }

        .btn-chart:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Document Button */
        .btn-document {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-document:hover {
            background: linear-gradient(135deg, #e879f9 0%, #ef4444 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.4);
        }

        /* Sakin Actions */
        .sakin-actions {
            display: flex;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            background: var(--hover-color);
        }

        /* Document Modal */
        .document-modal-content {
            max-width: 900px;
            width: 95%;
        }

        .document-table-container {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .document-table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .document-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .document-table tbody tr:hover {
            background: var(--hover-color);
        }

        .document-table tbody tr:last-child td {
            border-bottom: none;
        }

        .document-summary {
            background: var(--background-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .document-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .document-summary-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 16px;
            color: var(--primary-color);
        }

        .document-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chart-modal-content {
                width: 98%;
                max-width: none;
                height: 90vh;
                max-height: 90vh;
            }
            
            .chart-container-modal {
                height: 300px;
            }
            
            .chart-legend-modal {
                gap: 16px;
            }
            
            .btn-chart, .btn-document {
                font-size: 11px;
                padding: 6px 12px;
                margin-right: 8px;
            }

            .sakin-actions {
                padding: 12px 16px;
                gap: 8px;
            }

            .document-modal-content {
                width: 98%;
                max-width: none;
                height: 90vh;
                max-height: 90vh;
            }

            .document-table th,
            .document-table td {
                padding: 8px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .chart-legend-modal {
                gap: 16px;
            }
            
            .btn-chart, .btn-document {
                font-size: 10px;
                padding: 4px 8px;
            }

            .sakin-actions {
                flex-direction: column;
                gap: 8px;
            }

            .sakin-actions .btn-chart,
            .sakin-actions .btn-document {
                width: 100%;
                margin-right: 0;
            }

            .document-table {
                font-size: 11px;
            }

            .document-actions {
                flex-direction: column;
                gap: 8px;
            }

            .document-actions .btn {
                width: 100%;
            }
        }
    </style>
    <?php
}

/**
 * Sakin grafik fonksiyonlarını JavaScript olarak render eder
 */
function renderSakinChartJS() {
    ?>
    <script>
        let sakinChart;

        // Sakin grafiğini göster
        function showSakinChart(sakinId, sakinAdi, daireNo) {
            console.log('Grafik gösteriliyor:', sakinId, sakinAdi, daireNo);
            
            // Sakin aidatlarını bul
            const sakinGroup = document.querySelector(`#aidatlar-${sakinId}`);
            if (!sakinGroup) {
                console.error('Sakin grubu bulunamadı:', sakinId);
                return;
            }
            
            const aidatItems = sakinGroup.querySelectorAll('.aidat-item');
            const aidatlar = [];
            
            console.log('Bulunan aidat items:', aidatItems.length);
            
            // Aidat verilerini topla
            aidatItems.forEach(item => {
                const ay = item.dataset.ay;
                const status = item.dataset.status;
                const periodText = item.querySelector('.period-text')?.textContent || '';
                const amountText = item.querySelector('.amount-text')?.textContent || '0';
                
                // Türkçe para formatını parse et
                let miktar = 0;
                if (amountText) {
                    const cleanAmount = amountText.replace('₺', '').replace(/\./g, '').replace(',', '.');
                    miktar = parseFloat(cleanAmount) || 0;
                }
                
                aidatlar.push({
                    ay: ay,
                    ay_text: periodText,
                    miktar: miktar,
                    odendi: status === 'paid'
                });
            });

            // Son 6 ay verilerini hazırla (aidatlarim.php'deki gibi)
            const son6Ay = [];
            for (let i = 5; i >= 0; i--) {
                const targetDate = new Date();
                targetDate.setMonth(targetDate.getMonth() - i);
                const ayKey = targetDate.getFullYear() + '-' + String(targetDate.getMonth() + 1).padStart(2, '0');
                
                // Bu ay için aidatları filtrele
                const ayAidatlari = aidatlar.filter(a => a.ay === ayKey);
                const toplamMiktar = ayAidatlari.reduce((sum, a) => sum + a.miktar, 0);
                const odenenMiktar = ayAidatlari.filter(a => a.odendi).reduce((sum, a) => sum + a.miktar, 0);
                
                son6Ay.push({
                    ay: ayKey,
                    ay_text: targetDate.toLocaleDateString('tr-TR', { month: 'long' }),
                    toplam: toplamMiktar,
                    odenen: odenenMiktar,
                    adet: ayAidatlari.length
                });
            }

            console.log('Son 6 ay verisi:', son6Ay);
            
            // Modal başlığını güncelle
            const modalTitle = document.getElementById('chartModalTitle');
            if (modalTitle) {
                modalTitle.textContent = `📊 ${sakinAdi} - Daire ${daireNo}`;
            }
            
            // Grafiği çiz
            drawSakinChart(son6Ay);
            
            // Modal'ı aç
            if (typeof openModal === 'function') {
                openModal('sakinChartModal');
            }
        }

        // Grafik çizme fonksiyonu (aidatlarim.php'deki initChart fonksiyonuna benzer)
        function drawSakinChart(chartData) {
            const ctx = document.getElementById('sakinChart');
            if (!ctx) {
                console.error('Sakin chart canvas bulunamadı');
                return;
            }
            
            // Mevcut grafiği temizle
            if (sakinChart) {
                sakinChart.destroy();
            }
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js yüklenmemiş');
                return;
            }
            
            console.log('Chart çiziliyor, data:', chartData);
            
            sakinChart = new Chart(ctx, {
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
            
            console.log('Chart başarıyla oluşturuldu');
        }

        // Sakin dokümanını göster
        function showSakinDocument(sakinId, sakinAdi, daireNo) {
            console.log('Doküman gösteriliyor:', sakinId, sakinAdi, daireNo);
            
            // Sakin aidatlarını bul
            const sakinGroup = document.querySelector(`#aidatlar-${sakinId}`);
            if (!sakinGroup) {
                console.error('Sakin grubu bulunamadı:', sakinId);
                return;
            }
            
            const aidatItems = sakinGroup.querySelectorAll('.aidat-item');
            const aidatlar = [];
            
            // Aidat verilerini topla
            aidatItems.forEach(item => {
                const ay = item.dataset.ay;
                const status = item.dataset.status;
                const periodText = item.querySelector('.period-text')?.textContent || '';
                const amountText = item.querySelector('.amount-text')?.textContent || '0';
                
                // Ödeme tarihi bilgisini al
                const odemeInfo = item.querySelector('small');
                let odemeTarihi = '';
                if (odemeInfo && odemeInfo.textContent.includes('📅')) {
                    odemeTarihi = odemeInfo.textContent.replace('📅 ', '');
                }
                
                // Türkçe para formatını parse et
                let miktar = 0;
                if (amountText) {
                    const cleanAmount = amountText.replace('₺', '').replace(/\./g, '').replace(',', '.');
                    miktar = parseFloat(cleanAmount) || 0;
                }
                
                aidatlar.push({
                    ay: ay,
                    ay_text: periodText,
                    miktar: miktar,
                    odendi: status === 'paid',
                    odeme_tarihi: odemeTarihi
                });
            });

            // Modal başlığını güncelle
            const modalTitle = document.getElementById('documentModalTitle');
            if (modalTitle) {
                modalTitle.textContent = `📄 ${sakinAdi} - Daire ${daireNo}`;
            }
            
            // Tabloyu doldur
            fillDocumentTable(aidatlar);
            
            // Modal'ı aç
            if (typeof openModal === 'function') {
                openModal('sakinDocumentModal');
            }
        }

        // Doküman tablosunu doldur
        function fillDocumentTable(aidatlar) {
            const tableBody = document.getElementById('documentTableBody');
            const summaryDiv = document.getElementById('documentSummary');
            
            if (!tableBody || !summaryDiv) return;

            // Tabloyu temizle
            tableBody.innerHTML = '';
            
            // Aidatları tarihe göre sırala (en yeni önce)
            const sortedAidatlar = [...aidatlar].sort((a, b) => b.ay.localeCompare(a.ay));
            
            let toplamAidat = 0;
            let odenenAidat = 0;
            let bekleyenAidat = 0;

            // Tablo satırlarını oluştur
            sortedAidatlar.forEach(aidat => {
                toplamAidat += aidat.miktar;
                if (aidat.odendi) {
                    odenenAidat += aidat.miktar;
                } else {
                    bekleyenAidat += aidat.miktar;
                }

                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${aidat.ay_text}</strong></td>
                    <td><strong>₺${aidat.miktar.toLocaleString('tr-TR')}</strong></td>
                    <td>
                        ${aidat.odendi ? 
                            '<span style="color: var(--success-color); font-weight: 600;">Ödendi</span>' : 
                            '<span style="color: var(--danger-color); font-weight: 600;">Bekliyor</span>'
                        }
                    </td>
                    <td>${aidat.odeme_tarihi || '-'}</td>
                `;
                tableBody.appendChild(row);
            });

            // Özet bilgileri
            summaryDiv.innerHTML = `
                <div class="document-summary-row">
                    <span>Toplam Aidat:</span>
                    <span>₺${toplamAidat.toLocaleString('tr-TR')}</span>
                </div>
                <div class="document-summary-row">
                    <span>Ödenen:</span>
                    <span style="color: var(--success-color);">₺${odenenAidat.toLocaleString('tr-TR')}</span>
                </div>
                <div class="document-summary-row">
                    <span>Bekleyen:</span>
                    <span style="color: var(--danger-color);">₺${bekleyenAidat.toLocaleString('tr-TR')}</span>
                </div>
                <div class="document-summary-row">
                    <span>Ödeme Oranı:</span>
                    <span>%${toplamAidat > 0 ? ((odenenAidat / toplamAidat) * 100).toFixed(1) : '0'}</span>
                </div>
            `;
        }

        // Dokümanı yazdır
        function printDocument() {
            const modalContent = document.querySelector('#sakinDocumentModal .modal-body');
            if (!modalContent) return;

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Aidat Dokümanı</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .document-info { border: 2px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
                        .document-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                        .document-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .document-table th, .document-table td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                        .document-table th { background-color: #3b82f6; color: white; }
                        .document-summary { background: #f9fafb; padding: 16px; border-radius: 8px; }
                        .document-summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #ddd; }
                        .document-summary-row:last-child { border-bottom: none; font-weight: bold; }
                        @media print { .document-actions { display: none; } }
                    </style>
                </head>
                <body>
                    ${modalContent.innerHTML}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Modal kapatılınca grafik ve dokümanı temizle
        document.addEventListener('DOMContentLoaded', function() {
            // closeModal fonksiyonunu override et
            const originalCloseModal = window.closeModal;
            if (originalCloseModal) {
                window.closeModal = function(modalId) {
                    if (modalId === 'sakinChartModal' && sakinChart) {
                        sakinChart.destroy();
                        sakinChart = null;
                    }
                    if (modalId === 'sakinDocumentModal') {
                        // Doküman tablosunu temizle
                        const tableBody = document.getElementById('documentTableBody');
                        const summaryDiv = document.getElementById('documentSummary');
                        if (tableBody) tableBody.innerHTML = '';
                        if (summaryDiv) summaryDiv.innerHTML = '';
                    }
                    originalCloseModal(modalId);
                };
            }
        });
    </script>
    <?php
}
?>