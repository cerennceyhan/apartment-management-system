// assets/js/app.js - Modern Apartman Yönetim Sistemi


const CONFIG = {
    API_BASE: 'api.php',
    ANIMATION_DURATION: 300,
    NOTIFICATION_DURATION: 5000,
    SIDEBAR_BREAKPOINT: 768
};


const AppState = {
    sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
    currentPage: 'dashboard',
    user: null,
    notifications: [],
    modals: new Set(),
    dropdowns: new Set()
};


const Utils = {

    $: (selector) => document.querySelector(selector),
    $$: (selector) => document.querySelectorAll(selector),
    

    createElement: (tag, attributes = {}, content = '') => {
        const element = document.createElement(tag);
        Object.entries(attributes).forEach(([key, value]) => {
            if (key === 'className') {
                element.className = value;
            } else if (key === 'innerHTML') {
                element.innerHTML = value;
            } else {
                element.setAttribute(key, value);
            }
        });
        if (content) element.textContent = content;
        return element;
    },
    

    formatMoney: (amount) => {
        return new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY'
        }).format(amount);
    },
    
    formatDate: (dateString) => {
        return new Date(dateString).toLocaleDateString('tr-TR');
    },
    
    formatMonth: (monthString) => {
        const [year, month] = monthString.split('-');
        const monthNames = [
            'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
            'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
        ];
        return `${monthNames[parseInt(month) - 1]} ${year}`;
    },
    
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    throttle: (func, limit) => {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    },
    
    generateId: () => Date.now().toString(36) + Math.random().toString(36).substr(2),
    
    sanitize: (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

const API = {

    call: async (endpoint, method = 'GET', data = null, urlParams = null) => {
        try {
            const config = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            if (data) {
                config.body = JSON.stringify(data);
            }
            
            let url = `${CONFIG.API_BASE}?endpoint=${endpoint}`;
            if (urlParams) {
                Object.keys(urlParams).forEach(key => {
                    url += `&${key}=${encodeURIComponent(urlParams[key])}`;
                });
            }
            
            UI.showLoading(true);
            const response = await fetch(url, config);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || `HTTP ${response.status}`);
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        } finally {
            UI.showLoading(false);
        }
    },
    

    login: (username, password) => API.call('login', 'POST', { username, password }),
    logout: () => API.call('logout', 'POST'),
    getCurrentUser: () => API.call('current-user'),
    getDashboard: () => API.call('dashboard'),
    
    // Sakinler
    getSakinler: () => API.call('sakinler'),
    addSakin: (data) => API.call('add-sakin', 'POST', data),
    deleteSakin: (id) => API.call('delete-sakin', 'POST', { id }),
    
    // Aidatlar
    getAidatlar: () => API.call('aidatlar'),
    addAidat: (data) => API.call('add-aidat', 'POST', data),
    deleteAidat: (id) => API.call('delete-aidat', 'POST', { id }),
    payAidat: (aidat_id) => API.call('odeme-yap', 'POST', { aidat_id }),
    
    // Giderler
    getGiderler: () => API.call('giderler'),
    addGider: (data) => API.call('add-gider', 'POST', data),
    deleteGider: (id) => API.call('delete-gider', 'POST', { id }),
    
    // Yetkiler
    getYetkiGruplari: () => API.call('yetki-gruplari'),
    getYetkiler: () => API.call('yetkiler'),
    getGrupYetkiler: (grup_id) => API.call('grup-yetkiler', 'GET', null, { grup_id }),
    updateGrupYetkiler: (grup_id, yetkiler) => API.call('update-grup-yetkiler', 'POST', { grup_id, yetkiler })
};

