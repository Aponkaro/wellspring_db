<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

// Fetch Fault Reports
$fault_alerts = $pdo->query("
    SELECT f.*, c.first_name, c.last_name 
    FROM fault_reports f 
    LEFT JOIN customers c ON f.customer_id = c.customer_id 
    ORDER BY f.fault_id DESC LIMIT 15
")->fetchAll();

// Fetch Water Delivery Requests
$water_alerts = [];
try {
    $water_alerts = $pdo->query("
        SELECT w.*, c.first_name, c.last_name 
        FROM water_requests w 
        LEFT JOIN customers c ON w.customer_id = c.customer_id 
        ORDER BY w.request_id DESC LIMIT 15
    ")->fetchAll();
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications & Alerts - Water Supply System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .bg-pending { background: #fff3cd; color: #856404; }
        .bg-resolved { background: #d4edda; color: #155724; }
        .bg-dispatched { background: #cce5ff; color: #004085; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <a href="dashboard.php">Dashboard</a>
            <a href="customers.php">Customers</a>
            <a href="billing.php">Billing & Invoices</a>
            <a href="notifications.php" class="active">Notifications</a>
            <a href="logout.php" style="background:#e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <h2>Notifications & System Alerts Center</h2>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3 style="color: #e63946;">🛠️ Recent Fault & Maintenance Alerts</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#0077b6; color:white;">
                            <th style="padding:10px; text-align:left;">ID</th>
                            <th style="padding:10px; text-align:left;">Customer</th>
                            <th style="padding:10px; text-align:left;">Description</th>
                            <th style="padding:10px; text-align:left;">Priority</th>
                            <th style="padding:10px; text-align:left;">Status</th>
                            <th style="padding:10px; text-align:left;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($fault_alerts) > 0): ?>
                            <?php foreach ($fault_alerts as $f): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding:10px;">#<?= $f['fault_id'] ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars(($f['first_name'] ?? 'System') . ' ' . ($f['last_name'] ?? '')) ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars($f['description']) ?></td>
                                <td style="padding:10px;"><b><?= htmlspecialchars($f['priority']) ?></b></td>
                                <td style="padding:10px;">
                                    <span class="badge <?= ($f['status'] === 'Resolved') ? 'bg-resolved' : 'bg-pending' ?>">
                                        <?= htmlspecialchars($f['status']) ?>
                                    </span>
                                </td>
                                <td style="padding:10px;"><?= $f['reported_at'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding:10px; text-align:center;">No fault alerts logged.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px;">
                <h3 style="color: #0077b6;">💧 Water & Tanker Delivery Requests</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#0077b6; color:white;">
                            <th style="padding:10px; text-align:left;">ID</th>
                            <th style="padding:10px; text-align:left;">Customer</th>
                            <th style="padding:10px; text-align:left;">Type</th>
                            <th style="padding:10px; text-align:left;">Location</th>
                            <th style="padding:10px; text-align:left;">Status</th>
                            <th style="padding:10px; text-align:left;">Requested At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($water_alerts) > 0): ?>
                            <?php foreach ($water_alerts as $w): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding:10px;">#<?= $w['request_id'] ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars(($w['first_name'] ?? 'Guest') . ' ' . ($w['last_name'] ?? '')) ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars($w['request_type']) ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars($w['location']) ?></td>
                                <td style="padding:10px;">
                                    <span class="badge <?= ($w['status'] === 'Completed') ? 'bg-resolved' : (($w['status'] === 'Dispatched') ? 'bg-dispatched' : 'bg-pending') ?>">
                                        <?= htmlspecialchars($w['status']) ?>
                                    </span>
                                </td>
                                <td style="padding:10px;"><?= $w['requested_at'] ?? $w['created_at'] ?? 'N/A' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding:10px; text-align:center;">No delivery requests logged.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>