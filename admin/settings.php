<?php
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

requireLogin();
$user = getCurrentUser($conn);
$message = '';
$messageType = '';

// Handle update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $keys = $_POST['setting_key'] ?? [];
        $values = $_POST['setting_value'] ?? [];
        
        $updated = 0;
        for ($i = 0; $i < count($keys); $i++) {
            $key = sanitizeInput($keys[$i]);
            $value = sanitizeInput($values[$i]);
            if (updateSetting($conn, $key, $value)) {
                $updated++;
            }
        }
        $message = "$updated pengaturan berhasil diperbarui";
        $messageType = 'success';
    }
    
    if ($action === 'add_setting') {
        $key = sanitizeInput($_POST['new_key'] ?? '');
        $value = sanitizeInput($_POST['new_value'] ?? '');
        $desc = sanitizeInput($_POST['new_description'] ?? '');
        
        if (empty($key)) {
            $message = 'Key pengaturan wajib diisi';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), description=VALUES(description)");
            $stmt->bind_param("sss", $key, $value, $desc);
            if ($stmt->execute()) {
                $message = 'Pengaturan berhasil ditambahkan';
                $messageType = 'success';
            } else {
                $message = 'Gagal menambahkan: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'delete_setting') {
        $id = (int)$_POST['setting_id'];
        $stmt = $conn->prepare("DELETE FROM settings WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Pengaturan berhasil dihapus';
            $messageType = 'success';
        }
        $stmt->close();
    }

    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPass) || empty($newPass)) {
            $message = 'Password lama dan baru wajib diisi';
            $messageType = 'danger';
        } elseif ($newPass !== $confirmPass) {
            $message = 'Password baru dan konfirmasi tidak cocok';
            $messageType = 'danger';
        } elseif (strlen($newPass) < 6) {
            $message = 'Password minimal 6 karakter';
            $messageType = 'danger';
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $currentUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (password_verify($currentPass, $currentUser['password'])) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("si", $hash, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $message = 'Password berhasil diubah';
                    $messageType = 'success';
                }
                $stmt->close();
            } else {
                $message = 'Password lama salah';
                $messageType = 'danger';
            }
        }
    }
}

// Get all settings
$settings = $conn->query("SELECT * FROM settings ORDER BY id ASC");

// System info
$dbSize = $conn->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetch_assoc();

$tableCounts = [];
$countTables = ['solar_monitoring', 'dryer_monitoring', 'cattle_monitoring', 'permaculture_monitoring', 'sales'];
foreach ($countTables as $t) {
    $r = $conn->query("SELECT COUNT(*) as c FROM $t");
    $tableCounts[$t] = $r ? $r->fetch_assoc()['c'] : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - IoT Pesantren</title>
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
                <li><a href="relay_control.php"><span class="nav-icon">&#128268;</span> Kontrol Relay</a></li>
                <li class="sidebar-divider">PENJUALAN</li>
                <li><a href="sales.php"><span class="nav-icon">&#128176;</span> Penjualan</a></li>
                <li><a href="sales_chart.php"><span class="nav-icon">&#128201;</span> Grafik Penjualan</a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php" class="active"><span class="nav-icon">&#9881;&#65039;</span> Pengaturan</a></li>
                <li><a href="../logout.php"><span class="nav-icon">&#128682;</span> Logout</a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Pengaturan Sistem</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <!-- System Info -->
            <div class="card mb-3">
                <div class="card-header"><h3>Informasi Sistem</h3></div>
                <div style="padding:15px;">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Ukuran Database</div>
                            <div class="stat-value"><?= $dbSize['size_mb'] ?? '0' ?> MB</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Data Solar</div>
                            <div class="stat-value"><?= number_format($tableCounts['solar_monitoring']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Data Dryer</div>
                            <div class="stat-value"><?= number_format($tableCounts['dryer_monitoring']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Data Cattle</div>
                            <div class="stat-value"><?= number_format($tableCounts['cattle_monitoring']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Data Permaculture</div>
                            <div class="stat-value"><?= number_format($tableCounts['permaculture_monitoring']) ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Data Penjualan</div>
                            <div class="stat-value"><?= number_format($tableCounts['sales']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Table -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3>Pengaturan Sistem</h3>
                    <button onclick="openModal('addSettingModal')" class="btn btn-sm btn-primary">+ Tambah</button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_settings">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Value</th>
                                    <th>Deskripsi</th>
                                    <th>Update</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($settings && $settings->num_rows > 0): ?>
                                    <?php while($s = $settings->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <code><?= htmlspecialchars($s['setting_key']) ?></code>
                                            <input type="hidden" name="setting_key[]" value="<?= htmlspecialchars($s['setting_key']) ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="setting_value[]" class="form-control" value="<?= htmlspecialchars($s['setting_value']) ?>" style="min-width:150px">
                                        </td>
                                        <td style="font-size:0.85rem;color:var(--text-light)"><?= htmlspecialchars($s['description']) ?></td>
                                        <td style="font-size:0.8rem"><?= $s['updated_at'] ? timeAgo($s['updated_at']) : '-' ?></td>
                                        <td>
                                            <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus pengaturan ini?')">
                                                <input type="hidden" name="action" value="delete_setting">
                                                <input type="hidden" name="setting_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">Belum ada pengaturan</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding:15px;">
                        <button type="submit" class="btn btn-primary">Simpan Semua Perubahan</button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><h3>Ubah Password Admin</h3></div>
                <form method="POST" style="padding:15px; max-width:400px;">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Password Lama *</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password Baru *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru *</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-warning">Ubah Password</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Modal Tambah Setting -->
    <div class="modal-overlay" id="addSettingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Pengaturan</h3>
                <button onclick="closeModal('addSettingModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_setting">
                <div class="form-group">
                    <label class="form-label">Key *</label>
                    <input type="text" name="new_key" class="form-control" required placeholder="contoh: biodigester_height">
                </div>
                <div class="form-group">
                    <label class="form-label">Value *</label>
                    <input type="text" name="new_value" class="form-control" required placeholder="contoh: 150">
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="new_description" class="form-control" placeholder="Penjelasan pengaturan">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="closeModal('addSettingModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
