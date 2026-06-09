<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$success = "";
$error = "";
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

// SAVE AUDIT
if (isset($_POST['save_audit'])) {
    $medicine_id = $_POST['medicine_id'];
    $physical_qty = $_POST['physical_qty'];
    $checked_by = $_SESSION['user_id'];

    $stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_quantity FROM stock_levels WHERE medicine_id = $medicine_id"));
    $recorded_qty = $stock['current_quantity'];

    $sql = "INSERT INTO audit_log (medicine_id, recorded_qty, physical_qty, checked_by) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $medicine_id, $recorded_qty, $physical_qty, $checked_by);

    if (mysqli_stmt_execute($stmt)) {
        $success = "Audit saved successfully!";
    } else {
        $error = "Error saving audit.";
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
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 24px; }
        .card h3 { font-size: 16px; color: #111; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 6px; }
        input, select { width: 100%; padding: 10px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; color: #333; }
        input:focus, select:focus { outline: none; border-color: #185FA5; }
        .btn-primary { background: #185FA5; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .btn-primary:hover { background: #0C447C; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .audit-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f0f0f0; }
        .audit-item:last-child { border-bottom: none; }
        .audit-name { font-size: 13px; font-weight: bold; color: #111; }
        .audit-sub { font-size: 12px; color: #888; margin-top: 2px; }
        .audit-disc { font-size: 14px; font-weight: bold; }
        .disc-ok { color: #27ae60; }
        .disc-neg { color: #e74c3c; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-disc { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
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
    <a href="audit.php" class="nav-item active">Stock Audit</a>
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
            <h1>Stock Audit</h1>
            <p>Compare recorded stock versus physical count</p>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

    <?php if ($success != "") { ?>
        <div class="success-box"><?php echo $success; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
        <div class="error-box"><?php echo $error; ?></div>
    <?php } ?>

    <div class="two-col">
        <div class="card">
            <h3>Run New Audit</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Medicine</label>
                    <select name="medicine_id" required>
                        <option value="">Select medicine to audit</option>
                        <?php
                        $meds = mysqli_query($conn, "
                            SELECT m.id, m.name, sl.current_quantity
                            FROM medicines m
                            JOIN stock_levels sl ON m.id = sl.medicine_id
                            ORDER BY m.name ASC
                        ");
                        while ($m = mysqli_fetch_assoc($meds)) {
                            echo "<option value='{$m['id']}'>{$m['name']} (Recorded: {$m['current_quantity']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Physical Count (what you actually counted)</label>
                    <input type="number" name="physical_qty" placeholder="Enter your physical count" required>
                </div>
                <div class="form-group">
                    <label>Audited By</label>
                    <input type="text" value="<?php echo $_SESSION['user_name']; ?>" readonly>
                </div>
                <button type="submit" name="save_audit" class="btn-primary">Save Audit Result</button>
            </form>
        </div>

        <div class="card">
            <h3>Recent Audit Results</h3>
            <?php
            $recent = mysqli_query($conn, "
                SELECT al.*, m.name as medicine_name
                FROM audit_log al
                JOIN medicines m ON al.medicine_id = m.id
                ORDER BY al.checked_at DESC
                LIMIT 5
            ");
            if (mysqli_num_rows($recent) > 0) {
                while ($row = mysqli_fetch_assoc($recent)) {
                    $disc = $row['discrepancy'];
                    $disc_class = $disc == 0 ? 'disc-ok' : 'disc-neg';
                    $disc_label = $disc >= 0 ? "+$disc" : "$disc";
                    $date = date('d M Y', strtotime($row['checked_at']));
                    echo "<div class='audit-item'>
                        <div>
                            <div class='audit-name'>{$row['medicine_name']}</div>
                            <div class='audit-sub'>Recorded: {$row['recorded_qty']} | Physical: {$row['physical_qty']} | {$date}</div>
                        </div>
                        <div class='audit-disc {$disc_class}'>{$disc_label}</div>
                    </div>";
                }
            } else {
                echo "<div class='empty-state'>No audit records yet</div>";
            }
            ?>
        </div>
    </div>

    <div class="section-title">Full Audit Log</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
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
                SELECT al.*, m.name as medicine_name, u.name as user_name
                FROM audit_log al
                JOIN medicines m ON al.medicine_id = m.id
                JOIN users u ON al.checked_by = u.id
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
                        <td>{$row['medicine_name']}</td>
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
                echo "<tr><td colspan='8' class='empty-state'>No audit records yet</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>