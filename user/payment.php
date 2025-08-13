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

$error = '';
$success = '';

// Get bill ID from URL
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if (!$bill_id) {
    header('Location: dashboard.php');
    exit();
}

// Get bill details with customer info
$stmt = $pdo->prepare("
    SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type, c.user_id,
           t.price_per_kwh, t.admin_fee
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

// Check if bill exists and belongs to current user
if (!$bill || $bill['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// Check if bill is already paid
if ($bill['status'] == 'paid') {
    $error = 'Tagihan ini sudah dibayar!';
}

// Handle payment submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'pay' && $bill['status'] == 'unpaid') {
    $payment_method = sanitizeInput($_POST['payment_method']);
    $customer_name = sanitizeInput($_POST['customer_name']);
    $customer_phone = sanitizeInput($_POST['customer_phone']);
    
    try {
        // Generate unique transaction ID
        $transaction_id = 'TRX' . date('YmdHis') . rand(1000, 9999);
        
        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (bill_id, user_id, payment_method, payment_amount, transaction_id, status) 
            VALUES (?, ?, ?, ?, ?, 'success')
        ");
        $stmt->execute([
            $bill_id, 
            $_SESSION['user_id'], 
            $payment_method, 
            $bill['total_amount'], 
            $transaction_id
        ]);
        
        // Update bill status to paid
        $stmt = $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
        $stmt->execute([$bill_id]);
        
        $success = 'Pembayaran berhasil! ID Transaksi: ' . $transaction_id;
        
        // Refresh bill data
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type, c.user_id,
                   t.price_per_kwh, t.admin_fee
            FROM bills b
            JOIN customers c ON b.customer_id = c.id
            LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Listrik - PLN Payment</title>
    <link rel="stylesheet" href="../assets/css/user/payment.css">
    <script src="../assets/js/user/payment.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="navbar-brand">
                ⚡ PLN Payment
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
            <a href="dashboard.php" class="btn btn-secondary">
                ← Kembali
            </a>
            <h2 class="page-title">💳 Pembayaran Listrik</h2>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($bill['status'] == 'paid' && $success): ?>
            <!-- Success State -->
            <div class="content-card">
                <div class="card-body">
                    <div class="success-message">
                        <div class="success-icon">✅</div>
                        <h3>Pembayaran Berhasil!</h3>
                        <p>Tagihan listrik Anda telah berhasil dibayar.</p>
                        <div style="margin: 20px 0;">
                            <strong>No. Pelanggan:</strong> <?= $bill['customer_number'] ?><br>
                            <strong>Periode:</strong> <?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?><br>
                            <strong>Total Dibayar:</strong> Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <div class="content-grid">
                <!-- Bill Details -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>🧾 Detail Tagihan</h5>
                    </div>
                    <div class="card-body">
                        <div class="bill-detail">
                            <span class="label">No. Pelanggan:</span>
                            <span class="value"><?= $bill['customer_number'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Nama:</span>
                            <span class="value"><?= $bill['customer_name'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Periode:</span>
                            <span class="value"><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Tarif:</span>
                            <span class="value"><?= $bill['tariff_type'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Penggunaan:</span>
                            <span class="value"><?= number_format($bill['kwh_usage']) ?> kWh</span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Biaya kWh:</span>
                            <span class="value">Rp <?= number_format($bill['amount'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Biaya Admin:</span>
                            <span class="value">Rp <?= number_format($bill['admin_fee'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Total Bayar:</span>
                            <span class="value">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Jatuh Tempo:</span>
                            <span class="value"><?= date('d F Y', strtotime($bill['due_date'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>💳 Metode Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($bill['status'] == 'unpaid'): ?>
                            <form method="POST" id="paymentForm">
                                <input type="hidden" name="action" value="pay">
                                
                                <div class="form-group">
                                    <label class="form-label">Pilih Metode Pembayaran</label>
                                    
                                    <div class="payment-method" onclick="selectMethod('bank_transfer')">
                                        <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" required>
                                        <div class="method-icon">🏦</div>
                                        <div class="method-info">
                                            <div class="method-name">Transfer Bank</div>
                                            <div class="method-desc">BCA, Mandiri, BNI, BRI</div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method" onclick="selectMethod('ewallet')">
                                        <input type="radio" name="payment_method" value="ewallet" id="ewallet" required>
                                        <div class="method-icon">📱</div>
                                        <div class="method-info">
                                            <div class="method-name">E-Wallet</div>
                                            <div class="method-desc">GoPay, OVO, DANA, ShopeePay</div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method" onclick="selectMethod('credit_card')">
                                        <input type="radio" name="payment_method" value="credit_card" id="credit_card" required>
                                        <div class="method-icon">💳</div>
                                        <div class="method-info">
                                            <div class="method-name">Kartu Kredit</div>
                                            <div class="method-desc">Visa, Mastercard</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nama Pembayar</label>
                                    <input type="text" class="form-control" name="customer_name" 
                                           value="<?= $_SESSION['full_name'] ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="tel" class="form-control" name="customer_phone" 
                                           placeholder="08xxxxxxxxxx" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success" style="width: 100%;" 
                                        onclick="return confirm('Konfirmasi pembayaran sebesar Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>?')">
                                    💳 Bayar Sekarang - Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; color: #28a745; padding: 20px;">
                                <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                                <h4>Tagihan Sudah Lunas</h4>
                                <p>Tagihan ini telah dibayar sebelumnya.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>