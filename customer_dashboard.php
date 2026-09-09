<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Check user role across common session naming conventions
$user_role = strtolower($_SESSION['user_type'] ?? $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'customer');
$is_admin_or_staff = in_array($user_role, ['admin', 'staff', 'manager', 'superuser']);

// Auto-patch schema: create water_requests table if missing in PostgreSQL
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS water_requests (
            request_id SERIAL PRIMARY KEY,
            customer_id INT,
            request_type VARCHAR(100) NOT NULL,
            quantity VARCHAR(100),
            location TEXT,
            details TEXT,
            status VARCHAR(50) DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
} catch (PDOException $e) {
    // Graceful fallback
}

/**
 * Helper function to locate customer_id based on session or user table links
 */
function getCustomerId($pdo) {
    if (!empty($_SESSION['customer_id'])) {
        return $_SESSION['customer_id'];
    }

    $user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;

    if ($user_id) {
        // 1. Check if customer_id is stored inside users table
        try {
            $stmt = $pdo->prepare("SELECT customer_id FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $cid = $stmt->fetchColumn();
            if ($cid) {
                $_SESSION['customer_id'] = $cid;
                return $cid;
            }
        } catch (PDOException $e) {}

        // 2. Check if user_id is linked inside customers table
        try {
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $cid = $stmt->fetchColumn();
            if ($cid) {
                $_SESSION['customer_id'] = $cid;
                return $cid;
            }
        } catch (PDOException $e) {}
    }

    // 3. Fallback check by session values
    $search_val = $_SESSION['username'] ?? $_SESSION['email'] ?? $_SESSION['phone'] ?? '';
    if (!empty($search_val)) {
        try {
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE email = ? OR phone = ? OR meter_number = ? LIMIT 1");
            $stmt->execute([$search_val, $search_val, $search_val]);
            $cid = $stmt->fetchColumn();
            if ($cid) {
                $_SESSION['customer_id'] = $cid;
                return $cid;
            }
        } catch (PDOException $e) {}
    }

    return null;
}

$current_customer_id = getCustomerId($pdo);

// -------------------------------------------------------------------------
// 1. Handle Bill Payment Request
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_bill'])) {
    $bill_id = (int)($_POST['bill_id'] ?? 0);
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'Mobile Money';
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');

    if ($bill_id > 0 && $amount_paid > 0) {
        try {
            $pdo->beginTransaction();

            // Fetch Bill Info
            $bill_stmt = $pdo->prepare("SELECT customer_id, total_amount FROM bills WHERE bill_id = ?");
            $bill_stmt->execute([$bill_id]);
            $bill = $bill_stmt->fetch();

            if ($bill) {
                if (!$is_admin_or_staff && $current_customer_id && $bill['customer_id'] != $current_customer_id) {
                    $pdo->rollBack();
                    $error = "Unauthorized attempt to pay a bill belonging to another account.";
                } else {
                    $bill_customer_id = $bill['customer_id'];
                    
                    // Calculate total paid so far
                    $total_paid_stmt = $pdo->prepare("SELECT SUM(amount_paid) FROM payments WHERE bill_id = ?");
                    $total_paid_stmt->execute([$bill_id]);
                    $previous_paid = (float)($total_paid_stmt->fetchColumn() ?: 0.00);

                    $new_total_paid = $previous_paid + $amount_paid;
                    $balance_after_payment = $bill['total_amount'] - $new_total_paid;
                    if ($balance_after_payment < 0) {
                        $balance_after_payment = 0.00;
                    }

                    $receipt_number = 'RCT-' . date('YmdHis') . '-' . rand(100, 999);

                    // Insert payment record
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (bill_id, customer_id, amount_paid, payment_method, transaction_reference, balance_after_payment, receipt_number) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $bill_id,
                        $bill_customer_id,
                        $amount_paid,
                        $payment_method,
                        $transaction_reference,
                        $balance_after_payment,
                        $receipt_number
                    ]);

                    // Update bill status
                    $new_status = ($balance_after_payment <= 0) ? 'Paid' : 'Partially Paid';
                    $update_bill = $pdo->prepare("UPDATE bills SET status = ? WHERE bill_id = ?");
                    $update_bill->execute([$new_status, $bill_id]);

                    $pdo->commit();
                    $message = "Payment of GH₵ " . number_format($amount_paid, 2) . " successful! Receipt No: <b>" . htmlspecialchars($receipt_number) . "</b>";
                }
            } else {
                $pdo->rollBack();
                $error = "Selected bill was not found.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Payment failed: " . $e->getMessage();
        }
    } else {
        $error = "Invalid bill selection or payment amount.";
    }
}

