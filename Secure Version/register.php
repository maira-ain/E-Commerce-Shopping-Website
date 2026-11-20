<?php
include 'db.php';
include 'security.php';

apply_security_headers();
start_secure_session();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!is_valid_username($username)) {
            $error = 'Username must be 3-20 characters and contain only letters, numbers, or underscores.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!is_valid_password($password)) {
            $error = 'Password must be at least 8 characters and include upper, lower, and numeric characters.';
        } else {
            $checkSql = 'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1';
            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, 'ss', $username, $email);
            mysqli_stmt_execute($checkStmt);
            $existing = mysqli_stmt_get_result($checkStmt);

            if (mysqli_fetch_assoc($existing)) {
                $error = 'That username or email is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $insertSql = 'INSERT INTO users (username, email, password) VALUES (?, ?, ?)';
                $insertStmt = mysqli_prepare($conn, $insertSql);
                mysqli_stmt_bind_param($insertStmt, 'sss', $username, $email, $passwordHash);

                if (mysqli_stmt_execute($insertStmt)) {
                    $success = 'Account created successfully. You can now log in.';
                } else {
                    $error = 'Unable to create account right now.';
                }

                mysqli_stmt_close($insertStmt);
            }

            mysqli_stmt_close($checkStmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register – ShopEasy Secure</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="auth-container">
    <h2>Create Account</h2>
    <p class="subtitle">Join ShopEasy today</p>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" maxlength="20" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="Enter email" maxlength="150" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Register</button>
    </form>

    <p class="switch-link">Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
