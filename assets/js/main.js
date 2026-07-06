/**
 * IoT Pesantren - Main JavaScript
 * Fungsi utama untuk dashboard monitoring dan admin panel
 */

// ===== Base URL =====
const BASE_URL = window.location.origin + '/SISTEM-IOT-PESANTREN';

// ===== Chart Colors =====
const chartColors = {
    primary: 'rgba(0, 150, 120, 1)',
    primaryBg: 'rgba(0, 150, 120, 0.12)',
    secondary: 'rgba(52, 97, 175, 1)',
    secondaryBg: 'rgba(52, 97, 175, 0.12)',
    danger: 'rgba(230, 96, 96, 1)',
    dangerBg: 'rgba(230, 96, 96, 0.12)',
    warning: 'rgba(255, 182, 0, 1)',
    warningBg: 'rgba(255, 182, 0, 0.12)',
    info: 'rgba(0, 164, 254, 1)',
    infoBg: 'rgba(0, 164, 254, 0.12)'
};

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
 * Tambahkan helper chart untuk Sales Chart
 */
function createBarChart(canvasId, labels, data, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: chartColors.primaryBg,
                borderColor: chartColors.primary,
                borderWidth: 2,
                hoverBackgroundColor: chartColors.primary,
                hoverBorderColor: chartColors.primary
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

function createLineChart(canvasId, labels, datasets, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets.map(ds => ({
                ...ds,
                tension: 0.35,
                borderWidth: ds.borderWidth || 3,
                pointRadius: ds.pointRadius || 4,
                pointHoverRadius: ds.pointHoverRadius || 6,
                pointBackgroundColor: ds.pointBackgroundColor || ds.borderColor,
                pointBorderColor: ds.pointBorderColor || ds.borderColor,
                backgroundColor: ds.backgroundColor || ds.borderColor,
                borderColor: ds.borderColor,
                fill: ds.fill === true
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });
}

function createDoughnutChart(canvasId, labels, data, label) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                backgroundColor: [
                    chartColors.primary,
                    chartColors.secondary,
                    chartColors.danger,
                    chartColors.warning,
                    chartColors.info
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
}

function loadSalesChart(period, month = '') {
    const buttons = document.querySelectorAll('.period-btn');
    buttons.forEach(btn => {
        const text = btn.textContent.toLowerCase();
        const isActive = text.includes(period === '7days' ? '7 hari' : period === '1month' ? '1 bulan' : '1 tahun');
        btn.classList.toggle('active', isActive);
        btn.classList.toggle('btn-primary', isActive);
        btn.classList.toggle('btn-outline', !isActive);
    });

    const monthFilter = document.getElementById('salesMonthFilter');
    const monthSelect = document.getElementById('salesMonth');
    if (period === '1month') {
        monthFilter.style.display = 'flex';
        if (!month) {
            month = monthSelect.value || (new Date().getMonth() + 1);
        }
    } else {
        monthFilter.style.display = 'none';
    }

    fetch(`sales_chart.php?ajax=1&period=${period}&month=${month}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (window.profitChart) {
                    window.profitChart.destroy();
                }
                window.profitChart = createBarChart('profitChart', data.labels, data.profits, 'Keuntungan');

                if (window.trendChart) {
                    window.trendChart.destroy();
                }
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
                        backgroundColor: 'rgba(0, 150, 120, 0.08)',
                        pointBackgroundColor: chartColors.primary,
                        pointBorderColor: chartColors.primary,
                        fill: false
                    }
                ]);

                if (window.productChart) {
                    window.productChart.data.labels = data.products;
                    window.productChart.data.datasets[0].data = data.product_totals;
                    window.productChart.update();
                }
            }
        })
        .catch(err => console.error('Chart load error:', err));
}

function exportTableCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr'));
    const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => `"${cell.textContent.replace(/"/g, '""')}"`).join(',');
    }).join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function toggleRelay(id, status) {
    const formData = new FormData();
    formData.append('relay_id', id);
    formData.append('status', status);

    fetch('../api/update_relay_status.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
        .then(res => res.json())
        .then(response => {
            if (response.status === 'success') {
                showToast(response.message, 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showToast(response.message || 'Gagal mengubah relay', 'error');
            }
        })
        .catch(err => {
            console.error('Relay toggle error:', err);
            showToast('Terjadi kesalahan jaringan', 'error');
        });
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
 * Buka modal berdasarkan id
 */
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
}

/**
 * Tutup modal berdasarkan id
 */
function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('active');
}

/**
 * Toggle navbar mobile
 */
function toggleNavbar() {
    const nav = document.querySelector('.navbar-nav');
    if (!nav) return;
    nav.classList.toggle('show');
}

/**
 * Cari di tabel berdasarkan input
 */
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    const filter = input.value.toLowerCase();
    const rows = table.tBodies[0] ? Array.from(table.tBodies[0].rows) : [];

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

/**
 * Hitung profit otomatis pada form penjualan
 */
function calculateProfit() {
    const capitalInput = document.getElementById('capital');
    const revenueInput = document.getElementById('revenue');
    const profitInput = document.getElementById('profit');
    if (!capitalInput || !revenueInput || !profitInput) return;

    const capital = parseFloat(capitalInput.value) || 0;
    const revenue = parseFloat(revenueInput.value) || 0;
    const profit = revenue - capital;

    profitInput.value = profit;
    profitInput.style.color = profit >= 0 ? 'var(--secondary)' : 'var(--danger)';
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
document.addEventListener('DOMContentLoaded', function () {
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