// -------------------------------------------------------------------------
// 2. Handle Fault Report Submission
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_fault'])) {
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';

    if (!empty($description)) {
        try {
            $target_customer_id = $current_customer_id ?? $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO fault_reports (customer_id, description, priority, status) VALUES (?, ?, ?, 'Reported')");
            $stmt->execute([$target_customer_id, $description, $priority]);
            
            if ($target_customer_id) {
                $_SESSION['customer_id'] = $target_customer_id;
                $current_customer_id = $target_customer_id;
            }

            $message = "Fault/Maintenance request submitted successfully!";
        } catch (PDOException $e) {
            $error = "Could not submit report: " . $e->getMessage();
        }
    } else {
        $error = "Please describe the fault or maintenance needed.";
    }
}

// -------------------------------------------------------------------------
// 3. Handle Water Request
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_water'])) {
    $request_type = $_POST['request_type'] ?? 'Water Tanker Supply';
    $quantity     = trim($_POST['quantity'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $details      = trim($_POST['details'] ?? '');

    if (!empty($location)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO water_requests (customer_id, request_type, quantity, location, details, status) 
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$current_customer_id, $request_type, $quantity, $location, $details]);

            $message = "Your request for <b>" . htmlspecialchars($request_type) . "</b> has been submitted successfully!";
        } catch (PDOException $e) {
            $error = "Failed to send request: " . $e->getMessage();
        }
    } else {
        $error = "Please provide your delivery/service location.";
    }
}

// -------------------------------------------------------------------------
// 4. Handle Password Change
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        try {
            $pwd_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $pwd_stmt->execute([$user_id]);
            $user_record = $pwd_stmt->fetch();

            if ($user_record && password_verify($current_password, $user_record['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_pwd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $update_pwd->execute([$new_hash, $user_id]);

                $message = "Your password has been changed successfully!";
            } else {
                $error = "Your current password was incorrect.";
            }
        } catch (PDOException $e) {
            $error = "Database error while updating password: " . $e->getMessage();
        }
    }
}

