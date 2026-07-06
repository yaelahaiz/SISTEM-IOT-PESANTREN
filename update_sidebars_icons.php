<?php
$dir = 'C:/xampp/htdocs/SISTEM-IOT-PESANTREN/admin/';
$files = ['dashboard.php', 'devices.php', 'sensors.php', 'monitoring.php', 'relay_control.php', 'sales.php', 'sales_chart.php', 'settings.php'];

$sidebar_template = <<<EOT
<aside class="sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <div class="logo-full">
                    <h2>IoT<span>Pesantren</span></h2>
                </div>
                <div class="logo-mini">
                    <h2>I<span>P</span></h2>
                </div>
                <button class="sidebar-collapse-btn" onclick="collapseSidebar()" title="Tutup/Buka Sidebar"><i class="bx bx-chevron-left"></i></button>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="{{dashboard_active}}"><i class="bx bx-home-alt nav-icon"></i> <span class="nav-text">Dashboard</span></a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php" class="{{devices_active}}"><i class="bx bx-chip nav-icon"></i> <span class="nav-text">Perangkat</span></a></li>
                <li><a href="sensors.php" class="{{sensors_active}}"><i class="bx bx-tachometer nav-icon"></i> <span class="nav-text">Sensor</span></a></li>
                <li><a href="monitoring.php" class="{{monitoring_active}}"><i class="bx bx-line-chart nav-icon"></i> <span class="nav-text">Monitoring</span></a></li>
                <li><a href="relay_control.php" class="{{relay_control_active}}"><i class="bx bx-power-off nav-icon"></i> <span class="nav-text">Kontrol Relay</span></a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php" class="{{sales_active}}"><i class="bx bx-store-alt nav-icon"></i> <span class="nav-text">Penjualan</span></a></li>
                <li><a href="sales_chart.php" class="{{sales_chart_active}}"><i class="bx bx-pie-chart-alt-2 nav-icon"></i> <span class="nav-text">Grafik Penjualan</span></a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" class="{{settings_active}}"><i class="bx bx-cog nav-icon"></i> <span class="nav-text">Pengaturan</span></a></li>
                <li><a href="../logout.php"><i class="bx bx-log-out nav-icon"></i> <span class="nav-text">Logout</span></a></li>
            </ul>
        </aside>
EOT;

foreach ($files as $file) {
    $path = $dir . $file;
    $content = file_get_contents($path);
    
    // Add boxicons CSS to head if not present
    if (strpos($content, 'boxicons') === false) {
        $content = str_replace('</head>', "    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>\n</head>", $content);
    }
    
    $page_key = str_replace('.php', '_active', $file);
    $new_sidebar = $sidebar_template;
    
    $active_vars = ['dashboard_active', 'devices_active', 'sensors_active', 'monitoring_active', 'relay_control_active', 'sales_active', 'sales_chart_active', 'settings_active'];
    foreach ($active_vars as $var) {
        $val = ($var === $page_key) ? 'active' : '';
        $new_sidebar = str_replace('class="{{' . $var . '}}"', $val ? 'class="active"' : '', $new_sidebar);
    }
    
    // Replace mobile toggle icon
    $content = preg_replace('/<button class="sidebar-toggle-btn"[^>]*>.*?<\/button>/s', '<button class="sidebar-toggle-btn" onclick="toggleSidebar()"><i class="bx bx-menu"></i></button>', $content);
    
    // Regex replace old sidebar
    $content = preg_replace('/<aside class="sidebar" id="adminSidebar">.*?<\/aside>/s', $new_sidebar, $content);
    
    file_put_contents($path, $content);
    echo "Updated $file\n";
}
echo "Done.\n";
?>