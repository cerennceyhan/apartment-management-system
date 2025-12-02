// assets/js/sidebar.js - Site/Blok destekli Modern Sidebar JavaScript

// Sidebar toggle fonksiyonu
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    
    if (window.innerWidth <= 768) {
        // Mobile: Sidebar'ı aç/kapat
        sidebar.classList.toggle('mobile-open');
        
        // Mobile overlay oluştur/kaldır
        let overlay = document.getElementById('mobileOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'mobileOverlay';
            overlay.className = 'mobile-overlay';
            overlay.onclick = closeSidebar;
            document.body.appendChild(overlay);
        }
        
        if (sidebar.classList.contains('mobile-open')) {
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden'; // Scroll'u engelle
        } else {
            overlay.classList.remove('active');
            document.body.style.overflow = ''; // Scroll'u geri aç
        }
    } else {
        // Desktop: Sidebar'ı daralt/genişlet
        sidebar.classList.toggle('collapsed');
        
        // Local storage'a kaydet
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
        
        // Toggle icon'u güncelle
        const toggleIcon = document.getElementById('toggle-icon');
        if (toggleIcon) {
            toggleIcon.textContent = isCollapsed ? '›' : '‹';
        }
    }
}

// Sidebar'ı kapat (mobile için)
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (sidebar) {
        sidebar.classList.remove('mobile-open');
    }
    
    if (overlay) {
        overlay.classList.remove('active');
    }
    
    document.body.style.overflow = ''; // Scroll'u geri aç
}

// Site değiştirme fonksiyonu
function changeSite(siteId) {
    if (!siteId) return;
    
    // Basit URL redirect ile site değiştir
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('site_id', siteId);
    
    // Blok seçimini temizle
    currentUrl.searchParams.delete('blok_id');
    
    // Sayfayı yenile
    window.location.href = currentUrl.toString();
}

// Blok değiştirme fonksiyonu
function changeBlok(blokId) {
    const currentUrl = new URL(window.location);
    
    if (blokId) {
        currentUrl.searchParams.set('blok_id', blokId);
    } else {
        currentUrl.searchParams.delete('blok_id');
    }
    
    // Sayfayı yenile
    window.location.href = currentUrl.toString();
}

// Site/Blok seçimini session'a kaydet
function saveSiteBlokSelection(siteId, blokId) {
    const data = {
        site_id: parseInt(siteId) || null,
        blok_id: parseInt(blokId) || null
    };
    
    // Includes klasörünün doğru yolunu belirle
    const isInPages = window.location.pathname.includes('/pages/');
    const apiPath = isInPages ? '../includes/set_selection.php' : 'includes/set_selection.php';
    
    fetch(apiPath, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.warn('Site/Blok seçimi kaydedilemedi:', data.error);
        }
    })
    .catch(error => {
        console.warn('Site/Blok seçim kaydetme hatası:', error);
    });
}

// URL'den site/blok parametrelerini oku ve session'a kaydet
function handleUrlParameters() {
    const urlParams = new URLSearchParams(window.location.search);
    const siteId = urlParams.get('site_id');
    const blokId = urlParams.get('blok_id');
    
    if (siteId) {
        saveSiteBlokSelection(siteId, blokId);
    }
}

// Sayfa yüklendiğinde sidebar durumunu geri yükle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    
    if (!sidebar) return; // Sidebar yoksa çık
    
    // Desktop'ta collapsed durumunu geri yükle
    if (window.innerWidth > 768) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            
            // Toggle icon'u güncelle
            const toggleIcon = document.getElementById('toggle-icon');
            if (toggleIcon) {
                toggleIcon.textContent = '›';
            }
        }
    }
    
    // URL parametrelerini işle
    handleUrlParameters();
    
    // Aktif link'i işaretle (ekstra güvenlik için)
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href.replace('../', '').replace('./', ''))) {
            // Önce tüm active sınıflarını kaldır
            navLinks.forEach(l => l.classList.remove('active'));
            // Sonra bu link'e active ekle
            link.classList.add('active');
        }
    });
    
    // Site/Blok seçim dropdown'larını dinle
    const siteSelector = document.getElementById('siteSelector');
    const blokSelector = document.getElementById('blokSelector');
    
    if (siteSelector) {
        siteSelector.addEventListener('change', function() {
            changeSite(this.value);
        });
    }
    
    if (blokSelector) {
        blokSelector.addEventListener('change', function() {
            changeBlok(this.value);
        });
    }
});

