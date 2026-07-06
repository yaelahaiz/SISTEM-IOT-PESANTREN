<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$latest = getLatestCattleData($conn);
$height = getSetting($conn, 'biodigester_height') ?: 150;
$diameter = getSetting($conn, 'biodigester_diameter') ?: 100;

// Get chart data (last 20 records)
$chartQuery = $conn->query("SELECT liquid_level, gas_pressure, soil_moisture_percent, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM cattle_monitoring ORDER BY id DESC LIMIT 20");
$chartLabels = [];
$chartLevel = [];
$chartPressure = [];
$chartMoisture = [];
if ($chartQuery) {
    while($row = $chartQuery->fetch_assoc()) {
        array_unshift($chartLabels, $row['time_label']);
        array_unshift($chartLevel, floatval($row['liquid_level']));
        array_unshift($chartPressure, floatval($row['gas_pressure']));
        array_unshift($chartMoisture, floatval($row['soil_moisture_percent']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Kandang Sapi - IoT Pesantren</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-refresh-type="cattle">

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">IoT<span>Pesantren</span></a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php" class="active">Kandang Sapi</a></li>
                <li><a href="dashboard_permaculture.php">Permaculture</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top:30px; margin-bottom: 50px;">
        <h2 class="section-title">Monitoring Kandang Sapi & Biodigester</h2>
        
        <h3 class="mt-3 mb-2">Data Biodigester</h3>
        <div class="dashboard-grid">
            <div class="card" id="cattle-level">
                <div class="card-header cattle">
                    <span class="card-label">Tinggi Cairan</span>
                </div>
                <div class="card-value"><?= formatNumber($latest['liquid_level'] ?? 0, 1) ?> <span class="card-unit">cm</span></div>
                <div class="card-footer last-updated">Tinggi Total: <?= $height ?> cm</div>
            </div>

            <div class="card" id="cattle-volume">
                <div class="card-header cattle">
                    <span class="card-label">Volume Cairan</span>
                </div>
                <div class="card-value"><?= formatNumber($latest['liquid_volume'] ?? 0, 2) ?> <span class="card-unit">L</span></div>
                <div class="card-footer last-updated">Diameter: <?= $diameter ?> cm</div>
            </div>

            <div class="card" id="cattle-pressure">
                <div class="card-header cattle">
                    <span class="card-label">Tekanan Gas</span>
                </div>
                <div class="card-value"><?= formatNumber($latest['gas_pressure'] ?? 0, 1) ?> <span class="card-unit">kPa</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>
        </div>

        <h3 class="mt-3 mb-2">Data Kelembaban Tanah (Kandang)</h3>
        <div class="dashboard-grid">
            <div class="card" id="cattle-moisture">
                <div class="card-header cattle">
                    <span class="card-label">Kelembaban Tanah</span>
                    <span class="status-indicator status-<?= getStatusClass($latest['soil_moisture_percent'] ?? 0, 20, null, 0, 20) ?>"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['soil_moisture_percent'] ?? 0, 1) ?> <span class="card-unit">%</span></div>
            </div>
            
            <div class="card" id="cattle-moisture-raw">
                <div class="card-header cattle">
                    <span class="card-label">Nilai Mentah (ADC)</span>
                </div>
                <div class="card-value"><?= formatNumber($latest['soil_moisture_raw'] ?? 0, 0) ?></div>
            </div>
        </div>

        <div class="chart-container">
            <h3>Grafik Biodigester & Kelembaban Tanah (20 Data Terakhir)</h3>
            <div class="chart-wrapper">
                <canvas id="cattleChart"></canvas>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tinggi Cairan (cm)</th>
                        <th>Volume (L)</th>
                        <th>Tekanan Gas (kPa)</th>
                        <th>Kelembaban Tanah (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $conn->query("SELECT * FROM cattle_monitoring ORDER BY id DESC LIMIT 10");
                    if ($history && $history->num_rows > 0) {
                        while ($row = $history->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . date('d/m/Y H:i:s', strtotime($row['recorded_at'])) . "</td>";
                            echo "<td>" . formatNumber($row['liquid_level'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['liquid_volume'], 2) . "</td>";
                            echo "<td>" . formatNumber($row['gas_pressure'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['soil_moisture_percent'], 1) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>Belum ada data sensor</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const labels = <?= json_encode($chartLabels) ?>;
            const level = <?= json_encode($chartLevel) ?>;
            const pressure = <?= json_encode($chartPressure) ?>;
            const moisture = <?= json_encode($chartMoisture) ?>;
            
            if(labels.length > 0) {
                createLineChart('cattleChart', labels, [
                    {
                        label: 'Tinggi Cairan (cm)',
                        data: level,
                        borderColor: '#009678',
                        backgroundColor: 'rgba(0, 150, 120, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Tekanan Gas (kPa)',
                        data: pressure,
                        borderColor: '#e66060',
                        backgroundColor: 'transparent'
                    },
                    {
                        label: 'Kelembaban Tanah (%)',
                        data: moisture,
                        borderColor: '#04a2b3',
                        backgroundColor: 'transparent'
                    }
                ]);
            }
        });
    </script>
</body>
</html>
