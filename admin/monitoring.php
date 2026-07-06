<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);

// Filter parameters
$type = $_GET['type'] ?? 'solar';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $tableMap = [
        'solar' => 'solar_monitoring',
        'dryer' => 'dryer_monitoring',
        'cattle' => 'cattle_monitoring',
        'permaculture' => 'permaculture_monitoring'
    ];
    $table = $tableMap[$type] ?? 'solar_monitoring';
    
    $exportQuery = "SELECT * FROM $table WHERE DATE(recorded_at) BETWEEN ? AND ? ORDER BY recorded_at DESC";
    $stmt = $conn->prepare($exportQuery);
    $stmt->bind_param("ss", $dateFrom, $dateTo);
    $stmt->execute();
    $result = $stmt->get_result();
    $exportData = [];
    while ($row = $result->fetch_assoc()) {
        $exportData[] = $row;
    }
    $stmt->close();
    
    if (!empty($exportData)) {
        exportToCSV($exportData, "monitoring_{$type}_{$dateFrom}_{$dateTo}.csv");
    }
}

// Table configs
$tables = [
    'solar' => [
        'table' => 'solar_monitoring',
        'title' => 'Panel Surya',
        'columns' => ['Waktu', 'Suhu (°C)', 'Kelembaban (%)', 'Tegangan (V)', 'Arus (A)', 'Daya (W)', 'Energi (Wh)'],
        'fields' => ['recorded_at', 'temperature', 'humidity', 'voltage', 'current_amp', 'power', 'energy']
    ],
    'dryer' => [
        'table' => 'dryer_monitoring',
        'title' => 'Rumah Pengering',
        'columns' => ['Waktu', 'Suhu (°C)', 'Kelembaban (%)', 'Teg. AC (V)', 'Arus AC (A)', 'Daya AC (W)', 'Frekuensi', 'PF', 'Lampu'],
        'fields' => ['recorded_at', 'temperature', 'humidity', 'voltage_ac', 'current_ac', 'power_ac', 'frequency', 'power_factor', 'relay_lamp']
    ],
    'cattle' => [
        'table' => 'cattle_monitoring',
        'title' => 'Biodigester',
        'columns' => ['Waktu', 'Level Cairan (cm)', 'Volume (L)', 'Tekanan Gas (kPa)', 'Kelembaban Tanah (Raw)', 'Kelembaban (%)'],
        'fields' => ['recorded_at', 'liquid_level', 'liquid_volume', 'gas_pressure', 'soil_moisture_raw', 'soil_moisture_percent']
    ],
    'permaculture' => [
        'table' => 'permaculture_monitoring',
        'title' => 'Permaculture',
        'columns' => ['Waktu', 'pH Tanah'],
        'fields' => ['recorded_at', 'soil_ph']
    ]
];

$config = $tables[$type] ?? $tables['solar'];

// Count total
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM {$config['table']} WHERE DATE(recorded_at) BETWEEN ? AND ?");
$countStmt->bind_param("ss", $dateFrom, $dateTo);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, ceil($totalRows / $perPage));

// Get data
$dataStmt = $conn->prepare("SELECT * FROM {$config['table']} WHERE DATE(recorded_at) BETWEEN ? AND ? ORDER BY recorded_at DESC LIMIT ? OFFSET ?");
$dataStmt->bind_param("ssii", $dateFrom, $dateTo, $perPage, $offset);
$dataStmt->execute();
$data = $dataStmt->get_result();
$dataStmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Data - Riyadul Muta'alimin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <li><a href="monitoring.php" class="active"><i class="bx bx-line-chart nav-icon"></i> <span class="nav-text">Monitoring</span></a></li>
                <li><a href="relay_control.php" ><i class="bx bx-power-off nav-icon"></i> <span class="nav-text">Kontrol Relay</span></a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php" ><i class="bx bx-store-alt nav-icon"></i> <span class="nav-text">Penjualan</span></a></li>
                <li><a href="sales_chart.php" ><i class="bx bx-pie-chart-alt-2 nav-icon"></i> <span class="nav-text">Grafik Penjualan</span></a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" ><i class="bx bx-cog nav-icon"></i> <span class="nav-text">Pengaturan</span></a></li>
                <li><a href="../logout.php"><i class="bx bx-log-out nav-icon"></i> <span class="nav-text">Logout</span></a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Data Monitoring - <?= $config['title'] ?></h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <!-- Filter Bar -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3>Filter Data</h3>
                </div>
                <form method="GET" class="d-flex gap-2 flex-wrap" style="padding:15px;">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Tipe Monitoring</label>
                        <select name="type" class="form-control">
                            <?php foreach($tables as $key => $t): ?>
                                <option value="<?= $key ?>" <?= $type === $key ? 'selected' : '' ?>><?= $t['title'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0; align-self:flex-end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="monitoring.php?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&export=csv" class="btn btn-success">Export CSV</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><?= $config['title'] ?> - <?= number_format($totalRows) ?> data</h3>
                    <input type="text" id="searchMonitor" data-search-table="monitorTable" class="form-control" style="max-width:250px" placeholder="Cari data...">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="monitorTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <?php foreach($config['columns'] as $col): ?>
                                    <th><?= $col ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($data && $data->num_rows > 0): ?>
                                <?php $no = $offset + 1; while($row = $data->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <?php foreach($config['fields'] as $field): ?>
                                        <td>
                                            <?php if($field === 'recorded_at'): ?>
                                                <?= date('d/m/Y H:i:s', strtotime($row[$field])) ?>
                                            <?php elseif($field === 'relay_lamp'): ?>
                                                <?= $row[$field] ? '<span class="badge badge-success">ON</span>' : '<span class="badge" style="background:#ccc">OFF</span>' ?>
                                            <?php else: ?>
                                                <?= formatNumber($row[$field] ?? 0) ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="<?= count($config['columns']) + 1 ?>" class="text-center">Tidak ada data pada rentang tanggal ini</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($totalPages > 1): ?>
                <div class="pagination" style="padding:15px; text-align:center;">
                    <?php if($page > 1): ?>
                        <a href="?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-outline">&laquo; Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $startP = max(1, $page - 2);
                    $endP = min($totalPages, $page + 2);
                    for($i = $startP; $i <= $endP; $i++): 
                    ?>
                        <a href="?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&page=<?= $i ?>" 
                           class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if($page < $totalPages): ?>
                        <a href="?type=<?= $type ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-outline">Next &raquo;</a>
                    <?php endif; ?>
                    
                    <span style="margin-left:10px; color:var(--text-light); font-size:0.85rem;">Halaman <?= $page ?> dari <?= $totalPages ?></span>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>

