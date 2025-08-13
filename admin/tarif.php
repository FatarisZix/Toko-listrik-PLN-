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
            $tariff_name = sanitizeInput($_POST['tariff_name']);
            $price_per_kwh = sanitizeInput($_POST['price_per_kwh']);
            $admin_fee = sanitizeInput($_POST['admin_fee']);
            $description = sanitizeInput($_POST['description']);
            
            try {
                // Check if tariff already exists
                $stmt = $pdo->prepare("SELECT id FROM tariffs WHERE tariff_name = ?");
                $stmt->execute([$tariff_name]);
                if ($stmt->fetch()) {
                    $error = "Tarif dengan nama tersebut sudah ada!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tariffs (tariff_name, price_per_kwh, admin_fee, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tariff_name, $price_per_kwh, $admin_fee, $description]);
                    $message = "Tarif berhasil ditambahkan!";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = sanitizeInput($_POST['id']);
            $tariff_name = sanitizeInput($_POST['tariff_name']);
            $price_per_kwh = sanitizeInput($_POST['price_per_kwh']);
            $admin_fee = sanitizeInput($_POST['admin_fee']);
            $description = sanitizeInput($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE tariffs SET tariff_name = ?, price_per_kwh = ?, admin_fee = ?, description = ? WHERE id = ?");
                $stmt->execute([$tariff_name, $price_per_kwh, $admin_fee, $description, $id]);
                $message = "Tarif berhasil diupdate!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM tariffs WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Tarif berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Create tariffs table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tariffs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tariff_name VARCHAR(100) UNIQUE NOT NULL,
        price_per_kwh DECIMAL(10,2) NOT NULL,
        admin_fee DECIMAL(10,2) DEFAULT 2500.00,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default tariffs if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tariffs");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $default_tariffs = [
            ['Rumah Tangga', 1444.70, 2500.00, 'Tarif untuk pelanggan rumah tangga dengan daya 450VA - 2200VA'],
            ['Bisnis Kecil', 1699.53, 3000.00, 'Tarif untuk usaha kecil dengan daya 1300VA - 5500VA'],
            ['Bisnis Menengah', 1956.72, 5000.00, 'Tarif untuk usaha menengah dengan daya di atas 5500VA']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO tariffs (tariff_name, price_per_kwh, admin_fee, description) VALUES (?, ?, ?, ?)");
        foreach ($default_tariffs as $tariff) {
            $stmt->execute($tariff);
        }
    }
} catch (Exception $e) {
    // Table might already exist
}

// Get all tariffs
$stmt = $pdo->query("SELECT * FROM tariffs ORDER BY tariff_name ASC");
$tariffs = $stmt->fetchAll();

// Get specific tariff for editing
$edit_tariff = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tariffs WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_tariff = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tarif - PLN Admin</title>
    <link rel="stylesheet" href="../assets/css/admin/tarif.css">
    <script src="../assets/js/admin/tarif.js"></script>
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
                <h2 class="page-title">🧮 Kelola Tarif Listrik</h2>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ Informasi:</strong> Kelola tarif harga per kWh untuk berbagai jenis pelanggan. Tarif ini akan digunakan untuk perhitungan tagihan otomatis berdasarkan penggunaan listrik.
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

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Tariff Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5><?= $edit_tariff ? '✏️ Edit Tarif' : '➕ Tambah Tarif Baru' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $edit_tariff ? 'edit' : 'add' ?>">
                        <?php if ($edit_tariff): ?>
                            <input type="hidden" name="id" value="<?= $edit_tariff['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Nama Tarif</label>
                            <input type="text" class="form-control" name="tariff_name" 
                                   placeholder="Contoh: Rumah Tangga" required
                                   value="<?= $edit_tariff ? $edit_tariff['tariff_name'] : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Harga per kWh (Rp)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_kwh" 
                                   placeholder="Contoh: 1444.70" required
                                   value="<?= $edit_tariff ? $edit_tariff['price_per_kwh'] : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Biaya Admin (Rp)</label>
                            <input type="number" step="0.01" class="form-control" name="admin_fee" 
                                   placeholder="Contoh: 2500.00" required
                                   value="<?= $edit_tariff ? $edit_tariff['admin_fee'] : '2500.00' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Deskripsi tarif..."><?= $edit_tariff ? $edit_tariff['description'] : '' ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                💾 <?= $edit_tariff ? 'Update' : 'Simpan' ?>
                            </button>
                            <?php if ($edit_tariff): ?>
                                <a href="tarif.php" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tariff List -->
            <div class="content-card">
                <div class="card-header">
                    <h5>💰</h5>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jenis Tarif</th>
                                <th>Harga/kWh</th>
                                <th>Biaya Admin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tariffs)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <div class="empty-icon">🧮</div>
                                            <p>Belum ada data tarif</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tariffs as $tariff): ?>
                                <tr>
                                    <td>
                                        <strong><?= $tariff['tariff_name'] ?></strong><br>
                                        <small style="color: #666;"><?= $tariff['description'] ?></small>
                                    </td>
                                    <td>
                                        <span class="price-display">Rp <?= number_format($tariff['price_per_kwh'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>Rp <?= number_format($tariff['admin_fee'], 0, ',', '.') ?></td>
                                    <td>
                                        <a href="?edit=<?= $tariff['id'] ?>" class="btn btn-success">
                                            ✏️
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus tarif ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $tariff['id'] ?>">
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
    </div>
</body>
</html>