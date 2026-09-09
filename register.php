<?php
session_start();
require 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']);
    $username     = trim($_POST['username']);
    $email        = trim($_POST['email']);
    $password     = trim($_POST['password']);
    $user_type    = $_POST['user_type'];
    $role         = ($user_type === 'staff') ? $_POST['staff_role'] : 'Customer';
    $meter_number = isset($_POST['meter_number']) ? trim($_POST['meter_number']) : null;

    if (!empty($full_name) && !empty($username) && !empty($email) && !empty($password)) {
        if ($user_type === 'customer' && empty($meter_number)) {
            $error = "Meter Number is required for customer registration.";
        } else {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO users (full_name, username, email, password_hash, role, user_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $username, $email, $password_hash, $role, $user_type]);
                $user_id = $pdo->lastInsertId();

                if (strtolower($user_type) === 'customer') {
                    $parts = explode(' ', $full_name, 2);
                    $first_name = $parts[0];
                    $last_name  = $parts[1] ?? '';

                    $stmt_cust = $pdo->prepare("INSERT INTO customers (user_id, first_name, last_name, email, meter_number, connection_date, is_active) VALUES (?, ?, ?, ?, ?, CURDATE(), 1)");
                    $stmt_cust->execute([$user_id, $first_name, $last_name, $email, $meter_number]);
                }

                $pdo->commit();
                $message = "Account created successfully! <a href='login.php' style='color:#0077b6;'>Click here to Login</a>";
            } catch (PDOException $e) {
                $pdo->rollBack();
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $error = "Username, email, or meter number already exists.";
                } else {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - WellSpring Water</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function toggleRoleSelect() {
            var type = document.getElementById("user_type").value;
            var staffRoleGroup = document.getElementById("staff_role_group");
            var meterNumberGroup = document.getElementById("meter_number_group");
            var meterInput = document.getElementById("meter_number");

            if (type === "staff") {
                staffRoleGroup.style.display = "block";
                meterNumberGroup.style.display = "none";
                meterInput.removeAttribute("required");
            } else {
                staffRoleGroup.style.display = "none";
                meterNumberGroup.style.display = "block";
                meterInput.setAttribute("required", "required");
            }
        }
        window.onload = function() { toggleRoleSelect(); };
    </script>
</head>
<body>
    <div class="login-container" style="display:flex; justify-content:center; align-items:center; padding: 40px 0;">
        <div class="login-card fade-in" style="background:white; padding: 30px; border-radius: 8px; width: 380px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            <h2 style="text-align: center; color: #0077b6;">Create Account</h2>
            <?php if($message): ?><div style="color: green; margin-bottom: 15px; text-align: center; font-weight: bold;"><?= $message ?></div><?php endif; ?>
            <?php if($error): ?><div style="color: red; margin-bottom: 15px; text-align: center; font-weight: bold;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST" action="register.php">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Account Type</label>
                    <select name="user_type" id="user_type" onchange="toggleRoleSelect()" style="width:100%; padding:8px;" required>
                        <option value="staff">Staff Account</option>
                        <option value="customer">Customer Account</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required style="width:100%; padding:8px;">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Email Address</label>
                    <input type="email" name="email" required style="width:100%; padding:8px;">
                </div>
                <div class="form-group" id="meter_number_group" style="margin-bottom:12px; display:none;">
                    <label>Meter Number</label>
                    <input type="text" name="meter_number" id="meter_number" placeholder="e.g. MTR-10293" style="width:100%; padding:8px;">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Username</label>
                    <input type="text" name="username" required style="width:100%; padding:8px;">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>Password</label>
                    <input type="password" name="password" required style="width:100%; padding:8px;">
                </div>
                <div class="form-group" id="staff_role_group" style="margin-bottom:12px;">
                    <label>Staff Role</label>
                    <select name="staff_role" style="width:100%; padding:8px;">
                        <option value="Staff">General Staff</option>
                        <option value="Assistant Admin">Assistant Admin</option>
                        <option value="Billing Officer">Billing Officer</option>
                        <option value="Maintenance Officer">Maintenance Officer</option>
                    </select>
                </div>
                <button type="submit" class="btn" style="width:100%; padding:10px; background:#0077b6; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Register</button>
            </form>
            <p style="text-align:center; margin-top: 15px; font-size: 14px;">
                Already have an account? <a href="login.php" style="color:#0077b6; text-decoration:none; font-weight:bold;">Login here</a>
            </p>
        </div>
    </div>
</body>
</html>