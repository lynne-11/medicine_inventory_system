<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

// GET MEDICINE ID
if (!isset($_GET['id'])) {
    header("Location: stock.php");
    exit();
}

$id = $_GET['id'];

// GET MEDICINE DETAILS
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

// GET TRANSACTION HISTORY
$transactions = mysqli_query($conn, "
    SELECT st.*, u.name as user_name
    FROM stock_transactions st
    JOIN users u ON st.user_id = u.id
    WHERE st.medicine_id = $id
    ORDER BY st.created_at DESC
");

// GET AUDIT HISTORY
$audits = mysqli_query($conn, "
    SELECT al.*, u.name as user_name
    FROM audit_log al
    JOIN users u ON al.checked_by = u.id
    WHERE al.medicine_id = $id
    ORDER BY al.checked_at DESC
");

// GET ALERTS
$alerts = mysqli_query($conn, "
    SELECT * FROM alerts
    WHERE medicine_id = $id
    ORDER BY created_at DESC
");

// CALCULATE STATS
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
        .topbar-right { display: flex; gap: 10px; align-items: center; }
        .back-btn { background: #f4f6f8; color: #333; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; border: 1px solid #e0e0e0; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }

        /* MEDICINE INFO CARD */
        .medicine-card {
            background: white; border-radius: 12px; border: 1px solid #e8e8e8;
            padding: 24px; margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .medicine-info h2 { font-size: 22px; color: #111; margin-bottom: 8px; }
        .medicine-meta { display: flex; gap: 16px; flex-wrap: wrap; }
        .meta-item { font-size: 13px; color: #666; }
        .meta-item strong { color: #333; }
        .stock-display { text-align: center; }
        .stock-number { font-size: 48px; font-weight: bold; color: #185FA5; }
        .stock-label { font-size: 13px; color: #888; margin-top: 4px; }
        .badge { padding: 4px 12px; border-radius: 99px; font-size: 12px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-low { background: #fef9e7; color: #b7950b; }
        .badge-empty { background: #fdecea; color: #a93226; }

        /* STATS */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #111; }
        .stat-card.success .value { color: #27ae60; }
        .stat-card.danger .value { color: #e74c3c; }

        /* TABS */
        .tabs { display: flex; gap: 4px; margin-bottom: 16px; }
        .tab {
            padding: 9px 20px; border-radius: 8px; font-size: 13px;
            cursor: pointer; border: 1px solid #e0e0e0;
            background: white; color: #666; font-weight: bold;
        }
        .tab.active { background: #185FA5; color: white; border-color: #185FA5; }

        /* TABLE */
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge-in { background: #d5f5e3; color: #1e8449; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-out { background: #fdecea; color: #a93226; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-disc { background: #fdecea; color: #a93226; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-nodisc { background: #d5f5e3; color: #1e8449; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }
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
    <div class="nav-section">Admin</div>
    <a href="users.php" class="nav-item">Manage Users</a>
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
            <p>Full details and history for this medicine</p>
        </div>
        <div class="topbar-right">
            <a href="stock.php" class="back-btn">Back to Stock</a>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>

    <!-- MEDICINE INFO -->
    <div class="medicine-card">
        <div class="medicine-info">
            <h2><?php echo $medicine['name']; ?></h2>
            <div class="medicine-meta">
                <div class="meta-item"><strong>Category:</strong> <?php echo $medicine['category']; ?></div>
                <div class="meta-item"><strong>Unit:</strong> <?php echo $medicine['unit']; ?></div>
                <div class="meta-item"><strong>Min Threshold:</strong> <?php echo $medicine['min_threshold']; ?></div>
                <div class="meta-item"><strong>Added:</strong> <?php echo date('d M Y', strtotime($medicine['created_at'])); ?></div>
                <div class="meta-item">
                    <?php
                    $qty = $medicine['current_quantity'] ?? 0;
                    if ($qty == 0) {
                        echo "<span class='badge badge-empty'>Out of Stock</span>";
                    } elseif ($qty <= $medicine['min_threshold']) {
                        echo "<span class='badge badge-low'>Low Stock</span>";
                    } else {
                        echo "<span class='badge badge-ok'>OK</span>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="stock-display">
            <div class="stock-number"><?php echo $qty; ?></div>
            <div class="stock-label"><?php echo $medicine['unit']; ?> in stock</div>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="label">Total Stock In</div>
            <div class="value"><?php echo number_format($total_in); ?></div>
        </div>
        <div class="stat-card danger">
            <div class="label">Total Stock Out</div>
            <div class="value"><?php echo number_format($total_out); ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Transactions</div>
            <div class="value"><?php echo $total_transactions; ?></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <div class="tab active" onclick="showTab('transactions', this)">Transaction History</div>
        <div class="tab" onclick="showTab('audits', this)">Audit History</div>
        <div class="tab" onclick="showTab('alerts', this)">Alerts</div>
    </div>

    <!-- TRANSACTIONS TAB -->
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
                if (mysqli_num_rows($transactions) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($transactions)) {
                        $badge = $row['type'] == 'stock_in' ? 'badge-in' : 'badge-out';
                        $label = $row['type'] == 'stock_in' ? 'Stock In' : 'Stock Out';
                        $date = date('d M Y', strtotime($row['created_at']));
                        $time = date('h:i A', strtotime($row['created_at']));
                        $notes = $row['notes'] ? $row['notes'] : '-';
                        echo "<tr>
                            <td>{$i}</td>
                            <td><span class='{$badge}'>{$label}</span></td>
                            <td>{$row['quantity']}</td>
                            <td>{$notes}</td>
                            <td>{$row['user_name']}</td>
                            <td>{$date}</td>
                            <td>{$time}</td>
                        </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='7' class='empty-state'>No transactions yet for this medicine</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AUDITS TAB -->
    <div class="tab-content" id="tab-audits">
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
                if (mysqli_num_rows($audits) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($audits)) {
                        $disc = $row['discrepancy'];
                        $status = $disc == 0
                            ? "<span class='badge-nodisc'>No Discrepancy</span>"
                            : "<span class='badge-disc'>Discrepancy ({$disc})</span>";
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
                    echo "<tr><td colspan='7' class='empty-state'>No audit records for this medicine</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ALERTS TAB -->
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
                if (mysqli_num_rows($alerts) > 0) {
                    $i = 1;
                    while ($row = mysqli_fetch_assoc($alerts)) {
                        $date = date('d M Y h:i A', strtotime($row['created_at']));
                        $status_badge = $row['status'] == 'resolved'
                            ? "<span class='badge-nodisc'>Resolved</span>"
                            : "<span class='badge-out'>Unread</span>";
                        echo "<tr>
                            <td>{$i}</td>
                            <td>{$row['message']}</td>
                            <td>{$status_badge}</td>
                            <td>{$date}</td>
                        </tr>";
                        $i++;
                    }
                } else {
                    echo "<tr><td colspan='4' class='empty-state'>No alerts for this medicine</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function showTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}
</script>

</body>
</html>