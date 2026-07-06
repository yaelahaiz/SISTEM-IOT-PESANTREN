<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$latest = getLatestDryerData($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Rumah Pengering - IoT Pesantren</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body data-refresh-type="dryer">

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">IoT<span>Pesantren</span></a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php" class="active">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php">Kandang Sapi</a></li>
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
</body>
</html>