// Data Retrieval
$my_pending_bills = [];
try {
    if ($current_customer_id) {
        $bill_query = $pdo->prepare("
            SELECT b.*, c.first_name, c.last_name 
            FROM bills b 
            LEFT JOIN customers c ON b.customer_id = c.customer_id 
            WHERE b.customer_id = ? AND b.status != 'Paid' 
            ORDER BY b.bill_id DESC
        ");
        $bill_query->execute([$current_customer_id]);
        $my_pending_bills = $bill_query->fetchAll();
    } elseif ($is_admin_or_staff) {
        $my_pending_bills = $pdo->query("
            SELECT b.*, c.first_name, c.last_name 
            FROM bills b 
            LEFT JOIN customers c ON b.customer_id = c.customer_id 
            WHERE b.status != 'Paid' 
            ORDER BY b.bill_id DESC
        ")->fetchAll();
    }
} catch (PDOException $e) {}

$my_notifications = [];
try {
    if ($current_customer_id) {
        $fault_stmt = $pdo->prepare("
            SELECT 'Fault Report' AS ticket_type, description AS details, status, reported_at AS created_at 
            FROM fault_reports 
            WHERE customer_id = ? 
            ORDER BY reported_at DESC
        ");
        $fault_stmt->execute([$current_customer_id]);
        $faults = $fault_stmt->fetchAll(PDO::FETCH_ASSOC);

        $water_stmt = $pdo->prepare("
            SELECT 'Water Request' AS ticket_type, CONCAT(request_type, ' - ', location) AS details, status, created_at 
            FROM water_requests 
            WHERE customer_id = ? 
            ORDER BY created_at DESC
        ");
        $water_stmt->execute([$current_customer_id]);
        $water_reqs = $water_stmt->fetchAll(PDO::FETCH_ASSOC);

        $my_notifications = array_merge($faults, $water_reqs);
        usort($my_notifications, function($a, $b) {
            return strtotime($b['created_at'] ?? 'now') - strtotime($a['created_at'] ?? 'now');
        });
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Water Supply System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        html { scroll-behavior: smooth; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-submit { background: #0077b6; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; transition: background 0.2s; }
        .btn-submit:hover { background: #023e8a; }
        .alert-success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .alert-error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 12px; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .admin-nav-btn { display: block; background: #0077b6; color: white !important; font-weight: bold; text-align: center; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-decoration: none; }
        .admin-nav-btn:hover { background: #023e8a; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <p style="text-align:center;">Welcome, <b><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Customer') ?></b></p>
            <hr>

            <?php if ($is_admin_or_staff): ?>
                <a href="dashboard.php" class="admin-nav-btn">⬅️ Admin Dashboard</a>
            <?php endif; ?>

            <a href="customer_dashboard.php" class="active">Dashboard</a>
            <a href="#pay-bills">Pay Bills</a>
            <a href="#maintenance">Maintenance</a>
            <a href="#water-request">Request Water</a>
            <a href="#change-password">Change Password</a>
            <a href="#notifications">Notifications</a>
            <a href="logout.php" style="background: #e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h1 style="margin: 0;">Customer Self-Service Portal</h1>
                <?php if ($is_admin_or_staff): ?>
                    <a href="dashboard.php" style="background: #0077b6; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px;">🏠 Main Dashboard</a>
                <?php endif; ?>
            </div>

            <?php if ($message): ?>
                <div class="alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <h3>👤 Account Overview</h3>
                <div style="display: flex; gap: 40px; flex-wrap: wrap;">
                    <p><b>Name:</b> <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></p>
                    <p><b>Username / Meter:</b> <?= htmlspecialchars($_SESSION['username'] ?? 'N/A') ?></p>
                    <p><b>Status:</b> <span style="color: green; font-weight: bold;">Active</span></p>
                </div>
            </div>

            <div class="grid-2">
                <div class="card" id="pay-bills">
                    <h3 style="color: #2a9d8f;">💳 Pay Water Bills (GH₵)</h3>
                    <form method="POST" action="customer_dashboard.php#pay-bills">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Unpaid Bills</label>
                            <select name="bill_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="">-- Select Bill --</option>
                                <?php foreach ($my_pending_bills as $b): ?>
                                    <option value="<?= $b['bill_id'] ?>">
                                        Invoice #<?= $b['bill_id'] ?> - GH₵ <?= number_format($b['total_amount'], 2) ?> (<?= htmlspecialchars($b['status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Amount (GH₵)</label>
                            <input type="number" step="0.01" name="amount_paid" placeholder="0.00" required style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Payment Method</label>
                            <select name="payment_method" required style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="Mobile Money">Mobile Money (MTN MoMo / Telecel Cash / AT)</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Transaction Ref</label>
                            <input type="text" name="transaction_reference" placeholder="e.g. TRX9827364501" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <button type="submit" name="pay_bill" class="btn-submit" style="background: #2a9d8f;">Submit Payment</button>
                    </form>
                </div>

                <div class="card" id="maintenance">
                    <h3 style="color: #e63946;">🛠️ Maintenance & Faults</h3>
                    <form method="POST" action="customer_dashboard.php#maintenance">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Priority</label>
                            <select name="priority" style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Issue Details</label>
                            <textarea name="description" rows="4" placeholder="Describe leak or meter issue..." required style="width: 100%; padding: 8px; margin-top: 5px;"></textarea>
                        </div>

                        <button type="submit" name="report_fault" class="btn-submit" style="background: #e63946;">Report Fault</button>
                    </form>
                </div>
            </div>

            <div class="grid-2">
                <div class="card" id="water-request">
                    <h3 style="color: #0077b6;">💧 Request Water Service</h3>
                    <form method="POST" action="customer_dashboard.php#water-request">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Type</label>
                            <select name="request_type" required style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="Water Tanker Supply">Water Tanker Delivery</option>
                                <option value="New Connection">New Pipeline Connection</option>
                                <option value="Meter Re-calibration">Meter Inspection</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Volume</label>
                            <select name="quantity" required style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="1,000 Liters">1,000 Liters</option>
                                <option value="5,000 Liters">5,000 Liters</option>
                                <option value="10,000 Liters">10,000 Liters</option>
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Location / GhanaPost GPS</label>
                            <input type="text" name="location" placeholder="Address..." required style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Notes</label>
                            <textarea name="details" rows="2" placeholder="Additional details..." style="width: 100%; padding: 8px; margin-top: 5px;"></textarea>
                        </div>

                        <button type="submit" name="request_water" class="btn-submit">Submit Request</button>
                    </form>
                </div>

                <div class="card" id="change-password">
                    <h3 style="color: #457b9d;">🔒 Change Password</h3>
                    <form method="POST" action="customer_dashboard.php#change-password">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>New Password</label>
                            <input type="password" name="new_password" required minlength="6" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="6" style="width: 100%; padding: 8px; margin-top: 5px;">
                        </div>

                        <button type="submit" name="change_password" class="btn-submit" style="background: #457b9d;">Update Password</button>
                    </form>
                </div>
            </div>

            <div class="card" id="notifications">
                <h3 style="color: #e76f51;">🔔 Recent Activity & Status</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php if (count($my_notifications) > 0): ?>
                        <?php foreach ($my_notifications as $note): ?>
                            <li style="padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <span><b>[<?= htmlspecialchars($note['ticket_type']) ?>]</b> <?= htmlspecialchars($note['details']) ?></span>
                                <span style="padding: 3px 8px; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-weight: bold; font-size: 12px;">
                                    <?= htmlspecialchars($note['status']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="padding: 10px; color: #666;">No recent requests found.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </main>
    </div>
</body>
</html>
