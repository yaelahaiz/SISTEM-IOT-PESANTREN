<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);

// Get stats
$deviceCount = $conn->query("SELECT COUNT(*) as c FROM devices")->fetch_assoc()['c'] ?? 0;
$activeDeviceCount = $conn->query("SELECT COUNT(*) as c FROM devices WHERE is_active=1")->fetch_assoc()['c'] ?? 0;

$salesStats = $conn->query("
    SELECT 
        SUM(revenue) as total_rev, 
        SUM(profit) as total_profit 
    FROM sales 
    WHERE MONTH(sale_date) = MONTH(CURRENT_DATE()) AND YEAR(sale_date) = YEAR(CURRENT_DATE())
")->fetch_assoc();

$totalRev = $salesStats['total_rev'] ?? 0;
$totalProfit = $salesStats['total_profit'] ?? 0;

// Fetch Chart Data
// Solar
$solarQuery = $conn->query("SELECT temperature, voltage, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM solar_monitoring ORDER BY id DESC LIMIT 15");
$solarLabels = []; $solarTemps = []; $solarVolts = [];
if ($solarQuery) {
    while($row = $solarQuery->fetch_assoc()) {
        array_unshift($solarLabels, $row['time_label']);
        array_unshift($solarTemps, floatval($row['temperature']));
        array_unshift($solarVolts, floatval($row['voltage']));
    }
}

// Dryer
$dryerQuery = $conn->query("SELECT temperature, power_ac, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM dryer_monitoring ORDER BY id DESC LIMIT 15");
$dryerLabels = []; $dryerTemps = []; $dryerPower = [];
if ($dryerQuery) {
    while($row = $dryerQuery->fetch_assoc()) {
        array_unshift($dryerLabels, $row['time_label']);
        array_unshift($dryerTemps, floatval($row['temperature']));
        array_unshift($dryerPower, floatval($row['power_ac']));
    }
}

// Cattle
$cattleQuery = $conn->query("SELECT liquid_level, gas_pressure, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM cattle_monitoring ORDER BY id DESC LIMIT 15");
$cattleLabels = []; $cattleLevel = []; $cattlePressure = [];
if ($cattleQuery) {
    while($row = $cattleQuery->fetch_assoc()) {
        array_unshift($cattleLabels, $row['time_label']);
        array_unshift($cattleLevel, floatval($row['liquid_level']));
        array_unshift($cattlePressure, floatval($row['gas_pressure']));
    }
}

// Permaculture
$permQuery = $conn->query("SELECT soil_ph, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM permaculture_monitoring ORDER BY id DESC LIMIT 15");
$permLabels = []; $permPh = [];
if ($permQuery) {
    while($row = $permQuery->fetch_assoc()) {
        array_unshift($permLabels, $row['time_label']);
        array_unshift($permPh, floatval($row['soil_ph']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Riyadul Muta'alimin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <button class="sidebar-toggle-btn" onclick="toggleSidebar()"><i class="bx bx-menu"></i></button>
    
    <div class="admin-wrapper">
        <!-- Sidebar -->
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
                <li><a href="dashboard.php" class="active"><i class="bx bx-home-alt nav-icon"></i> <span class="nav-text">Dashboard</span></a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php" ><i class="bx bx-chip nav-icon"></i> <span class="nav-text">Perangkat</span></a></li>
                <li><a href="sensors.php" ><i class="bx bx-tachometer nav-icon"></i> <span class="nav-text">Sensor</span></a></li>
                <li><a href="monitoring.php" ><i class="bx bx-line-chart nav-icon"></i> <span class="nav-text">Monitoring</span></a></li>
                <li><a href="relay_control.php" ><i class="bx bx-power-off nav-icon"></i> <span class="nav-text">Kontrol Relay</span></a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php" ><i class="bx bx-store-alt nav-icon"></i> <span class="nav-text">Penjualan</span></a></li>
                <li><a href="sales_chart.php" ><i class="bx bx-pie-chart-alt-2 nav-icon"></i> <span class="nav-text">Grafik Penjualan</span></a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" ><i class="bx bx-cog nav-icon"></i> <span class="nav-text">Pengaturan</span></a></li>
                <li><a href="../logout.php"><i class="bx bx-log-out nav-icon"></i> <span class="nav-text">Logout</span></a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Dashboard Utama</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $deviceCount ?></div>
                    <div class="stat-label">Total Perangkat</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $activeDeviceCount ?></div>
                    <div class="stat-label">Perangkat Aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size:1.5rem; line-height:2rem;"><?= formatRupiah($totalRev) ?></div>
                    <div class="stat-label">Pendapatan Bulan Ini</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="font-size:1.5rem; line-height:2rem; color:var(--secondary)"><?= formatRupiah($totalProfit) ?></div>
                    <div class="stat-label">Keuntungan Bulan Ini</div>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap mb-3">
                <a href="devices.php" class="btn btn-primary">Kelola Perangkat</a>
                <a href="sales.php" class="btn btn-success">Catat Penjualan</a>
                <a href="relay_control.php" class="btn btn-warning">Kontrol Lampu/Relay</a>
                <a href="../index.php" target="_blank" class="btn btn-outline">Lihat Halaman Publik ↗</a>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3>Status Sistem Real-time</h3>
                </div>
                <div class="table-responsive" style="margin-bottom: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Sistem</th>
                                <th>Update Terakhir</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $systems = [
                                ['name' => 'Panel Surya', 'table' => 'solar_monitoring', 'link' => '../dashboard_panel_surya.php'],
                                ['name' => 'Rumah Pengering', 'table' => 'dryer_monitoring', 'link' => '../dashboard_rumah_pengering.php'],
                                ['name' => 'Biodigester', 'table' => 'cattle_monitoring', 'link' => '../dashboard_kandang_sapi.php'],
                                ['name' => 'Permaculture', 'table' => 'permaculture_monitoring', 'link' => '../dashboard_permaculture.php']
                            ];
                            
                            foreach ($systems as $sys) {
                                $q = $conn->query("SELECT recorded_at FROM {$sys['table']} ORDER BY id DESC LIMIT 1");
                                $time = '-';
                                $status = '<span class="badge" style="background:#ccc">Offline</span>';
                                
                                if ($q && $q->num_rows > 0) {
                                    $row = $q->fetch_assoc();
                                    $time = date('d/m/Y H:i:s', strtotime($row['recorded_at']));
                                    
                                    $diff = time() - strtotime($row['recorded_at']);
                                    if ($diff < 300) { // 5 menit
                                        $status = '<span class="badge badge-success">Online Aktif</span>';
                                    } elseif ($diff < 3600) { // 1 jam
                                        $status = '<span class="badge badge-warning">Delay (> 5mnt)</span>';
                                    } else {
                                        $status = '<span class="badge badge-danger">Offline</span>';
                                    }
                                }
                                
                                echo "<tr>";
                                echo "<td><strong>{$sys['name']}</strong></td>";
                                echo "<td>$time</td>";
                                echo "<td>$status</td>";
                                echo "<td><a href='{$sys['link']}' target='_blank' class='btn btn-sm btn-outline'>Lihat Detail</a></td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Solar Chart Mini -->
                <div class="card">
                    <div class="card-header solar">
                        <h3>Grafik Panel Surya</h3>
                    </div>
                    <div style="height: 250px;">
                        <canvas id="miniSolarChart"></canvas>
                    </div>
                </div>

                <!-- Dryer Chart Mini -->
                <div class="card">
                    <div class="card-header dryer">
                        <h3>Grafik Rumah Pengering</h3>
                    </div>
                    <div style="height: 250px;">
                        <canvas id="miniDryerChart"></canvas>
                    </div>
                </div>

                <!-- Cattle Chart Mini -->
                <div class="card">
                    <div class="card-header cattle">
                        <h3>Grafik Biodigester</h3>
                    </div>
                    <div style="height: 250px;">
                        <canvas id="miniCattleChart"></canvas>
                    </div>
                </div>

                <!-- Permaculture Chart Mini -->
                <div class="card">
                    <div class="card-header permaculture">
                        <h3>Grafik Permaculture</h3>
                    </div>
                    <div style="height: 250px;">
                        <canvas id="miniPermChart"></canvas>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Chart Data
        const sLabels = <?= json_encode($solarLabels) ?>;
        const sTemps = <?= json_encode($solarTemps) ?>;
        
        const dLabels = <?= json_encode($dryerLabels) ?>;
        const dTemps = <?= json_encode($dryerTemps) ?>;
        
        const cLabels = <?= json_encode($cattleLabels) ?>;
        const cLevel = <?= json_encode($cattleLevel) ?>;
        
        const pLabels = <?= json_encode($permLabels) ?>;
        const pPh = <?= json_encode($permPh) ?>;

        // Common Options for Mini Charts
        const miniOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: false },
                y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { maxTicksLimit: 5, font: {size: 10} } }
            },
            elements: { point: { radius: 0, hitRadius: 5 }, line: { tension: 0.3, borderWidth: 2 } }
        };

        // 1. Solar
        if(sLabels.length > 0 && document.getElementById('miniSolarChart')) {
            new Chart(document.getElementById('miniSolarChart'), {
                type: 'line',
                data: {
                    labels: sLabels,
                    datasets: [{
                        label: 'Suhu Panel (°C)',
                        data: sTemps,
                        borderColor: '#ffb600',
                        backgroundColor: 'rgba(255, 182, 0, 0.1)',
                        fill: true
                    }]
                },
                options: miniOptions
            });
        }

        // 2. Dryer
        if(dLabels.length > 0 && document.getElementById('miniDryerChart')) {
            new Chart(document.getElementById('miniDryerChart'), {
                type: 'line',
                data: {
                    labels: dLabels,
                    datasets: [{
                        label: 'Suhu Pengering (°C)',
                        data: dTemps,
                        borderColor: '#e66060',
                        backgroundColor: 'rgba(230, 96, 96, 0.1)',
                        fill: true
                    }]
                },
                options: miniOptions
            });
        }

        // 3. Cattle
        if(cLabels.length > 0 && document.getElementById('miniCattleChart')) {
            new Chart(document.getElementById('miniCattleChart'), {
                type: 'line',
                data: {
                    labels: cLabels,
                    datasets: [{
                        label: 'Level Cairan (cm)',
                        data: cLevel,
                        borderColor: '#009678',
                        backgroundColor: 'rgba(0, 150, 120, 0.1)',
                        fill: true
                    }]
                },
                options: miniOptions
            });
        }

        // 4. Permaculture
        if(pLabels.length > 0 && document.getElementById('miniPermChart')) {
            new Chart(document.getElementById('miniPermChart'), {
                type: 'line',
                data: {
                    labels: pLabels,
                    datasets: [{
                        label: 'pH Tanah',
                        data: pPh,
                        borderColor: '#04a2b3',
                        backgroundColor: 'rgba(4, 162, 179, 0.1)',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { display: false },
                        y: { min: 0, max: 14, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { maxTicksLimit: 5, font: {size: 10} } }
                    },
                    elements: { point: { radius: 0, hitRadius: 5 }, line: { tension: 0.3, borderWidth: 2 } }
                }
            });
        }
    </script>
</body>
</html>

