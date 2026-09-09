<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db.php';

$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;

if ($payment_id <= 0) {
    die("Invalid Payment Receipt Request.");
}

$stmt = $pdo->prepare("
    SELECT p.*, b.total_amount AS total_bill_amount, c.first_name, c.last_name, c.email, c.phone 
    FROM payments p 
    JOIN bills b ON p.bill_id = b.bill_id 
    LEFT JOIN customers c ON p.customer_id = c.customer_id 
    WHERE p.payment_id = ?
");
$stmt->execute([$payment_id]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Receipt record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - <?= htmlspecialchars($receipt['receipt_number']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f8; margin: 0; padding: 20px; }
        .receipt-card { max-width: 600px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; border: 1px solid #ddd; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 2px solid #0077b6; padding-bottom: 15px; margin-bottom: 20px; }
        .header h2 { color: #0077b6; margin: 0; }
        .status-badge { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 20px; font-weight: bold; display: inline-block; margin-top: 10px; }
        .receipt-details { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .receipt-details td { padding: 10px; border-bottom: 1px solid #eee; }
        .receipt-details td:first-child { font-weight: bold; color: #555; width: 40%; }
        .footer { text-align: center; margin-top: 30px; color: #777; font-size: 13px; }
        .btn-print { background: #0077b6; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .receipt-card { border: none; box-shadow: none; }
        }
    </style>
</head>
<body>

    <div style="text-align: center;" class="no-print">
        <button onclick="window.print();" class="btn-print">🖨️ Print / Save Receipt</button>
    </div>

    <div class="receipt-card">
        <div class="header">
            <h2>💧 WellSpring Water</h2>
            <p style="margin: 5px 0; color: #666;">Official Water Bill Payment Receipt</p>
            <div class="status-badge">✓ PAYMENT APPROVED</div>
        </div>

        <table class="receipt-details">
            <tr>
                <td>Receipt Number:</td>
                <td><b><?= htmlspecialchars($receipt['receipt_number']) ?></b></td>
            </tr>
            <tr>
                <td>Date Issued:</td>
                <td><?= date('F j, Y, g:i a', strtotime($receipt['payment_date'] ?? 'now')) ?></td>
            </tr>
            <tr>
                <td>Customer Name:</td>
                <td><?= htmlspecialchars(($receipt['first_name'] ?? 'Customer') . ' ' . ($receipt['last_name'] ?? '')) ?></td>
            </tr>
            <tr>
                <td>Invoice / Bill ID:</td>
                <td>#<?= $receipt['bill_id'] ?></td>
            </tr>
            <tr>
                <td>Total Bill Amount:</td>
                <td>GH₵ <?= number_format($receipt['total_bill_amount'], 2) ?></td>
            </tr>
            <tr>
                <td>Amount Paid:</td>
                <td style="color: #2a9d8f; font-size: 18px;"><b>GH₵ <?= number_format($receipt['amount_paid'], 2) ?></b></td>
            </tr>
            <tr>
                <td>Remaining Balance:</td>
                <td><b>GH₵ <?= number_format($receipt['balance_after_payment'], 2) ?></b></td>
            </tr>
            <tr>
                <td>Payment Method:</td>
                <td><?= htmlspecialchars($receipt['payment_method']) ?></td>
            </tr>
            <tr>
                <td>Transaction Reference:</td>
                <td><?= htmlspecialchars($receipt['transaction_reference'] ?: 'N/A') ?></td>
            </tr>
        </table>

        <div class="footer">
            <p>Thank you for your payment!</p>
            <small>WellSpring Water Management System &bull; System Generated Receipt</small>
        </div>
    </div>

</body>
</html>