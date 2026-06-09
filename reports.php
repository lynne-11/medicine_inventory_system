<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$category = isset($_GET['category']) ? $_GET['category'] : '';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

$cat_filter = $category != '' ? "AND m.category = '$category'" : '';

$total_in = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(st.quantity) as total FROM stock_transactions st
    JOIN medicines m ON st.medicine_id = m.id
    WHERE st.type = 'stock_in'
    AND DATE_FORMAT(st.created_at, '%Y-%m') = '$month' $cat_filter
"))['total'] ?? 0;

$total_out = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(st.quantity) as total FROM stock_transactions st
    JOIN medicines m ON st.medicine_id = m.id
    WHERE st.type = 'stock_out'
    AND DATE_FORMAT(st.created_at, '%Y-%m') = '$month' $cat_filter
"))['total'] ?? 0;

$total_transactions = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as count FROM stock_transactions st
    JOIN medicines m ON st.medicine_id = m.id
    WHERE DATE_FORMAT(st.created_at, '%Y-%m') = '$month' $cat_filter
"))['count'] ?? 0;

$most_used = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT m.name, SUM(st.quantity) as total
    FROM stock_transactions st
    JOIN medicines m ON st.medicine_id = m.id
    WHERE st.type = 'stock_out'
    AND DATE_FORMAT(st.created_at, '%Y-%m') = '$month' $cat_filter
    GROUP BY st.medicine_id
    ORDER BY total DESC LIMIT 1
"));

