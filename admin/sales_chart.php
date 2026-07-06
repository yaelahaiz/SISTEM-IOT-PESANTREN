<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);

function buildSalesPeriodTimeline($period, $month = null) {
    $now = new DateTime();
    $labels = [];

    switch ($period) {
        case '7days':
            $start = (clone $now)->modify('-6 days');
            $keyFormat = 'Y-m-d';
            $displayFormat = 'd/m';
            $interval = new DateInterval('P1D');
            break;
        case '1month':
            $year = (int)date('Y');
            $month = $month ? intval($month) : (int)date('n');
            $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
            $now = (clone $start)->modify('last day of this month');
            $keyFormat = 'Y-m-d';
            $displayFormat = 'd/m';
            $interval = new DateInterval('P1D');
            break;
        case '1year':
            $start = (clone $now)->modify('first day of -11 months');
            $keyFormat = 'Y-m';
            $displayFormat = 'M Y';
            $interval = new DateInterval('P1M');
            break;
        default:
            $start = (clone $now)->modify('-6 days');
            $keyFormat = 'Y-m-d';
            $displayFormat = 'd/m';
            $interval = new DateInterval('P1D');
            break;
    }

    $current = clone $start;
    while ($current <= $now) {
        $labels[$current->format($keyFormat)] = $current->format($displayFormat);
        $current->add($interval);
    }

    return $labels;
}

function getSalesChartData($conn, $period, $month = null) {
    $timeline = buildSalesPeriodTimeline($period, $month);
    $chartData = array_fill_keys(array_keys($timeline), ['capital' => 0, 'revenue' => 0, 'profit' => 0]);
    $year = (int)date('Y');
    $month = $month ? intval($month) : (int)date('n');

    switch ($period) {
        case '7days':
            $query = "SELECT DATE(sale_date) as label, SUM(capital) as capital, SUM(revenue) as revenue, SUM(profit) as profit
                      FROM sales
                      WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)";
            break;
        case '1month':
            $query = "SELECT DATE(sale_date) as label, SUM(capital) as capital, SUM(revenue) as revenue, SUM(profit) as profit
                      FROM sales
                      WHERE YEAR(sale_date) = $year AND MONTH(sale_date) = $month
                      GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)";
            break;
        case '1year':
            $query = "SELECT DATE_FORMAT(sale_date, '%Y-%m') as label, SUM(capital) as capital, SUM(revenue) as revenue, SUM(profit) as profit
                      FROM sales
                      WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                      GROUP BY DATE_FORMAT(sale_date, '%Y-%m') ORDER BY label";
            break;
        default:
            $query = "SELECT DATE(sale_date) as label, SUM(capital) as capital, SUM(revenue) as revenue, SUM(profit) as profit
                      FROM sales
                      WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)";
            break;
    }

    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (isset($chartData[$row['label']])) {
                $chartData[$row['label']] = [
                    'capital' => (float)$row['capital'],
                    'revenue' => (float)$row['revenue'],
                    'profit' => (float)$row['profit']
                ];
            }
        }
    }

    $labels = [];
    $capitals = [];
    $revenues = [];
    $profits = [];
    foreach ($chartData as $key => $values) {
        $labels[] = $timeline[$key];
        $capitals[] = $values['capital'];
        $revenues[] = $values['revenue'];
        $profits[] = $values['profit'];
    }

    return [$labels, $capitals, $revenues, $profits];
}

// AJAX request for chart data
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? '7days';
    $month = $_GET['month'] ?? date('n');
    list($labels, $capitals, $revenues, $profits) = getSalesChartData($conn, $period, $month);

    // Product breakdown
    switch ($period) {
        case '7days': $interval = '7 DAY'; break;
        case '1month': $interval = '1 MONTH'; break;
        case '1year': $interval = '1 YEAR'; break;
        default: $interval = '7 DAY'; break;
    }

    $productQuery = "SELECT product_name, SUM(revenue) as total
                     FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL $interval)
                     GROUP BY product_name ORDER BY total DESC LIMIT 10";
    $productResult = $conn->query($productQuery);
    $products = []; $productTotals = [];
    if ($productResult) {
        while ($row = $productResult->fetch_assoc()) {
            $products[] = $row['product_name'];
            $productTotals[] = (float)$row['total'];
        }
    }

    echo json_encode([
        'status' => 'success',
        'labels' => $labels,
        'capitals' => $capitals,
        'revenues' => $revenues,
        'profits' => $profits,
        'products' => $products,
        'product_totals' => $productTotals
    ]);
    exit;
}

