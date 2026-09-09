<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}
require 'db.php';

$message = '';
$error = '';

// Auto-upgrade payments table schema safely if extra columns exist
try {
    $pdo->exec("
        ALTER TABLE payments ADD COLUMN IF NOT EXISTS transaction_reference VARCHAR(100);
        ALTER TABLE payments ADD COLUMN IF NOT EXISTS balance_after_payment NUMERIC(10, 2) DEFAULT 0.00;
        ALTER TABLE payments ADD COLUMN IF NOT EXISTS receipt_number VARCHAR(100);
    ");
} catch (PDOException $e) {
    // Soft catch if auto-alter fails or permissions are limited
}

// Handle Payment Submission by Staff/Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');

    if ($bill_id > 0 && $amount_paid > 0) {
        try {
            $pdo->beginTransaction();

            $bill_stmt = $pdo->prepare("SELECT customer_id, total_amount FROM bills WHERE bill_id = ?");
            $bill_stmt->execute([$bill_id]);
            $bill = $bill_stmt->fetch();

            if ($bill) {
                $paid_stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE bill_id = ?");
                $paid_stmt->execute([$bill_id]);
                $previous_paid = (float)($paid_stmt->fetchColumn() ?: 0.00);

                $new_total_paid = $previous_paid + $amount_paid;
                $balance_after = max(0.00, (float)$bill['total_amount'] - $new_total_paid);
                $receipt_number = 'RCT-' . date('YmdHis') . '-' . rand(100, 999);

                $stmt = $pdo->prepare("
                    INSERT INTO payments (bill_id, amount_paid, payment_method, transaction_reference, balance_after_payment, receipt_number) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$bill_id, $amount_paid, $payment_method, $transaction_reference, $balance_after, $receipt_number]);

                $new_status = ($balance_after <= 0) ? 'Paid' : 'Partially Paid';
                $update_bill = $pdo->prepare("UPDATE bills SET status = ? WHERE bill_id = ?");
                $update_bill->execute([$new_status, $bill_id]);

                $pdo->commit();
                $message = "Payment of GH₵ " . number_format($amount_paid, 2) . " recorded successfully! Receipt: <b>" . htmlspecialchars($receipt_number) . "</b>";
            } else {
                $pdo->rollBack();
                $error = "Selected bill was not found.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Payment recording failed: " . $e->getMessage();
        }
    } else {
        $error = "Please select a valid bill and enter a positive payment amount.";
    }
}

// Fetch Unpaid/Partially Paid Bills
$unpaid_bills = [];
try {
    $unpaid_bills = $pdo->query("
        SELECT b.bill_id, b.total_amount, b.status, c.first_name, c.last_name 
        FROM bills b 
        LEFT JOIN customers c ON b.customer_id = c.customer_id 
        WHERE b.status != 'Paid' 
        ORDER BY b.bill_id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $unpaid_bills = [];
}

// Fetch Payment Logs joining via Bills table (prevents column error)
$payments = [];
try {
    $payments = $pdo->query("
        SELECT p.*, b.customer_id, c.first_name, c.last_name 
        FROM payments p 
        LEFT JOIN bills b ON p.bill_id = b.bill_id 
        LEFT JOIN customers c ON b.customer_id = c.customer_id 
        ORDER BY p.payment_id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $payments = [];
    $error = "Error fetching payments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payments & Collections - Water Supply System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <a href="dashboard.php">Dashboard</a>
            <a href="customers.php">Customers</a>
            <a href="billing.php">Billing & Invoices</a>
            <a href="payments.php" class="active">Payments</a>
            <a href="notifications.php">Notifications</a>
            <a href="logout.php" style="background:#e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <h2>Payment Collections Module</h2>

            <?php if ($message): ?><div class="alert-success"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3>Record New Payment</h3>
                <form method="POST" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div style="grid-column: span 2;">
                        <label>Select Invoice / Bill</label>
                        <select name="bill_id" required style="width:100%; padding:8px;">
                            <option value="">-- Select Pending Bill --</option>
                            <?php foreach ($unpaid_bills as $ub): ?>
                                <option value="<?= $ub['bill_id'] ?>">
                                    Bill #<?= $ub['bill_id'] ?> - <?= htmlspecialchars(($ub['first_name'] ?? 'N/A').' '.($ub['last_name'] ?? '')) ?> (GH₵ <?= number_format($ub['total_amount'], 2) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label>Amount Received (GH₵)</label>
                        <input type="number" step="0.01" name="amount_paid" placeholder="0.00" required style="width:100%; padding:8px;">
                    </div>

                    <div>
                        <label>Payment Method</label>
                        <select name="payment_method" required style="width:100%; padding:8px;">
                            <option value="Cash">Cash</option>
                            <option value="Mobile Money">Mobile Money (MoMo)</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>

                    <div style="grid-column: span 2;">
                        <label>Transaction / Reference ID</label>
                        <input type="text" name="transaction_reference" placeholder="e.g. TRX10928374" style="width:100%; padding:8px;">
                    </div>

                    <button type="submit" name="record_payment" class="btn" style="grid-column: span 2; padding:10px; background:#2a9d8f; color:white; border:none; font-weight:bold; cursor:pointer;">Process Payment</button>
                </form>
            </div>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px;">
                <h3>Payment Records History</h3>
                <table style="width:100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr style="background:#0077b6; color:white;">
                            <th style="padding:10px; text-align:left;">Receipt #</th>
                            <th style="padding:10px; text-align:left;">Bill ID</th>
                            <th style="padding:10px; text-align:left;">Customer</th>
                            <th style="padding:10px; text-align:left;">Amount Paid</th>
                            <th style="padding:10px; text-align:left;">Method</th>
                            <th style="padding:10px; text-align:left;">Balance Remaining</th>
                            <th style="padding:10px; text-align:left;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach($payments as $p): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding:10px;"><b><?= htmlspecialchars($p['receipt_number'] ?? 'N/A') ?></b></td>
                                <td style="padding:10px;">#<?= $p['bill_id'] ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars(($p['first_name'] ?? 'N/A').' '.($p['last_name'] ?? '')) ?></td>
                                <td style="padding:10px; color:#2a9d8f; font-weight:bold;">GH₵ <?= number_format($p['amount_paid'], 2) ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars($p['payment_method'] ?? 'Cash') ?></td>
                                <td style="padding:10px; color:#e63946;">GH₵ <?= number_format($p['balance_after_payment'] ?? 0.00, 2) ?></td>
                                <td style="padding:10px;"><?= isset($p['paid_at']) ? date('M d, Y h:i A', strtotime($p['paid_at'])) : ($p['created_at'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="padding:15px; text-align:center; color:#777;">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