// Resize olayını dinle
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    if (!sidebar) return; // Sidebar yoksa çık
    
    if (window.innerWidth > 768) {
        // Desktop'a geçildiğinde mobile durumları temizle
        sidebar.classList.remove('mobile-open');
        document.body.style.overflow = ''; // Scroll'u geri aç
        
        if (overlay) {
            overlay.classList.remove('active');
        }
        
        // Collapsed durumunu geri yükle
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        } else {
            sidebar.classList.remove('collapsed');
        }
        
        // Toggle icon'u güncelle
        const toggleIcon = document.getElementById('toggle-icon');
        if (toggleIcon) {
            toggleIcon.textContent = isCollapsed ? '›' : '‹';
        }
    } else {
        // Mobile'a geçildiğinde collapsed durumunu temizle
        sidebar.classList.remove('collapsed');
        
        // Toggle icon'u resetle
        const toggleIcon = document.getElementById('toggle-icon');
        if (toggleIcon) {
            toggleIcon.textContent = '‹';
        }
    }
});

// Dropdown toggle fonksiyonu (topbar için)
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

// Dışarı tıklandığında dropdown'ları ve mobile sidebar'ı kapat
document.addEventListener('click', function(e) {
    // Dropdown dışına tıklandığında kapat
    if (!e.target.closest('.dropdown')) {
        const allDropdowns = document.querySelectorAll('.dropdown-menu');
        allDropdowns.forEach(d => d.classList.remove('show'));
    }
    
    // Mobile sidebar dışına tıklandığında kapat
    if (window.innerWidth <= 768) {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        
        if (sidebar && 
            sidebar.classList.contains('mobile-open') && 
            !sidebar.contains(e.target) && 
            !sidebarToggle.contains(e.target)) {
            closeSidebar();
        }
    }
});

// ESC tuşu ile sidebar ve dropdown'ları kapat
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Dropdown'ları kapat
        const allDropdowns = document.querySelectorAll('.dropdown-menu.show');
        allDropdowns.forEach(d => d.classList.remove('show'));
        
        // Mobile sidebar'ı kapat
        if (window.innerWidth <= 768) {
            closeSidebar();
        }
    }
});

// Smooth scroll için
document.addEventListener('DOMContentLoaded', function() {
    // Tüm internal linklere smooth scroll ekle
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Site/Blok seçim durumu kontrolü
function checkSiteBlokSelection() {
    const currentUrl = new URL(window.location);
    const siteId = currentUrl.searchParams.get('site_id');
    const blokId = currentUrl.searchParams.get('blok_id');
    
    // Dropdown'ları güncelle
    const siteSelector = document.getElementById('siteSelector');
    const blokSelector = document.getElementById('blokSelector');
    
    if (siteSelector && siteId) {
        siteSelector.value = siteId;
    }
    
    if (blokSelector && blokId) {
        blokSelector.value = blokId;
    }
}

// Sayfa navigation'ı
function navigateToPage(page) {
    const currentUrl = new URL(window.location);
    const siteId = currentUrl.searchParams.get('site_id');
    const blokId = currentUrl.searchParams.get('blok_id');
    
    // Yeni URL oluştur
    const isInPages = window.location.pathname.includes('/pages/');
    let newUrl;
    
    if (isInPages) {
        newUrl = `${page}.php`;
    } else {
        newUrl = `pages/${page}.php`;
    }
    
    // Site/Blok parametrelerini koru
    const newUrlObj = new URL(newUrl, window.location.origin);
    if (siteId) newUrlObj.searchParams.set('site_id', siteId);
    if (blokId) newUrlObj.searchParams.set('blok_id', blokId);
    
    window.location.href = newUrlObj.toString();
}

// Navigation helper'ları
window.navigateToPage = navigateToPage;
window.changeSite = changeSite;
window.changeBlok = changeBlok;
window.toggleSidebar = toggleSidebar;
window.toggleDropdown = toggleDropdown;
window.closeSidebar = closeSidebar;

// Debug fonksiyonu
function debugSidebarState() {
    const sidebar = document.getElementById('sidebar');
    console.log('Sidebar Debug Info:', {
        exists: !!sidebar,
        collapsed: sidebar?.classList.contains('collapsed'),
        mobileOpen: sidebar?.classList.contains('mobile-open'),
        windowWidth: window.innerWidth,
        localStorageCollapsed: localStorage.getItem('sidebarCollapsed'),
        currentUrl: window.location.href,
        urlParams: Object.fromEntries(new URLSearchParams(window.location.search))
    });
}

// Development modunda debug bilgilerini göster
if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
    window.debugSidebar = debugSidebarState;
    console.log('Sidebar.js loaded. Use debugSidebar() for debug info.');
}

