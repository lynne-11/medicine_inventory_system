<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['user_role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}
include 'db.php';

$success = "";
$error = "";
$alert_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM alerts WHERE status = 'unread'"))['count'];

// ADD USER
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (!preg_match('/^[a-zA-Z ]+$/', $name)) {
        $error = "Full name must contain letters only.";
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $error = "Username must contain letters and numbers only.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be 8+ characters with uppercase, number and special character.";
    } else {
        $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'"));
        if ($check) {
            $error = "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssss", $name, $username, $email, $hash, $role);
            if (mysqli_stmt_execute($stmt)) {
                $success = "User added successfully!";
            } else {
                $error = "Error adding user.";
            }
        }
    }
}

// EDIT USER
if (isset($_POST['edit_user'])) {
    $edit_id = $_POST['edit_id'];
    $edit_role = $_POST['edit_role'];
    $new_password = $_POST['new_password'];

    mysqli_query($conn, "UPDATE users SET role = '$edit_role' WHERE id = $edit_id");

    if (!empty($new_password)) {
        if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $new_password)) {
            $error = "New password must be 8+ characters with uppercase, number and special character.";
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET password_hash = '$hash' WHERE id = $edit_id");
        }
    }
    if ($error == "") $success = "User updated successfully!";
}

// DELETE USER
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    if ($del_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = $del_id");
        $success = "User deleted successfully!";
    } else {
        $error = "You cannot delete your own account.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Manage Users</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f6f8; display: flex; }
        .sidebar { width: 240px; background: #1a1a2e; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; }
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
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
        .overlay.open { display: block; }
        .menu-btn { display: none; position: fixed; top: 16px; left: 16px; background: #185FA5; color: white; border: none; padding: 9px 14px; border-radius: 8px; font-size: 20px; cursor: pointer; z-index: 998; }
        .main { margin-left: 240px; flex: 1; padding: 28px; transition: all 0.3s; }
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
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; overflow-x: auto; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px; }
        th { background: #1a1a2e; padding: 12px 16px; text-align: left; font-size: 12px; color: white; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .badge-admin { background: #e8e3ff; color: #4a3aaa; }
        .badge-pharmacist { background: #d5f5e3; color: #1e8449; }
        .badge-storekeeper { background: #fef9e7; color: #b7950b; }
        .action-btn { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: none; margin-right: 4px; }
        .btn-edit { background: #eaf3fb; color: #185FA5; }
        .btn-edit:hover { background: #c8e1ff; }
        .btn-delete { background: #fdecea; color: #a93226; }
        .btn-delete:hover { background: #f5b7b1; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        .role-info { background: #EEF4FF; border-radius: 10px; border: 1.5px solid #185FA5; padding: 16px 20px; }
        .role-info h4 { font-size: 14px; color: #185FA5; margin-bottom: 10px; }
        .role-info p { font-size: 13px; color: #555; margin-bottom: 8px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 200; align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: white; border-radius: 12px; padding: 28px; width: 100%; max-width: 420px; margin: 20px; }
        .modal-box h3 { font-size: 18px; color: #111; margin-bottom: 20px; }
        .modal-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: bold; cursor: pointer; margin-top: 8px; }
        .modal-save { background: #185FA5; color: white; }
        .modal-save:hover { background: #0C447C; }
        .modal-cancel { background: #f0f0f0; color: #333; }
        .modal-cancel:hover { background: #e0e0e0; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { left: -240px; } .sidebar.open { left: 0; } .main { margin-left: 0; padding: 16px; padding-top: 60px; } .menu-btn { display: block; } }
    </style>
</head>
<body>
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<button class="menu-btn" onclick="openSidebar()">☰</button>

<div class="sidebar" id="sidebar">
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
    <a href="reports.php" class="nav-item">Reports</a>
    <div class="nav-section">Admin</div>
    <a href="users.php" class="nav-item active">Manage Users</a>
    <div class="sidebar-footer">
        <p>Logged in as</p>
        <h4><?php echo $_SESSION['user_name']; ?></h4>
        <small><?php echo $_SESSION['user_role']; ?></small>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div>
            <h1>Manage Users</h1>
            <p>Add and manage system users and their roles</p>
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
            <h3>Add New User</h3>
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Letters only" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Letters and numbers only" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter email address" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Min 8 chars, uppercase, number, special char" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat password" autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select role</option>
                        <option value="admin">Admin</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="storekeeper">Storekeeper</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn-primary">Add User</button>
            </form>
        </div>
        <div class="card">
            <h3>User Roles</h3>
            <div class="role-info">
                <h4>What each role can do</h4>
                <p><strong>Admin</strong> — Full access to all pages and features including managing users.</p>
                <p><strong>Pharmacist</strong> — Can record stock out transactions and view reports.</p>
                <p><strong>Storekeeper</strong> — Can record stock in transactions and view alerts.</p>
            </div>
        </div>
    </div>

    <div class="section-title">All System Users</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
            if (mysqli_num_rows($users) > 0) {
                $i = 1;
                while ($row = mysqli_fetch_assoc($users)) {
                    $badge = "badge-" . $row['role'];
                    $date = date('d M Y', strtotime($row['created_at']));
                    $is_self = $row['id'] == $_SESSION['user_id'] ? " (You)" : "";
                    echo "<tr>
                        <td>{$i}</td>
                        <td>{$row['name']}{$is_self}</td>
                        <td>{$row['username']}</td>
                        <td>{$row['email']}</td>
                        <td><span class='badge {$badge}'>{$row['role']}</span></td>
                        <td>{$date}</td>
                        <td>
                            <button class='action-btn btn-edit' onclick='openEdit({$row['id']}, \"{$row['role']}\")'>Edit</button>
                            " . ($row['id'] != $_SESSION['user_id'] ? "<a href='users.php?delete={$row['id']}' onclick='return confirm(\"Are you sure you want to delete {$row['name']}?\")'><button class='action-btn btn-delete'>Delete</button></a>" : "") . "
                        </td>
                    </tr>";
                    $i++;
                }
            } else {
                echo "<tr><td colspan='7' class='empty-state'>No users found</td></tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <h3>Edit User</h3>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>Change Role</label>
                <select name="edit_role" id="edit_role">
                    <option value="admin">Admin</option>
                    <option value="pharmacist">Pharmacist</option>
                    <option value="storekeeper">Storekeeper</option>
                </select>
            </div>
            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" placeholder="Enter new password or leave blank" autocomplete="new-password">
            </div>
            <button type="submit" name="edit_user" class="modal-btn modal-save">Save Changes</button>
            <button type="button" class="modal-btn modal-cancel" onclick="closeEdit()">Cancel</button>
        </form>
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
function openEdit(id, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_role').value = role;
    document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}
</script>
</body>
</html>