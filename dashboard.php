<?php
session_start();

// Fallback session check for flexibility
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}

require 'db.php';

// Initialize default stat variables
$total_billed_revenue = 0.00;
$total_collected_revenue = 0.00;
$total_outstanding_balance = 0.00;
$total_customers = 0;
$pending_bills_count = 0;
$recent_payments = [];
$recent_bills = [];
$supply_requests = [];

try {
    // 1. Calculate Total Billed Revenue
    $billed_stmt = $pdo->query("SELECT SUM(total_amount) FROM bills");
    $total_billed_revenue = $billed_stmt->fetchColumn() ?: 0.00;

    // 2. Calculate Total Collected Revenue
    $payments_stmt = $pdo->query("SELECT SUM(amount_paid) FROM payments");
    $total_collected_revenue = $payments_stmt->fetchColumn() ?: 0.00;

    // 3. Calculate Outstanding Balance
    $total_outstanding_balance = max(0, $total_billed_revenue - $total_collected_revenue);

    // 4. Count Total Customers
    $cust_stmt = $pdo->query("SELECT COUNT(*) FROM customers");
    $total_customers = $cust_stmt->fetchColumn() ?: 0;

    // 5. Count Unpaid or Partially Paid Bills
    $pending_stmt = $pdo->query("SELECT COUNT(*) FROM bills WHERE status != 'Paid'");
    $pending_bills_count = $pending_stmt->fetchColumn() ?: 0;

    // 6. Fetch Recent 5 Payments
    $recent_payments = $pdo->query("
        SELECT p.*, b.bill_id, c.first_name, c.last_name 
        FROM payments p 
        LEFT JOIN bills b ON p.bill_id = b.bill_id 
        LEFT JOIN customers c ON b.customer_id = c.customer_id 
        ORDER BY p.payment_id DESC LIMIT 5
    ")->fetchAll();

    // 7. Fetch Recent 5 Issued Bills
    $recent_bills = $pdo->query("
        SELECT b.*, c.first_name, c.last_name 
        FROM bills b 
        LEFT JOIN customers c ON b.customer_id = c.customer_id 
        ORDER BY b.bill_id DESC LIMIT 5
    ")->fetchAll();
    
    // 8. Fetch Water Requests
    $supply_requests = $pdo->query("
        SELECT wr.*, c.first_name, c.last_name, c.phone 
        FROM water_requests wr
        LEFT JOIN customers c ON wr.customer_id = c.customer_id
        ORDER BY wr.requested_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    $error_msg = "Database Warning: " . $e->getMessage();
}

// Handle Supply Request Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_supply_status'])) {
    $req_id = (int)$_POST['request_id'];
    $new_status = $_POST['status']; 

    try {
        $update_stmt = $pdo->prepare("UPDATE water_requests SET status = ? WHERE request_id = ?");
        $update_stmt->execute([$new_status, $req_id]);
        $_SESSION['pwd_success'] = "Water request #{$req_id} status updated to '{$new_status}'.";
        header("Location: dashboard.php#supply-distribution");
        exit;
    } catch (PDOException $e) {
        $_SESSION['pwd_error'] = "Failed to update status: " . $e->getMessage();
    }
}

// Handle Supply Request Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supply_request'])) {
    $req_id = (int)$_POST['request_id'];

    try {
        $delete_stmt = $pdo->prepare("DELETE FROM water_requests WHERE request_id = ?");
        $delete_stmt->execute([$req_id]);
        $_SESSION['pwd_success'] = "Water request #{$req_id} deleted successfully.";
        header("Location: dashboard.php#supply-distribution");
        exit;
    } catch (PDOException $e) {
        $_SESSION['pwd_error'] = "Failed to delete request: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Water Supply System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; border-left: 5px solid #0077b6; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        .stat-card h4 { margin: 0; color: #666; font-size: 14px; text-transform: uppercase; }
        .stat-card .amount { font-size: 22px; font-weight: bold; margin-top: 10px; color: #2b2d42; }
        .tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        @media (max-width: 900px) { .tables-grid { grid-template-columns: 1fr; } }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #eee; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); position: relative; }
        .close-btn { position: absolute; right: 15px; top: 10px; font-size: 22px; font-weight: bold; cursor: pointer; color: #666; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #333; }
        .form-group input { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .btn-submit { background-color: #0077b6; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 15px; font-weight: bold; }
        .btn-submit:hover { background-color: #005f92; }
        .action-flex { display: flex; align-items: center; gap: 6px; }
        .btn-delete { background-color: #e63946; color: white; border: none; padding: 5px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; font-weight: bold; }
        .btn-delete:hover { background-color: #c52a36; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <p style="text-align:center;">Welcome, <b><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin') ?></b></p>
            <hr>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="customers.php">Customers</a>
            <a href="billing.php">Billing & Invoices</a>
            <a href="payments.php">Payments</a>
            <a href="#supply-distribution">Supply & Distribution</a>
            <a href="inventory.php">Inventory</a>
            <a href="notifications.php">Notifications</a>
            <a href="javascript:void(0);" onclick="openPasswordModal()">Change Password</a>
            <a href="logout.php" style="background:#e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <h2> Overview & Analytics</h2>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['pwd_success'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['pwd_success']); unset($_SESSION['pwd_success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['pwd_error'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['pwd_error']); unset($_SESSION['pwd_error']); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card" style="border-color: #0077b6;">
                    <h4>Total Billed Revenue</h4>
                    <div class="amount">GH₵ <?= number_format($total_billed_revenue, 2) ?></div>
                </div>

                <div class="stat-card" style="border-color: #2a9d8f;">
                    <h4>Payments Collected</h4>
                    <div class="amount" style="color: #2a9d8f;">GH₵ <?= number_format($total_collected_revenue, 2) ?></div>
                </div>

                <div class="stat-card" style="border-color: #e63946;">
                    <h4>Outstanding Balance</h4>
                    <div class="amount" style="color: #e63946;">GH₵ <?= number_format($total_outstanding_balance, 2) ?></div>
                </div>

                <div class="stat-card" style="border-color: #e9c46a;">
                    <h4>Pending Invoices</h4>
                    <div class="amount"><?= number_format($pending_bills_count) ?> Bills</div>
                </div>
            </div>

            <div class="tables-grid">
                <div class="card-container" style="background: white; padding: 20px; border-radius: 8px;">
                    <h3>Recent Issued Invoices</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Bill #</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_bills)): ?>
                                <?php foreach ($recent_bills as $b): ?>
                                    <tr>
                                        <td>#<?= $b['bill_id'] ?></td>
                                        <td><?= htmlspecialchars(($b['first_name'] ?? 'N/A') . ' ' . ($b['last_name'] ?? '')) ?></td>
                                        <td><b>GH₵ <?= number_format($b['total_amount'], 2) ?></b></td>
                                        <td><?= htmlspecialchars($b['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center;">No bills generated yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-container" style="background: white; padding: 20px; border-radius: 8px;">
                    <h3>Recent Payments Received</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Ref #</th>
                                <th>Customer</th>
                                <th>Amount Paid</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_payments)): ?>
                                <?php foreach ($recent_payments as $p): ?>
                                    <tr>
                                        <td>#<?= $p['bill_id'] ?></td>
                                        <td><?= htmlspecialchars(($p['first_name'] ?? 'N/A') . ' ' . ($p['last_name'] ?? '')) ?></td>
                                        <td style="color: #2a9d8f; font-weight: bold;">GH₵ <?= number_format($p['amount_paid'], 2) ?></td>
                                        <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center;">No payments recorded yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-container" id="supply-distribution" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <h3 style="color: #0077b6; margin-top: 0;">🚛 Supply & Distribution Requests</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Req ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Details</th>
                            <th>Status</th>
                            <th>Date Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($supply_requests)): ?>
                            <?php foreach ($supply_requests as $req): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($req['request_id']) ?></td>
                                    <td><?= htmlspecialchars(($req['first_name'] ?? 'N/A') . ' ' . ($req['last_name'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($req['phone'] ?? 'N/A') ?></td>
                                    <td><strong style="color: #2c3e50;"><?= htmlspecialchars($req['request_type']) ?></strong></td>
                                    <td><?= htmlspecialchars($req['location']) ?></td>
                                    <td><?= htmlspecialchars($req['details'] ?? 'None') ?></td>
                                    <td>
                                        <?php 
                                            $status = $req['status'] ?? 'Pending';
                                            $bg = ($status === 'Completed') ? '#d4edda' : (($status === 'Dispatched') ? '#cce5ff' : '#fff3cd');
                                            $color = ($status === 'Completed') ? '#155724' : (($status === 'Dispatched') ? '#004085' : '#856404');
                                        ?>
                                        <span style="padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; background: <?= $bg ?>; color: <?= $color ?>;">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y h:i A', strtotime($req['requested_at'])) ?></td>
                                    <td>
                                        <div class="action-flex">
                                            <form method="POST" action="dashboard.php" style="margin: 0;">
                                                <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 4px 6px; border-radius: 4px; border: 1px solid #ccc; font-size: 12px; cursor: pointer;">
                                                    <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="Dispatched" <?= $status === 'Dispatched' ? 'selected' : '' ?>>Water Dispatched/Supplied</option>
                                                    <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Request Completed</option>
                                                </select>
                                                <input type="hidden" name="update_supply_status" value="1">
                                            </form>

                                            <form method="POST" action="dashboard.php" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete water request #<?= $req['request_id'] ?>?');">
                                                <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                                <button type="submit" name="delete_supply_request" class="btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #777;">No water supply requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closePasswordModal()">&times;</span>
            <h3 style="margin-top:0;">Change Password</h3>
            <form action="change_password.php" method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn-submit">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        function openPasswordModal() {
            document.getElementById('passwordModal').style.display = 'block';
        }
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        window.onclick = function(event) {
            let modal = document.getElementById('passwordModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>