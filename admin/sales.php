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
        $productName = sanitizeInput($_POST['product_name'] ?? '');
        $capital = (float)$_POST['capital'];
        $revenue = (float)$_POST['revenue'];
        $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        if (empty($productName) || $capital < 0 || $revenue < 0) {
            $message = 'Nama produk, modal, dan pendapatan wajib diisi dengan benar';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO sales (product_name, capital, revenue, sale_date, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sddss", $productName, $capital, $revenue, $saleDate, $notes);
            if ($stmt->execute()) {
                $message = 'Data penjualan berhasil ditambahkan';
                $messageType = 'success';
            } else {
                $message = 'Gagal menambahkan data: ' . $conn->error;
                $messageType = 'danger';
            }
            $stmt->close();
        }
    }
    
    if ($action === 'edit') {
        $id = (int)$_POST['sale_id'];
        $productName = sanitizeInput($_POST['product_name'] ?? '');
        $capital = (float)$_POST['capital'];
        $revenue = (float)$_POST['revenue'];
        $saleDate = $_POST['sale_date'] ?? date('Y-m-d');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("UPDATE sales SET product_name=?, capital=?, revenue=?, sale_date=?, notes=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("sddssi", $productName, $capital, $revenue, $saleDate, $notes, $id);
        if ($stmt->execute()) {
            $message = 'Data penjualan berhasil diperbarui';
            $messageType = 'success';
        } else {
            $message = 'Gagal memperbarui data';
            $messageType = 'danger';
        }
        $stmt->close();
    }
    
    if ($action === 'delete') {
        $id = (int)$_POST['sale_id'];
        $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Data penjualan berhasil dihapus';
            $messageType = 'success';
        } else {
            $message = 'Gagal menghapus data';
            $messageType = 'danger';
        }
        $stmt->close();
    }
}

// Summary stats
$stats = $conn->query("SELECT COUNT(*) as total_sales, COALESCE(SUM(capital),0) as total_capital, COALESCE(SUM(revenue),0) as total_revenue, COALESCE(SUM(profit),0) as total_profit FROM sales")->fetch_assoc();

// Get sales data with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalRows = $conn->query("SELECT COUNT(*) as c FROM sales")->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalRows / $perPage));

