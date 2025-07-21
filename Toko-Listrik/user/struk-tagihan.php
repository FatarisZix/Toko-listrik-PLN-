<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Get bill ID from URL
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if (!$bill_id) {
    header('Location: dashboard.php');
    exit();
}

$message = '';
$error = '';

// Handle payment confirmation
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'confirm_payment') {
    try {
        // Generate unique transaction ID
        $transaction_id = 'TRX' . date('YmdHis') . rand(1000, 9999);
        
        // Get bill amount
        $stmt = $pdo->prepare("SELECT total_amount FROM bills WHERE id = ? AND status = 'unpaid'");
        $stmt->execute([$bill_id]);
        $bill_data = $stmt->fetch();
        
        if ($bill_data) {
            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (bill_id, user_id, payment_method, payment_amount, transaction_id, status) 
                VALUES (?, ?, ?, ?, ?, 'success')
            ");
            $stmt->execute([
                $bill_id, 
                $_SESSION['user_id'], 
                'offline', // Pembayaran offline (Indomaret/ATM)
                $bill_data['total_amount'], 
                $transaction_id
            ]);
            
            // Update bill status to paid
            $stmt = $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
            $stmt->execute([$bill_id]);
            
            // Redirect to dashboard with success message
            $_SESSION['payment_success'] = 'Pembayaran berhasil dikonfirmasi!';
            header('Location: dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        $error = 'Gagal mengkonfirmasi pembayaran: ' . $e->getMessage();
    }
}

// Get bill details with customer info
$stmt = $pdo->prepare("
    SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type, c.user_id, 
           c.address, c.phone, t.price_per_kwh, t.admin_fee as tariff_admin_fee
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
    WHERE b.id = ? AND b.status = 'unpaid'
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

// Check if bill exists and belongs to current user
if (!$bill || $bill['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// Generate payment code (ID Pelanggan + random 4 digit)
$payment_code = $bill['customer_number'] . rand(1000, 9999);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Tagihan Listrik - PLN</title>
    <link rel="stylesheet" href="..\assets\css\user\struk-tagihan.css">
</head>
<body>
    <div class="struk-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">⚡ PLN</div>
            <div class="title">STRUK TAGIHAN LISTRIK</div>
            <div style="font-size: 0.8rem; color: #666;">
                <?= date('d/m/Y H:i:s') ?>
            </div>
        </div>

        <!-- Payment Code -->
        <div class="payment-code">
            KODE BAYAR: <?= $payment_code ?>
        </div>

        <!-- Customer Info -->
        <div class="section">
            <div class="row">
                <span class="label">ID PELANGGAN</span>
                <span class="value"><?= $bill['customer_number'] ?></span>
            </div>
            <div class="row">
                <span class="label">NAMA</span>
                <span class="value"><?= strtoupper($bill['customer_name']) ?></span>
            </div>
            <div class="row">
                <span class="label">ALAMAT</span>
            </div>
            <div style="font-size: 0.8rem; margin-left: 10px;">
                <?= $bill['address'] ?>
            </div>
            <div class="row">
                <span class="label">NO TELP</span>
                <span class="value"><?= $bill['phone'] ?? '-' ?></span>
            </div>
        </div>

        <!-- Bill Details -->
        <div class="section">
            <div class="row">
                <span class="label">PERIODE</span>
                <span class="value"><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></span>
            </div>
            <div class="row">
                <span class="label">TARIF/DAYA</span>
                <span class="value"><?= $bill['tariff_type'] ?></span>
            </div>
            <div class="row">
                <span class="label">PEMAKAIAN</span>
                <span class="value"><?= number_format($bill['kwh_usage']) ?> kWh</span>
            </div>
            <div class="row">
                <span class="label">BIAYA LISTRIK</span>
                <span class="value">Rp <?= number_format($bill['amount'], 0, ',', '.') ?></span>
            </div>
            <div class="row">
                <span class="label">ADMIN BANK</span>
                <span class="value">Rp <?= number_format($bill['admin_fee'], 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Total -->
        <div class="total-section">
            <div>TOTAL BAYAR</div>
            <div class="total-amount">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></div>
        </div>

        <!-- Due Date -->
        <div class="section">
            <div class="row">
                <span class="label">BATAS BAYAR</span>
                <span class="value" style="color: red;">
                     <?= date('d F Y', strtotime($bill['due_date'] . ' +3 days')) ?>
            </span>
            </div>
        </div>

        <!-- Status -->
        <div class="status-unpaid">
            BELUM LUNAS
        </div>

        <!-- Instructions -->
        <div class="instructions">
            <strong>CARA PEMBAYARAN:</strong><br>
            1. Tunjukkan struk ini ke kasir<br>
            2. Sebutkan bayar listrik PLN<br>
            3. Berikan kode bayar: <strong><?= $payment_code ?></strong><br>
            4. Bayar sesuai total yang tertera<br>
            5. Simpan bukti pembayaran<br><br>
            
            <strong>TEMPAT PEMBAYARAN:</strong><br>
            • Alfamart / Indomaret<br>
            • ATM Bank (BCA, Mandiri, BNI, BRI)<br>
            • Mobile Banking<br>
            • Kantor Pos<br>
            • Loket PPOB
        </div>

    </div>

    <!-- Print Button -->
    <button class="btn-print no-print" onclick="window.print()">
        🖨️ Cetak Struk
    </button>

    <!-- Confirm Payment Button -->
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <form method="POST" onsubmit="return confirm('Apakah Anda sudah melakukan pembayaran di tempat?')">
            <input type="hidden" name="action" value="confirm_payment">
            <button type="submit" style="
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                border: none;
                padding: 15px 40px;
                border-radius: 25px;
                font-size: 1.1rem;
                cursor: pointer;
                transition: all 0.3s ease;
                font-weight: bold;
            " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 15px rgba(40, 167, 69, 0.4)'" 
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                ✅ Konfirmasi Sudah Bayar
            </button>
        </form>
        <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">
            Klik tombol ini setelah Anda melakukan pembayaran di Indomaret/ATM/tempat lainnya
        </p>
    </div>

    <script>
        // Auto print dialog on load (optional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>