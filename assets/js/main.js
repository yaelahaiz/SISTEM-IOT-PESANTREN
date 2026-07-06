/**
 * IoT Pesantren - Main JavaScript
 * Fungsi utama untuk dashboard monitoring dan admin panel
 */

// ===== Base URL =====
const BASE_URL = window.location.origin + '/SISTEM IOT PESANTREN';

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
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// ===== Navbar Toggle (Mobile) =====
function toggleNavbar() {
    const nav = document.querySelector('.navbar-nav');
    if (nav) {
        nav.classList.toggle('show');
    }
}

// ===== Modal Functions =====
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// ===== Relay Toggle =====
function toggleRelay(relayId, newStatus) {
    fetch(`${BASE_URL}/api/update_relay_status.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `relay_id=${relayId}&status=${newStatus}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast(data.message, 'success');
            // Update UI
            const card = document.querySelector(`[data-relay-id="${relayId}"]`);
            if (card) {
                if (newStatus == 1) {
                    card.classList.add('relay-on');
                    card.querySelector('.relay-status').textContent = 'ON';
                    card.querySelector('.relay-icon').textContent = '💡';
                } else {
                    card.classList.remove('relay-on');
                    card.querySelector('.relay-status').textContent = 'OFF';
                    card.querySelector('.relay-icon').textContent = '🔌';
                }
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(err => {
        showToast('Gagal mengubah relay', 'error');
        console.error(err);
    });
}

// ===== Chart Helpers =====
const chartColors = {
    primary: 'rgba(37, 99, 235, 1)',
    primaryBg: 'rgba(37, 99, 235, 0.1)',
    secondary: 'rgba(5, 150, 105, 1)',
    secondaryBg: 'rgba(5, 150, 105, 0.1)',
    warning: 'rgba(217, 119, 6, 1)',
    warningBg: 'rgba(217, 119, 6, 0.1)',
    danger: 'rgba(220, 38, 38, 1)',
    dangerBg: 'rgba(220, 38, 38, 0.1)',
    info: 'rgba(8, 145, 178, 1)',
    infoBg: 'rgba(8, 145, 178, 0.1)'
};

/**
 * Buat line chart
 */
function createLineChart(canvasId, labels, datasets, title = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: !!title, text: title },
                legend: { position: 'top' }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { maxTicksLimit: 12 }
                },
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: { radius: 2, hoverRadius: 5 },
                line: { tension: 0.3 }
            }
        }
    });
}

/**
 * Buat bar chart
 */
function createBarChart(canvasId, labels, data, title = '', color = null) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const bgColor = color || chartColors.primary;
    
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: title,
                data,
                backgroundColor: data.map(v => v >= 0 ? chartColors.secondaryBg : chartColors.dangerBg),
                borderColor: data.map(v => v >= 0 ? chartColors.secondary : chartColors.danger),
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: !!title, text: title },
                legend: { display: false }
            },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: 'rgba(0,0,0,0.05)' } }
            }
        }
    });
}

/**
 * Buat doughnut chart
 */
function createDoughnutChart(canvasId, labels, data, title = '') {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const colors = [
        '#2563eb', '#059669', '#d97706', '#dc2626', '#0891b2',
        '#7c3aed', '#db2777', '#65a30d', '#ea580c', '#0284c7'
    ];
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: { display: !!title, text: title },
                legend: { position: 'bottom' }
            }
        }
    });
}

// ===== Sales Chart Period Selector =====
function loadSalesChart(period) {
    // Update active button
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    fetch(`${BASE_URL}/admin/sales_chart.php?ajax=1&period=${period}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                updateSalesCharts(data);
            }
        })
        .catch(err => console.error('Chart error:', err));
}

function updateSalesCharts(data) {
    // Destroy existing charts
    if (window.profitChart) window.profitChart.destroy();
    if (window.trendChart) window.trendChart.destroy();
    if (window.productChart) window.productChart.destroy();
    
    // Profit bar chart
    window.profitChart = createBarChart('profitChart', data.labels, data.profits, 'Keuntungan');
    
    // Trend line chart
    window.trendChart = createLineChart('trendChart', data.labels, [
        {
            label: 'Modal',
            data: data.capitals,
            borderColor: chartColors.danger,
            backgroundColor: chartColors.dangerBg,
            fill: true
        },
        {
            label: 'Pendapatan',
            data: data.revenues,
            borderColor: chartColors.secondary,
            backgroundColor: chartColors.secondaryBg,
            fill: true
        },
        {
            label: 'Keuntungan',
            data: data.profits,
            borderColor: chartColors.primary,
            backgroundColor: chartColors.primaryBg,
            fill: true
        }
    ], 'Trend Penjualan');
    
    // Product doughnut
    if (data.products && data.product_totals) {
        window.productChart = createDoughnutChart('productChart', data.products, data.product_totals, 'Per Produk');
    }
}

// ===== Table Search =====
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;
    
    const filter = input.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// ===== Export to CSV =====
function exportTableCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = '\ufeff'; // BOM
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];
        cols.forEach(col => {
            let text = col.textContent.trim().replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        csv += rowData.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename || 'export.csv';
    link.click();
}

// ===== Auto Calculate Profit =====
function calculateProfit() {
    const capital = parseFloat(document.getElementById('capital')?.value) || 0;
    const revenue = parseFloat(document.getElementById('revenue')?.value) || 0;
    const profitEl = document.getElementById('profit');
    if (profitEl) {
        const profit = revenue - capital;
        profitEl.value = profit;
        profitEl.style.color = profit >= 0 ? 'var(--secondary)' : 'var(--danger)';
    }
}

// ===== DOMContentLoaded =====
document.addEventListener('DOMContentLoaded', function() {
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