$top_medicines = mysqli_query($conn, "
    SELECT m.name, SUM(st.quantity) as total
    FROM stock_transactions st
    JOIN medicines m ON st.medicine_id = m.id
    WHERE st.type = 'stock_out'
    AND DATE_FORMAT(st.created_at, '%Y-%m') = '$month' $cat_filter
    GROUP BY st.medicine_id
    ORDER BY total DESC LIMIT 6
");

$max_usage = 1;
$top_data = [];
while ($row = mysqli_fetch_assoc($top_medicines)) {
    $top_data[] = $row;
    if ($row['total'] > $max_usage) $max_usage = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Reports</title>
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
        .filter-bar { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 16px 20px; display: flex; gap: 16px; align-items: center; margin-bottom: 24px; flex-wrap: wrap; }
        .filter-bar label { font-size: 13px; font-weight: bold; color: #333; }
        .filter-bar select, .filter-bar input[type="month"] { padding: 8px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; color: #333; width: auto; }
        .filter-btn { padding: 8px 18px; background: #185FA5; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .print-btn { padding: 8px 18px; background: #27ae60; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #111; }
        .stat-card .sub { font-size: 11px; color: #aaa; margin-top: 4px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 24px; margin-bottom: 24px; }
        .bar-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .bar-label { font-size: 13px; color: #333; width: 160px; flex-shrink: 0; }
        .bar-track { flex: 1; height: 10px; background: #f0f0f0; border-radius: 99px; overflow: hidden; }
        .bar-fill { height: 100%; background: #185FA5; border-radius: 99px; }
        .bar-value { font-size: 13px; font-weight: bold; color: #333; width: 40px; text-align: right; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-in { background: #d5f5e3; color: #1e8449; }
        .badge-out { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        @media print {
            .sidebar { display: none; }
            .main { margin-left: 0; }
            .logout-btn { display: none; }
            .filter-bar { display: none; }
            .print-header { display: block !important; }
        }
        .print-header { display: none; text-align: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #111; }
        .print-header h2 { font-size: 20px; color: #111; }
        .print-header p { font-size: 13px; color: #555; margin-top: 4px; }
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
    <a href="alerts.php" class="nav-item">
        Alerts
        <?php if ($alert_count > 0) { ?>
            <span class="alert-badge"><?php echo $alert_count; ?></span>
        <?php } ?>
    </a>
    <div class="nav-section">Reports</div>
    <a href="audit.php" class="nav-item">Stock Audit</a>
    <a href="reports.php" class="nav-item active">Reports</a>
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

    <div class="print-header">
        <h2>PharmTrack — Stock Report</h2>
        <p>Kiambu Sub-County Hospital — Pharmacy Department</p>
        <p>Month: <?php echo date('F Y', strtotime($month . '-01')); ?> | Generated by: <?php echo $_SESSION['user_name']; ?></p>
    </div>

    <div class="topbar">
        <div>
            <h1>Reports</h1>
            <p>Stock usage and transaction history</p>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

    <form method="GET" action="reports.php">
        <div class="filter-bar">
            <label>Month</label>
            <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>">
            <label>Category</label>
            <select name="category">
                <option value="">All Categories</option>
                <option value="Antibiotic" <?php echo $category=='Antibiotic'?'selected':''; ?>>Antibiotic</option>
                <option value="Analgesic" <?php echo $category=='Analgesic'?'selected':''; ?>>Analgesic</option>
                <option value="Antidiabetic" <?php echo $category=='Antidiabetic'?'selected':''; ?>>Antidiabetic</option>
                <option value="Antihypertensive" <?php echo $category=='Antihypertensive'?'selected':''; ?>>Antihypertensive</option>
                <option value="Rehydration" <?php echo $category=='Rehydration'?'selected':''; ?>>Rehydration</option>
                <option value="Antifungal" <?php echo $category=='Antifungal'?'selected':''; ?>>Antifungal</option>
                <option value="Other" <?php echo $category=='Other'?'selected':''; ?>>Other</option>
            </select>
            <button type="submit" class="filter-btn">Generate Report</button>
            <button type="button" class="print-btn" onclick="window.print()">Print Report</button>
        </div>
    </form>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Stock In</div>
            <div class="value"><?php echo number_format($total_in); ?></div>
            <div class="sub">units received</div>
        </div>
        <div class="stat-card">
            <div class="label">Total Stock Out</div>
            <div class="value"><?php echo number_format($total_out); ?></div>
            <div class="sub">units issued</div>
        </div>
        <div class="stat-card">
            <div class="label">Total Transactions</div>
            <div class="value"><?php echo $total_transactions; ?></div>
            <div class="sub">this month</div>
        </div>
        <div class="stat-card">
            <div class="label">Most Used Medicine</div>
            <div class="value" style="font-size:16px">
                <?php echo $most_used ? $most_used['name'] : 'N/A'; ?>
            </div>
            <div class="sub">
                <?php echo $most_used ? number_format($most_used['total']).' units issued' : 'No data yet'; ?>
            </div>
        </div>
    </div>

    <div class="section-title">Top Medicine Usage This Month</div>
    <div class="card">
        <?php if (count($top_data) > 0): ?>
            <?php foreach ($top_data as $item): ?>
                <div class="bar-row">
                    <div class="bar-label"><?php echo $item['name']; ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?php echo ($item['total']/$max_usage)*100; ?>%"></div>
                    </div>
                    <div class="bar-value"><?php echo $item['total']; ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">No usage data for this month yet.</div>
        <?php endif; ?>
    </div>

    <div class="section-title">
        Transaction History —
        <?php echo $category != '' ? $category.' — ' : ''; ?>
        <?php echo date('F Y', strtotime($month.'-01')); ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Done By</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $query = "
                SELECT st.*, m.name as medicine_name, u.name as user_name
                FROM stock_transactions st
                JOIN medicines m ON st.medicine_id = m.id
                JOIN users u ON st.user_id = u.id
                WHERE DATE_FORMAT(st.created_at, '%Y-%m') = '$month'
            ";
            if ($category != '') $query .= " AND m.category = '$category'";
            $query .= " ORDER BY st.created_at DESC";

            $transactions = mysqli_query($conn, $query);
            if (mysqli_num_rows($transactions) > 0) {
                $i = 1;
                while ($row = mysqli_fetch_assoc($transactions)) {
                    $badge = $row['type'] == 'stock_in' ? 'badge-in' : 'badge-out';
                    $label = $row['type'] == 'stock_in' ? 'Stock In' : 'Stock Out';
                    $date = date('d M Y', strtotime($row['created_at']));
                    $time = date('h:i A', strtotime($row['created_at']));
                    echo "<tr>
                        <td>{$i}</td>
                        <td>{$row['medicine_name']}</td>
                        <td><span class='badge {$badge}'>{$label}</span></td>
                        <td>{$row['quantity']}</td>
                        <td>{$row['user_name']}</td>
                        <td>{$date}</td>
                        <td>{$time}</td>
                    </tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='7' class='empty-state'>No transactions found.</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>