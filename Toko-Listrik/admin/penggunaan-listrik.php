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
            $customer_id = sanitizeInput($_POST['customer_id']);
            $period_month = sanitizeInput($_POST['period_month']);
            $kwh_usage = sanitizeInput($_POST['kwh_usage']);
            $meter_start = sanitizeInput($_POST['meter_start']);
            $meter_end = sanitizeInput($_POST['meter_end']);
            
            try {
                // Check if usage for this customer and period already exists
                $stmt = $pdo->prepare("SELECT id FROM usage_records WHERE customer_id = ? AND period_month = ?");
                $stmt->execute([$customer_id, $period_month]);
                if ($stmt->fetch()) {
                    $error = "Data penggunaan untuk pelanggan ini di bulan tersebut sudah ada!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO usage_records (customer_id, period_month, kwh_usage, meter_start, meter_end) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$customer_id, $period_month, $kwh_usage, $meter_start, $meter_end]);
                    $message = "Data penggunaan berhasil ditambahkan!";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'approve_report') {
            $report_id = sanitizeInput($_POST['report_id']);
            $admin_note = sanitizeInput($_POST['admin_note']);
            
            try {
                // Get report details
                $stmt = $pdo->prepare("
                    SELECT mr.*, c.customer_number, c.name as customer_name,
                           (SELECT meter_reading FROM meter_reports mr2 
                            WHERE mr2.customer_id = mr.customer_id 
                            AND mr2.period_month < mr.period_month 
                            AND mr2.status = 'approved' 
                            ORDER BY mr2.period_month DESC LIMIT 1) as previous_reading
                    FROM meter_reports mr
                    JOIN customers c ON mr.customer_id = c.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$report_id]);
                $report = $stmt->fetch();
                
                if (!$report) {
                    throw new Exception("Laporan tidak ditemukan!");
                }
                
                // Calculate kWh usage
                $meter_start = $report['previous_reading'] ?? $report['meter_start'] ?? 0;
                $meter_end = $report['meter_reading'] ?? $report['meter_end'] ?? 0;
                $kwh_usage = $meter_end - $meter_start;
                
                if ($kwh_usage < 0) {
                    throw new Exception("Meter reading tidak valid! Angka harus lebih besar dari reading sebelumnya.");
                }
                
                // Update report status
                $stmt = $pdo->prepare("UPDATE meter_reports SET status = 'approved', admin_note = ?, approved_by = ?, approved_date = NOW() WHERE id = ?");
                $stmt->execute([$admin_note, $_SESSION['user_id'], $report_id]);
                
                // Create usage record
                $stmt = $pdo->prepare("INSERT INTO usage_records (customer_id, period_month, kwh_usage, meter_start, meter_end) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE kwh_usage = ?, meter_start = ?, meter_end = ?");
                $stmt->execute([$report['customer_id'], $report['period_month'], $kwh_usage, $meter_start, $meter_end, $kwh_usage, $meter_start, $meter_end]);
                
                $message = "Laporan meter disetujui dan data penggunaan berhasil dibuat! Usage: {$kwh_usage} kWh";
                
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'reject_report') {
            $report_id = sanitizeInput($_POST['report_id']);
            $admin_note = sanitizeInput($_POST['admin_note']);
            
            try {
                $stmt = $pdo->prepare("UPDATE meter_reports SET status = 'rejected', admin_note = ?, approved_by = ?, approved_date = NOW() WHERE id = ?");
                $stmt->execute([$admin_note, $_SESSION['user_id'], $report_id]);
                $message = "Laporan meter ditolak.";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM usage_records WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Data penggunaan berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Create usage_records table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usage_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        period_month VARCHAR(7) NOT NULL,
        kwh_usage INT NOT NULL,
        meter_start INT NOT NULL,
        meter_end INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_customer_period (customer_id, period_month)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Get all usage records with customer info
$stmt = $pdo->query("
    SELECT ur.*, c.customer_number, c.name as customer_name, c.tariff_type, c.power_capacity
    FROM usage_records ur
    JOIN customers c ON ur.customer_id = c.id 
    ORDER BY ur.period_month DESC, c.customer_number ASC
");
$usage_records = $stmt->fetchAll();

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, customer_number, name FROM customers ORDER BY customer_number");
$customers = $stmt->fetchAll();

// Get pending meter reports for approval
$stmt = $pdo->query("
    SELECT mr.*, c.customer_number, c.name as customer_name,
           COALESCE(mr.meter_reading, mr.meter_end) as meter_reading,
           (SELECT COALESCE(meter_reading, meter_end) FROM meter_reports mr2 
            WHERE mr2.customer_id = mr.customer_id 
            AND mr2.period_month < mr.period_month 
            AND mr2.status = 'approved' 
            ORDER BY mr2.period_month DESC LIMIT 1) as previous_reading
    FROM meter_reports mr
    JOIN customers c ON mr.customer_id = c.id
    WHERE mr.status = 'pending'
    ORDER BY mr.report_date ASC
");
$pending_reports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penggunaan Listrik - PLN Admin</title>
    <link rel="stylesheet" href="../assets/css/admin/penggunaan-listrik.css">
    <script src="../assets/js/admin/penggunaan-listrik.js"></script>
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
                <h2 class="page-title">📈 Kelola Penggunaan Listrik</h2>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ Informasi:</strong> Kelola penggunaan listrik dan review laporan meter reading dari pelanggan.
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

        <!-- Pending Meter Reports -->
        <?php if (!empty($pending_reports)): ?>
        <div class="content-card">
            <div class="card-header">
                <h5>📋 Laporan Meter Menunggu Persetujuan (<?= count($pending_reports) ?>)</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Periode</th>
                            <th>Meter Reading</th>
                            <th>Estimasi kWh</th>
                            <th>Tanggal Lapor</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_reports as $report): ?>
                        <?php 
                        $meter_start = $report['meter_start'] ?? $report['previous_reading'] ?? 0;
                        $meter_end = $report['meter_end'] ?? $report['meter_reading'] ?? 0;

                        $estimated_kwh = $meter_end - $meter_start;
                        ?>
                        <tr>
                            <td><strong><?= $report['customer_number'] ?></strong></td>
                            <td><?= $report['customer_name'] ?></td>
                            <td><?= date('F Y', strtotime($report['period_month'] . '-01')) ?></td>
                            <td>
    <?php 
    $meter_reading = $report['meter_reading'] ?? $report['meter_end'] ?? 0;
    $meter_start = $report['previous_reading'] ?? $report['meter_start'] ?? 0;
    ?>
    <strong><?= number_format($meter_reading) ?></strong><br>
    <small style="color: #666;">
        Sebelumnya: <?= number_format($meter_start) ?>
    </small>
</td>

                            <td>
                                <?php if ($estimated_kwh >= 0): ?>
                                    <strong style="color: #28a745;"><?= number_format($estimated_kwh) ?> kWh</strong>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Invalid!</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($report['report_date'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_report">
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="admin_note" value="Disetujui - Usage: <?= $estimated_kwh ?> kWh">
                                        <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;"
                                                onclick="return confirm('Setujui laporan meter ini?')" 
                                                <?= $estimated_kwh < 0 ? 'disabled' : '' ?>>
                                            ✅ Setujui
                                        </button>
                                    </form>
                                    <button onclick="openRejectModal(<?= $report['id'] ?>, '<?= $report['customer_name'] ?>')" 
                                            class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;">
                                        ❌ Tolak
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Usage Records Table -->
        <div class="content-card">
            <div class="card-header">
                <h5>📊 Data Penggunaan Listrik</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Periode</th>
                            <th>Meter Awal</th>
                            <th>Meter Akhir</th>
                            <th>Penggunaan (kWh)</th>
                            <th>Tarif</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usage_records)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">📊</div>
                                        <p>Belum ada data penggunaan listrik</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usage_records as $record): ?>
                            <tr>
                                <td><strong><?= $record['customer_number'] ?></strong></td>
                                <td><?= $record['customer_name'] ?></td>
                                <td><?= date('F Y', strtotime($record['period_month'] . '-01')) ?></td>
                                <td><?= number_format($record['meter_start']) ?></td>
                                <td><?= number_format($record['meter_end']) ?></td>
                                <td><strong><?= number_format($record['kwh_usage']) ?> kWh</strong></td>
                                <td><span class="badge badge-info"><?= $record['tariff_type'] ?></span></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus data penggunaan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $record['id'] ?>">
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

    <!-- Add Usage Modal -->
    <div class="modal" id="addModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📈 Input Penggunaan Listrik</h5>
                    <button type="button" class="close" onclick="closeModal()">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label class="form-label">Pelanggan</label>
                            <select class="form-control" name="customer_id" required>
                                <option value="">Pilih Pelanggan</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>"><?= $customer['customer_number'] ?> - <?= $customer['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Periode Bulan</label>
                            <input type="month" class="form-control" name="period_month" 
                                   value="<?= date('Y-m') ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Meter Stand Awal</label>
                                <input type="number" class="form-control" name="meter_start" 
                                       placeholder="Contoh: 12500" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Meter Stand Akhir</label>
                                <input type="number" class="form-control" name="meter_end" 
                                       placeholder="Contoh: 12650" min="0" required 
                                       onchange="calculateUsage()">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Total Penggunaan (kWh)</label>
                            <input type="number" class="form-control" name="kwh_usage" 
                                   placeholder="Akan otomatis terhitung" min="0" required readonly>
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

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">❌ Tolak Laporan Meter</h5>
                    <button type="button" class="close" onclick="closeRejectModal()">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_report">
                        <input type="hidden" name="report_id" id="reject_report_id">
                        
                        <p>Tolak laporan meter untuk: <strong id="reject_customer_name"></strong></p>
                        
                        <div class="form-group">
                            <label class="form-label">Alasan Penolakan</label>
                            <textarea class="form-control" name="admin_note" rows="3" 
                                      placeholder="Berikan alasan penolakan..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            ❌ Tolak Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</html>