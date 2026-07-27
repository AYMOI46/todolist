<?php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    $error = 'Invalid username or password';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <i class="fas fa-layer-group"></i>
                    <h1><?= APP_NAME ?></h1>
                    <p>Sign in to manage your tasks</p>
                </div>
                <?php if ($error): ?>
                    <div style="background:rgba(239,68,68,0.1);color:#f87171;padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.875rem;">
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Username or Email</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
                <div class="auth-footer">
                    Don't have an account? <a href="<?= APP_URL ?>/register.php">Register</a>
                </div>
                <div class="auth-footer" style="margin-top:12px;font-size:0.75rem;">
                    Demo: admin / password
                </div>
            </div>
        </div>
    </div>
</body>
</html>
