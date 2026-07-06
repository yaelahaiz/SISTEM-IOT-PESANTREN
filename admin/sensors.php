<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);
$message = '';
$messageType = '';

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitizeInput($_POST['sensor_name'] ?? '');
        $type = sanitizeInput($_POST['sensor_type'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $deviceId = !empty($_POST['device_id']) ? (int)$_POST['device_id'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($type)) {
            $message = 'Nama dan tipe sensor wajib diisi';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO sensors (sensor_name, sensor_type, unit, location, device_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssii", $name, $type, $unit, $location, $deviceId, $isActive);
            if ($stmt->execute()) {
                $message = 'Sensor berhasil ditambahkan';
                $messageType = 'success';
            } else {
                $message = 'Gagal menambahkan sensor: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['sensor_id'];
        $name = sanitizeInput($_POST['sensor_name'] ?? '');
        $type = sanitizeInput($_POST['sensor_type'] ?? '');
        $unit = sanitizeInput($_POST['unit'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $deviceId = !empty($_POST['device_id']) ? (int)$_POST['device_id'] : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE sensors SET sensor_name=?, sensor_type=?, unit=?, location=?, device_id=?, is_active=? WHERE id=?");
        $stmt->bind_param("ssssiis", $name, $type, $unit, $location, $deviceId, $isActive, $id);
        if ($stmt->execute()) {
            $message = 'Sensor berhasil diperbarui';
            $messageType = 'success';
        } else {
            $message = 'Gagal memperbarui sensor';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['sensor_id'];
        $stmt = $conn->prepare("DELETE FROM sensors WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Sensor berhasil dihapus';
            $messageType = 'success';
        } else {
            $message = 'Gagal menghapus sensor';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all sensors with device name
$sensors = $conn->query("SELECT s.*, d.device_name FROM sensors s LEFT JOIN devices d ON s.device_id = d.id ORDER BY s.id ASC");
$deviceList = $conn->query("SELECT id, device_name FROM devices ORDER BY device_name");
$devices = [];
if ($deviceList) {
    while ($row = $deviceList->fetch_assoc()) {
        $devices[] = $row;
    }
}

$sensorTypes = ['DHT22', 'PZEM-017', 'PZEM-016', 'HC-SR04', 'Soil Moisture', 'pH RS485', 'Pressure Analog', 'Relay'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Sensor - Riyadul Muta'alimin</title>
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
                <li><a href="sensors.php" class="active"><i class="bx bx-tachometer nav-icon"></i> <span class="nav-text">Sensor</span></a></li>
                <li><a href="monitoring.php" ><i class="bx bx-line-chart nav-icon"></i> <span class="nav-text">Monitoring</span></a></li>
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
                <h1 class="page-title">Kelola Sensor</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-3">
                <button onclick="openModal('addSensorModal')" class="btn btn-primary">+ Tambah Sensor</button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Daftar Sensor</h3>
                    <input type="text" id="searchSensor" data-search-table="sensorTable" class="form-control" style="max-width:250px" placeholder="Cari sensor...">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="sensorTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Sensor</th>
                                <th>Tipe</th>
                                <th>Satuan</th>
                                <th>Lokasi</th>
                                <th>Perangkat</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($sensors && $sensors->num_rows > 0): ?>
                                <?php while($s = $sensors->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $s['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($s['sensor_name']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($s['sensor_type']) ?></span></td>
                                    <td><?= htmlspecialchars($s['unit']) ?></td>
                                    <td><?= htmlspecialchars($s['location']) ?></td>
                                    <td><?= $s['device_name'] ? htmlspecialchars($s['device_name']) : '<em>-</em>' ?></td>
                                    <td>
                                        <?php if($s['is_active']): ?>
                                            <span class="badge badge-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick='editSensor(<?= json_encode($s) ?>)' class="btn btn-sm btn-warning">Edit</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus sensor ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="sensor_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">Belum ada sensor terdaftar</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah -->
    <div class="modal-overlay" id="addSensorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Sensor Baru</h3>
                <button onclick="closeModal('addSensorModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nama Sensor *</label>
                    <input type="text" name="sensor_name" class="form-control" required placeholder="Contoh: Sensor Suhu Panel">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Sensor *</label>
                    <select name="sensor_type" class="form-control" required>
                        <option value="">-- Pilih Tipe --</option>
                        <?php foreach($sensorTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" class="form-control" placeholder="Contoh: °C, V, A, pH">
                </div>
                <div class="form-group">
                    <label class="form-label">Lokasi Pemasangan</label>
                    <input type="text" name="location" class="form-control" placeholder="Contoh: Panel Surya Atap">
                </div>
                <div class="form-group">
                    <label class="form-label">Perangkat Terkait</label>
                    <select name="device_id" class="form-control">
                        <option value="">-- Tidak Terkait --</option>
                        <?php foreach($devices as $dv): ?>
                            <option value="<?= $dv['id'] ?>"><?= htmlspecialchars($dv['device_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" value="1" checked> Aktifkan sensor
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="closeModal('addSensorModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal-overlay" id="editSensorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Sensor</h3>
                <button onclick="closeModal('editSensorModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="sensor_id" id="edit_sensor_id">
                <div class="form-group">
                    <label class="form-label">Nama Sensor *</label>
                    <input type="text" name="sensor_name" id="edit_sensor_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Sensor *</label>
                    <select name="sensor_type" id="edit_sensor_type" class="form-control" required>
                        <?php foreach($sensorTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" id="edit_unit" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Lokasi Pemasangan</label>
                    <input type="text" name="location" id="edit_sensor_location" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Perangkat Terkait</label>
                    <select name="device_id" id="edit_device_id" class="form-control">
                        <option value="">-- Tidak Terkait --</option>
                        <?php foreach($devices as $dv): ?>
                            <option value="<?= $dv['id'] ?>"><?= htmlspecialchars($dv['device_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" id="edit_sensor_active" value="1"> Aktifkan sensor
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <button type="button" onclick="closeModal('editSensorModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    function editSensor(sensor) {
        document.getElementById('edit_sensor_id').value = sensor.id;
        document.getElementById('edit_sensor_name').value = sensor.sensor_name;
        document.getElementById('edit_sensor_type').value = sensor.sensor_type;
        document.getElementById('edit_unit').value = sensor.unit || '';
        document.getElementById('edit_sensor_location').value = sensor.location || '';
        document.getElementById('edit_device_id').value = sensor.device_id || '';
        document.getElementById('edit_sensor_active').checked = sensor.is_active == 1;
        openModal('editSensorModal');
    }
    </script>
</body>
</html>

