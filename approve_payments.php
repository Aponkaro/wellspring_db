<?php
session_start();

if (!isset($_SESSION['user_id']) || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer')) {
    header("Location: login.php");
    exit;
}

require 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    $bill_id = (int)$_POST['bill_id'];

    if ($payment_id > 0 && $bill_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE payments SET payment_status = 'Approved', approved_at = NOW(), approved_by = ? WHERE payment_id = ?");
            $stmt->execute([$_SESSION['user_id'], $payment_id]);

            $sum_stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE bill_id = ? AND payment_status = 'Approved'");
            $sum_stmt->execute([$bill_id]);
            $total_approved_paid = $sum_stmt->fetchColumn() ?: 0.00;

            $bill_stmt = $pdo->prepare("SELECT total_amount, customer_id FROM bills WHERE bill_id = ?");
            $bill_stmt->execute([$bill_id]);
            $bill = $bill_stmt->fetch();

            if ($bill) {
                $balance_remaining = $bill['total_amount'] - $total_approved_paid;
                
                if ($balance_remaining <= 0) {
                    $new_bill_status = 'Paid';
                } elseif ($total_approved_paid > 0) {
                    $new_bill_status = 'Partially Paid';
                } else {
                    $new_bill_status = 'Pending';
                }

                $update_bill = $pdo->prepare("UPDATE bills SET status = ? WHERE bill_id = ?");
                $update_bill->execute([$new_bill_status, $bill_id]);

                $notice_text = "Notice: Your payment for Invoice #{$bill_id} has been approved. Status: {$new_bill_status}.";
                $notif_stmt = $pdo->prepare("INSERT INTO fault_reports (customer_id, description, priority, status) VALUES (?, ?, 'Low', 'Approved Payment Notice')");
                $notif_stmt->execute([$bill['customer_id'], $notice_text]);

                $pdo->commit();
                $message = "Payment ID #{$payment_id} approved successfully! Official receipt generated.";
            } else {
                $pdo->rollBack();
                $error = "Associated bill not found.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Approval failed: " . $e->getMessage();
        }
    }
}

$payments = [];
try {
    $query = "SELECT p.*, b.total_amount AS bill_total, c.first_name, c.last_name, c.email 
              FROM payments p 
              JOIN bills b ON p.bill_id = b.bill_id 
              LEFT JOIN customers c ON p.customer_id = c.customer_id 
              ORDER BY p.payment_id DESC";
    $payments = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching payments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Payments & Notices - WellSpring Water</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .badge-pending { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .badge-approved { background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .btn-approve { background: #2a9d8f; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-approve:hover { background: #218377; }
        .btn-view { background: #0077b6; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-block; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f4f6f8; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <p style="text-align:center;">Welcome, <b><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></b></p>
            <hr>
            <a href="dashboard.php">Dashboard</a>
            <a href="customer_dashboard.php">👤 Customer Portal</a>
            <a href="customers.php">Customers</a>
            <a href="billing.php">Billing & Invoices</a>
            <a href="approve_payments.php" class="active">Approve Payments</a>
            <a href="logout.php" style="background: #e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <h1>Received Payments Approval & Receipts</h1>

            <?php if ($message): ?>
                <div class="alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card" style="background: white; padding: 20px; border-radius: 8px;">
                <h3>📥 Customer Payments Awaiting Action</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Receipt / Ref</th>
                            <th>Customer</th>
                            <th>Bill #</th>
                            <th>Amount Paid (GH₵)</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Actions / Notice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($payments) > 0): ?>
                            <?php foreach ($payments as $p): ?>
                                <tr>
                                    <td>
                                        <b><?= htmlspecialchars($p['receipt_number'] ?? 'N/A') ?></b><br>
                                        <small><?= htmlspecialchars($p['transaction_reference'] ?? '') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars(($p['first_name'] ?? 'User') . ' ' . ($p['last_name'] ?? '')) ?></td>
                                    <td>#<?= $p['bill_id'] ?> (GH₵ <?= number_format($p['bill_total'], 2) ?>)</td>
                                    <td><b>GH₵ <?= number_format($p['amount_paid'], 2) ?></b></td>
                                    <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                    <td>
                                        <?php if (($p['payment_status'] ?? 'Pending') === 'Approved'): ?>
                                            <span class="badge-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge-pending">Pending Review</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($p['payment_status'] ?? 'Pending') !== 'Approved'): ?>
                                            <form method="POST" action="approve_payments.php" style="display:inline;">
                                                <input type="hidden" name="payment_id" value="<?= $p['payment_id'] ?>">
                                                <input type="hidden" name="bill_id" value="<?= $p['bill_id'] ?>">
                                                <button type="submit" name="approve_payment" class="btn-approve">Approve Payment</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="view_receipt.php?payment_id=<?= $p['payment_id'] ?>" target="_blank" class="btn-view">📄 Print Receipt</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No payment records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>