<?php
$host = getenv('DB_HOST') ?: 'dpg-dagoap2d0e5s73dbp6tg-a';
$db   = getenv('DB_NAME') ?: 'wellspring_db_8435';
$user = getenv('DB_USER') ?: 'water_user';
$pass = getenv('DB_PASS') ?: 'N6mRudDMV0G8YbtzIeZ1CK1IV195Zw2W';
$port = getenv('DB_PORT') ?: '5432';

// pgsql DSN format
$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
