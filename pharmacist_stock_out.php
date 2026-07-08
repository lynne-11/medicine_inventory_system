<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
if ($_SESSION['user_role'] != 'pharmacist') { header("Location: login.php"); exit(); }
include 'db.php';

$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];
$success = "";
$error = "";

if (isset($_POST['stock_out'])) {
    $medicine_id = $_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $notes = trim($_POST['notes']);
    $user_id = $_SESSION['user_id'];

    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_quantity FROM stock_levels WHERE medicine_id = $medicine_id"));

    if (!$current) {
        $error = "Medicine not found.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } elseif ($quantity > $current['current_quantity']) {
        $error = "Not enough stock! Only " . $current['current_quantity'] . " units available.";
    } else {
        $new_qty = $current['current_quantity'] - $quantity;
        mysqli_query($conn, "UPDATE stock_levels SET current_quantity = $new_qty WHERE medicine_id = $medicine_id");
        $sql = "INSERT INTO stock_transactions (medicine_id, user_id, type, quantity, notes) VALUES (?, ?, 'stock_out', ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiis", $medicine_id, $user_id, $quantity, $notes);
        mysqli_stmt_execute($stmt);

        $min = mysqli_fetch_assoc(mysqli_query($conn, "SELECT min_threshold, name FROM medicines WHERE id = $medicine_id"));
        if ($new_qty <= $min['min_threshold']) {
            $msg = $new_qty == 0 ? "{$min['name']} is OUT OF STOCK! Please restock immediately." : "{$min['name']} is running low. Only $new_qty units remaining.";
            mysqli_query($conn, "INSERT INTO alerts (medicine_id, message, status) VALUES ($medicine_id, '$msg', 'unread')");
        }
        $success = "Stock out recorded successfully!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Record Stock Out</title>
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
        .btn-primary { background: #1e8449; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 14px; font-weight: bold; cursor: pointer; }
        .btn-primary:hover { background: #196f3d; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; overflow-x: auto; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 500px; }
        th { background: #0d2b1a; padding: 12px 16px; text-align: left; font-size: 12px; color: white; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-low { background: #fef9e7; color: #b7950b; }
        .badge-empty { background: #fdecea; color: #a93226; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
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
    <a href="pharmacist_dashboard.php" class="nav-item">Dashboard</a>
    <a href="pharmacist_stock_out.php" class="nav-item active">Record Stock Out</a>
    <a href="pharmacist_alerts.php" class="nav-item">Alerts <?php if ($alert_count > 0) { ?><span class="alert-badge"><?php echo $alert_count; ?></span><?php } ?></a>
    <div class="nav-section">Reports</div>
    <a href="pharmacist_reports.php" class="nav-item">View Reports</a>
    <div class="sidebar-footer"><p>Logged in as</p><h4><?php echo $_SESSION['user_name']; ?></h4><small><?php echo $_SESSION['user_role']; ?></small></div>
</div>
<div class="main">
    <div class="topbar">
        <div><h1>Record Stock Out</h1><p>Issue medicines to patients</p></div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>
    <?php if ($success != "") { ?><div class="success-box"><?php echo $success; ?></div><?php } ?>
    <?php if ($error != "") { ?><div class="error-box"><?php echo $error; ?></div><?php } ?>
    <div class="two-col">
        <div class="card">
            <h3>Issue Medicine to Patient</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Medicine</label>
                    <select name="medicine_id" required>
                        <option value="">Select medicine</option>
                        <?php
                        $meds = mysqli_query($conn, "SELECT m.id, m.name, sl.current_quantity FROM medicines m JOIN stock_levels sl ON m.id = sl.medicine_id WHERE sl.current_quantity > 0 ORDER BY m.name");
                        while ($row = mysqli_fetch_assoc($meds)) {
                            echo "<option value='{$row['id']}'>{$row['name']} (In stock: {$row['current_quantity']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity to Issue</label>
                    <input type="number" name="quantity" placeholder="Enter quantity" min="1" required>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <input type="text" name="notes" placeholder="e.g. Issued to patient ward 3">
                </div>
                <button type="submit" name="stock_out" class="btn-primary">Issue Medicine (Stock Out)</button>
            </form>
        </div>
        <div class="card">
            <h3>Medicines Available for Issue</h3>
            <table>
                <thead><tr><th>Medicine</th><th>Category</th><th>In Stock</th><th>Status</th></tr></thead>
                <tbody>
                <?php
                $meds2 = mysqli_query($conn, "SELECT m.*, sl.current_quantity FROM medicines m JOIN stock_levels sl ON m.id = sl.medicine_id ORDER BY m.name");
                while ($row = mysqli_fetch_assoc($meds2)) {
                    $qty = $row['current_quantity'];
                    if ($qty == 0) $status = "<span class='badge badge-empty'>Out of Stock</span>";
                    elseif ($qty <= $row['min_threshold']) $status = "<span class='badge badge-low'>Low Stock</span>";
                    else $status = "<span class='badge badge-ok'>OK</span>";
                    echo "<tr><td>{$row['name']}</td><td>{$row['category']}</td><td>{$qty}</td><td>{$status}</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="section-title">My Stock Out History</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Medicine</th><th>Qty Issued</th><th>Notes</th><th>Date</th><th>Time</th></tr></thead>
            <tbody>
            <?php
            $trans = mysqli_query($conn, "SELECT st.*, m.name as medicine_name FROM stock_transactions st JOIN medicines m ON st.medicine_id = m.id WHERE st.user_id = {$_SESSION['user_id']} AND st.type = 'stock_out' ORDER BY st.created_at DESC");
            if (mysqli_num_rows($trans) > 0) {
                while ($row = mysqli_fetch_assoc($trans)) {
                    $date = date('d M Y', strtotime($row['created_at']));
                    $time = date('h:i A', strtotime($row['created_at']));
                    $notes = $row['notes'] ? $row['notes'] : '—';
                    echo "<tr><td>{$row['medicine_name']}</td><td>{$row['quantity']}</td><td>{$notes}</td><td>{$date}</td><td>{$time}</td></tr>";
                }
            } else {
                echo "<tr><td colspan='5' class='empty-state'>No transactions yet</td></tr>";
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