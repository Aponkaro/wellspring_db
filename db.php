<?php
$host = getenv('DB_HOST') ?: 'dpg-dagoap2d0e5s73dbp6tg-a';
$db   = getenv('DB_NAME') ?: 'wellspring_db_8435';
$user = getenv('DB_USER') ?: 'wellspring_db_hvza_user';
$pass = getenv('DB_PASS') ?: 'rzBooHpciQkk6aHisWIkRpd8pHoJGDOL';
$port = getenv('DB_PORT') ?: '5432';

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Auto-create core tables if they do not exist
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

        CREATE TABLE IF NOT EXISTS bills (
            bill_id SERIAL PRIMARY KEY,
            customer_id INT REFERENCES customers(customer_id) ON DELETE CASCADE,
            total_amount NUMERIC(10, 2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS payments (
            payment_id SERIAL PRIMARY KEY,
            bill_id INT REFERENCES bills(bill_id) ON DELETE CASCADE,
            amount_paid NUMERIC(10, 2) DEFAULT 0.00,
            payment_method VARCHAR(50) DEFAULT 'Cash',
            paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

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

    // Auto-seed default admin account if users table is empty
    $check_admin = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    if ($check_admin == 0) {
        $admin_pass = password_hash('password123', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, email, role, user_type, is_active)
            VALUES ('admin', ?, 'System Admin', 'admin@wellspring.com', 'admin', 'admin', 1)
        ");
        $insert->execute([$admin_pass]);
    }

} catch (\PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
