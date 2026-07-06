<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);

// AJAX request for chart data
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    $period = $_GET['period'] ?? '7days';
    
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
                      WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
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
    }
    
    $result = $conn->query($query);
    $labels = []; $capitals = []; $revenues = []; $profits = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($period === '1year') {
                $labels[] = date('M Y', strtotime($row['label'] . '-01'));
            } else {
                $labels[] = date('d/m', strtotime($row['label']));
            }
            $capitals[] = (float)$row['capital'];
            $revenues[] = (float)$row['revenue'];
            $profits[] = (float)$row['profit'];
        }
    }
    
    // Product breakdown
    switch ($period) {
        case '7days': $interval = '7 DAY'; break;
        case '1month': $interval = '1 MONTH'; break;
        case '1year': $interval = '1 YEAR'; break;
        default: $interval = '7 DAY';
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
$defaultData = $conn->query("SELECT DATE(sale_date) as label, SUM(capital) as capital, SUM(revenue) as revenue, SUM(profit) as profit
    FROM sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)");

$labels = []; $capitals = []; $revenues = []; $profits = [];
if ($defaultData) {
    while ($row = $defaultData->fetch_assoc()) {
        $labels[] = date('d/m', strtotime($row['label']));
        $capitals[] = (float)$row['capital'];
        $revenues[] = (float)$row['revenue'];
        $profits[] = (float)$row['profit'];
    }
}

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
    <title>Grafik Penjualan - IoT Pesantren</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <button class="sidebar-toggle-btn">&#9776;</button>
    
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>IoT Pesantren</h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><span class="nav-icon">&#128202;</span> Dashboard</a></li>
                <li class="sidebar-divider"></li>
                <li><a href="devices.php"><span class="nav-icon">&#128241;</span> Perangkat</a></li>
                <li><a href="sensors.php"><span class="nav-icon">&#127777;&#65039;</span> Sensor</a></li>
                <li><a href="monitoring.php"><span class="nav-icon">&#128200;</span> Monitoring</a></li>
                <li><a href="relay_control.php"><span class="nav-icon">&#128268;</span> Kontrol Relay</a></li>
                <li class="sidebar-divider"></li>
                <li><a href="sales.php"><span class="nav-icon">&#128176;</span> Penjualan</a></li>
                <li><a href="sales_chart.php" class="active"><span class="nav-icon">&#128201;</span> Grafik Penjualan</a></li>
                <li class="sidebar-divider"></li>
                <li><a href="settings.php"><span class="nav-icon">&#9881;&#65039;</span> Pengaturan</a></li>
                <li><a href="../logout.php"><span class="nav-icon">&#128682;</span> Logout</a></li>
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

    document.addEventListener('DOMContentLoaded', function() {
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
                backgroundColor: chartColors.primaryBg,
                fill: true
            }
        ], 'Trend Penjualan');

        if (initProducts.length > 0) {
            window.productChart = createDoughnutChart('productChart', initProducts, initProductTotals, 'Per Produk');
        }
    });
    </script>
</body>
</html>
