<?php
require_once 'config/database.php';
require_once 'config/functions.php';

// Ambil data overview
$solar = getLatestSolarData($conn);
$dryer = getLatestDryerData($conn);
$cattle = getLatestCattleData($conn);
$perm = getLatestPermacultureData($conn);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Monitoring IoT Pesantren</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">
                IoT<span>Pesantren</span>
            </a>
            <button class="navbar-toggle">☰</button>
            <ul class="navbar-nav">
                <li><a href="index.php" class="active">Beranda</a></li>
                <li><a href="dashboard_panel_surya.php">Panel Surya</a></li>
                <li><a href="dashboard_rumah_pengering.php">Rumah Pengering</a></li>
                <li><a href="dashboard_kandang_sapi.php">Kandang Sapi</a></li>
                <li><a href="dashboard_permaculture.php">Permaculture</a></li>
                <li><a href="login.php" style="background:var(--primary);color:white;border-radius:var(--radius);padding:8px 20px;margin-left:8px;font-weight:500;">Login Admin</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Sistem Monitoring IoT Terpadu</h1>
            <p>Program Pengabdian Masyarakat - Monitoring Panel Surya, Rumah Pengering, Kandang Sapi & Biodigester, serta Permaculture secara real-time.</p>
            <a href="#dashboard" class="hero-btn">Lihat Dashboard</a>
        </div>
    </section>

    <!-- Overview Dashboard -->
    <div id="dashboard" class="container" style="margin-top:-40px;position:relative;z-index:10;">
        <div class="overview-grid">
            
            <!-- Panel Surya -->
            <div class="overview-card" onclick="location.href='dashboard_panel_surya.php'">
                <div class="overview-icon">☀️</div>
                <h3>Panel Surya</h3>
                <div class="overview-value"><?= formatNumber($solar['temperature'] ?? 0, 1) ?> <span class="card-unit">°C</span></div>
                <div class="stat-label">Suhu Panel</div>
                <div class="card-footer" style="justify-content:center;margin-top:16px;">
                    Daya: <?= formatNumber($solar['power'] ?? 0) ?> W
                </div>
            </div>

            <!-- Rumah Pengering -->
            <div class="overview-card" onclick="location.href='dashboard_rumah_pengering.php'">
                <div class="overview-icon">🌡️</div>
                <h3>Rumah Pengering</h3>
                <div class="overview-value"><?= formatNumber($dryer['temperature'] ?? 0, 1) ?> <span class="card-unit">°C</span></div>
                <div class="stat-label">Suhu Ruangan</div>
                <div class="card-footer" style="justify-content:center;margin-top:16px;">
                    Lampu: 
                    <?php if(($dryer['relay_lamp'] ?? 0) == 1): ?>
                        <span class="badge badge-success">ON</span>
                    <?php else: ?>
                        <span class="badge" style="background:#ccc;">OFF</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kandang Sapi -->
            <div class="overview-card" onclick="location.href='dashboard_kandang_sapi.php'">
                <div class="overview-icon">🐄</div>
                <h3>Biodigester</h3>
                <div class="overview-value"><?= formatNumber($cattle['liquid_volume'] ?? 0) ?> <span class="card-unit">L</span></div>
                <div class="stat-label">Volume Cairan</div>
                <div class="card-footer" style="justify-content:center;margin-top:16px;">
                    Tekanan Gas: <?= formatNumber($cattle['gas_pressure'] ?? 0) ?> kPa
                </div>
            </div>

            <!-- Permaculture -->
            <div class="overview-card" onclick="location.href='dashboard_permaculture.php'">
                <div class="overview-icon">🌱</div>
                <h3>Permaculture</h3>
                <div class="overview-value"><?= formatNumber($perm['soil_ph'] ?? 0, 1) ?> <span class="card-unit">pH</span></div>
                <div class="stat-label">pH Tanah</div>
                <div class="card-footer" style="justify-content:center;margin-top:16px;">
                    Status: <?= getStatusClass($perm['soil_ph'] ?? 7, 5.5, 7.5, 4.5, 8.5) == 'normal' ? 'Baik' : 'Perlu Perhatian' ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> Sistem Monitoring IoT Pesantren. Program Pengabdian Masyarakat.</p>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
