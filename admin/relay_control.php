<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);
$message = '';
$messageType = '';

// Handle add relay
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitizeInput($_POST['relay_name'] ?? '');
        $pin = (int)$_POST['relay_pin'];
        $deviceId = !empty($_POST['device_id']) ? (int)$_POST['device_id'] : null;
        
        if (empty($name)) {
            $message = 'Nama relay wajib diisi';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO relay_control (relay_name, relay_pin, status, device_id) VALUES (?, ?, 0, ?)");
            $stmt->bind_param("sii", $name, $pin, $deviceId);
            if ($stmt->execute()) {
                $message = 'Relay berhasil ditambahkan';
                $messageType = 'success';
            } else {
                $message = 'Gagal menambahkan relay: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['relay_id'];
        $stmt = $conn->prepare("DELETE FROM relay_control WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Relay berhasil dihapus';
            $messageType = 'success';
        } else {
            $message = 'Gagal menghapus relay';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Get all relays
$relays = $conn->query("SELECT r.*, d.device_name FROM relay_control r LEFT JOIN devices d ON r.device_id = d.id ORDER BY r.id ASC");
$deviceList = $conn->query("SELECT id, device_name FROM devices ORDER BY device_name");
$devices = [];
if ($deviceList) {
    while ($row = $deviceList->fetch_assoc()) {
        $devices[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontrol Relay - IoT Pesantren</title>
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
                <li><a href="devices.php"><span class="nav-icon">&#128241;</span> Perangkat</a></li>
                <li><a href="sensors.php"><span class="nav-icon">&#127777;&#65039;</span> Sensor</a></li>
                <li><a href="monitoring.php"><span class="nav-icon">&#128200;</span> Monitoring</a></li>
                <li><a href="relay_control.php" class="active"><span class="nav-icon">&#128268;</span> Kontrol Relay</a></li>
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
                <h1 class="page-title">Kontrol Relay</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-3">
                <button onclick="openModal('addRelayModal')" class="btn btn-primary">+ Tambah Relay</button>
            </div>

            <div class="dashboard-grid">
                <?php if($relays && $relays->num_rows > 0): ?>
                    <?php while($relay = $relays->fetch_assoc()): ?>
                    <div class="card relay-card <?= $relay['status'] ? 'relay-on' : '' ?>" data-relay-id="<?= $relay['id'] ?>">
                        <div class="card-header">
                            <span class="card-label"><?= htmlspecialchars($relay['relay_name']) ?></span>
                        </div>
                        <div style="text-align:center; padding: 20px 0;">
                            <div class="relay-icon" style="font-size: 3rem;">
                                <?= $relay['status'] ? '&#128161;' : '&#128268;' ?>
                            </div>
                            <div class="relay-status" style="font-size: 1.5rem; font-weight: 700; margin: 10px 0;">
                                <?= $relay['status'] ? 'ON' : 'OFF' ?>
                            </div>
                            <div style="font-size: 0.8rem; color: var(--text-light); margin-bottom: 15px;">
                                Pin: GPIO <?= $relay['relay_pin'] ?><br>
                                Perangkat: <?= $relay['device_name'] ? htmlspecialchars($relay['device_name']) : '-' ?><br>
                                Update: <?= $relay['updated_at'] ? timeAgo($relay['updated_at']) : '-' ?>
                            </div>
                            <div class="d-flex gap-2" style="justify-content:center;">
                                <button onclick="toggleRelay(<?= $relay['id'] ?>, 1)" class="btn btn-sm btn-success" <?= $relay['status'] ? 'disabled' : '' ?>>Nyalakan</button>
                                <button onclick="toggleRelay(<?= $relay['id'] ?>, 0)" class="btn btn-sm btn-danger" <?= !$relay['status'] ? 'disabled' : '' ?>>Matikan</button>
                            </div>
                        </div>
                        <div class="card-footer" style="text-align:center; padding-top:10px; border-top: 1px solid var(--border);">
                            <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus relay ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="relay_id" value="<?= $relay['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline" style="font-size:0.75rem;">Hapus Relay</button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card" style="grid-column: 1/-1; text-align:center; padding: 40px;">
                        <p style="color:var(--text-light)">Belum ada relay yang dikonfigurasi. Klik tombol "Tambah Relay" untuk memulai.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3>Cara Kerja Kontrol Relay</h3>
                </div>
                <div style="padding:15px; font-size:0.9rem; color:var(--text-light); line-height:1.8;">
                    <ol>
                        <li>Admin mengubah status relay melalui halaman ini (ON/OFF)</li>
                        <li>Status relay disimpan di database server</li>
                        <li>ESP32 Gateway secara periodik membaca status relay dari server via API</li>
                        <li>Gateway mengirim perintah relay ke node ESP32 terkait via ESP-NOW</li>
                        <li>Node ESP32 mengaktifkan/menonaktifkan relay fisik sesuai perintah</li>
                    </ol>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Tambah -->
    <div class="modal-overlay" id="addRelayModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Relay Baru</h3>
                <button onclick="closeModal('addRelayModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nama Relay *</label>
                    <input type="text" name="relay_name" class="form-control" required placeholder="Contoh: Lampu Rumah Pengering">
                </div>
                <div class="form-group">
                    <label class="form-label">GPIO Pin *</label>
                    <input type="number" name="relay_pin" class="form-control" required value="26" min="0" max="39">
                </div>
                <div class="form-group">
                    <label class="form-label">Perangkat Terkait</label>
                    <select name="device_id" class="form-control">
                        <option value="">-- Pilih Perangkat --</option>
                        <?php foreach($devices as $dv): ?>
                            <option value="<?= $dv['id'] ?>"><?= htmlspecialchars($dv['device_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="closeModal('addRelayModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
