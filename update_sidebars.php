<?php
$dir = 'C:/xampp/htdocs/SISTEM-IOT-PESANTREN/admin/';
$files = ['dashboard.php', 'devices.php', 'sensors.php', 'monitoring.php', 'relay_control.php', 'sales.php', 'sales_chart.php', 'settings.php'];

$sidebar_template = <<<EOT
<aside class="sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <h2>Riyadul <span>Muta'alimin</span></h2>
                <small style="display:block; font-size:0.8rem; color:var(--text-light); margin-top:4px;">Powered By Bestari</small>
                <button class="sidebar-collapse-btn" onclick="collapseSidebar()" title="Tutup/Buka Sidebar">&#10094;</button>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="{{dashboard_active}}"><span class="nav-text">Dashboard</span></a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php" class="{{devices_active}}"><span class="nav-text">Perangkat</span></a></li>
                <li><a href="sensors.php" class="{{sensors_active}}"><span class="nav-text">Sensor</span></a></li>
                <li><a href="monitoring.php" class="{{monitoring_active}}"><span class="nav-text">Monitoring</span></a></li>
                <li><a href="relay_control.php" class="{{relay_control_active}}"><span class="nav-text">Kontrol Relay</span></a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php" class="{{sales_active}}"><span class="nav-text">Penjualan</span></a></li>
                <li><a href="sales_chart.php" class="{{sales_chart_active}}"><span class="nav-text">Grafik Penjualan</span></a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" class="{{settings_active}}"><span class="nav-text">Pengaturan</span></a></li>
                <li><a href="../logout.php"><span class="nav-text">Logout</span></a></li>
            </ul>
        </aside>
EOT;

foreach ($files as $file) {
    $path = $dir . $file;
    $content = file_get_contents($path);
    
    // 1. Replace mobile toggle
    $content = preg_replace('/<button class="sidebar-toggle-btn">[^<]*<\/button>/', '<button class="sidebar-toggle-btn" onclick="toggleSidebar()">&#9776;</button>', $content);
    
    // 2. Replace sidebar block
    $page_key = str_replace('.php', '_active', $file);
    $new_sidebar = $sidebar_template;
    
    // Fill the correct active class
    $active_vars = ['dashboard_active', 'devices_active', 'sensors_active', 'monitoring_active', 'relay_control_active', 'sales_active', 'sales_chart_active', 'settings_active'];
    foreach ($active_vars as $var) {
        $val = ($var === $page_key) ? 'active' : '';
        $new_sidebar = str_replace('class="{{' . $var . '}}"', $val ? 'class="active"' : '', $new_sidebar);
    }
    
    // Regex replace old sidebar
    $content = preg_replace('/<aside class="sidebar">.*?<\/aside>/s', $new_sidebar, $content);
    
    // 3. Clean stat-icons if it's dashboard or sales
    if ($file === 'dashboard.php' || $file === 'sales.php') {
        $content = preg_replace('/<div class="stat-icon">.*?<\/div>\s*/s', '', $content);
    }
    
    file_put_contents($path, $content);
    echo "Updated $file\n";
}
echo "Done.\n";
?>
