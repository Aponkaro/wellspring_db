<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar">
    <h2>WellSpring Water</h2>
    <p style="text-align:center; font-size:14px;">User: <b><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?></b> (<?= htmlspecialchars($_SESSION['role'] ?? 'Staff') ?>)</p>
    <hr style="border-color: rgba(255,255,255,0.2);">
    <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="customers.php" class="<?= $current_page === 'customers.php' ? 'active' : '' ?>">Customer Management</a>
    <a href="supply.php" class="<?= $current_page === 'supply.php' ? 'active' : '' ?>">Supply & Distribution</a>
    <a href="meters.php" class="<?= $current_page === 'meters.php' ? 'active' : '' ?>">Meters & Consumption</a>
    <a href="billing.php" class="<?= $current_page === 'billing.php' ? 'active' : '' ?>">Billing & Invoices</a>
    <a href="payments.php" class="<?= $current_page === 'payments.php' ? 'active' : '' ?>">Payment Management</a>
    <a href="inventory.php" class="<?= $current_page === 'inventory.php' ? 'active' : '' ?>">Inventory Management</a>
    <a href="maintenance.php" class="<?= $current_page === 'maintenance.php' ? 'active' : '' ?>">Maintenance & Faults</a>
    <a href="reports.php" class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">Reports & Analytics</a>
    <a href="notifications.php" class="<?= $current_page === 'notifications.php' ? 'active' : '' ?>">Notifications</a>
    <a href="logout.php" style="background:#e63946; margin-top: 15px;">Logout</a>
</nav>