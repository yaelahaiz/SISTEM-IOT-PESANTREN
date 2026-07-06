<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$latest = getLatestDryerData($conn);

// Get chart data (last 20 records)
$chartQuery = $conn->query("SELECT temperature, voltage_ac, power_ac, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM dryer_monitoring ORDER BY id DESC LIMIT 20");
$chartLabels = [];
$chartTemps = [];
$chartVolts = [];
$chartPower = [];
if ($chartQuery) {
    while($row = $chartQuery->fetch_assoc()) {
        array_unshift($chartLabels, $row['time_label']);
        array_unshift($chartTemps, floatval($row['temperature']));
        array_unshift($chartVolts, floatval($row['voltage_ac']));
        array_unshift($chartPower, floatval($row['power_ac']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Rumah Pengering - Riyadul Muta'alimin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-refresh-type="dryer">

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">
                <div style="display:flex; flex-direction:column; align-items:flex-start; gap:2px;">
                    <span>Riyadul <span>Muta'alimin</span></span>
                    <small style="font-size:0.8rem; color:var(--text-light);">Powered By Bestari</small>
                </div>
            </a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php" class="active">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php">Biodigester</a></li>
                <li><a href="dashboard_permaculture.php">Permaculture</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top:30px; margin-bottom: 50px;">
        <h2 class="section-title">Monitoring Rumah Pengering</h2>
        
        <div class="dashboard-grid">
            <div class="card" id="dryer-temp">
                <div class="card-header dryer">
                    <span class="card-label">Suhu Ruangan</span>
                    <span class="status-indicator status-<?= getStatusClass($latest['temperature'] ?? 0, 30, 60, 0, 80) ?>"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['temperature'] ?? 0, 1) ?> <span class="card-unit">°C</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="dryer-hum">
                <div class="card-header dryer">
                    <span class="card-label">Kelembaban</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['humidity'] ?? 0, 1) ?> <span class="card-unit">%</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>

            <div class="card" id="dryer-volt">
                <div class="card-header dryer">
                    <span class="card-label">Tegangan AC</span>
                    <span class="status-indicator status-normal"></span>
                </div>
                <div class="card-value"><?= formatNumber($latest['voltage_ac'] ?? 0, 1) ?> <span class="card-unit">V</span></div>
                <div class="card-footer last-updated">Diperbarui: <?= $latest ? date('d/m/Y H:i', strtotime($latest['recorded_at'])) : '-' ?></div>
            </div>
            
            <div class="card">
                <div class="card-header dryer">
                    <span class="card-label">Status Lampu</span>
                </div>
                <div style="font-size: 2rem; margin: 8px 0;">
                    <?php if(($latest['relay_lamp'] ?? 0) == 1): ?>
                        <span class="badge badge-success" style="font-size: 1.5rem; padding: 10px 20px;">ON</span>
                    <?php else: ?>
                        <span class="badge" style="background:#ccc; font-size: 1.5rem; padding: 10px 20px;">OFF</span>
                    <?php endif; ?>
                </div>
                <div class="card-footer" style="color:var(--text-light);font-size:0.8rem;margin-top:16px;">Read Only - Kontrol via Admin Panel</div>
            </div>
        </div>

        <div class="chart-container">
            <h3>Grafik Suhu, Tegangan AC & Daya (20 Data Terakhir)</h3>
            <div class="chart-wrapper">
                <canvas id="dryerChart"></canvas>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Suhu (°C)</th>
                        <th>Kelembaban (%)</th>
                        <th>Tegangan AC (V)</th>
                        <th>Daya AC (W)</th>
                        <th>Status Lampu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $conn->query("SELECT * FROM dryer_monitoring ORDER BY id DESC LIMIT 10");
                    if ($history && $history->num_rows > 0) {
                        while ($row = $history->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . date('d/m/Y H:i:s', strtotime($row['recorded_at'])) . "</td>";
                            echo "<td>" . formatNumber($row['temperature'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['humidity'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['voltage_ac'], 1) . "</td>";
                            echo "<td>" . formatNumber($row['power_ac'], 2) . "</td>";
                            echo "<td>" . ($row['relay_lamp'] ? '<span class="badge badge-success">ON</span>' : '<span class="badge" style="background:#ccc">OFF</span>') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>Belum ada data sensor</td></tr>";
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
            const temps = <?= json_encode($chartTemps) ?>;
            const volts = <?= json_encode($chartVolts) ?>;
            const power = <?= json_encode($chartPower) ?>;
            
            if(labels.length > 0) {
                createLineChart('dryerChart', labels, [
                    {
                        label: 'Suhu (°C)',
                        data: temps,
                        borderColor: '#ffb600',
                        backgroundColor: 'rgba(255, 182, 0, 0.1)',
                        fill: true
                    },
                    {
                        label: 'Tegangan AC (V)',
                        data: volts,
                        borderColor: '#009678',
                        backgroundColor: 'transparent'
                    },
                    {
                        label: 'Daya AC (W)',
                        data: power,
                        borderColor: '#04a2b3',
                        backgroundColor: 'transparent'
                    }
                ]);
            }
        });
    </script>
</body>
</html>

