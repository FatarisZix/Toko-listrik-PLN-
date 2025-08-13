<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLN Payment System</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="bolt-icon">⚡</div>
            <h1 class="brand-title">PLN Payment</h1>
            <p class="description">
                Sistem Pembayaran Listrik Online<br>
                Mudah, Cepat & Aman
            </p>
            
            <div class="button-group">
                <a href="login.php" class="btn btn-login">
                    🔑 Login
                </a>
                <a href="register.php" class="btn btn-register">
                    👤 Daftar
                </a>
            </div>
        </div>
    </div>
</body>
</html>