<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit; 
}
require 'db.php';

$error_msg = "";

// Auto-upgrade customers schema if columns are missing
try {
    $pdo->exec("
        ALTER TABLE customers ADD COLUMN IF NOT EXISTS address_line1 TEXT;
        ALTER TABLE customers ADD COLUMN IF NOT EXISTS city VARCHAR(100);
        ALTER TABLE customers ADD COLUMN IF NOT EXISTS postal_code VARCHAR(20);
        ALTER TABLE customers ADD COLUMN IF NOT EXISTS meter_number VARCHAR(100);
    ");
} catch (PDOException $e) {
    // Soft catch if permissions prevent table alteration
}

// Helper Function: Optional SMS Gateway Trigger
function sendSMS($phone, $message) {
    return true; 
}

// --- HANDLE TOGGLE ACTIVE / INACTIVE STATUS ---
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, is_active FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();

        if ($customer) {
            $new_status = ((int)$customer['is_active'] === 1) ? 0 : 1;

            $pdo->beginTransaction();

            $stmtCust = $pdo->prepare("UPDATE customers SET is_active = ? WHERE customer_id = ?");
            $stmtCust->execute([$new_status, $customer_id]);

            if (!empty($customer['user_id'])) {
                $stmtUser = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
                $stmtUser->execute([$new_status, $customer['user_id']]);
            }

            $pdo->commit();

            $status_msg = ($new_status === 1) ? 'activated' : 'deactivated';
            header("Location: customers.php?msg={$status_msg}");
            exit;
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error updating customer status: " . $e->getMessage());
    }
}

// --- HANDLE DELETE CUSTOMER ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $customer_id = (int)$_GET['id'];
    
    try {
        $pdo->beginTransaction();

        $stmtPay = $pdo->prepare("DELETE FROM payments WHERE bill_id IN (SELECT bill_id FROM bills WHERE customer_id = ?)");
        $stmtPay->execute([$customer_id]);

        $stmtBills = $pdo->prepare("DELETE FROM bills WHERE customer_id = ?");
        $stmtBills->execute([$customer_id]);

        $stmtFaults = $pdo->prepare("UPDATE fault_reports SET customer_id = NULL WHERE customer_id = ?");
        $stmtFaults->execute([$customer_id]);

        $stmtGetCust = $pdo->prepare("SELECT user_id FROM customers WHERE customer_id = ?");
        $stmtGetCust->execute([$customer_id]);
        $linked_user = $stmtGetCust->fetchColumn();

        if ($linked_user) {
            $stmtUser = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmtUser->execute([$linked_user]);
        }

        $stmtCustomer = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
        $stmtCustomer->execute([$customer_id]);

        $pdo->commit();
        header("Location: customers.php?msg=deleted");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error deleting customer: " . $e->getMessage());
    }
}