// UI
const UI = {
    showLoading: (show = true) => {
        const loader = Utils.$('#globalLoader') || UI.createLoader();
        loader.style.display = show ? 'flex' : 'none';
    },
    
    createLoader: () => {
        const loader = Utils.createElement('div', {
            id: 'globalLoader',
            className: 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50',
            style: 'display: none;'
        });
        
        loader.innerHTML = `
            <div class="bg-white rounded-lg p-6 flex items-center gap-3">
                <div class="spinner"></div>
                <span>Yükleniyor...</span>
            </div>
        `;
        
        document.body.appendChild(loader);
        return loader;
    },
    
    showNotification: (message, type = 'info', duration = CONFIG.NOTIFICATION_DURATION) => {
        const notification = UI.createNotification(message, type);
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('show'), 10);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), CONFIG.ANIMATION_DURATION);
        }, duration);
        
        return notification;
    },
    
    createNotification: (message, type) => {
        const icons = {
            'success': '✓',
            'error': '✕',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        
        const colors = {
            'success': '#10b981',
            'error': '#ef4444',
            'warning': '#f59e0b',
            'info': '#3b82f6'
        };
        
        const notification = Utils.createElement('div', {
            className: 'fixed top-4 right-4 bg-white border rounded-lg shadow-lg p-4 max-w-sm z-50 transform translate-x-full transition-transform duration-300',
            style: `border-left: 4px solid ${colors[type]};`
        });
        
        notification.innerHTML = `
            <div class="flex items-start gap-3">
                <span style="color: ${colors[type]}; font-size: 18px;">${icons[type]}</span>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900">${Utils.sanitize(message)}</p>
                </div>
                <button class="text-gray-400 hover:text-gray-600" onclick="this.parentElement.parentElement.remove()">
                    ×
                </button>
            </div>
        `;
        
        return notification;
    },
    
    openModal: (modalId) => {
        const modal = Utils.$(`#${modalId}`);
        if (modal) {
            modal.classList.add('show');
            AppState.modals.add(modalId);
            document.body.style.overflow = 'hidden';
            
            // Focus first input
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    },
    
    closeModal: (modalId) => {
        const modal = Utils.$(`#${modalId}`);
        if (modal) {
            modal.classList.remove('show');
            AppState.modals.delete(modalId);
            
            if (AppState.modals.size === 0) {
                document.body.style.overflow = '';
            }
        }
    },
    
    closeAllModals: () => {
        AppState.modals.forEach(modalId => UI.closeModal(modalId));
    },
    
    toggleDropdown: (dropdownId) => {
        const dropdown = Utils.$(`#${dropdownId}`);
        if (!dropdown) return;
        
        const parent = dropdown.closest('.dropdown');
        const isOpen = parent.classList.contains('open');
        

        UI.closeAllDropdowns();
        

        if (!isOpen) {
            parent.classList.add('open');
            AppState.dropdowns.add(dropdownId);
        }
    },
    
    closeAllDropdowns: () => {
        Utils.$$('.dropdown.open').forEach(dropdown => {
            dropdown.classList.remove('open');
        });
        AppState.dropdowns.clear();
    },

    toggleSidebar: () => {
        const sidebar = Utils.$('#sidebar') || Utils.$('.sidebar');
        if (!sidebar) return;
        
        const toggleIcon = Utils.$('#toggle-icon');
        const isCollapsed = sidebar.classList.contains('collapsed');
        
        if (isCollapsed) {
            sidebar.classList.remove('collapsed');
            if (toggleIcon) toggleIcon.textContent = '‹';
            AppState.sidebarCollapsed = false;
        } else {
            sidebar.classList.add('collapsed');
            if (toggleIcon) toggleIcon.textContent = '›';
            AppState.sidebarCollapsed = true;
        }
        
        localStorage.setItem('sidebarCollapsed', AppState.sidebarCollapsed);
    },
    
 
    toggleMobileSidebar: () => {
        const sidebar = Utils.$('#sidebar') || Utils.$('.sidebar');
        if (sidebar) {
            sidebar.classList.toggle('mobile-open');
        }
    },
   
    clearForm: (formElement) => {
        if (typeof formElement === 'string') {
            formElement = Utils.$(formElement);
        }
        
        if (formElement) {
            formElement.reset();
            Utils.$$('.form-group', formElement).forEach(group => {
                group.classList.remove('error', 'success');
            });
        }
    },
    
    validateForm: (formElement, rules) => {
        if (typeof formElement === 'string') {
            formElement = Utils.$(formElement);
        }
        
        const errors = [];
        
        Object.entries(rules).forEach(([fieldName, rule]) => {
            const field = formElement.querySelector(`[name="${fieldName}"]`);
            const value = field ? field.value.trim() : '';
            
            if (rule.required && !value) {
                errors.push(`${rule.label || fieldName} gereklidir`);
                UI.setFieldError(field, 'Bu alan gereklidir');
            } else if (value && rule.pattern && !rule.pattern.test(value)) {
                errors.push(`${rule.label || fieldName} geçerli değil`);
                UI.setFieldError(field, rule.message || 'Geçersiz format');
            } else {
                UI.clearFieldError(field);
            }
        });
        
        return errors;
    },
    
    setFieldError: (field, message) => {
        if (!field) return;
        
        const group = field.closest('.form-group');
        if (group) {
            group.classList.add('error');
            group.classList.remove('success');
            
            let errorElement = group.querySelector('.field-error');
            if (!errorElement) {
                errorElement = Utils.createElement('div', { className: 'field-error text-red-500 text-xs mt-1' });
                group.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }
    },
    
    clearFieldError: (field) => {
        if (!field) return;
        
        const group = field.closest('.form-group');
        if (group) {
            group.classList.remove('error');
            const errorElement = group.querySelector('.field-error');
            if (errorElement) {
                errorElement.remove();
            }
        }
    },
    
    updateTable: (tableId, data, columns) => {
        const table = Utils.$(`#${tableId}`);
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        if (data.length === 0) {
            const row = Utils.createElement('tr');
            const cell = Utils.createElement('td', {
                colspan: columns.length,
                className: 'text-center py-8 text-gray-500'
            }, 'Veri bulunamadı');
            row.appendChild(cell);
            tbody.appendChild(row);
            return;
        }
        
        data.forEach(item => {
            const row = Utils.createElement('tr', { className: 'hover:bg-gray-50' });
            
            columns.forEach(column => {
                const cell = Utils.createElement('td', { className: 'px-3 py-2' });
                
                if (typeof column.render === 'function') {
                    cell.innerHTML = column.render(item[column.key], item);
                } else {
                    cell.textContent = item[column.key] || '-';
                }
                
                row.appendChild(cell);
            });
            
            tbody.appendChild(row);
        });
    }
};

const PageManager = {
    currentPage: 'dashboard',
    
    navigateTo: (page) => {
        Utils.$$('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = Utils.$(`.nav-link[data-page="${page}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
        
        const titles = {
            'dashboard': 'Dashboard',
            'sakinler': 'Sakinler',
            'aidatlar': 'Aidatlar',
            'giderler': 'Giderler',
            'raporlar': 'Raporlar',
            'yetkiler': 'Yetki Yönetimi',
            'ayarlar': 'Ayarlar'
        };
        
        const pageTitle = Utils.$('#pageTitle') || Utils.$('.page-title');
        if (pageTitle) {
            pageTitle.textContent = titles[page] || 'Dashboard';
        }
        
        PageManager.currentPage = page;
        PageManager.loadPage(page);
    },
    
    loadPage: async (page) => {
        const content = Utils.$('#content') || Utils.$('.dashboard-content');
        if (!content) return;
        
        try {
            UI.showLoading(true);
            
            switch (page) {
                case 'dashboard':
                    await PageManager.loadDashboard();
                    break;
                case 'sakinler':
                    await PageManager.loadSakinler();
                    break;
                case 'aidatlar':
                    await PageManager.loadAidatlar();
                    break;
                case 'giderler':
                    await PageManager.loadGiderler();
                    break;
                case 'yetkiler':
                    await PageManager.loadYetkiler();
                    break;
                default:
                    content.innerHTML = `<div class="text-center py-8">Sayfa bulunamadı: ${page}</div>`;
            }
        } catch (error) {
            console.error('Page load error:', error);
            UI.showNotification('Sayfa yüklenirken hata oluştu', 'error');
            content.innerHTML = `<div class="text-center py-8 text-red-500">Sayfa yüklenirken hata oluştu</div>`;
        }
    },
    
    loadDashboard: async () => {
        const data = await API.getDashboard();
        const content = Utils.$('#content') || Utils.$('.dashboard-content');
        
        console.log('Dashboard data:', data);
    },
    
    loadSakinler: async () => {
        const data = await API.getSakinler();
        console.log('Sakinler data:', data);
    },
    
    loadAidatlar: async () => {
        const data = await API.getAidatlar();
        console.log('Aidatlar data:', data);
    },
    
    loadGiderler: async () => {
        const data = await API.getGiderler();
        console.log('Giderler data:', data);
    },
    
    loadYetkiler: async () => {
        const data = await API.getYetkiGruplari();
        console.log('Yetkiler data:', data);
    }
};

const FormManager = {
    init: () => {
        FormManager.setupFormatters();
        
        Utils.$$('form').forEach(form => {
            form.addEventListener('submit', FormManager.handleSubmit);
        });
    },
    
    setupFormatters: () => {
        Utils.$$('input[name="card_number"]').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
                let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = formattedValue;
            });
        });
        
        Utils.$$('input[name="card_expiry"]').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^0-9]/g, '');
                if (value.length >= 2) {
                    e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
                } else {
                    e.target.value = value;
                }
            });
        });
        
        Utils.$$('input[name="card_cvv"]').forEach(input => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        });
        
        Utils.$$('input[type="tel"]').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/[^0-9]/g, '');
                if (value.length > 0) {
                    value = value.replace(/(\d{4})(\d{3})(\d{2})(\d{2})/, '$1-$2-$3-$4');
                }
                e.target.value = value;
            });
        });
    },
    
    handleSubmit: async (e) => {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const action = form.dataset.action;
        
        if (!action) {
            console.warn('Form action not specified');
            return;
        }
        
        try {
            let result;
            
            switch (action) {
                case 'add-sakin':
                    result = await API.addSakin(data);
                    break;
                case 'add-aidat':
                    result = await API.addAidat(data);
                    break;
                case 'add-gider':
                    result = await API.addGider(data);
                    break;
                case 'pay-aidat':
                    result = await API.payAidat(data.aidat_id);
                    break;
                case 'login':
                    result = await API.login(data.username, data.password);
                    if (result.success) {
                        window.location.href = 'index.php';
                        return;
                    }
                    break;
                default:
                    console.warn('Unknown form action:', action);
                    return;
            }
            
            if (result && result.success) {
                UI.showNotification(result.message || 'İşlem başarıyla tamamlandı', 'success');
                
                const modal = form.closest('.modal');
                if (modal) {
                    UI.closeModal(modal.id);
                }
                
                UI.clearForm(form);
                
                PageManager.loadPage(PageManager.currentPage);
            } else {
                UI.showNotification(result.error || 'İşlem başarısız', 'error');
            }
            
        } catch (error) {
            console.error('Form submission error:', error);
            UI.showNotification(error.message || 'İşlem sırasında hata oluştu', 'error');
        }
    }
};

