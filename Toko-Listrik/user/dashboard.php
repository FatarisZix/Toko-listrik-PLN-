<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Redirect admin to admin dashboard
if (isAdmin()) {
    header('Location: /Toko-Listrik/admin/dashboard.php');
    exit();
}

// Get user's customers and bills
$customers = [];
$recent_bills = [];
$total_unpaid = 0;
$unpaid_count = 0;

// Get customers for current user
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customers = $stmt->fetchAll();

if (!empty($customers)) {
    $customer_ids = array_column($customers, 'id');
    $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
    
    // Get bills with tariff info
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type,
               t.price_per_kwh, t.admin_fee
        FROM bills b 
        JOIN customers c ON b.customer_id = c.id 
        LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
        WHERE b.customer_id IN ($placeholders)
        ORDER BY b.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute($customer_ids);
    $recent_bills = $stmt->fetchAll();
    
    // Get total unpaid amount and count
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as total_unpaid, COUNT(*) as unpaid_count
        FROM bills b 
        WHERE b.customer_id IN ($placeholders) AND b.status = 'unpaid'
    ");
    $stmt->execute($customer_ids);
    $unpaid_data = $stmt->fetch();
    $total_unpaid = $unpaid_data['total_unpaid'] ?? 0;
    $unpaid_count = $unpaid_data['unpaid_count'] ?? 0;
}

// Get all tariffs for display
$stmt = $pdo->query("SELECT * FROM tariffs ORDER BY tariff_name ASC");
$tariffs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - PLN Payment</title>
    <link rel="stylesheet" href="../assets/css/user/dashboard.css">
    <script src="../assets/js/user/dashboard.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="#" class="navbar-brand">
                ⚡ PLN Payment
            </a>
            <div class="navbar-user">
                <button class="dropdown-toggle" onclick="toggleDropdown()">
                    👤 <?= $_SESSION['full_name'] ?> ▼
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="/Toko-Listrik/logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>🏠 Dashboard Pelanggan</h2>
            <p>Selamat datang, <?= $_SESSION['full_name'] ?>! Kelola pembayaran listrik Anda dengan mudah dan aman.</p>
        </div>

        <?php if (isset($_SESSION['payment_success'])): ?>
    <div class="alert alert-success" style="
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        position: relative;
    ">
        ✅ <?= $_SESSION['payment_success'] ?>
    </div>
    <?php unset($_SESSION['payment_success']); ?>
<?php endif; ?>

        <?php if (empty($customers)): ?>
            <div class="alert-info">
                <h5>ℹ️ Informasi Akun</h5>
                <p class="mb-0">Akun Anda belum memiliki data pelanggan listrik. Silakan hubungi admin untuk menambahkan data pelanggan Anda ke sistem.</p>
            </div>
        <?php else: ?>
            <!-- Action Prompt for Meter Reading -->
            

            <!-- Statistics -->
            <div class="stats-grid">
                
                <div class="stat-card">
                    <div class="stat-icon">📄</div>
                    <div class="stat-number"><?= $unpaid_count ?></div>
                    <div class="stat-label">Tagihan Belum Bayar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">Rp <?= number_format($total_unpaid, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Belum Dibayar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-number">Normal</div>
                    <div class="stat-label">Status Listrik</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Bills Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>🧾 Tagihan Listrik Terbaru</h5>
                    </div>
                    
                    <?php if (empty($recent_bills)): ?>
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <p>Belum ada tagihan tersedia</p>
                                <br>
                                <a href="lapor-meter.php" class="btn btn-view">
                                    📊 Lapor Meter Reading
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No. Pelanggan</th>
                                        <th>Periode</th>
                                        <th>Penggunaan</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                        <th>Catatan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bills as $bill): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $bill['customer_number'] ?></strong><br>
                                            <small><?= $bill['customer_name'] ?></small>
                                        </td>
                                        <td><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></td>
                                        <td><?= number_format($bill['kwh_usage']) ?> kWh</td>
                                        <td>
                                            <span class="price-display">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></span><br>
                                            <small style="color: #666;">
                                                kWh: Rp <?= number_format($bill['amount'], 0, ',', '.') ?> + 
                                                Admin: Rp <?= number_format($bill['admin_fee'], 0, ',', '.') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($bill['status'] == 'paid'): ?>
                                                <span class="badge badge-success">✅ Lunas</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">⏱️ Belum Bayar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bill['status'] == 'unpaid'): ?>
                                                <a href="struk-tagihan.php?bill_id=<?= $bill['id'] ?>" class="btn btn-pay">
                                                🧾 Bayar
                                                </a>
                                            <?php else: ?>
                                                <span class="badge badge-success">Lunas</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $bill['admin_note'] ?? '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-body" style="text-align: center; padding-top: 20px;">
                            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                                <a href="lapor-meter.php" class="btn btn-view" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                                    📊 Lapor Meter Reading
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tariff Info -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>💰 Informasi Tarif Listrik</h5>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($tariffs)): ?>
                            <div style="padding: 25px; text-align: center; color: #666;">
                                Informasi tarif belum tersedia
                            </div>
                        <?php else: ?>
                            <?php foreach ($tariffs as $tariff): ?>
                            <div class="tariff-item">
                                <div class="tariff-name"><?= $tariff['tariff_name'] ?></div>
                                <div class="tariff-price">
                                    Rp <?= number_format($tariff['price_per_kwh'], 0, ',', '.') ?>/kWh
                                </div>
                                <div style="color: #666; font-size: 0.85rem;">
                                    Biaya Admin: Rp <?= number_format($tariff['admin_fee'], 0, ',', '.') ?>
                                </div>
                                <div class="tariff-desc"><?= $tariff['description'] ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>