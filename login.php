<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';

if ($_POST) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (login($username, $password)) {
        redirectByRole();
    } else {
        $error = 'Username/Email atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PLN Payment System</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <div class="bolt-icon">⚡</div>
                <h2 class="brand-title">PLN Payment</h2>
                <p class="subtitle">Masuk ke akun Anda</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-icon">👤</span>
                        <input type="text" class="form-control" name="username" 
                               placeholder="Username atau Email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" class="form-control" name="password" 
                               placeholder="Password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    🔑 Masuk
                </button>
            </form>
            
            <div class="footer-text">
                Belum punya akun? <a href="register.php">Daftar</a>
            </div>
            
            <div class="demo-info">
                <strong>Login Admin:</strong><br>
                Admin: admin / password<br>
            </div>
        </div>
    </div>
</body>
</html>