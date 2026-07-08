<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] != 'storekeeper') { header("Location: login.php"); exit(); }
include 'db.php';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];
$success = "";
$error = "";

if (isset($_POST['save_audit'])) {
    $medicine_id = $_POST['medicine_id'];
    $physical_qty = (int)$_POST['physical_qty'];
    $user_id = $_SESSION['user_id'];

    $recorded = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_quantity FROM stock_levels WHERE medicine_id = $medicine_id"));

    if (!$recorded) {
        $error = "Medicine not found.";
    } else {
        $recorded_qty = $recorded['current_quantity'];
        $discrepancy = $physical_qty - $recorded_qty;
        $sql = "INSERT INTO audit_log (medicine_id, recorded_qty, physical_qty, discrepancy, checked_by) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiiii", $medicine_id, $recorded_qty, $physical_qty, $discrepancy, $user_id);
        mysqli_stmt_execute($stmt);
        $success = "Audit saved successfully! Discrepancy: " . ($discrepancy >= 0 ? "+$discrepancy" : $discrepancy);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Stock Audit</title>
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
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 24px; }
        .card h3 { font-size: 16px; color: #111; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; color: #333; }
        input:focus, select:focus { outline: none; border-color: #1e8449; }
        .readonly-field { background: #f8f9fa; color: #555; }
        .btn-primary { background: #1e8449; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; }
        .btn-primary:hover { background: #196f3d; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; overflow-x: auto; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
        th { background: #0d2b1a; padding: 12px 16px; text-align: left; font-size: 12px; color: white; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-disc { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .recent-item { padding: 14px 16px; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center; }
        .recent-item:last-child { border-bottom: none; }
        .recent-info h5 { font-size: 13px; font-weight: bold; color: #333; margin: 0 0 4px; }
        .recent-info p { font-size: 12px; color: #888; margin: 0; }
        .disc-value { font-size: 18px; font-weight: bold; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { left: -240px; } .sidebar.open { left: 0; } .main { margin-left: 0; padding: 16px; padding-top: 60px; } .menu-btn { display: block; } }
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
    <a href="storekeeper_alerts.php" class="nav-item">Alerts <?php if ($alert_count > 0) { ?><span class="alert-badge"><?php echo $alert_count; ?></span><?php } ?></a>
    <div class="nav-section">Reports</div>
    <a href="storekeeper_audit.php" class="nav-item active">Stock Audit</a>
    <div class="sidebar-footer"><p>Logged in as</p><h4><?php echo $_SESSION['user_name']; ?></h4><small><?php echo $_SESSION['user_role']; ?></small></div>
</div>
<div class="main">
    <div class="topbar">
        <div><h1>Stock Audit</h1><p>Compare recorded stock versus physical count</p></div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
    <?php if ($success != "") { ?><div class="success-box"><?php echo $success; ?></div><?php } ?>
    <?php if ($error != "") { ?><div class="error-box"><?php echo $error; ?></div><?php } ?>
    <div class="two-col">
        <div class="card">
            <h3>Run New Audit</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Medicine</label>
                    <select name="medicine_id" required onchange="loadStock(this.value)">
                        <option value="">Select medicine to audit</option>
                        <?php
                        $meds = mysqli_query($conn, "SELECT m.id, m.name, sl.current_quantity FROM medicines m JOIN stock_levels sl ON m.id = sl.medicine_id ORDER BY m.name");
                        while ($row = mysqli_fetch_assoc($meds)) {
                            echo "<option value='{$row['id']}' data-qty='{$row['current_quantity']}'>{$row['name']} (Recorded: {$row['current_quantity']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Recorded Quantity (System)</label>
                    <input type="number" id="recorded_qty" class="readonly-field" readonly placeholder="Select medicine first">
                </div>
                <div class="form-group">
                    <label>Physical Count (What you counted)</label>
                    <input type="number" name="physical_qty" id="physical_qty" placeholder="Enter your physical count" min="0" required onchange="calcDisc()">
                </div>
                <div class="form-group">
                    <label>Discrepancy (Auto calculated)</label>
                    <input type="number" id="discrepancy" class="readonly-field" readonly placeholder="Will be calculated automatically">
                </div>
                <div class="form-group">
                    <label>Audited By</label>
                    <input type="text" class="readonly-field" value="<?php echo $_SESSION['user_name']; ?>" readonly>
                </div>
                <button type="submit" name="save_audit" class="btn-primary">Save Audit Result</button>
            </form>
        </div>
        <div class="card">
            <h3>Recent Audit Results</h3>
            <?php
            $recent = mysqli_query($conn, "SELECT al.*, m.name as medicine_name FROM audit_log al JOIN medicines m ON al.medicine_id = m.id WHERE al.checked_by = {$_SESSION['user_id']} ORDER BY al.checked_at DESC LIMIT 5");
            if (mysqli_num_rows($recent) > 0) {
                while ($row = mysqli_fetch_assoc($recent)) {
                    $disc = $row['discrepancy'];
                    $color = $disc == 0 ? '#27ae60' : '#e74c3c';
                    $prefix = $disc > 0 ? '+' : '';
                    $date = date('d M Y', strtotime($row['checked_at']));
                    echo "<div class='recent-item'>
                        <div class='recent-info'>
                            <h5>{$row['medicine_name']}</h5>
                            <p>Recorded: {$row['recorded_qty']} | Physical: {$row['physical_qty']} | {$date}</p>
                        </div>
                        <div class='disc-value' style='color:{$color};'>{$prefix}{$disc}</div>
                    </div>";
                }
            } else {
                echo "<div class='empty-state'>No audits done yet</div>";
            }
            ?>
        </div>
    </div>
    <div class="section-title">Full Audit Log</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Medicine</th><th>Recorded Qty</th><th>Physical Qty</th><th>Discrepancy</th><th>Audited By</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php
            $audits = mysqli_query($conn, "SELECT al.*, m.name as medicine_name, u.name as user_name FROM audit_log al JOIN medicines m ON al.medicine_id = m.id JOIN users u ON al.checked_by = u.id ORDER BY al.checked_at DESC");
            if (mysqli_num_rows($audits) > 0) {
                while ($row = mysqli_fetch_assoc($audits)) {
                    $disc = $row['discrepancy'];
                    $status = $disc == 0 ? "<span class='badge badge-ok'>No Discrepancy</span>" : "<span class='badge badge-disc'>Discrepancy</span>";
                    $prefix = $disc > 0 ? '+' : '';
                    $date = date('d M Y', strtotime($row['checked_at']));
                    echo "<tr>
                        <td>{$row['medicine_name']}</td>
                        <td>{$row['recorded_qty']}</td>
                        <td>{$row['physical_qty']}</td>
                        <td style='color:" . ($disc == 0 ? '#27ae60' : '#e74c3c') . ";font-weight:bold;'>{$prefix}{$disc}</td>
                        <td>{$row['user_name']}</td>
                        <td>{$date}</td>
                        <td>{$status}</td>
                    </tr>";
                }
            } else {
                echo "<tr><td colspan='7' class='empty-state'>No audit records yet</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function loadStock(val) {
    var sel = document.querySelector('select[name="medicine_id"]');
    var opt = sel.options[sel.selectedIndex];
    document.getElementById('recorded_qty').value = opt.getAttribute('data-qty') || '';
    document.getElementById('physical_qty').value = '';
    document.getElementById('discrepancy').value = '';
}
function calcDisc() {
    var rec = parseInt(document.getElementById('recorded_qty').value) || 0;
    var phy = parseInt(document.getElementById('physical_qty').value) || 0;
    document.getElementById('discrepancy').value = phy - rec;
}
function openSidebar() { document.getElementById('sidebar').classList.add('open'); document.getElementById('overlay').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('open'); }
</script>
</body>
</html>