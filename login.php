<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/functions.php';

if (isLoggedIn()) {
    header("Location: admin/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $login = loginUser($conn, $username, $password);
        if ($login['success']) {
            header("Location: admin/dashboard.php");
            exit();
        } else {
            $error = $login['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Riyadul Muta'alimin</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Riyadul <span>Muta'alimin</span></h1>
                <p>Login ke Panel Admin</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" style="margin-top: 10px;">Login</button>
                
                <div class="text-center" style="margin-top: 20px;">
                    <a href="index.php" style="font-size: 0.9rem;">&larr; Kembali ke Beranda</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

