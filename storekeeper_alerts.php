<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] != 'storekeeper') { header("Location: login.php"); exit(); }
include 'db.php';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

if (isset($_GET['resolve'])) {
    $id = (int)$_GET['resolve'];
    mysqli_query($conn, "UPDATE alerts SET status = 'resolved' WHERE id = $id");
    header("Location: storekeeper_alerts.php");
    exit();
}
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
        .sidebar { width: 240px; background: #0d2b1a; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #1a4a2a; }
        .sidebar-header h2 { color: white; font-size: 20px; }
        .sidebar-header p { color: #aaa; font-size: 11px; margin-top: 4px; }
        .nav-section { padding: 16px 20px 6px; font-size: 11px; color: #555; text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: #9fe1cb; text-decoration: none; font-size: 14px; border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-item:hover { background: #1a4a2a; color: white; }
        .nav-item.active { background: #1a4a2a; color: white; border-left-color: #27ae60; }
        .alert-badge { margin-left: auto; background: #e74c3c; color: white; border-radius: 99px; padding: 1px 7px; font-size: 11px; }
        .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid #1a4a2a; }
        .sidebar-footer p { color: #aaa; font-size: 11px; }
        .sidebar-footer h4 { color: white; font-size: 14px; margin-top: 4px; }
        .sidebar-footer small { color: #27ae60; font-size: 12px; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
        .overlay.open { display: block; }
        .menu-btn { display: none; position: fixed; top: 16px; left: 16px; background: #1e8449; color: white; border: none; padding: 9px 14px; border-radius: 8px; font-size: 20px; cursor: pointer; z-index: 998; }
        .main { margin-left: 240px; flex: 1; padding: 28px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; color: #111; }
        .topbar p { font-size: 13px; color: #888; margin-top: 2px; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 22px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .alert-card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; margin-bottom: 24px; }
        .alert-item { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #f5f5f5; }
        .alert-item:last-child { border-bottom: none; }
        .alert-item.warning { border-left: 4px solid #e67e22; background: #fffdf0; }
        .alert-item.danger { border-left: 4px solid #e74c3c; background: #fff5f5; }
        .alert-info h5 { font-size: 14px; font-weight: bold; color: #333; margin: 0 0 4px; }
        .alert-info p { font-size: 12px; color: #888; margin: 0 0 4px; }
        .alert-info small { font-size: 11px; color: #aaa; }
        .resolve-btn { background: #1e8449; color: white; padding: 7px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; white-space: nowrap; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; font-size: 13px; }
        @media (max-width: 768px) { .sidebar { left: -240px; } .sidebar.open { left: 0; } .main { margin-left: 0; padding: 16px; padding-top: 60px; } .menu-btn { display: block; } .stats-grid { grid-template-columns: 1fr 1fr; } .alert-item { flex-direction: column; align-items: flex-start; gap: 10px; } }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<button class="menu-btn" onclick="openSidebar()">☰</button>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><h2>PharmTrack</h2><p>Kiambu Sub-County Hospital</p></div>
    <div class="nav-section">Main</div>
    <a href="storekeeper_dashboard.php" class="nav-item">Dashboard</a>
    <a href="storekeeper_stock_in.php" class="nav-item">Record Stock In</a>
    <a href="storekeeper_alerts.php" class="nav-item active">Alerts <?php if ($alert_count > 0) { ?><span class="alert-badge"><?php echo $alert_count; ?></span><?php } ?></a>
    <div class="nav-section">Reports</div>
    <a href="storekeeper_audit.php" class="nav-item">Stock Audit</a>
    <div class="sidebar-footer"><p>Logged in as</p><h4><?php echo $_SESSION['user_name']; ?></h4><small><?php echo $_SESSION['user_role']; ?></small></div>
</div>
<div class="main">
    <div class="topbar">
        <div><h1>Alerts</h1><p>Low stock and out of stock notifications</p></div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
    <?php
    $out_of_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread' AND message LIKE '%OUT OF STOCK%'"))['count'];
    $low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread' AND message LIKE '%running low%'"))['count'];
    $resolved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'resolved'"))['count'];
    ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="label">Out of Stock</div><div class="value" style="color:#e74c3c;"><?php echo $out_of_stock; ?></div></div>
        <div class="stat-card"><div class="label">Low Stock</div><div class="value" style="color:#e67e22;"><?php echo $low_stock; ?></div></div>
        <div class="stat-card"><div class="label">Resolved</div><div class="value" style="color:#27ae60;"><?php echo $resolved; ?></div></div>
    </div>
    <div class="section-title">Active Alerts</div>
    <div class="alert-card">
        <?php
        $alerts = mysqli_query($conn, "SELECT a.*, m.name as medicine_name FROM alerts a JOIN medicines m ON a.medicine_id = m.id WHERE a.status = 'unread' ORDER BY a.created_at DESC");
        if (mysqli_num_rows($alerts) > 0) {
            while ($row = mysqli_fetch_assoc($alerts)) {
                $class = strpos($row['message'], 'OUT OF STOCK') !== false ? 'danger' : 'warning';
                $date = date('d M Y h:i A', strtotime($row['created_at']));
                echo "<div class='alert-item {$class}'>
                    <div class='alert-info'>
                        <h5>{$row['medicine_name']}</h5>
                        <p>{$row['message']}</p>
                        <small>Raised on {$date}</small>
                    </div>
                    <a href='storekeeper_alerts.php?resolve={$row['id']}' class='resolve-btn'>Mark Resolved</a>
                </div>";
            }
        } else {
            echo "<div class='empty-state'>No active alerts</div>";
        }
        ?>
    </div>
    <div class="section-title">Resolved Alerts</div>
    <div class="alert-card">
        <?php
        $resolved_alerts = mysqli_query($conn, "SELECT a.*, m.name as medicine_name FROM alerts a JOIN medicines m ON a.medicine_id = m.id WHERE a.status = 'resolved' ORDER BY a.created_at DESC LIMIT 10");
        if (mysqli_num_rows($resolved_alerts) > 0) {
            while ($row = mysqli_fetch_assoc($resolved_alerts)) {
                $date = date('d M Y h:i A', strtotime($row['created_at']));
                echo "<div class='alert-item' style='opacity:0.6;'>
                    <div class='alert-info'>
                        <h5>{$row['medicine_name']}</h5>
                        <p>{$row['message']}</p>
                        <small>Raised on {$date}</small>
                    </div>
                    <span style='color:#27ae60;font-size:12px;font-weight:bold;'>✓ Resolved</span>
                </div>";
            }
        } else {
            echo "<div class='empty-state'>No resolved alerts yet</div>";
        }
        ?>
    </div>
</div>
<script>
function openSidebar() { document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
</script>
</body>
</html>