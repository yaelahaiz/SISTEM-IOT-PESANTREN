<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);
$message = '';
$messageType = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitizeInput($_POST['device_name'] ?? '');
        $type = sanitizeInput($_POST['device_type'] ?? '');
        $mac = sanitizeInput($_POST['mac_address'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $apiKey = sanitizeInput($_POST['api_key'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name) || empty($type)) {
            $message = 'Nama dan tipe perangkat wajib diisi';
            $messageType = 'danger';
        } else {
            if (empty($apiKey)) {
                $apiKey = strtoupper($type) . '_API_KEY_' . date('Y') . '_' . bin2hex(random_bytes(4));
            }
            $stmt = $conn->prepare("INSERT INTO devices (device_name, device_type, mac_address, location, api_key, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssi", $name, $type, $mac, $location, $apiKey, $isActive);
            if ($stmt->execute()) {
                $message = 'Perangkat berhasil ditambahkan';
                $messageType = 'success';
            } else {
                $message = 'Gagal menambahkan perangkat: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['device_id'];
        $name = sanitizeInput($_POST['device_name'] ?? '');
        $type = sanitizeInput($_POST['device_type'] ?? '');
        $mac = sanitizeInput($_POST['mac_address'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $apiKey = sanitizeInput($_POST['api_key'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE devices SET device_name=?, device_type=?, mac_address=?, location=?, api_key=?, is_active=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sssssii", $name, $type, $mac, $location, $apiKey, $isActive, $id);
        if ($stmt->execute()) {
            $message = 'Perangkat berhasil diperbarui';
            $messageType = 'success';
        } else {
            $message = 'Gagal memperbarui perangkat';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['device_id'];
        $stmt = $conn->prepare("DELETE FROM devices WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Perangkat berhasil dihapus';
            $messageType = 'success';
        } else {
            $message = 'Gagal menghapus perangkat';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all devices
$devices = $conn->query("SELECT * FROM devices ORDER BY id ASC");
$deviceTypes = ['gateway', 'node_solar', 'node_dryer', 'node_cattle', 'node_permaculture'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Perangkat - IoT Pesantren</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <button class="sidebar-toggle-btn">&#9776;</button>
    
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>IoT<span>Pesantren</span></h2>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php"><span class="nav-icon">&#128202;</span> Dashboard</a></li>
                <li class="sidebar-divider">PERANGKAT</li>
                <li><a href="devices.php" class="active"><span class="nav-icon">&#128241;</span> Perangkat</a></li>
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

        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Kelola Perangkat ESP32</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-3">
                <button onclick="openModal('addDeviceModal')" class="btn btn-primary">+ Tambah Perangkat</button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Daftar Perangkat</h3>
                    <input type="text" id="searchDevice" data-search-table="deviceTable" class="form-control" style="max-width:250px" placeholder="Cari perangkat...">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="deviceTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama</th>
                                <th>Tipe</th>
                                <th>MAC Address</th>
                                <th>Lokasi</th>
                                <th>API Key</th>
                                <th>Status</th>
                                <th>Last Seen</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($devices && $devices->num_rows > 0): ?>
                                <?php while($d = $devices->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $d['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($d['device_name']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= htmlspecialchars($d['device_type']) ?></span></td>
                                    <td><code><?= htmlspecialchars($d['mac_address']) ?></code></td>
                                    <td><?= htmlspecialchars($d['location']) ?></td>
                                    <td><code style="font-size:0.75rem"><?= htmlspecialchars($d['api_key']) ?></code></td>
                                    <td>
                                        <?php if($d['is_active']): ?>
                                            <span class="badge badge-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $d['last_seen'] ? timeAgo($d['last_seen']) : '-' ?></td>
                                    <td>
                                        <button onclick='editDevice(<?= json_encode($d) ?>)' class="btn btn-sm btn-warning">Edit</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus perangkat ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="device_id" value="<?= $d['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center">Belum ada perangkat terdaftar</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah -->
    <div class="modal-overlay" id="addDeviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Perangkat Baru</h3>
                <button onclick="closeModal('addDeviceModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nama Perangkat *</label>
                    <input type="text" name="device_name" class="form-control" required placeholder="Contoh: ESP32 Gateway Utama">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Perangkat *</label>
                    <select name="device_type" class="form-control" required>
                        <option value="">-- Pilih Tipe --</option>
                        <?php foreach($deviceTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">MAC Address</label>
                    <input type="text" name="mac_address" class="form-control" placeholder="AA:BB:CC:DD:EE:FF">
                </div>
                <div class="form-group">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="location" class="form-control" placeholder="Contoh: Ruang Server Pesantren">
                </div>
                <div class="form-group">
                    <label class="form-label">API Key (kosongkan untuk auto-generate)</label>
                    <input type="text" name="api_key" class="form-control" placeholder="Otomatis jika kosong">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" value="1" checked> Aktifkan perangkat
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="closeModal('addDeviceModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal-overlay" id="editDeviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Perangkat</h3>
                <button onclick="closeModal('editDeviceModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="device_id" id="edit_device_id">
                <div class="form-group">
                    <label class="form-label">Nama Perangkat *</label>
                    <input type="text" name="device_name" id="edit_device_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipe Perangkat *</label>
                    <select name="device_type" id="edit_device_type" class="form-control" required>
                        <?php foreach($deviceTypes as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">MAC Address</label>
                    <input type="text" name="mac_address" id="edit_mac_address" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="location" id="edit_location" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">API Key</label>
                    <input type="text" name="api_key" id="edit_api_key" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1"> Aktifkan perangkat
                    </label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <button type="button" onclick="closeModal('editDeviceModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    function editDevice(device) {
        document.getElementById('edit_device_id').value = device.id;
        document.getElementById('edit_device_name').value = device.device_name;
        document.getElementById('edit_device_type').value = device.device_type;
        document.getElementById('edit_mac_address').value = device.mac_address || '';
        document.getElementById('edit_location').value = device.location || '';
        document.getElementById('edit_api_key').value = device.api_key || '';
        document.getElementById('edit_is_active').checked = device.is_active == 1;
        openModal('editDeviceModal');
    }
    </script>
</body>
</html>
