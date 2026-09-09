<?php
require 'db.php';

echo "<h2>Starting Database Migration...</h2>";

try {
    // 1. Create Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id SERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            email VARCHAR(100),
            role VARCHAR(20) DEFAULT 'user',
            user_type VARCHAR(20) DEFAULT 'customer',
            is_active INT DEFAULT 1,
            last_login TIMESTAMP
        );
    ");
    echo " Users table ready.<br>";

    // 2. Create Customers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            customer_id SERIAL PRIMARY KEY,
            user_id INT REFERENCES users(user_id) ON DELETE SET NULL,
            first_name VARCHAR(50),
            last_name VARCHAR(50),
            email VARCHAR(100),
            phone VARCHAR(20),
            address TEXT,
            status VARCHAR(20) DEFAULT 'Active'
        );
    ");
    echo " Customers table ready.<br>";

    // 3. Create Bills Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bills (
            bill_id SERIAL PRIMARY KEY,
            customer_id INT REFERENCES customers(customer_id) ON DELETE CASCADE,
            total_amount NUMERIC(10, 2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo " Bills table ready.<br>";

    // 4. Create Payments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            payment_id SERIAL PRIMARY KEY,
            bill_id INT REFERENCES bills(bill_id) ON DELETE CASCADE,
            amount_paid NUMERIC(10, 2) DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT 'Cash',
            paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo " Payments table ready.<br>";

    // 5. Create Water Requests Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS water_requests (
            request_id SERIAL PRIMARY KEY,
            customer_id INT REFERENCES customers(customer_id) ON DELETE CASCADE,
            request_type VARCHAR(50) NOT NULL,
            location TEXT NOT NULL,
            details TEXT,
            status VARCHAR(20) DEFAULT 'Pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo " Water Requests table ready.<br>";

    // 6. Seed Default Admin User
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $admin_pass = password_hash('password123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, email, role, user_type, is_active)
            VALUES ('admin', ?, 'System Admin', 'admin@wellspring.com', 'admin', 'admin', 1)
        ");
        $insert->execute([$admin_pass]);
        echo " Default admin account created!<br>";
    } else {
        echo " Admin account already exists.<br>";
    }

    echo "<h3 style='color:green;'> Migration Completed Successfully! You can now log in.</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Migration Failed: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>