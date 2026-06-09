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

// ADD NEW MEDICINE
if (isset($_POST['add_medicine'])) {
    $name = $_POST['medicine_name'];
    $category = $_POST['category'];
    $unit = $_POST['unit'];
    $min_threshold = $_POST['min_threshold'];

    $sql = "INSERT INTO medicines (name, category, unit, min_threshold) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssi", $name, $category, $unit, $min_threshold);

    if (mysqli_stmt_execute($stmt)) {
        $medicine_id = mysqli_insert_id($conn);
        $sql2 = "INSERT INTO stock_levels (medicine_id, current_quantity) VALUES (?, 0)";
        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "i", $medicine_id);
        mysqli_stmt_execute($stmt2);
        $success = "Medicine added successfully!";
    } else {
        $error = "Error adding medicine.";
    }
}

// RECORD TRANSACTION
if (isset($_POST['record_transaction'])) {
    $medicine_id = $_POST['medicine_id'];
    $type = $_POST['type'];
    $quantity = $_POST['quantity'];
    $notes = $_POST['notes'];
    $user_id = $_SESSION['user_id'];

    $stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT current_quantity FROM stock_levels WHERE medicine_id = $medicine_id"));
    $current = $stock['current_quantity'];

    if ($type == 'stock_out' && $quantity > $current) {
        $error = "Not enough stock! Current quantity is $current.";
    } else {
        $sql = "INSERT INTO stock_transactions (medicine_id, user_id, type, quantity, notes) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iisis", $medicine_id, $user_id, $type, $quantity, $notes);

        if (mysqli_stmt_execute($stmt)) {
            if ($type == 'stock_in') {
                $new_qty = $current + $quantity;
            } else {
                $new_qty = $current - $quantity;
            }

            $sql2 = "UPDATE stock_levels SET current_quantity = ? WHERE medicine_id = ?";
            $stmt2 = mysqli_prepare($conn, $sql2);
            mysqli_stmt_bind_param($stmt2, "ii", $new_qty, $medicine_id);
            mysqli_stmt_execute($stmt2);

            $med = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM medicines WHERE id = $medicine_id"));
            if ($new_qty <= $med['min_threshold']) {
                $msg = $new_qty == 0 ? "{$med['name']} is OUT OF STOCK!" : "{$med['name']} is running low. Only $new_qty {$med['unit']} remaining.";
                $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM alerts WHERE medicine_id = $medicine_id AND status = 'unread'"));
                if (!$existing) {
                    $sql3 = "INSERT INTO alerts (medicine_id, message, status) VALUES (?, ?, 'unread')";
                    $stmt3 = mysqli_prepare($conn, $sql3);
                    mysqli_stmt_bind_param($stmt3, "is", $medicine_id, $msg);
                    mysqli_stmt_execute($stmt3);
                }
            }
            $success = "Transaction recorded successfully!";
        } else {
            $error = "Error recording transaction.";
        }
    }
}

