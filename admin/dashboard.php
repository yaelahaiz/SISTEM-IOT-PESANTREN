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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - IoT Pesantren</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <button class="sidebar-toggle-btn">☰</button>
    
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>IoT<span>Pesantren</span></h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="active"><span class="nav-icon">&#128202;</span> Dashboard</a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php"><span class="nav-icon">&#128241;</span> Perangkat</a></li>
                <li><a href="sensors.php"><span class="nav-icon">&#127777;&#65039;</span> Sensor</a></li>
                <li><a href="monitoring.php"><span class="nav-icon">&#128200;</span> Monitoring</a></li>
                <li><a href="relay_control.php"><span class="nav-icon">&#128268;</span> Kontrol Relay</a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php"><span class="nav-icon">&#128176;</span> Penjualan</a></li>
                <li><a href="sales_chart.php"><span class="nav-icon">&#128201;</span> Grafik Penjualan</a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php"><span class="nav-icon">&#9881;&#65039;</span> Pengaturan</a></li>
                <li><a href="../logout.php"><span class="nav-icon">&#128682;</span> Logout</a></li>
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
                    <div class="stat-icon">📱</div>
                    <div class="stat-value"><?= $deviceCount ?></div>
                    <div class="stat-label">Total Perangkat</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🟢</div>
                    <div class="stat-value"><?= $activeDeviceCount ?></div>
                    <div class="stat-label">Perangkat Aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value" style="font-size:1.5rem; line-height:2rem;"><?= formatRupiah($totalRev) ?></div>
                    <div class="stat-label">Pendapatan Bulan Ini</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
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

            <div class="card">
                <div class="card-header">
                    <h3>Status Sistem Real-time</h3>
                </div>
                <div class="table-responsive">
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
                                ['name' => 'Kandang Sapi', 'table' => 'cattle_monitoring', 'link' => '../dashboard_kandang_sapi.php'],
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

        </main>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
