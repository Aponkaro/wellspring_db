<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$total_billed_revenue = 0.00;
$total_collected_revenue = 0.00;
$total_outstanding_balance = 0.00;
$total_consumption = 0.00;

try {
    $billed_stmt = $pdo->query("SELECT SUM(total_amount) FROM bills");
    $total_billed_revenue = $billed_stmt->fetchColumn() ?: 0.00;

    $payments_stmt = $pdo->query("SELECT SUM(amount_paid) FROM payments");
    $total_collected_revenue = $payments_stmt->fetchColumn() ?: 0.00;

    $total_outstanding_balance = max(0, $total_billed_revenue - $total_collected_revenue);

    $consumption_stmt = $pdo->query("SELECT SUM(reading_value) FROM meter_readings");
    $total_consumption = $consumption_stmt->fetchColumn() ?: 0.00;
} catch (PDOException $e) {
    $error_msg = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics - WellSpring Water</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-row { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-card { flex: 1; min-width: 200px; text-align: center; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        .stat-card h3 { margin: 0; font-size: 14px; color: #666; text-transform: uppercase; }
        .stat-card p { font-size: 24px; font-weight: bold; margin: 10px 0 0 0; }
        @media print {
            .sidebar, button { display: none !important; }
            .wrapper { display: block; }
            .main-content { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <h1 class="fade-in">System Reports & Analytics</h1>

            <div class="stats-row">
                <div class="stat-card" style="border-top: 4px solid #0077b6;">
                    <h3>Total Billed Revenue</h3>
                    <p style="color: #0077b6;">GH₵ <?= number_format($total_billed_revenue, 2) ?></p>
                </div>
                <div class="stat-card" style="border-top: 4px solid #2a9d8f;">
                    <h3>Collected Payments</h3>
                    <p style="color: #2a9d8f;">GH₵ <?= number_format($total_collected_revenue, 2) ?></p>
                </div>
                <div class="stat-card" style="border-top: 4px solid #e63946;">
                    <h3>Outstanding Balance</h3>
                    <p style="color: #e63946;">GH₵ <?= number_format($total_outstanding_balance, 2) ?></p>
                </div>
                <div class="stat-card" style="border-top: 4px solid #e76f51;">
                    <h3>Water Distributed</h3>
                    <p style="color: #e76f51;"><?= number_format($total_consumption, 2) ?> m³</p>
                </div>
            </div>

            <div class="table-container fade-in" style="background: white; padding: 20px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>Printable Summary Report</h3>
                    <button onclick="window.print()" style="padding: 10px 20px; background: #0077b6; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">🖨️ Print Report</button>
                </div>

                <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#0077b6; color:white;">
                            <th style="padding:12px;">Metric Category</th>
                            <th style="padding:12px;">Value / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding:12px;"><b>Total Revenue Billed</b></td>
                            <td style="padding:12px;">GH₵ <?= number_format($total_billed_revenue, 2) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding:12px;"><b>Financial Revenue Collected</b></td>
                            <td style="padding:12px; color: #2a9d8f; font-weight: bold;">GH₵ <?= number_format($total_collected_revenue, 2) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding:12px;"><b>Pending / Uncollected Revenue</b></td>
                            <td style="padding:12px; color: #e63946; font-weight: bold;">GH₵ <?= number_format($total_outstanding_balance, 2) ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding:12px;"><b>Overall Water Consumption</b></td>
                            <td style="padding:12px;"><?= number_format($total_consumption, 2) ?> m³</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>