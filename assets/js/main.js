/**
 * IoT Pesantren - Main JavaScript
 * Fungsi utama untuk dashboard monitoring dan admin panel
 */

// ===== Base URL =====
const BASE_URL = window.location.origin + '/SISTEM-IOT-PESANTREN';

// ===== Auto Refresh =====
let refreshInterval = null;
const REFRESH_RATE = 30000; // 30 detik

/**
 * Mulai auto-refresh dashboard
 * @param {string} type - Tipe dashboard (solar, dryer, cattle, permaculture)
 */
function startAutoRefresh(type) {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(() => {
        updateDashboard(type);
    }, REFRESH_RATE);
}

/**
 * Ambil dan perbarui data dashboard
 * @param {string} type - Tipe data
 */
function updateDashboard(type) {
    fetch(`${BASE_URL}/api/latest_data.php?type=${type}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                updateCards(type, data.data);
                updateLastUpdated();
            }
        })
        .catch(err => console.error('Refresh error:', err));
}

/**
 * Update card values berdasarkan data terbaru
 */
function updateCards(type, data) {
    if (!data) return;
    
    if (type === 'solar' && data.solar) {
        const d = data.solar;
        updateCardValue('solar-temp', d.temperature, '°C');
        updateCardValue('solar-hum', d.humidity, '%');
        updateCardValue('solar-volt', d.voltage, 'V');
        updateCardValue('solar-curr', d.current_amp, 'A');
        updateCardValue('solar-power', d.power, 'W');
        updateCardValue('solar-energy', d.energy, 'Wh');
    }
    
    if (type === 'dryer' && data.dryer) {
        const d = data.dryer;
        updateCardValue('dryer-temp', d.temperature, '°C');
        updateCardValue('dryer-hum', d.humidity, '%');
        updateCardValue('dryer-volt', d.voltage_ac, 'V');
        updateCardValue('dryer-curr', d.current_ac, 'A');
        updateCardValue('dryer-power', d.power_ac, 'W');
        updateCardValue('dryer-energy', d.energy_ac, 'Wh');
        updateCardValue('dryer-freq', d.frequency, 'Hz');
        updateCardValue('dryer-pf', d.power_factor, '');
    }
    
    if (type === 'cattle' && data.cattle) {
        const d = data.cattle;
        updateCardValue('cattle-level', d.liquid_level, 'cm');
        updateCardValue('cattle-volume', d.liquid_volume, 'L');
        updateCardValue('cattle-pressure', d.gas_pressure, 'kPa');
        updateCardValue('cattle-moisture-raw', d.soil_moisture_raw, '');
        updateCardValue('cattle-moisture', d.soil_moisture_percent, '%');
    }
    
    if (type === 'permaculture' && data.permaculture) {
        const d = data.permaculture;
        updateCardValue('perm-ph', d.soil_ph, 'pH');
        updatePhMarker(d.soil_ph);
    }
}

/**
 * Update satu card value
 */
function updateCardValue(elementId, value, unit) {
    const el = document.getElementById(elementId);
    if (!el) return;
    
    const valEl = el.querySelector('.card-value') || el;
    if (value !== null && value !== undefined) {
        valEl.textContent = formatNumber(parseFloat(value)) + (unit ? ' ' + unit : '');
    } else {
        valEl.textContent = '-';
    }
}

/**
 * Update status indicator
 */
function updateStatusIndicator(elementId, status) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.className = 'status-indicator status-' + status;
}

/**
 * Update last updated timestamp
 */
function updateLastUpdated() {
    const els = document.querySelectorAll('.last-updated');
    const now = new Date().toLocaleString('id-ID');
    els.forEach(el => {
        el.textContent = 'Diperbarui: ' + now;
    });
}

/**
 * Update pH marker position
 */
function updatePhMarker(ph) {
    const marker = document.querySelector('.ph-marker');
    if (marker && ph !== null) {
        const pct = (ph / 14) * 100;
        marker.style.left = pct + '%';
    }
}

// ===== Format Helpers =====
function formatNumber(num, decimals = 2) {
    if (isNaN(num) || num === null) return '-';
    return parseFloat(num).toFixed(decimals);
}

function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'Baru saja';
    if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
    if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
    return Math.floor(diff / 86400) + ' hari lalu';
}

function formatRupiah(num) {
    if (isNaN(num)) return 'Rp 0';
    return 'Rp ' + parseInt(num).toLocaleString('id-ID');
}

// ===== Toast Notification =====
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== Confirm Delete =====
function confirmDelete(message) {
    return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
}

// ===== Sidebar Toggle (Mobile) =====
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// ===== Sidebar Collapse (Desktop) =====
function collapseSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const content = document.querySelector('.admin-content');
    if (sidebar && content) {
        sidebar.classList.toggle('collapsed');
        content.classList.toggle('fullwidth');
        
        // Save state to localStorage
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

// ===== DOMContentLoaded =====
document.addEventListener('DOMContentLoaded', function() {
    // Restore sidebar state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 768) {
        const sidebar = document.getElementById('adminSidebar');
        const content = document.querySelector('.admin-content');
        if (sidebar && content) {
            sidebar.classList.add('collapsed');
            content.classList.add('fullwidth');
        }
    }
    // Auto-refresh for dashboard pages
    const dashboardEl = document.querySelector('[data-refresh-type]');
    if (dashboardEl) {
        const type = dashboardEl.getAttribute('data-refresh-type');
        startAutoRefresh(type);
    }
    
    // Mobile navbar toggle
    const navToggle = document.querySelector('.navbar-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', toggleNavbar);
    }
    
    // Sidebar toggle button
    const sideToggle = document.querySelector('.sidebar-toggle-btn');
    if (sideToggle) {
        sideToggle.addEventListener('click', toggleSidebar);
    }
    
    // Auto-calculate profit
    const capitalInput = document.getElementById('capital');
    const revenueInput = document.getElementById('revenue');
    if (capitalInput) capitalInput.addEventListener('input', calculateProfit);
    if (revenueInput) revenueInput.addEventListener('input', calculateProfit);
    
    // Table search
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-search-table');
        input.addEventListener('input', () => searchTable(input.id, tableId));
    });
    
    // Close alert auto
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});
