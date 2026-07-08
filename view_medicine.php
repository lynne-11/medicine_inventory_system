<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

$medicine = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT m.*, sl.current_quantity
    FROM medicines m
    LEFT JOIN stock_levels sl ON m.id = sl.medicine_id
    WHERE m.id = $id
"));

if (!$medicine) {
    header("Location: stock.php");
    exit();
}

$total_in = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock_transactions WHERE medicine_id = $id AND type = 'stock_in'"))['total'] ?? 0;
$total_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as total FROM stock_transactions WHERE medicine_id = $id AND type = 'stock_out'"))['total'] ?? 0;
$total_transactions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transactions WHERE medicine_id = $id"))['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - View Medicine</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; display: flex; }
        .sidebar { width: 240px; background: #1a1a2e; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 100; transition: all 0.3s; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid #2a2a4a; }
        .sidebar-header h2 { color: white; font-size: 20px; }
        .sidebar-header p { color: #aaa; font-size: 11px; margin-top: 4px; }
        .nav-section { padding: 16px 20px 6px; font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: 0.05em; }
        .nav-item { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: #ccc; text-decoration: none; font-size: 14px; border-left: 3px solid transparent; transition: all 0.2s; }
        .nav-item:hover { background: #2a2a4a; color: white; }
        .nav-item.active { background: #2a2a4a; color: white; border-left-color: #4e9af1; }
        .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid #2a2a4a; }
        .sidebar-footer p { color: #aaa; font-size: 11px; }
        .sidebar-footer h4 { color: white; font-size: 14px; margin-top: 4px; }
        .sidebar-footer small { color: #4e9af1; font-size: 12px; }
        .alert-badge { margin-left: auto; background: #e74c3c; color: white; border-radius: 99px; padding: 1px 7px; font-size: 11px; }
        .main { margin-left: 240px; flex: 1; padding: 28px; transition: all 0.3s; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .topbar h1 { font-size: 22px; color: #111; }
        .topbar p { font-size: 13px; color: #888; margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .back-btn { background: #185FA5; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .menu-btn { display: none; background: #185FA5; color: white; border: none; padding: 9px 14px; border-radius: 8px; font-size: 18px; cursor: pointer; }
        /* Medicine Header Card */
        .med-header { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .med-info h2 { font-size: 22px; color: #111; margin-bottom: 6px; }
        .med-info p { font-size: 13px; color: #888; margin-bottom: 4px; }
        .med-stock { text-align: right; }
        .med-stock .qty { font-size: 48px; font-weight: bold; color: #185FA5; line-height: 1; }
        .med-stock .qty-label { font-size: 13px; color: #888; margin-top: 4px; }
        .badge { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-low { background: #fef9e7; color: #b7950b; }
        .badge-empty { background: #fdecea; color: #a93226; }
        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #111; }
        .stat-card.in .value { color: #27ae60; }
        .stat-card.out .value { color: #e74c3c; }
        .stat-card.total .value { color: #185FA5; }
        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 16px; background: white; border-radius: 10px; padding: 6px; border: 1px solid #e8e8e8; width: fit-content; flex-wrap: wrap; }
        .tab-btn { padding: 8px 20px; border-radius: 8px; border: none; font-size: 13px; font-weight: bold; cursor: pointer; background: transparent; color: #888; transition: all 0.2s; }
        .tab-btn.active { background: #185FA5; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        /* Tables */
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; overflow-x: auto; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 500px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge-in { background: #d5f5e3; color: #1e8449; }
        .badge-out { background: #fdecea; color: #a93226; }
        .badge-resolved { background: #d5f5e3; color: #1e8449; }
        .badge-unread { background: #fef9e7; color: #b7950b; }
        .badge-disc { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        /* Overlay */
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
        .overlay.open { display: block; }
        /* Responsive */
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .med-header { flex-direction: column; align-items: flex-start; }
            .med-stock { text-align: left; }
        }
        @media (max-width: 768px) {
            .sidebar { left: -240px; }
            .sidebar.open { left: 0; }
            .main { margin-left: 0; padding: 16px; }
            .menu-btn { display: block; }
            .stats-grid { grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
            .stat-card { padding: 14px; }
            .stat-card .value { font-size: 22px; }
            .med-stock .qty { font-size: 36px; }
            .tabs { width: 100%; }
            .tab-btn { flex: 1; text-align: center; padding: 8px 10px; font-size: 12px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
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
    <a href="dashboard.php" class="nav-item">Dashboard</a>
    <a href="stock.php" class="nav-item active">Stock Management</a>
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
            <h1>View Medicine</h1>
            <p>Full profile and history</p>
        </div>
        <div class="topbar-right">
            <button class="menu-btn" onclick="openSidebar()">☰</button>
            <a href="stock.php" class="back-btn">← Back</a>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>

    <!-- Medicine Header -->
    <div class="med-header">
        <div class="med-info">
            <h2><?php echo $medicine['name']; ?></h2>
            <p>Category: <strong><?php echo $medicine['category']; ?></strong></p>
            <p>Unit: <strong><?php echo $medicine['unit']; ?></strong></p>
            <p>Minimum Threshold: <strong><?php echo $medicine['min_threshold']; ?> <?php echo $medicine['unit']; ?></strong></p>
            <p>Date Added: <strong><?php echo date('d M Y', strtotime($medicine['created_at'])); ?></strong></p>
            <?php
            $qty = $medicine['current_quantity'] ?? 0;
            if ($qty == 0) {
                echo "<span class='badge badge-empty' style='margin-top:8px;display:inline-block'>Out of Stock</span>";
            } elseif ($qty <= $medicine['min_threshold']) {
                echo "<span class='badge badge-low' style='margin-top:8px;display:inline-block'>Low Stock</span>";
            } else {
                echo "<span class='badge badge-ok' style='margin-top:8px;display:inline-block'>In Stock</span>";
            }
            ?>
        </div>
        <div class="med-stock">
            <div class="qty"><?php echo $qty; ?></div>
            <div class="qty-label"><?php echo $medicine['unit']; ?> in stock</div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card in">
            <div class="label">Total Stock Received</div>
            <div class="value"><?php echo number_format($total_in); ?></div>
        </div>
        <div class="stat-card out">
            <div class="label">Total Stock Issued</div>
            <div class="value"><?php echo number_format($total_out); ?></div>
        </div>
        <div class="stat-card total">
            <div class="label">Total Transactions</div>
            <div class="value"><?php echo $total_transactions; ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('transactions', this)">Transaction History</button>
        <button class="tab-btn" onclick="showTab('audit', this)">Audit History</button>
        <button class="tab-btn" onclick="showTab('alerts', this)">Alerts</button>
    </div>

    <!-- Transaction History Tab -->
    <div class="tab-content active" id="tab-transactions">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Notes</th>
                        <th>Done By</th>
                        <th>Date</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $transactions = mysqli_query($conn, "
                    SELECT st.*, u.name as user_name
                    FROM stock_transactions st
                    JOIN users u ON st.user_id = u.id
                    WHERE st.medicine_id = $id
                    ORDER BY st.created_at DESC
                ");
                if (mysqli_num_rows($transactions) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($transactions)) {
                        $badge = $row['type'] == 'stock_in' ? 'badge-in' : 'badge-out';
                        $label = $row['type'] == 'stock_in' ? 'Stock In' : 'Stock Out';
                        $date = date('d M Y', strtotime($row['created_at']));
                        $time = date('h:i A', strtotime($row['created_at']));
                        $notes = $row['notes'] ? $row['notes'] : '—';
                        echo "<tr>
                            <td>{$i}</td>
                            <td><span class='badge {$badge}'>{$label}</span></td>
                            <td>{$row['quantity']}</td>
                            <td>{$notes}</td>
                            <td>{$row['user_name']}</td>
                            <td>{$date}</td>
                            <td>{$time}</td>
                        </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='7' class='empty-state'>No transactions recorded yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit History Tab -->
    <div class="tab-content" id="tab-audit">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recorded Qty</th>
                        <th>Physical Qty</th>
                        <th>Discrepancy</th>
                        <th>Audited By</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $audits = mysqli_query($conn, "
                    SELECT al.*, u.name as user_name
                    FROM audit_log al
                    JOIN users u ON al.checked_by = u.id
                    WHERE al.medicine_id = $id
                    ORDER BY al.checked_at DESC
                ");
                if (mysqli_num_rows($audits) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($audits)) {
                        $disc = $row['discrepancy'];
                        $status = $disc == 0
                            ? "<span class='badge badge-ok'>No Discrepancy</span>"
                            : "<span class='badge badge-disc'>Discrepancy</span>";
                        $date = date('d M Y', strtotime($row['checked_at']));
                        echo "<tr>
                            <td>{$i}</td>
                            <td>{$row['recorded_qty']}</td>
                            <td>{$row['physical_qty']}</td>
                            <td>{$disc}</td>
                            <td>{$row['user_name']}</td>
                            <td>{$date}</td>
                            <td>{$status}</td>
                        </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='7' class='empty-state'>No audit records yet.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alerts Tab -->
    <div class="tab-content" id="tab-alerts">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $alerts = mysqli_query($conn, "
                    SELECT * FROM alerts
                    WHERE medicine_id = $id
                    ORDER BY created_at DESC
                ");
                if (mysqli_num_rows($alerts) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($alerts)) {
                        $badge = $row['status'] == 'resolved' ? 'badge-resolved' : 'badge-unread';
                        $date = date('d M Y h:i A', strtotime($row['created_at']));
                        echo "<tr>
                            <td>{$i}</td>
                            <td>{$row['message']}</td>
                            <td><span class='badge {$badge}'>{$row['status']}</span></td>
                            <td>{$date}</td>
                        </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='4' class='empty-state'>No alerts for this medicine.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showTab(tab, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    btn.classList.add('active');
}
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