// Default chart data (7 days)
list($labels, $capitals, $revenues, $profits) = getSalesChartData($conn, '7days');

// Product breakdown
$productData = $conn->query("SELECT product_name, SUM(revenue) as total FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY product_name ORDER BY total DESC LIMIT 10");
$productNames = []; $productTotals = [];
if ($productData) {
    while ($row = $productData->fetch_assoc()) {
        $productNames[] = $row['product_name'];
        $productTotals[] = (float)$row['total'];
    }
}

// Summary per period
$summary7 = $conn->query("SELECT COALESCE(SUM(capital),0) as cap, COALESCE(SUM(revenue),0) as rev, COALESCE(SUM(profit),0) as prof FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc();
$summary30 = $conn->query("SELECT COALESCE(SUM(capital),0) as cap, COALESCE(SUM(revenue),0) as rev, COALESCE(SUM(profit),0) as prof FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")->fetch_assoc();
$summaryYear = $conn->query("SELECT COALESCE(SUM(capital),0) as cap, COALESCE(SUM(revenue),0) as rev, COALESCE(SUM(profit),0) as prof FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grafik Penjualan - Riyadul Muta'alimin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

    <button class="sidebar-toggle-btn" onclick="toggleSidebar()"><i class="bx bx-menu"></i></button>
    
    <div class="admin-wrapper">
        <aside class="sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <div class="logo-full">
                    <h2>Riyadul <span>Muta'alimin</span></h2>
                    <small style="display:block; font-size:0.8rem; color:var(--text-light); margin-top:4px;">Powered By Bestari</small>
                </div>
                <div class="logo-mini">
                    <h2>I<span>P</span></h2>
                </div>
                <button class="sidebar-collapse-btn" onclick="collapseSidebar()" title="Tutup/Buka Sidebar"><i class="bx bx-chevron-left"></i></button>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" ><i class="bx bx-home-alt nav-icon"></i> <span class="nav-text">Dashboard</span></a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php" ><i class="bx bx-chip nav-icon"></i> <span class="nav-text">Perangkat</span></a></li>
                <li><a href="sensors.php" ><i class="bx bx-tachometer nav-icon"></i> <span class="nav-text">Sensor</span></a></li>
                <li><a href="monitoring.php" ><i class="bx bx-line-chart nav-icon"></i> <span class="nav-text">Monitoring</span></a></li>
                <li><a href="relay_control.php" ><i class="bx bx-power-off nav-icon"></i> <span class="nav-text">Kontrol Relay</span></a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php" ><i class="bx bx-store-alt nav-icon"></i> <span class="nav-text">Penjualan</span></a></li>
                <li><a href="sales_chart.php" class="active"><i class="bx bx-pie-chart-alt-2 nav-icon"></i> <span class="nav-text">Grafik Penjualan</span></a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" ><i class="bx bx-cog nav-icon"></i> <span class="nav-text">Pengaturan</span></a></li>
                <li><a href="../logout.php"><i class="bx bx-log-out nav-icon"></i> <span class="nav-text">Logout</span></a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Grafik Penjualan</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <!-- Period Selector -->
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <button class="btn btn-primary period-btn active" onclick="loadSalesChart('7days')">7 Hari</button>
                <button class="btn btn-outline period-btn" onclick="loadSalesChart('1month')">1 Bulan</button>
                <button class="btn btn-outline period-btn" onclick="loadSalesChart('1year')">1 Tahun</button>
            </div>
            <div id="salesMonthFilter" class="d-flex gap-2 mb-3 flex-wrap" style="display:none; align-items:center;">
                <label for="salesMonth" style="margin:0; font-weight:600;">Pilih Bulan:</label>
                <select id="salesMonth" class="form-control" onchange="loadSalesChart('1month', this.value)"></select>
            </div>

            <!-- Summary per Period -->
            <div class="stats-grid mb-3">
                <div class="stat-card">
                    <div class="stat-label">7 Hari Terakhir</div>
                    <div style="margin-top:5px;">
                        <div style="font-size:0.8rem;color:var(--text-light)">Modal: <?= formatRupiah($summary7['cap']) ?></div>
                        <div style="font-size:0.8rem;color:var(--primary)">Pendapatan: <?= formatRupiah($summary7['rev']) ?></div>
                        <div style="font-size:1rem;font-weight:700;color:var(--secondary)">Untung: <?= formatRupiah($summary7['prof']) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">1 Bulan Terakhir</div>
                    <div style="margin-top:5px;">
                        <div style="font-size:0.8rem;color:var(--text-light)">Modal: <?= formatRupiah($summary30['cap']) ?></div>
                        <div style="font-size:0.8rem;color:var(--primary)">Pendapatan: <?= formatRupiah($summary30['rev']) ?></div>
                        <div style="font-size:1rem;font-weight:700;color:var(--secondary)">Untung: <?= formatRupiah($summary30['prof']) ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">1 Tahun Terakhir</div>
                    <div style="margin-top:5px;">
                        <div style="font-size:0.8rem;color:var(--text-light)">Modal: <?= formatRupiah($summaryYear['cap']) ?></div>
                        <div style="font-size:0.8rem;color:var(--primary)">Pendapatan: <?= formatRupiah($summaryYear['rev']) ?></div>
                        <div style="font-size:1rem;font-weight:700;color:var(--secondary)">Untung: <?= formatRupiah($summaryYear['prof']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="card mb-3">
                <div class="card-header"><h3>Grafik Keuntungan</h3></div>
                <div style="padding:15px; height:350px;">
                    <canvas id="profitChart"></canvas>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3>Trend Modal, Pendapatan, Keuntungan</h3></div>
                <div style="padding:15px; height:350px;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>Pendapatan per Produk</h3></div>
                <div style="padding:15px; height:300px; max-width:400px; margin:0 auto;">
                    <canvas id="productChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    // Initial chart data from PHP
    const initLabels = <?= json_encode($labels) ?>;
    const initCapitals = <?= json_encode($capitals) ?>;
    const initRevenues = <?= json_encode($revenues) ?>;
    const initProfits = <?= json_encode($profits) ?>;
    const initProducts = <?= json_encode($productNames) ?>;
    const initProductTotals = <?= json_encode($productTotals) ?>;
    const monthOptions = [
        { value: '1', label: 'Januari' },
        { value: '2', label: 'Februari' },
        { value: '3', label: 'Maret' },
        { value: '4', label: 'April' },
        { value: '5', label: 'Mei' },
        { value: '6', label: 'Juni' },
        { value: '7', label: 'Juli' },
        { value: '8', label: 'Agustus' },
        { value: '9', label: 'September' },
        { value: '10', label: 'Oktober' },
        { value: '11', label: 'November' },
        { value: '12', label: 'Desember' }
    ];
    const selectedMonth = new Date().getMonth() + 1;

    function initMonthFilter() {
        const monthSelect = document.getElementById('salesMonth');
        if (!monthSelect) return;

        monthSelect.innerHTML = '';
        monthOptions.forEach(month => {
            const option = document.createElement('option');
            option.value = month.value;
            option.textContent = month.label;
            if (parseInt(month.value, 10) === selectedMonth) option.selected = true;
            monthSelect.appendChild(option);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        initMonthFilter();

        // Create initial charts
        window.profitChart = createBarChart('profitChart', initLabels, initProfits, 'Keuntungan');
        
        window.trendChart = createLineChart('trendChart', initLabels, [
            {
                label: 'Modal',
                data: initCapitals,
                borderColor: chartColors.danger,
                backgroundColor: chartColors.dangerBg,
                fill: true
            },
            {
                label: 'Pendapatan',
                data: initRevenues,
                borderColor: chartColors.secondary,
                backgroundColor: chartColors.secondaryBg,
                fill: true
            },
            {
                label: 'Keuntungan',
                data: initProfits,
                borderColor: chartColors.primary,
                backgroundColor: 'rgba(0, 150, 120, 0.08)',
                pointBackgroundColor: chartColors.primary,
                pointBorderColor: chartColors.primary,
                fill: false
            }
        ], 'Trend Penjualan');

        if (initProducts.length > 0) {
            window.productChart = createDoughnutChart('productChart', initProducts, initProductTotals, 'Per Produk');
        }
    });
    </script>
</body>
</html>

