<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] != 'pharmacist') { header("Location: login.php"); exit(); }
include 'db.php';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$category = isset($_GET['category']) ? $_GET['category'] : '';

$where = "WHERE DATE_FORMAT(st.created_at, '%Y-%m') = '$month'";
if ($category != '') $where .= " AND m.category = '$category'";

$total_in = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(st.quantity) as total FROM stock_transactions st JOIN medicines m ON st.medicine_id = m.id $where AND st.type = 'stock_in'"))['total'] ?? 0;
$total_out = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(st.quantity) as total FROM stock_transactions st JOIN medicines m ON st.medicine_id = m.id $where AND st.type = 'stock_out'"))['total'] ?? 0;
$total_trans = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM stock_transactions st JOIN medicines m ON st.medicine_id = m.id $where"))['count'] ?? 0;
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
        .topbar-right { display: flex; gap: 10px; align-items: center; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .print-btn { background: #1e8449; color: white; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: bold; border: none; cursor: pointer; }
        .filter-card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 20px; margin-bottom: 24px; display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-group label { font-size: 13px; font-weight: bold; color: #333; }
        .filter-group input, .filter-group select { padding: 9px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; }
        .filter-btn { background: #1e8449; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; padding: 22px; border-radius: 12px; border: 1px solid #e8e8e8; }
        .stat-card .label { font-size: 12px; color: #888; margin-bottom: 8px; }
        .stat-card .value { font-size: 28px; font-weight: bold; }
        .stat-card .sub { font-size: 11px; color: #aaa; margin-top: 4px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; overflow-x: auto; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 500px; }
        th { background: #0d2b1a; padding: 12px 16px; text-align: left; font-size: 12px; color: white; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-in { background: #d5f5e3; color: #1e8449; }
        .badge-out { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        @media print { .sidebar, .menu-btn, .topbar-right, .filter-card, .overlay { display: none !important; } .main { margin-left: 0 !important; } }
        @media (max-width: 768px) { .sidebar { left: -240px; } .sidebar.open { left: 0; } .main { margin-left: 0; padding: 16px; padding-top: 60px; } .menu-btn { display: block; } .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<button class="menu-btn" onclick="openSidebar()">☰</button>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header"><h2>PharmTrack</h2><p>Kiambu Sub-County Hospital</p></div>
    <div class="nav-section">Main</div>
    <a href="pharmacist_dashboard.php" class="nav-item">Dashboard</a>
    <a href="pharmacist_stock_out.php" class="nav-item">Record Stock Out</a>
    <a href="pharmacist_alerts.php" class="nav-item">Alerts <?php if ($alert_count > 0) { ?><span class="alert-badge"><?php echo $alert_count; ?></span><?php } ?></a>
    <div class="nav-section">Reports</div>
    <a href="pharmacist_reports.php" class="nav-item active">View Reports</a>
    <div class="sidebar-footer"><p>Logged in as</p><h4><?php echo $_SESSION['user_name']; ?></h4><small><?php echo $_SESSION['user_role']; ?></small></div>
</div>
<div class="main">
    <div class="topbar">
        <div><h1>Reports</h1><p>Stock usage and transaction history</p></div>
        <div class="topbar-right">
            <button class="print-btn" onclick="window.print()">Print Report</button>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>
    <div class="filter-card">
        <form method="GET" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
            <div class="filter-group">
                <label>Month</label>
                <input type="month" name="month" value="<?php echo $month; ?>">
            </div>
            <div class="filter-group">
                <label>Category</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php
                    $cats = mysqli_query($conn, "SELECT DISTINCT category FROM medicines ORDER BY category");
                    while ($row = mysqli_fetch_assoc($cats)) {
                        $sel = $category == $row['category'] ? 'selected' : '';
                        echo "<option value='{$row['category']}' $sel>{$row['category']}</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" class="filter-btn">Generate Report</button>
        </form>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><div class="label">Total Stock In</div><div class="value" style="color:#1e8449;"><?php echo number_format($total_in); ?></div><div class="sub">units received</div></div>
        <div class="stat-card"><div class="label">Total Stock Out</div><div class="value" style="color:#e74c3c;"><?php echo number_format($total_out); ?></div><div class="sub">units issued</div></div>
        <div class="stat-card"><div class="label">Total Transactions</div><div class="value" style="color:#185FA5;"><?php echo $total_trans; ?></div><div class="sub">this month</div></div>
    </div>
    <div class="section-title">Transaction History — <?php echo date('F Y', strtotime($month . '-01')); ?></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Medicine</th><th>Category</th><th>Type</th><th>Quantity</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
            <?php
            $trans = mysqli_query($conn, "SELECT st.*, m.name as medicine_name, m.category, u.name as user_name FROM stock_transactions st JOIN medicines m ON st.medicine_id = m.id JOIN users u ON st.user_id = u.id $where ORDER BY st.created_at DESC");
            if (mysqli_num_rows($trans) > 0) {
                while ($row = mysqli_fetch_assoc($trans)) {
                    $badge = $row['type'] == 'stock_in' ? 'badge-in' : 'badge-out';
                    $label = $row['type'] == 'stock_in' ? 'Stock In' : 'Stock Out';
                    $date = date('d M Y h:i A', strtotime($row['created_at']));
                    echo "<tr><td>{$row['medicine_name']}</td><td>{$row['category']}</td><td><span class='badge {$badge}'>{$label}</span></td><td>{$row['quantity']}</td><td>{$row['user_name']}</td><td>{$date}</td></tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='empty-state'>No transactions found for this period</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function openSidebar() { document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
</script>
</body>
</html>