// --- FORM HANDLER: ADD NEW CUSTOMER & AUTO-CREATE USER ACCOUNT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $fname  = trim($_POST['first_name']);
    $lname  = trim($_POST['last_name']);
    $phone  = trim($_POST['phone']);
    $email  = trim($_POST['email']);
    $addr1  = trim($_POST['address_line1']);
    $city   = trim($_POST['city']);
    $pcode  = trim($_POST['postal_code']);
    $meter  = !empty(trim($_POST['meter_number'])) ? trim($_POST['meter_number']) : null;

    $gen_username = !empty($meter) ? $meter : $phone; 
    $gen_password = $phone; 
    $password_hash = password_hash($gen_password, PASSWORD_BCRYPT);
    $full_name = $fname . ' ' . $lname;

    try {
        $pdo->beginTransaction();

        // 1. Create Login Account in `users` table
        $stmtUser = $pdo->prepare("
            INSERT INTO users (full_name, username, email, password_hash, role, user_type, is_active) 
            VALUES (?, ?, ?, ?, 'Customer', 'customer', 1)
        ");
        $stmtUser->execute([$full_name, $gen_username, $email, $password_hash]);
        $new_user_id = $pdo->lastInsertId();

        // 2. Create Profile Record in `customers` table
        $sqlCust = "INSERT INTO customers (user_id, first_name, last_name, phone, email, address_line1, city, postal_code, meter_number, connection_date, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, 1)";
        $pdo->prepare($sqlCust)->execute([$new_user_id, $fname, $lname, $phone, $email, $addr1, $city, $pcode, $meter]);

        $pdo->commit();

        // Send SMS with generated login credentials
        $smsMessage = "Hello {$fname}, your account at WellSpring Water is active. Login Username: {$gen_username}, Password: {$gen_password}";
        sendSMS($phone, $smsMessage);

        $_SESSION['created_creds'] = [
            'username' => $gen_username,
            'password' => $gen_password,
            'phone'    => $phone
        ];

        header("Location: customers.php?msg=added");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e->getCode() == '23505' || $e->getCode() == 23000) {
            $error_msg = "⚠️ A customer with this Meter Number, Phone Number, or Email address already exists in the system!";
        } else {
            $error_msg = "⚠️ Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

$customers = [];
try {
    $customers = $pdo->query("SELECT * FROM customers ORDER BY customer_id DESC")->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .btn-delete { background-color: #e63946; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; transition: background 0.2s; }
        .btn-delete:hover { background-color: #b71c1c; }
        .btn-toggle-deactivate { background-color: #f39c12; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 4px; transition: background 0.2s; }
        .btn-toggle-deactivate:hover { background-color: #d35400; }
        .btn-toggle-activate { background-color: #2ec4b6; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-right: 4px; transition: background 0.2s; }
        .btn-toggle-activate:hover { background-color: #0f9f90; }
        .badge-active { background-color: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .alert-msg { padding: 12px 15px; background-color: #e8f5e9; color: #2e7d32; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .alert-error { padding: 12px 15px; background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; border-radius: 5px; margin-bottom: 15px; font-weight: bold; }
        .creds-box { background-color: #e0f7fa; border: 1px solid #00acc1; color: #006064; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar">
            <h2>WellSpring Water</h2>
            <a href="dashboard.php">Dashboard</a>
            <a href="customers.php" class="active">Customers</a>
            <a href="billing.php">Billing & Invoices</a>
            <a href="payments.php">Payments</a>
            <a href="notifications.php">Notifications</a>
            <a href="logout.php" style="background: #e63946;">Logout</a>
        </nav>

        <main class="main-content">
            <h2>Customer Management Module</h2>

            <?php if (!empty($error_msg)): ?>
                <div class="alert-error">
                    <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
                <div class="alert-msg">
                    ✅ Customer registered & login account generated successfully!
                </div>
                <?php if (isset($_SESSION['created_creds'])): ?>
                    <div class="creds-box">
                        <h4 style="margin-top:0;">🔑 Login Details Generated for Customer:</h4>
                        <p style="margin: 5px 0;"><strong>Username:</strong> <?= htmlspecialchars($_SESSION['created_creds']['username']) ?></p>
                        <p style="margin: 5px 0;"><strong>Password:</strong> <?= htmlspecialchars($_SESSION['created_creds']['password']) ?></p>
                        <p style="margin: 5px 0;"><strong>Phone:</strong> <?= htmlspecialchars($_SESSION['created_creds']['phone']) ?></p>
                        <small><i>📱 An SMS notification with these details was dispatched to the customer.</i></small>
                    </div>
                    <?php unset($_SESSION['created_creds']); ?>
                <?php endif; ?>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert-msg" style="background-color: #ffebee; color: #c62828;">
                    🗑️ Customer and associated records deleted successfully!
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deactivated'): ?>
                <div class="alert-msg" style="background-color: #fff3cd; color: #856404;">
                    ⚠️ Customer account deactivated successfully. The customer will not be able to log in.
                </div>
            <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'activated'): ?>
                <div class="alert-msg">
                    ✅ Customer account activated successfully!
                </div>
            <?php endif; ?>

            <div class="table-container" style="margin-bottom:25px;">
                <h3>Register New Customer</h3>
                <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="First Name" required>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="Last Name" required>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="Phone Number (Used as default password)" required>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email Address">
                    <input type="text" name="address_line1" value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>" placeholder="Address Line 1" required>
                    <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" placeholder="City" required>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>" placeholder="Postal Code" required>
                    <input type="text" name="meter_number" value="<?= htmlspecialchars($_POST['meter_number'] ?? '') ?>" placeholder="Meter Number (e.g. MTR-10293)">
                    <button type="submit" name="add_customer" class="btn" style="grid-column: span 2;">Register Customer & Generate Account</button>
                </form>
            </div>

            <div class="table-container">
                <h3>Registered Customers</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #ddd;">
                            <th style="padding: 10px;">ID</th>
                            <th style="padding: 10px;">Name</th>
                            <th style="padding: 10px;">Phone</th>
                            <th style="padding: 10px;">City</th>
                            <th style="padding: 10px;">Meter #</th>
                            <th style="padding: 10px;">Status</th>
                            <th style="padding: 10px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) > 0): ?>
                            <?php foreach($customers as $c): ?>
                                <?php $isActive = isset($c['is_active']) && (int)$c['is_active'] === 1; ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;"><?= $c['customer_id'] ?></td>
                                    <td style="padding: 10px;"><b><?= htmlspecialchars(($c['first_name'] ?? 'N/A') . ' ' . ($c['last_name'] ?? '')) ?></b></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($c['phone'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($c['city'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px;"><?= htmlspecialchars($c['meter_number'] ?? 'N/A') ?></td>
                                    <td style="padding: 10px;">
                                        <span class="<?= $isActive ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 10px; text-align: center; white-space: nowrap;">
                                        <?php if ($isActive): ?>
                                            <a href="customers.php?action=toggle_status&id=<?= $c['customer_id'] ?>" 
                                               class="btn-toggle-deactivate"
                                               onclick="return confirm('Deactivate customer? They will not be able to log in.');">
                                                Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="customers.php?action=toggle_status&id=<?= $c['customer_id'] ?>" 
                                               class="btn-toggle-activate"
                                               onclick="return confirm('Activate customer?');">
                                                Activate
                                            </a>
                                        <?php endif; ?>

                                        <a href="customers.php?action=delete&id=<?= $c['customer_id'] ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('⚠️ Are you sure you want to delete this customer? All associated bills and payments will also be removed.');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="padding: 15px; text-align: center; color: #777;">No customers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
