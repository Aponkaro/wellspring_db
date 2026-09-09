<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    if ($schedule_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM distribution_schedules WHERE schedule_id = ?");
            $stmt->execute([$schedule_id]);
            header("Location: supply.php?deleted=1");
            exit;
        } catch (PDOException $e) {
            $message = "<p style='color:red; font-weight:bold;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $schedule_id = intval($_POST['schedule_id'] ?? 0);
    $new_status = $_POST['status'] ?? 'Scheduled';

    if ($schedule_id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE distribution_schedules SET status = ? WHERE schedule_id = ?");
            $stmt->execute([$new_status, $schedule_id]);
            header("Location: supply.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $message = "<p style='color:red; font-weight:bold;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $zone_id = !empty($_POST['zone_id']) ? intval($_POST['zone_id']) : null;
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $status = $_POST['status'] ?? 'Scheduled';
    $assigned_crew = trim($_POST['assigned_crew'] ?? '');

    if ($zone_id && !empty($start_time) && !empty($end_time)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO distribution_schedules (zone_id, start_time, end_time, status, assigned_crew) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$zone_id, $start_time, $end_time, $status, $assigned_crew]);
            header("Location: supply.php?success=1");
            exit;
        } catch (PDOException $e) {
            $message = "<p style='color:red; font-weight:bold;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        $message = "<p style='color:red; font-weight:bold;'>Please fill in all required fields.</p>";
    }
}

if (isset($_GET['success'])) $message = "<p style='color:green; font-weight:bold;'>Schedule created successfully!</p>";
if (isset($_GET['deleted'])) $message = "<p style='color:green; font-weight:bold;'>Record deleted successfully!</p>";
if (isset($_GET['updated'])) $message = "<p style='color:green; font-weight:bold;'>Status updated successfully!</p>";

$zones = $pdo->query("SELECT * FROM supply_zones ORDER BY zone_name ASC")->fetchAll();
$schedules = $pdo->query("SELECT s.*, z.zone_name FROM distribution_schedules s JOIN supply_zones z ON s.zone_id = z.zone_id ORDER BY s.schedule_id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supply & Distribution - WellSpring Water</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <h1 class="fade-in">Water Supply & Distribution</h1>
            <?= $message ?>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 25px;">
                <h3>Create Distribution Schedule</h3>
                <form method="POST" action="supply.php" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Supply Zone</label>
                        <select name="zone_id" required style="width:100%; padding:8px;">
                            <option value="">-- Select Supply Zone --</option>
                            <?php foreach($zones as $z): ?>
                                <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" required style="width:100%; padding:8px;">
                            <option value="Pending">Pending</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="datetime-local" name="start_time" required style="width:100%; padding:8px;">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="datetime-local" name="end_time" required style="width:100%; padding:8px;">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Assigned Crew</label>
                        <input type="text" name="assigned_crew" placeholder="e.g. Team Alpha" style="width:100%; padding:8px;">
                    </div>
                    <button type="submit" name="add_schedule" class="btn" style="grid-column: span 2; padding: 10px; background: #0077b6; color: white; border: none; cursor: pointer;">Schedule Distribution</button>
                </form>
            </div>

            <div class="card-container fade-in" style="background: white; padding: 20px; border-radius: 8px;">
                <h3>Active Distribution Schedules</h3>
                <table style="width:100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr style="background:#0077b6; color:white;">
                            <th style="padding:10px;">ID</th>
                            <th style="padding:10px;">Zone Name</th>
                            <th style="padding:10px;">Start Time</th>
                            <th style="padding:10px;">End Time</th>
                            <th style="padding:10px;">Status</th>
                            <th style="padding:10px;">Assigned Crew</th>
                            <th style="padding:10px; text-align:center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($schedules) > 0): ?>
                            <?php foreach($schedules as $s): ?>
                            <tr style="border-bottom: 1px solid #ddd;">
                                <td style="padding:10px;"><?= $s['schedule_id'] ?></td>
                                <td style="padding:10px;"><?= htmlspecialchars($s['zone_name']) ?></td>
                                <td style="padding:10px;"><?= $s['start_time'] ?></td>
                                <td style="padding:10px;"><?= $s['end_time'] ?></td>
                                <td style="padding:10px;"><b><?= htmlspecialchars($s['status']) ?></b></td>
                                <td style="padding:10px;"><?= htmlspecialchars($s['assigned_crew'] ?? 'Unassigned') ?></td>
                                <td style="padding:10px; text-align:center;">
                                    <div style="display:flex; gap: 8px; justify-content:center;">
                                        <form method="POST" action="supply.php" style="margin:0;">
                                            <input type="hidden" name="schedule_id" value="<?= $s['schedule_id'] ?>">
                                            <select name="status" onchange="this.form.submit()" style="padding:5px;">
                                                <option value="Pending" <?= $s['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Scheduled" <?= $s['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                <option value="Ongoing" <?= $s['status'] === 'Ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                                <option value="Completed" <?= $s['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Cancelled" <?= $s['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <form method="POST" action="supply.php" onsubmit="return confirm('Delete record?');" style="margin:0;">
                                            <input type="hidden" name="schedule_id" value="<?= $s['schedule_id'] ?>">
                                            <button type="submit" name="delete_schedule" style="padding:5px 10px; background:#e63946; color:white; border:none; border-radius:4px;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="padding:10px; text-align:center;">No distribution schedules found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>