const EventHandlers = {
    init: () => {
        EventHandlers.setupGlobalEvents();
        EventHandlers.setupKeyboardShortcuts();
        EventHandlers.setupTooltips();
    },
    
    setupGlobalEvents: () => {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                UI.closeAllDropdowns();
            }
        });
        
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                UI.closeModal(e.target.id);
            }
        });
        
        window.addEventListener('resize', Utils.throttle(() => {
            if (window.innerWidth <= CONFIG.SIDEBAR_BREAKPOINT) {
                const sidebar = Utils.$('.sidebar');
                if (sidebar) {
                    sidebar.classList.remove('collapsed');
                }
            }
        }, 250));
        
        Utils.$('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = link.dataset.page || link.getAttribute('href').replace('#', '');
                if (page) {
                    PageManager.navigateTo(page);
                }
            });
        });
        

        const searchInput = Utils.$('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', Utils.debounce((e) => {
                EventHandlers.handleSearch(e.target.value);
            }, 300));
        }
        
        Utils.$('[data-confirm]').forEach(element => {
            element.addEventListener('click', (e) => {
                const message = element.dataset.confirm;
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });
    },
    
    setupKeyboardShortcuts: () => {
        document.addEventListener('keydown', (e) => {

            if (e.key === 'Escape') {
                UI.closeAllModals();
                UI.closeAllDropdowns();
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = Utils.$('.search-input');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                UI.toggleSidebar();
            }
        });
    },
    
    setupTooltips: () => {
        Utils.$('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', EventHandlers.showTooltip);
            element.addEventListener('mouseleave', EventHandlers.hideTooltip);
        });
    },
    
    showTooltip: (e) => {
        const element = e.target;
        const text = element.dataset.tooltip;
        
        if (!text) return;
        
        const tooltip = Utils.createElement('div', {
            className: 'tooltip',
            innerHTML: Utils.sanitize(text)
        });
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        setTimeout(() => tooltip.classList.add('show'), 10);
        
        element._tooltip = tooltip;
    },
    
    hideTooltip: (e) => {
        const element = e.target;
        if (element._tooltip) {
            element._tooltip.remove();
            delete element._tooltip;
        }
    },
    
    handleSearch: (query) => {
        if (!query.trim()) return;
        
        console.log('Searching for:', query);
        
    }
};