// DELETE MEDICINE
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    mysqli_query($conn, "DELETE FROM audit_log WHERE medicine_id = $delete_id");
    mysqli_query($conn, "DELETE FROM alerts WHERE medicine_id = $delete_id");
    mysqli_query($conn, "DELETE FROM stock_transactions WHERE medicine_id = $delete_id");
    mysqli_query($conn, "DELETE FROM stock_levels WHERE medicine_id = $delete_id");
    mysqli_query($conn, "DELETE FROM medicines WHERE id = $delete_id");
    $success = "Medicine deleted successfully!";
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Stock Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; display: flex; }
        .sidebar {
            width: 240px; background: #1a1a2e; min-height: 100vh;
            position: fixed; top: 0; left: 0; display: flex;
            flex-direction: column; z-index: 100; transition: all 0.3s;
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
        .main { margin-left: 240px; flex: 1; padding: 28px; transition: all 0.3s; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .topbar h1 { font-size: 22px; color: #111; }
        .topbar p { font-size: 13px; color: #888; margin-top: 2px; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .logout-btn { background: #e74c3c; color: white; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .menu-btn { display: none; background: #185FA5; color: white; border: none; padding: 9px 14px; border-radius: 8px; font-size: 18px; cursor: pointer; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .card { background: white; border-radius: 12px; border: 1px solid #e8e8e8; padding: 24px; }
        .card h3 { font-size: 16px; color: #111; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #f0f0f0; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 10px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; color: #333; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #185FA5; }
        .btn-primary { background: #185FA5; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .btn-primary:hover { background: #0C447C; }
        .btn-success { background: #27ae60; color: white; width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; }
        .btn-success:hover { background: #1e8449; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; }
        .search-form { display: flex; gap: 8px; flex-wrap: wrap; }
        .search-box { padding: 8px 14px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 13px; width: 220px; }
        .search-btn { padding: 8px 16px; background: #185FA5; color: white; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; margin-bottom: 24px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-ok { background: #d5f5e3; color: #1e8449; }
        .badge-low { background: #fef9e7; color: #b7950b; }
        .badge-empty { background: #fdecea; color: #a93226; }
        .action-btn { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: none; margin-right: 4px; }
        .btn-view { background: #eaf3fb; color: #185FA5; }
        .btn-view:hover { background: #c8e1ff; }
        .btn-delete { background: #fdecea; color: #a93226; }
        .btn-delete:hover { background: #f5b7b1; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
        .overlay.open { display: block; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .two-col { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .sidebar { left: -240px; }
            .sidebar.open { left: 0; }
            .main { margin-left: 0; padding: 16px; }
            .menu-btn { display: block; }
            .search-box { width: 150px; }
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
            <h1>Stock Management</h1>
            <p>Add medicines and record stock transactions</p>
        </div>
        <div class="topbar-right">
            <button class="menu-btn" onclick="openSidebar()">☰</button>
            <a href="logout.php" class="logout-btn">Log Out</a>
        </div>
    </div>

    <?php if ($success != "") { ?>
        <div class="success-box"><?php echo $success; ?></div>
    <?php } ?>
    <?php if ($error != "") { ?>
        <div class="error-box"><?php echo $error; ?></div>
    <?php } ?>

    <div class="two-col">
        <div class="card">
            <h3>Add New Medicine</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Medicine Name</label>
                    <input type="text" name="medicine_name" placeholder="e.g. Amoxicillin 500mg" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="">Select category</option>
                        <option>Antibiotic</option>
                        <option>Analgesic</option>
                        <option>Antidiabetic</option>
                        <option>Antihypertensive</option>
                        <option>Rehydration</option>
                        <option>Antifungal</option>
                        <option>Antiparasitic</option>
                        <option>Vitamin</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <select name="unit" required>
                        <option value="">Select unit</option>
                        <option>Tablets</option>
                        <option>Capsules</option>
                        <option>Bottles</option>
                        <option>Vials</option>
                        <option>Sachets</option>
                        <option>Tubes</option>
                        <option>Ampoules</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Minimum Stock Threshold</label>
                    <input type="number" name="min_threshold" placeholder="e.g. 20" required>
                </div>
                <button type="submit" name="add_medicine" class="btn-primary">Add Medicine</button>
            </form>
        </div>

        <div class="card">
            <h3>Record Stock Transaction</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Medicine</label>
                    <select name="medicine_id" required>
                        <option value="">Select medicine</option>
                        <?php
                        $meds = mysqli_query($conn, "SELECT id, name FROM medicines ORDER BY name ASC");
                        while ($m = mysqli_fetch_assoc($meds)) {
                            echo "<option value='{$m['id']}'>{$m['name']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transaction Type</label>
                    <select name="type" required>
                        <option value="">Select type</option>
                        <option value="stock_in">Stock In (Received from store)</option>
                        <option value="stock_out">Stock Out (Issued to patient)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" placeholder="Enter quantity" required>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea name="notes" rows="3" placeholder="Any additional notes..."></textarea>
                </div>
                <button type="submit" name="record_transaction" class="btn-success">Save Transaction</button>
            </form>
        </div>
    </div>

    <div class="section-header">
        <div class="section-title">All Medicines</div>
        <form method="GET" class="search-form">
            <input type="text" name="search" class="search-box"
                   placeholder="Search medicine..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if ($search != '') { ?>
                <a href="stock.php" style="padding:8px 14px;background:#e74c3c;color:white;border-radius:8px;text-decoration:none;font-size:13px">Clear</a>
            <?php } ?>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>In Stock</th>
                    <th>Min Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            if ($search != '') {
                $search_safe = mysqli_real_escape_string($conn, $search);
                $medicines = mysqli_query($conn, "
                    SELECT m.*, sl.current_quantity
                    FROM medicines m
                    LEFT JOIN stock_levels sl ON m.id = sl.medicine_id
                    WHERE m.name LIKE '%$search_safe%'
                    OR m.category LIKE '%$search_safe%'
                    ORDER BY m.name ASC
                ");
            } else {
                $medicines = mysqli_query($conn, "
                    SELECT m.*, sl.current_quantity
                    FROM medicines m
                    LEFT JOIN stock_levels sl ON m.id = sl.medicine_id
                    ORDER BY m.name ASC
                ");
            }
            if (mysqli_num_rows($medicines) > 0) {
                $i = 1;
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
                        <td>{$i}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['category']}</td>
                        <td>{$row['unit']}</td>
                        <td>{$qty}</td>
                        <td>{$row['min_threshold']}</td>
                        <td>{$status}</td>
                        <td>
                            <a href='view_medicine.php?id={$row['id']}'><button class='action-btn btn-view'>View</button></a>
                            <a href='stock.php?delete={$row['id']}' onclick='return confirm(\"Are you sure you want to delete {$row['name']}?\")'><button class='action-btn btn-delete'>Delete</button></a>
                        </td>
                    </tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='8' class='empty-state'>No medicines found</td></tr>";
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