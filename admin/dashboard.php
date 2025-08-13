<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Get only necessary data for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as unpaid_bills FROM bills WHERE status = 'unpaid'");
$unpaid_bills = $stmt->fetch()['unpaid_bills'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PLN Payment</title>
    <link rel="stylesheet" href="../assets/css/admin/dashboard.css">
    <script src="../assets/js/admin/dashboard.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="#" class="navbar-brand">
                ⚡ PLN Admin Panel
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
            <h2>📊 Dashboard Admin</h2>
            <p>Selamat datang, <?= $_SESSION['full_name'] ?>! Kelola sistem pembayaran listrik PLN dengan mudah.</p>
        </div>

        <!-- Menu Management -->
        <div class="menu-grid">
            <a href="penggunaan-listrik.php" class="menu-card">
                <div class="menu-icon icon-usage">
                    📈
                </div>
                <h5>Penggunaan Listrik</h5>
                <p>Monitor dan kelola data penggunaan listrik pelanggan</p>
            </a>
            
            <a href="pelanggan-listrik.php" class="menu-card">
                <div class="menu-icon icon-customers">
                    👥
                </div>
                <h5>Pelanggan Listrik</h5>
                <p>Kelola data pelanggan listrik PLN</p>
            </a>
            
            <a href="tagihan-listrik.php" class="menu-card">
                <div class="menu-icon icon-bills">
                    💰
                </div>
                <h5>Tagihan Listrik</h5>
                <p>Kelola tagihan dan billing pelanggan</p>
            </a>
            
            <a href="tarif.php" class="menu-card">
                <div class="menu-icon icon-tariff">
                    🧮
                </div>
                <h5>Tarif Listrik</h5>
                <p>Kelola tarif dan perhitungan biaya listrik</p>
            </a>
        </div>
    </div>


</body>
</html>