const DataManager = {
    cache: new Map(),
    

    get: async (key, fetcher, ttl = 300000) => { 
        const cached = DataManager.cache.get(key);
        
        if (cached && Date.now() - cached.timestamp < ttl) {
            return cached.data;
        }
        
        const data = await fetcher();
        DataManager.cache.set(key, {
            data,
            timestamp: Date.now()
        });
        
        return data;
    },
    

    clear: (key) => {
        if (key) {
            DataManager.cache.delete(key);
        } else {
            DataManager.cache.clear();
        }
    },
    

    startAutoRefresh: (interval = 60000) => { 
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                DataManager.clear();
                PageManager.loadPage(PageManager.currentPage);
            }
        }, interval);
    }
};


const ThemeManager = {
    init: () => {
        const savedTheme = localStorage.getItem('theme') || 'light';
        ThemeManager.setTheme(savedTheme);
    },
    
    setTheme: (theme) => {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        
        // Update theme toggle button if exists
        const themeToggle = Utils.$('.theme-toggle');
        if (themeToggle) {
            themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    },
    
    toggleTheme: () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        ThemeManager.setTheme(newTheme);
    }
};


const ErrorHandler = {
    init: () => {
        window.addEventListener('error', (e) => {
            console.error('Global error:', e.error);
            ErrorHandler.handleError(e.error);
        });
        
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
            ErrorHandler.handleError(e.reason);
        });
    },
    
    handleError: (error) => {
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            return;
        }
        
        UI.showNotification('Bir hata oluştu. Lütfen sayfayı yenileyin.', 'error');
    }
};