$sales = $conn->query("SELECT * FROM sales ORDER BY sale_date DESC, id DESC LIMIT $perPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencatatan Penjualan - IoT Pesantren</title>
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
                <li><a href="sales.php" class="active"><span class="nav-icon">&#128176;</span> Penjualan</a></li>
                <li><a href="sales_chart.php"><span class="nav-icon">&#128201;</span> Grafik Penjualan</a></li>
                <li class="sidebar-divider">SISTEM</li>
                <li><a href="settings.php"><span class="nav-icon">&#9881;&#65039;</span> Pengaturan</a></li>
                <li><a href="../logout.php"><span class="nav-icon">&#128682;</span> Logout</a></li>
            </ul>
        </aside>

        <main class="admin-content">
            <header class="admin-header">
                <h1 class="page-title">Pencatatan Penjualan</h1>
                <div class="admin-user-info">
                    <span>Selamat datang, <strong><?= htmlspecialchars($user['full_name']) ?></strong></span>
                </div>
            </header>

            <?php if($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">&#128230;</div>
                    <div class="stat-value"><?= $stats['total_sales'] ?></div>
                    <div class="stat-label">Total Transaksi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128181;</div>
                    <div class="stat-value" style="font-size:1.3rem; line-height:2rem; color:var(--danger)"><?= formatRupiah($stats['total_capital']) ?></div>
                    <div class="stat-label">Total Modal</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128176;</div>
                    <div class="stat-value" style="font-size:1.3rem; line-height:2rem; color:var(--primary)"><?= formatRupiah($stats['total_revenue']) ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">&#128200;</div>
                    <div class="stat-value" style="font-size:1.3rem; line-height:2rem; color:var(--secondary)"><?= formatRupiah($stats['total_profit']) ?></div>
                    <div class="stat-label">Total Keuntungan</div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-3">
                <button onclick="openModal('addSaleModal')" class="btn btn-primary">+ Tambah Penjualan</button>
                <a href="sales_chart.php" class="btn btn-info">Lihat Grafik</a>
                <button onclick="exportTableCSV('salesTable', 'penjualan.csv')" class="btn btn-success">Export CSV</button>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Riwayat Penjualan</h3>
                    <input type="text" id="searchSales" data-search-table="salesTable" class="form-control" style="max-width:250px" placeholder="Cari penjualan...">
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="salesTable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Produk</th>
                                <th>Modal</th>
                                <th>Pendapatan</th>
                                <th>Keuntungan</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($sales && $sales->num_rows > 0): ?>
                                <?php $no = $offset + 1; while($s = $sales->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d/m/Y', strtotime($s['sale_date'])) ?></td>
                                    <td><strong><?= htmlspecialchars($s['product_name']) ?></strong></td>
                                    <td><?= formatRupiah($s['capital']) ?></td>
                                    <td><?= formatRupiah($s['revenue']) ?></td>
                                    <td>
                                        <span style="color: <?= $s['profit'] >= 0 ? 'var(--secondary)' : 'var(--danger)' ?>; font-weight:600;">
                                            <?= formatRupiah($s['profit']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($s['notes'] ?? '-') ?></td>
                                    <td>
                                        <button onclick='editSale(<?= json_encode($s) ?>)' class="btn btn-sm btn-warning">Edit</button>
                                        <form method="POST" style="display:inline" onsubmit="return confirmDelete('Hapus data penjualan ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">Belum ada data penjualan</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if($totalPages > 1): ?>
                <div class="pagination" style="padding:15px; text-align:center;">
                    <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline">&laquo; Prev</a>
                    <?php endif; ?>
                    <?php for($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                        <a href="?page=<?= $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline">Next &raquo;</a>
                    <?php endif; ?>
                    <span style="margin-left:10px; color:var(--text-light); font-size:0.85rem;">Halaman <?= $page ?> dari <?= $totalPages ?></span>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Tambah -->
    <div class="modal-overlay" id="addSaleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Data Penjualan</h3>
                <button onclick="closeModal('addSaleModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Nama Produk *</label>
                    <input type="text" name="product_name" class="form-control" required placeholder="Contoh: Pupuk Kompos">
                </div>
                <div class="form-group">
                    <label class="form-label">Modal (Rp) *</label>
                    <input type="number" name="capital" id="capital" class="form-control" required min="0" step="100" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Pendapatan / Uang Didapat (Rp) *</label>
                    <input type="number" name="revenue" id="revenue" class="form-control" required min="0" step="100" placeholder="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Keuntungan (otomatis)</label>
                    <input type="number" id="profit" class="form-control" readonly style="font-weight:700">
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Penjualan *</label>
                    <input type="date" name="sale_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Opsional"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Simpan</button>
                    <button type="button" onclick="closeModal('addSaleModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal-overlay" id="editSaleModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Data Penjualan</h3>
                <button onclick="closeModal('editSaleModal')" class="modal-close">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="sale_id" id="edit_sale_id">
                <div class="form-group">
                    <label class="form-label">Nama Produk *</label>
                    <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Modal (Rp) *</label>
                    <input type="number" name="capital" id="edit_capital" class="form-control" required min="0" step="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Pendapatan (Rp) *</label>
                    <input type="number" name="revenue" id="edit_revenue" class="form-control" required min="0" step="100">
                </div>
                <div class="form-group">
                    <label class="form-label">Keuntungan (otomatis)</label>
                    <input type="number" id="edit_profit" class="form-control" readonly style="font-weight:700">
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Penjualan *</label>
                    <input type="date" name="sale_date" id="edit_sale_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Perbarui</button>
                    <button type="button" onclick="closeModal('editSaleModal')" class="btn btn-outline">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    function editSale(sale) {
        document.getElementById('edit_sale_id').value = sale.id;
        document.getElementById('edit_product_name').value = sale.product_name;
        document.getElementById('edit_capital').value = sale.capital;
        document.getElementById('edit_revenue').value = sale.revenue;
        document.getElementById('edit_profit').value = sale.profit;
        document.getElementById('edit_sale_date').value = sale.sale_date;
        document.getElementById('edit_notes').value = sale.notes || '';
        
        // Auto-calculate profit on edit
        const editCap = document.getElementById('edit_capital');
        const editRev = document.getElementById('edit_revenue');
        const editProfit = document.getElementById('edit_profit');
        
        function calcEditProfit() {
            const c = parseFloat(editCap.value) || 0;
            const r = parseFloat(editRev.value) || 0;
            editProfit.value = r - c;
            editProfit.style.color = (r - c) >= 0 ? 'var(--secondary)' : 'var(--danger)';
        }
        editCap.oninput = calcEditProfit;
        editRev.oninput = calcEditProfit;
        calcEditProfit();
        
        openModal('editSaleModal');
    }
    </script>
</body>
</html>
