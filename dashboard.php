<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM medicines"))['count'];
$low = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_levels sl JOIN medicines m ON sl.medicine_id = m.id WHERE sl.current_quantity <= m.min_threshold AND sl.current_quantity > 0"))['count'];
$out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_levels WHERE current_quantity = 0"))['count'];
$today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transactions WHERE DATE(created_at) = CURDATE()"))['count'];
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; display: flex; }

        /* SIDEBAR */
        .sidebar {
            width: 240px; background: #1a1a2e; min-height: 100vh;
            position: fixed; top: 0; left: 0; display: flex;
            flex-direction: column; z-index: 1000;
            transition: left 0.3s ease;
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

        /* OVERLAY */
        .overlay {
            display: none; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 999;
        }
        .overlay.open { display: block; }

        /* MAIN */
        .main { margin-left: 240px; flex: 1; padding: 28px; transition: margin-left 0.3s ease; }

        /* TOPBAR */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; color: #111; }
        .topbar p { font-size: 13px; color: #888; margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .menu-btn {
            display: none; background: #185FA5; color: white;
            border: none; padding: 9px 14px; border-radius: 8px;
            font-size: 20px; cursor: pointer; line-height: 1;
        }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .stat-card { background: white; padding: 22px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #111; }
        .stat-card .sub { font-size: 11px; color: #aaa; margin-top: 4px; }
        .stat-card.warning .value { color: #e67e22; }
        .stat-card.danger .value { color: #e74c3c; }
        .stat-card.success .value { color: #27ae60; }

        /* SECTIONS */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; }
        .view-all { font-size: 13px; color: #185FA5; text-decoration: none; }
        .two-col-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }

        /* TABLES */
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; margin-bottom: 24px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 400px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }

        /* BADGES */
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-in { background: #d5f5e3; color: #1e8449; }
        .badge-out { background: #fdecea; color: #a93226; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-low { background: #fef9e7; color: #b7950b; }
        .badge-empty { background: #fdecea; color: #a93226; }

        /* ALERTS */
        .alert-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-bottom: 1px solid #f5f5f5; }
        .alert-item:last-child { border-bottom: none; }
        .alert-title { font-size: 13px; font-weight: bold; color: #333; }
        .alert-sub { font-size: 12px; color: #888; margin-top: 2px; }
        .alert-item.warning { background: #fffdf0; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .two-col-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { left: -240px; }
            .sidebar.open { left: 0; }
            .main { margin-left: 0; padding: 16px; }
            .menu-btn { display: block; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 16px; }
            .stat-card .value { font-size: 22px; }
            table { font-size: 11px; }
            th, td { padding: 8px 10px; }
            .topbar h1 { font-size: 18px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px; }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>PharmTrack</h2>
        <p>Kiambu Sub-County Hospital</p>
    </div>
    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="nav-item active">Dashboard</a>
    <a href="stock.php" class="nav-item">Stock Management</a>
    <a href="alerts.php" class="nav-item">
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
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
        </div>
        <div class="topbar-right">
            <button class="menu-btn" onclick="openSidebar()">☰</button>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Medicines</div>
            <div class="value"><?php echo $total; ?></div>
            <div class="sub">in system</div>
        </div>
        <div class="stat-card warning">
            <div class="label">Low Stock</div>
            <div class="value"><?php echo $low; ?></div>
            <div class="sub">need restocking</div>
        </div>
        <div class="stat-card danger">
            <div class="label">Out of Stock</div>
            <div class="value"><?php echo $out; ?></div>
            <div class="sub">critical</div>
        </div>
        <div class="stat-card success">
            <div class="label">Transactions Today</div>
            <div class="value"><?php echo $today; ?></div>
            <div class="sub">stock in and out</div>
        </div>
    </div>

    <div class="two-col-grid">
        <div>
            <div class="section-header">
                <div class="section-title">Recent Transactions</div>
                <a href="stock.php" class="view-all">View all</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $transactions = mysqli_query($conn, "
                        SELECT st.*, m.name as medicine_name, u.name as user_name
                        FROM stock_transactions st
                        JOIN medicines m ON st.medicine_id = m.id
                        JOIN users u ON st.user_id = u.id
                        ORDER BY st.created_at DESC LIMIT 5
                    ");
                    if (mysqli_num_rows($transactions) > 0) {
                        while ($row = mysqli_fetch_assoc($transactions)) {
                            $badge = $row['type'] == 'stock_in' ? 'badge-in' : 'badge-out';
                            $label = $row['type'] == 'stock_in' ? 'Stock In' : 'Stock Out';
                            $time = date('h:i A', strtotime($row['created_at']));
                            echo "<tr>
                                <td>{$row['medicine_name']}</td>
                                <td><span class='badge {$badge}'>{$label}</span></td>
                                <td>{$row['quantity']}</td>
                                <td>{$time}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='empty-state'>No transactions yet</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div>
            <div class="section-header">
                <div class="section-title">Active Alerts</div>
                <a href="alerts.php" class="view-all">View all</a>
            </div>
            <div class="table-wrap">
                <?php
                $alerts = mysqli_query($conn, "
                    SELECT a.*, m.name as medicine_name
                    FROM alerts a
                    JOIN medicines m ON a.medicine_id = m.id
                    WHERE a.status = 'unread'
                    ORDER BY a.created_at DESC LIMIT 3
                ");
                if (mysqli_num_rows($alerts) > 0) {
                    while ($row = mysqli_fetch_assoc($alerts)) {
                        echo "<div class='alert-item warning'>
                            <div>
                                <div class='alert-title'>{$row['medicine_name']}</div>
                                <div class='alert-sub'>{$row['message']}</div>
                            </div>
                        </div>";
                    }
                } else {
                    echo "<div class='empty-state'>No active alerts</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <div class="section-header">
        <div class="section-title">Medicines Stock Status</div>
        <a href="stock.php" class="view-all">Manage stock</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Medicine Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>In Stock</th>
                    <th>Min Level</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $medicines = mysqli_query($conn, "
                SELECT m.*, sl.current_quantity
                FROM medicines m
                LEFT JOIN stock_levels sl ON m.id = sl.medicine_id
                ORDER BY m.name ASC
            ");
            if (mysqli_num_rows($medicines) > 0) {
                while ($row = mysqli_fetch_assoc($medicines)) {
                    $qty = $row['current_quantity'] ?? 0;
                    if ($qty == 0) {
                        $status = "<span class='badge badge-empty'>Out of Stock</span>";
                    } elseif ($qty <= $row['min_threshold']) {
                        $status = "<span class='badge badge-low'>Low Stock</span>";
                    } else {
                        $status = "<span class='badge badge-ok'>OK</span>";
                    }
                    echo "<tr>
                        <td>{$row['name']}</td>
                        <td>{$row['category']}</td>
                        <td>{$row['unit']}</td>
                        <td>{$qty}</td>
                        <td>{$row['min_threshold']}</td>
                        <td>{$status}</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='empty-state'>No medicines added yet.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openSidebar() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('overlay').classList.add('open');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('open');
}
</script>

</body>
</html>