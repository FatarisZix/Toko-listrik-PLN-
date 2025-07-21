<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $user_id = !empty($_POST['user_id']) ? sanitizeInput($_POST['user_id']) : null;
            $customer_number = sanitizeInput($_POST['customer_number']);
            $name = sanitizeInput($_POST['name']);
            $address = sanitizeInput($_POST['address']);
            $tariff_type = sanitizeInput($_POST['tariff_type']);
            $power_capacity = sanitizeInput($_POST['power_capacity']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO customers (user_id, customer_number, name, address, tariff_type, power_capacity) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $customer_number, $name, $address, $tariff_type, $power_capacity]);
                $message = "Pelanggan berhasil ditambahkan!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Pelanggan berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get all customers with user info
$stmt = $pdo->query("
    SELECT c.*, u.full_name as user_name, u.username 
    FROM customers c 
    LEFT JOIN users u ON c.user_id = u.id 
    ORDER BY c.created_at DESC
");
$customers = $stmt->fetchAll();

// Get all users for dropdown
$stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'user' ORDER BY full_name");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelanggan Listrik - PLN Admin</title>
    <link rel="stylesheet" href="../assets/css/admin/pelanggan-listrik.css">
    <script src="../assets/js/admin/pelanggan-listrik.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="navbar-brand">
                ⚡ PLN Admin Panel
            </a>
            <div class="navbar-user">
                <button class="dropdown-toggle" onclick="toggleDropdown()">
                    👤 <?= $_SESSION['full_name'] ?> ▼
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="dashboard.php" class="dropdown-item">🏠 Dashboard</a>
                    <a href="/Toko-Listrik/logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Kembali
                </a>
                <h2 class="page-title">👥 Kelola Pelanggan Listrik</h2>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                ✅ <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <!-- Customers Table -->
        <div class="content-card">
            <div class="card-header">
                <h5>📋 Daftar Pelanggan Listrik</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th>Tarif</th>
                            <th>Daya</th>
                            <th>User</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-icon">📭</div>
                                        <p>Belum ada data pelanggan</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><strong><?= $customer['customer_number'] ?></strong></td>
                                <td><?= $customer['name'] ?></td>
                                <td><?= substr($customer['address'], 0, 50) ?>...</td>
                                <td><span class="badge badge-info"><?= $customer['tariff_type'] ?></span></td>
                                <td><?= $customer['power_capacity'] ?> VA</td>
                                <td><?= $customer['user_name'] ?? 'Tidak ada' ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus pelanggan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal" id="addModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">➕ Tambah Pelanggan Baru</h5>
                    <button type="button" class="close" onclick="closeModal()">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">No. Pelanggan</label>
                                <input type="text" class="form-control" name="customer_number" 
                                       placeholder="Contoh: 123456789012" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nama Pelanggan</label>
                                <input type="text" class="form-control" name="name" 
                                       placeholder="Nama lengkap pelanggan" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alamat</label>
                            <textarea class="form-control" name="address" rows="3" 
                                      placeholder="Alamat lengkap pelanggan" required></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tarif</label>
                                <select class="form-control" name="tariff_type" required>
                                    <option value="">Pilih Tarif</option>
                                    <option value="Rumah Tangga">Rumah Tangga</option>
                                    <option value="Bisnis Kecil">Bisnis Kecil</option>
                                    <option value="Bisnis Menengah">Bisnis Menengah</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Daya (VA)</label>
                                <select class="form-control" name="power_capacity" required>
                                    <option value="">Pilih Daya</option>
                                    <option value="450">450 VA</option>
                                    <option value="900">900 VA</option>
                                    <option value="1300">1300 VA</option>
                                    <option value="2200">2200 VA</option>
                                    <option value="3500">3500 VA</option>
                                    <option value="5500">5500 VA</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">User Pemilik</label>
                        <div class="form-group">
                            <label class="form-label">User Pemilik</label>
                            <select class="form-control" name="user_id">
                                <option value="">Pilih User (Opsional)</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= $user['full_name'] ?> (<?= $user['username'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            💾 Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>