const PerformanceMonitor = {
    init: () => {
     
        window.addEventListener('load', () => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('Page load time:', perfData.loadEventEnd - perfData.loadEventStart + 'ms');
        });
        
     
        const originalFetch = window.fetch;
        window.fetch = async (...args) => {
            const start = performance.now();
            const result = await originalFetch(...args);
            const end = performance.now();
            console.log(`API call to ${args[0]} took ${end - start}ms`);
            return result;
        };
    }
};


const App = {
    init: async () => {
        try {
            console.log('🚀 Initializing Apartman Pro...');
            
     
            ErrorHandler.init();
            PerformanceMonitor.init();
            ThemeManager.init();
            EventHandlers.init();
            FormManager.init();
            
         
            const sidebar = Utils.$('#sidebar') || Utils.$('.sidebar');
            if (sidebar && AppState.sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                const toggleIcon = Utils.$('#toggle-icon');
                if (toggleIcon) toggleIcon.textContent = '›';
            }
            
 
            try {
                const user = await API.getCurrentUser();
                AppState.user = user.user;
                console.log('Current user:', AppState.user);
            } catch (error) {
                console.log('No authenticated user');
            }
            
   
            const initialPage = App.getCurrentPageFromURL();
            PageManager.navigateTo(initialPage);
            
       
            DataManager.startAutoRefresh();
            

            setTimeout(() => {
                if (AppState.user) {
                    UI.showNotification(`Hoş geldiniz, ${AppState.user.ad_soyad || AppState.user.username}!`, 'success');
                }
            }, 1000);
            
            console.log('✅ Apartman Pro initialized successfully');
            
        } catch (error) {
            console.error('❌ Initialization error:', error);
            UI.showNotification('Sistem başlatılırken hata oluştu', 'error');
        }
    },
    
    getCurrentPageFromURL: () => {
        const path = window.location.pathname;
        const filename = path.split('/').pop().replace('.php', '');
        
        const pageMap = {
            'index': 'dashboard',
            'sakinler': 'sakinler',
            'aidatlar': 'aidatlar',
            'giderler': 'giderler',
            'yetkiler': 'yetkiler',
            'raporlar': 'raporlar',
            'ayarlar': 'ayarlar'
        };
        
        return pageMap[filename] || 'dashboard';
    }
};


window.toggleSidebar = UI.toggleSidebar;
window.openModal = UI.openModal;
window.closeModal = UI.closeModal;
window.toggleDropdown = UI.toggleDropdown;
window.showNotification = UI.showNotification;
window.showPage = PageManager.navigateTo;
window.formatMoney = Utils.formatMoney;
window.formatDate = Utils.formatDate;
window.formatMonth = Utils.formatMonth;

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});


document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        // Page became visible, refresh data if needed
        const lastRefresh = localStorage.getItem('lastRefresh');
        const now = Date.now();
        
        if (!lastRefresh || now - parseInt(lastRefresh) > 300000) { // 5 minutes
            DataManager.clear();
            PageManager.loadPage(PageManager.currentPage);
            localStorage.setItem('lastRefresh', now.toString());
        }
    }
});


if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        App,
        API,
        UI,
        Utils,
        PageManager,
        FormManager,
        EventHandlers,
        DataManager,
        ThemeManager
    };
}