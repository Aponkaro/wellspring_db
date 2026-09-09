<?php
session_start();
require 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Fetch user regardless of is_active status first to give specific feedback
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {

            // Check if user account itself has been deactivated/disabled
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                $error = "You have been deleted from the system by the Admin and cannot access the system, please contact the admin for assistance.";
            } else {

                // --- CUSTOMER SPECIFIC CHECK ---
                if (strtolower($user['user_type']) === 'customer') {
                    $user_email = $user['email'] ?? '';
                    
                    // Fetch customer matching user_id or email
                    $c_stmt = $pdo->prepare("
                        SELECT customer_id, status FROM customers 
                        WHERE user_id = ? 
                           OR (email IS NOT NULL AND email != '' AND email = ?)
                        LIMIT 1
                    ");
                    $c_stmt->execute([$user['user_id'], $user_email]);
                    $cust = $c_stmt->fetch();

                    // If the customer profile record was deleted or set to inactive
                    if (!$cust || (isset($cust['status']) && strtolower($cust['status']) === 'inactive')) {
                        $error = "You have been deleted from the system by the Admin and cannot access the system, please contact the admin for assistance.";
                    } else {
                        // Store customer_id in session
                        $_SESSION['customer_id'] = $cust['customer_id'];

                        // Keep user_id in sync inside customers table
                        $update_stmt = $pdo->prepare("UPDATE customers SET user_id = ? WHERE customer_id = ? AND (user_id IS NULL OR user_id = 0)");
                        $update_stmt->execute([$user['user_id'], $cust['customer_id']]);
                    }
                }

                // If no error occurred during customer validation, complete login
                if (empty($error)) {
                    // Set base session variables
                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];

                    // Safely update last login timestamp if column exists
                    try {
                        $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);
                    } catch (\PDOException $e) {
                        // Ignore if last_login column is omitted in schema
                    }

                    // Route based on User Type
                    if (strtolower($user['user_type']) === 'customer') {
                        header("Location: customer_dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit;
                }
            }
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Water Distribution System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card fade-in">
            <h2 style="text-align: center; color: #0077b6; margin-bottom: 20px;">System Login</h2>
            
            <?php if($error): ?>
                <div style="color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 5px; margin-bottom: 15px; text-align: center; font-weight: bold; font-size: 14px; line-height: 1.4;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>

            <p style="text-align:center; margin-top: 15px; font-size: 14px;">
                Don't have an account? <a href="register.php" style="color:#0077b6; text-decoration:none; font-weight:bold;">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>