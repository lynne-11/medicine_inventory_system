<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$success = "";
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

// MARK ALERT AS RESOLVED
if (isset($_POST['resolve_alert'])) {
    $alert_id = $_POST['alert_id'];
    $sql = "UPDATE alerts SET status = 'resolved' WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $alert_id);
    mysqli_stmt_execute($stmt);
    $success = "Alert marked as resolved!";
    $alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];
}

$out_of_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts a JOIN stock_levels sl ON a.medicine_id = sl.medicine_id WHERE sl.current_quantity = 0 AND a.status = 'unread'"))['count'];
$low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts a JOIN stock_levels sl ON a.medicine_id = sl.medicine_id WHERE sl.current_quantity > 0 AND a.status = 'unread'"))['count'];
$resolved_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'resolved' AND DATE(created_at) = CURDATE()"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Alerts</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; display: flex; }
        .sidebar {
            width: 240px; background: #1a1a2e; min-height: 100vh;
            position: fixed; top: 0; left: 0; display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #2a2a4a; }
        .sidebar-header h2 { color: white; font-size: 20px; }
        .sidebar-header p { color: #aaa; font-size: 11px; margin-top: 4px; }
        .nav-section { padding: 16px 20px 6px; font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-item {
            display: flex; align-items: center; gap: 10px; padding: 12px 20px;
            color: #ccc; text-decoration: none; font-size: 14px;
            border-left: 3px solid transparent; transition: all 0.2s;
        }
        .nav-item:hover { background: #2a2a4a; color: white; }
        .nav-item.active { background: #2a2a4a; color: white; border-left-color: #4e9af1; }
        .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid #2a2a4a; }
        .sidebar-footer p { color: #aaa; font-size: 11px; }
        .sidebar-footer h4 { color: white; font-size: 14px; margin-top: 4px; }
        .sidebar-footer small { color: #4e9af1; font-size: 12px; }
        .alert-badge { margin-left: auto; background: #e74c3c; color: white; border-radius: 99px; padding: 1px 7px; font-size: 11px; }
        .main { margin-left: 240px; flex: 1; padding: 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; color: #111; }
        .topbar p { font-size: 13px; color: #888; margin-top: 2px; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: white; padding: 22px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; }
        .stat-card.danger .value { color: #e74c3c; }
        .stat-card.warning .value { color: #e67e22; }
        .stat-card.success .value { color: #27ae60; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 16px; }
        .alert-card {
            background: white; border-radius: 12px; border: 1px solid #e8e8e8;
            padding: 18px 20px; margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .alert-card.danger { border-left: 4px solid #e74c3c; background: #fff5f5; }
        .alert-card.warning { border-left: 4px solid #e67e22; background: #fffdf0; }
        .alert-card.resolved { border-left: 4px solid #27ae60; background: #f0fff4; }
        .alert-left { display: flex; align-items: center; gap: 16px; }
        .alert-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
        .dot-danger { background: #e74c3c; }
        .dot-warning { background: #e67e22; }
        .dot-resolved { background: #27ae60; }
        .alert-title { font-size: 14px; font-weight: bold; color: #111; }
        .alert-sub { font-size: 12px; color: #888; margin-top: 3px; }
        .alert-time { font-size: 11px; color: #aaa; margin-top: 3px; }
        .alert-right { display: flex; align-items: center; gap: 10px; }
        .badge { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-danger { background: #fdecea; color: #a93226; }
        .badge-warning { background: #fef9e7; color: #b7950b; }
        .badge-resolved { background: #d5f5e3; color: #1e8449; }
        .resolve-btn { padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; border: none; background: #185FA5; color: white; }
        .resolve-btn:hover { background: #0C447C; }
        .divider { border: none; border-top: 1px solid #e8e8e8; margin: 24px 0; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; background: white; border-radius: 12px; border: 1px solid #e8e8e8; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>PharmTrack</h2>
        <p>Kiambu Sub-County Hospital</p>
    </div>
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="nav-item">Dashboard</a>
    <a href="stock.php" class="nav-item">Stock Management</a>
    <a href="alerts.php" class="nav-item active">
        Alerts
        <?php if ($alert_count > 0) { ?>
            <span class="alert-badge"><?php echo $alert_count; ?></span>
        <?php } ?>
    </a>
    <div class="nav-section">Reports</div>
    <a href="audit.php" class="nav-item">Stock Audit</a>
    <a href="reports.php" class="nav-item">Reports</a>
    <?php if ($_SESSION['user_role'] == 'admin') { ?>
<div class="nav-section">Admin</div>
<a href="users.php" class="nav-item">Manage Users</a>
<?php } ?>
    <div class="sidebar-footer">
        <p>Logged in as</p>
        <h4><?php echo $_SESSION['user_name']; ?></h4>
        <small><?php echo $_SESSION['user_role']; ?></small>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Alerts</h1>
            <p>Low stock and out of stock notifications</p>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

    <?php if ($success != "") { ?>
        <div class="success-box"><?php echo $success; ?></div>
    <?php } ?>

    <div class="stats-grid">
        <div class="stat-card danger">
            <div class="label">Out of Stock</div>
            <div class="value"><?php echo $out_of_stock; ?></div>
        </div>
        <div class="stat-card warning">
            <div class="label">Low Stock</div>
            <div class="value"><?php echo $low_stock; ?></div>
        </div>
        <div class="stat-card success">
            <div class="label">Resolved Today</div>
            <div class="value"><?php echo $resolved_today; ?></div>
        </div>
    </div>

    <div class="section-title">Active Alerts</div>
    <?php
    $active_alerts = mysqli_query($conn, "
        SELECT a.*, m.name as medicine_name, sl.current_quantity
        FROM alerts a
        JOIN medicines m ON a.medicine_id = m.id
        JOIN stock_levels sl ON a.medicine_id = sl.medicine_id
        WHERE a.status = 'unread'
        ORDER BY a.created_at DESC
    ");
    if (mysqli_num_rows($active_alerts) > 0) {
        while ($row = mysqli_fetch_assoc($active_alerts)) {
            $is_out = $row['current_quantity'] == 0;
            $card_class = $is_out ? 'danger' : 'warning';
            $dot_class = $is_out ? 'dot-danger' : 'dot-warning';
            $badge_class = $is_out ? 'badge-danger' : 'badge-warning';
            $badge_label = $is_out ? 'Out of Stock' : 'Low Stock';
            $time = date('d M Y h:i A', strtotime($row['created_at']));
            echo "
            <div class='alert-card {$card_class}'>
                <div class='alert-left'>
                    <div class='alert-dot {$dot_class}'></div>
                    <div>
                        <div class='alert-title'>{$row['medicine_name']}</div>
                        <div class='alert-sub'>{$row['message']}</div>
                        <div class='alert-time'>Raised on {$time}</div>
                    </div>
                </div>
                <div class='alert-right'>
                    <span class='badge {$badge_class}'>{$badge_label}</span>
                    <form method='POST' style='display:inline'>
                        <input type='hidden' name='alert_id' value='{$row['id']}'>
                        <button type='submit' name='resolve_alert' class='resolve-btn'>Mark Resolved</button>
                    </form>
                </div>
            </div>";
        }
    } else {
        echo "<div class='empty-state'>No active alerts. All medicines are well stocked!</div>";
    }
    ?>

    <hr class="divider">

    <div class="section-title">Resolved Alerts</div>
    <?php
    $resolved_alerts = mysqli_query($conn, "
        SELECT a.*, m.name as medicine_name
        FROM alerts a
        JOIN medicines m ON a.medicine_id = m.id
        WHERE a.status = 'resolved'
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    if (mysqli_num_rows($resolved_alerts) > 0) {
        while ($row = mysqli_fetch_assoc($resolved_alerts)) {
            $time = date('d M Y h:i A', strtotime($row['created_at']));
            echo "
            <div class='alert-card resolved'>
                <div class='alert-left'>
                    <div class='alert-dot dot-resolved'></div>
                    <div>
                        <div class='alert-title'>{$row['medicine_name']} — Resolved</div>
                        <div class='alert-sub'>{$row['message']}</div>
                        <div class='alert-time'>{$time}</div>
                    </div>
                </div>
                <div class='alert-right'>
                    <span class='badge badge-resolved'>Resolved</span>
                </div>
            </div>";
        }
    } else {
        echo "<div class='empty-state'>No resolved alerts yet.</div>";
    }
    ?>
</div>

</body>
</html>