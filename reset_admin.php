<?php
require 'db.php';

$username = 'Edmond';
$new_password = 'Edmond123';
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1 WHERE username = ?");
    $stmt->execute([$hashed_password, $username]);

    if ($stmt->rowCount() > 0) {
        echo "<h2 style='color:green;'>Password updated successfully!</h2>";
        echo "<p>Credentials: Username: <b>Edmond</b> | Password: <b>Edmond123</b></p>";
    } else {
        $insert = $pdo->prepare("INSERT INTO users (username, password_hash, email, full_name, role, user_type, is_active) VALUES (?, ?, 'admin@wellspring.com', 'Edmond Admin', 'Admin', 'staff', 1)");
        $insert->execute([$username, $hashed_password]);
        echo "<h2 style='color:green;'>Default admin account created successfully!</h2>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error updating admin password: " . $e->getMessage() . "</h2>";
}
?>