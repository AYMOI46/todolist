<?php
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $result = register($username, $email, $password, $fullName);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-logo">
                    <i class="fas fa-layer-group"></i>
                    <h1>Create Account</h1>
                    <p>Join <?= APP_NAME ?></p>
                </div>
                <?php if ($error): ?>
                    <div style="background:rgba(239,68,68,0.1);color:#f87171;padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.875rem;">
                        <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div style="background:rgba(16,185,129,0.1);color:#34d399;padding:12px;border-radius:8px;margin-bottom:16px;font-size:0.875rem;">
                        <?= sanitize($success) ?> <a href="<?= APP_URL ?>/login.php">Login now</a>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>
                <div class="auth-footer">
                    Already have an account? <a href="<?= APP_URL ?>/login.php">Sign In</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
