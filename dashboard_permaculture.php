<?php
require_once 'config/database.php';
require_once 'config/functions.php';

$latest = getLatestPermacultureData($conn);
$ph = $latest['soil_ph'] ?? 7.0;

// Get chart data (last 20 records)
$chartQuery = $conn->query("SELECT soil_ph, DATE_FORMAT(recorded_at, '%H:%i') as time_label FROM permaculture_monitoring ORDER BY id DESC LIMIT 20");
$chartLabels = [];
$chartPh = [];
if ($chartQuery) {
    while($row = $chartQuery->fetch_assoc()) {
        array_unshift($chartLabels, $row['time_label']);
        array_unshift($chartPh, floatval($row['soil_ph']));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Permaculture - IoT Pesantren</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body data-refresh-type="permaculture">

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">IoT<span>Pesantren</span></a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php">Kandang Sapi</a></li>
                <li><a href="dashboard_permaculture.php" class="active">Permaculture</a></li>
            </ul>
        </div>
    </nav>

    <div class="container" style="margin-top:30px; margin-bottom: 50px;">
        <h2 class="section-title">Monitoring Kebun Permaculture</h2>
        
        <div class="dashboard-grid">
            <div class="card" id="perm-ph" style="grid-column: 1 / -1; max-width: 600px; margin: 0 auto;">
                <div class="card-header permaculture">
                    <span class="card-label">pH Tanah Real-time</span>
                    <span class="status-indicator status-<?= getStatusClass($ph, 5.5, 7.5, 4.5, 8.5) ?>"></span>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div class="card-value" style="font-size: 4rem;"><?= formatNumber($ph, 1) ?> <span style="font-size: 1.5rem; color:var(--text-light)">pH</span></div>
                    
                    <div style="margin-top: 30px;">
                        <div class="ph-scale">
                            <div class="ph-marker" style="left: <?= ($ph / 14) * 100 ?>%"></div>
                        </div>
                        <div class="ph-labels">
                            <span>0 (Asam)</span>
                            <span>7 (Netral)</span>
                            <span>14 (Basa)</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer last-updated" style="justify-content: center; margin-top: 20px;">
                    Diperbarui: <?= $latest ? date('d/m/Y H:i:s', strtotime($latest['recorded_at'])) : '-' ?>
                </div>
            </div>
        </div>

        <div class="chart-container">
            <h3>Grafik pH Tanah (20 Data Terakhir)</h3>
            <div class="chart-wrapper">
                <canvas id="phChart"></canvas>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Nilai pH</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $history = $conn->query("SELECT * FROM permaculture_monitoring ORDER BY id DESC LIMIT 15");
                    if ($history && $history->num_rows > 0) {
                        while ($row = $history->fetch_assoc()) {
                            $val = floatval($row['soil_ph']);
                            $status = 'Normal';
                            $badge = 'badge-success';
                            if ($val < 4.5 || $val > 8.5) {
                                $status = 'Bahaya';
                                $badge = 'badge-danger';
                            } elseif ($val < 5.5 || $val > 7.5) {
                                $status = 'Peringatan';
                                $badge = 'badge-warning';
                            }
                            
                            echo "<tr>";
                            echo "<td>" . date('d/m/Y H:i:s', strtotime($row['recorded_at'])) . "</td>";
                            echo "<td><strong>" . formatNumber($val, 1) . "</strong></td>";
                            echo "<td><span class='badge $badge'>$status</span></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='3' class='text-center'>Belum ada data sensor</td></tr>";
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
            const phData = <?= json_encode($chartPh) ?>;
            
            if(labels.length > 0) {
                const ctx = document.getElementById('phChart');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'pH Tanah',
                            data: phData,
                            borderColor: '#059669',
                            backgroundColor: 'rgba(5, 150, 105, 0.1)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'top' },
                            annotation: {}
                        },
                        scales: {
                            y: {
                                min: 0,
                                max: 14,
                                grid: { color: 'rgba(0,0,0,0.05)' },
                                ticks: {
                                    callback: function(value) {
                                        if (value === 0) return '0 (Asam)';
                                        if (value === 7) return '7 (Netral)';
                                        if (value === 14) return '14 (Basa)';
                                        return value;
                                    }
                                }
                            },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
