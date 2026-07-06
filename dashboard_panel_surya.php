<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$latest = getLatestSolarData($conn);

// Get chart data (last 24 hours)
$chartQuery = $conn->query("SELECT temperature, voltage, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM solar_monitoring ORDER BY id DESC LIMIT 20");
$labels = [];
$temps = [];
$volts = [];
if ($chartQuery) {
    while($row = $chartQuery->fetch_assoc()) {
        array_unshift($labels, $row['time_label']);
        array_unshift($temps, floatval($row['temperature']));
        array_unshift($volts, floatval($row['voltage']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Panel Surya - IoT Pesantren</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-refresh-type="solar">

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">IoT<span>Pesantren</span></a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php" class="active">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php">Kandang Sapi</a></li>
                <li><a href="dashboard_permaculture.php">Permaculture</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top:30px; margin-bottom: 50px;">
        <h2 class="section-title">Monitoring Panel Surya</h2>
        
        <div class="dashboard-grid">
            <div class="card" id="solar-temp">
                <div class="card-header solar">
                    <span class="card-label">Suhu Panel</span>
                    <span class="status-indicator status-<?= getStatusClass($latest['temperature'] ?? 0, null, 60, null, 80) ?>"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['temperature'] ?? 0, 1) ?> <span class="card-unit">°C</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="solar-hum">
                <div class="card-header solar">
                    <span class="card-label">Kelembaban</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['humidity'] ?? 0, 1) ?> <span class="card-unit">%</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="solar-volt">
                <div class="card-header solar">
                    <span class="card-label">Tegangan Baterai</span>
                    <span class="status-indicator status-<?= getStatusClass($latest['voltage'] ?? 0, 11, null, 0, 11) ?>"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['voltage'] ?? 0, 2) ?> <span class="card-unit">V</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="solar-curr">
                <div class="card-header solar">
                    <span class="card-label">Arus Baterai</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['current_amp'] ?? 0, 2) ?> <span class="card-unit">A</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="solar-power">
                <div class="card-header solar">
                    <span class="card-label">Daya</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['power'] ?? 0, 2) ?> <span class="card-unit">W</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="solar-energy">
                <div class="card-header solar">
                    <span class="card-label">Energi</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['energy'] ?? 0, 2) ?> <span class="card-unit">Wh</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>
        </div>

        <div class="chart-container">
            <h3>Grafik Suhu & Tegangan (20 Data Terakhir)</h3>
            <div class="chart-wrapper">
                <canvas id="solarChart"></canvas>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Suhu (°C)</th>
                        <th>Kelembaban (%)</th>
                        <th>Tegangan (V)</th>
                        <th>Arus (A)</th>
                        <th>Daya (W)</th>
                        <th>Energi (Wh)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $conn->query("SELECT * FROM solar_monitoring ORDER BY id DESC LIMIT 10");
                    if ($history && $history->num_rows > 0) {
                        while ($row = $history->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . date('d/m/Y H:i:s', strtotime($row['recorded_at'])) . "</td>";
                            echo "<td>" . formatNumber($row['temperature'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['humidity'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['voltage'], 2) . "</td>";
                            echo "<td>" . formatNumber($row['current_amp'], 2) . "</td>";
                            echo "<td>" . formatNumber($row['power'], 2) . "</td>";
                            echo "<td>" . formatNumber($row['energy'], 2) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Belum ada data sensor</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Sistem Monitoring IoT Pesantren.</p>
    </footer>

    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = <?= json_encode($labels) ?>;
            const temps = <?= json_encode($temps) ?>;
            const volts = <?= json_encode($volts) ?>;
            
            if(labels.length > 0) {
                createLineChart('solarChart', labels, [
                    {
                        label: 'Suhu (°C)',
                        data: temps,
                        borderColor: '#d97706',
                        backgroundColor: 'transparent',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Tegangan (V)',
                        data: volts,
                        borderColor: '#2563eb',
                        backgroundColor: 'transparent',
                        yAxisID: 'y1'
                    }
                ]);
            }
        });
    </script>
</body>
</html>
