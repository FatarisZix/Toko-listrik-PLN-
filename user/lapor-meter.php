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

$message = '';
$error = '';

// Get user's customers
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customers = $stmt->fetchAll();

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'submit_report') {
    $customer_id = sanitizeInput($_POST['customer_id']);
    $period_month = sanitizeInput($_POST['period_month']);
    $meter_start = sanitizeInput($_POST['meter_start']);
    $meter_end = sanitizeInput($_POST['meter_end']);
    $kwh_usage = $meter_end - $meter_start;
    $notes = sanitizeInput($_POST['notes']);
    
    try {
        // Verify customer belongs to user
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND user_id = ?");
        $stmt->execute([$customer_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Customer tidak valid!");
        }
        
        // Validate meter readings
        if ($meter_end <= $meter_start) {
            throw new Exception("Meter akhir harus lebih besar dari meter awal!");
        }
        
        if ($kwh_usage < 0 || $kwh_usage > 10000) {
            throw new Exception("Penggunaan kWh tidak wajar! Periksa kembali angka meter Anda.");
        }
        
        // Check if report already exists
        $stmt = $pdo->prepare("SELECT id, status FROM meter_reports WHERE customer_id = ? AND period_month = ?");
        $stmt->execute([$customer_id, $period_month]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($existing['status'] == 'pending') {
                throw new Exception("Laporan meter untuk periode ini sudah ada dan menunggu persetujuan admin!");
            } elseif ($existing['status'] == 'approved') {
                throw new Exception("Laporan meter untuk periode ini sudah disetujui!");
            } else {
                // If rejected, allow new report (update existing)
                $stmt = $pdo->prepare("UPDATE meter_reports SET meter_start = ?, meter_end = ?, kwh_usage = ?, report_date = NOW(), status = 'pending', admin_note = NULL WHERE id = ?");
                $stmt->execute([$meter_start, $meter_end, $kwh_usage, $existing['id']]);
                $message = "Laporan meter berhasil diupdate dan dikirim ulang untuk review!";
            }
        } else {
            // Create new report
            $stmt = $pdo->prepare("INSERT INTO meter_reports (customer_id, period_month, meter_start, meter_end, kwh_usage, user_note, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$customer_id, $period_month, $meter_start, $meter_end, $kwh_usage, $notes]);
            $message = "Laporan meter reading berhasil dikirim! Penggunaan: {$kwh_usage} kWh. Menunggu persetujuan admin.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user's meter reports with customer info
if (!empty($customers)) {
    $customer_ids = array_column($customers, 'id');
    $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT mr.*, c.customer_number, c.name as customer_name
        FROM meter_reports mr
        JOIN customers c ON mr.customer_id = c.id
        WHERE mr.customer_id IN ($placeholders)
        ORDER BY mr.period_month DESC, mr.report_date DESC
    ");
    $stmt->execute($customer_ids);
    $meter_reports = $stmt->fetchAll();
} else {
    $meter_reports = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lapor Meter Reading - PLN Payment</title>
    <link rel="stylesheet" href="../assets/css/user/lapor-meter.css">
    <script src="../assets/js/user/lapor-meter.js"></script>
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
            <h2 class="page-title">📊 Lapor Meter Reading</h2>
        </div>

        <?php if (empty($customers)): ?>
            <div class="alert-info">
                <h5>ℹ️ Informasi</h5>
                <p class="mb-0">Anda belum memiliki data pelanggan listrik. Silakan hubungi admin untuk menambahkan data pelanggan Anda ke sistem.</p>
            </div>
        <?php else: ?>
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
                <!-- Report Form -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>📝 Lapor Meter Reading Baru</h5>
                    </div>
                    <div class="card-body">
                        <div class="meter-tips">
                            <h6>💡 Tips Membaca Meter:</h6>
                            <ul>
                                <li>Baca angka dari kiri ke kanan</li>
                                <li>Abaikan angka desimal (biasanya warna merah)</li>
                                <li>Pastikan angka yang dilaporkan lebih besar dari reading sebelumnya</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="submit_report">
                            
                            <div class="form-group">
                                <label class="form-label">Pilih Instalasi</label>
                                <select class="form-control" name="customer_id" required>
                                    <option value="">Pilih No. Pelanggan</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>">
                                            <?= $customer['customer_number'] ?> - <?= $customer['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Periode Bulan</label>
                                <input type="month" class="form-control" name="period_month" 
                                       value="<?= date('Y-m') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Meter Awal</label>
                                <input type="number" class="form-control" name="meter_start" id="meterStart"
                                       placeholder="Contoh: 12000" min="0" required onchange="calculateKwh()">
                                <small style="color: #666;">
                                    Angka meter pada awal periode/bulan lalu
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Meter Akhir (Saat Ini)</label>
                                <input type="number" class="form-control" name="meter_end" id="meterEnd"
                                       placeholder="Contoh: 12150" min="0" required onchange="calculateKwh()">
                                <small style="color: #666;">
                                    Angka meter yang tertera saat ini
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Penggunaan kWh (Otomatis)</label>
                                <input type="number" class="form-control" id="kwhUsage" 
                                       placeholder="Akan otomatis terhitung" readonly 
                                       style="background: #f8f9fa; font-weight: bold; font-size: 1.1rem;">
                                <small style="color: #666;">
                                    Penggunaan = Meter Akhir - Meter Awal
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Catatan tambahan..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                📤 Kirim Laporan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Reports History -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>📋 Riwayat Laporan</h5>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Meter Awal</th>
                                    <th>Meter Akhir</th>
                                    <th>kWh Usage</th>
                                    <th>Status</th>
                                    <th>Catatan Pengguna</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($meter_reports)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <div class="empty-icon">📊</div>
                                                <p>Belum ada laporan meter</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($meter_reports as $report): ?>
                                    <tr>
                                        <td><?= date('M Y', strtotime($report['period_month'] . '-01')) ?></td>
                                        <td><?= isset($report['meter_start']) ? number_format($report['meter_start']) : '-' ?></td>
                                        <td><?= isset($report['meter_end']) ? number_format($report['meter_end']) : number_format($report['meter_reading']) ?></td>
                                        <td>
                                            <?php if (isset($report['kwh_usage'])): ?>
                                                <strong><?= number_format($report['kwh_usage']) ?> kWh</strong>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($report['status'] == 'pending'): ?>
                                                <span class="badge badge-warning">⏱️ Menunggu</span>
                                            <?php elseif ($report['status'] == 'approved'): ?>
                                                <span class="badge badge-success">✅ Disetujui</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">❌ Ditolak</span>
                                                <?php if ($report['admin_note']): ?>
                                                    <br><small style="color: #666;"><?= $report['admin_note'] ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $report['user_note'] ?? '-' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>