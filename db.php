<?php
// db.php - Dual Local & Cloud Database Connection Configuration

$db_url = getenv('DATABASE_URL');

if ($db_url) {
    // Render / Cloud Deployment Setup
    $url = parse_url($db_url);
    $host = $url['host'] ?? 'localhost';
    $port = $url['port'] ?? '3306';
    $user = $url['user'] ?? '';
    $pass = $url['pass'] ?? '';
    $db   = ltrim($url['path'] ?? '', '/');
} else {
    // Localhost Setup Fallback
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'wellspring_db';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException("Database Connection Error: " . $e->getMessage(), (int)$e->getCode());
}
?>