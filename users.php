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

// ADD NEW USER
if (isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validate name — letters only
    if (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $error = "Full name must contain letters only — no numbers or special characters!";
    }
    // Validate username — letters and numbers only
    elseif (!preg_match("/^[a-zA-Z0-9]+$/", $username)) {
        $error = "Username must contain letters and numbers only — no spaces or special characters!";
    }
    // Validate email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address!";
    }
    // Validate password strength
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long!";
    }
    elseif (!preg_match("/[A-Z]/", $password)) {
        $error = "Password must contain at least one uppercase letter!";
    }
    elseif (!preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least one number!";
    }
    elseif (!preg_match("/[\W]/", $password)) {
        $error = "Password must contain at least one special character like @ # $ % !";
    }
    elseif ($password != $confirm) {
        $error = "Passwords do not match!";
    }
    elseif (empty($role)) {
        $error = "Please select a role!";
    }
    else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, username, email, password_hash, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", $name, $username, $email, $hashed, $role);
        if (mysqli_stmt_execute($stmt)) {
            $success = "User added successfully!";
        } else {
            $error = "Username already exists. Please choose a different one.";
        }
    }
}

// UPDATE USER ROLE AND PASSWORD
if (isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    $new_password = $_POST['new_password'];

    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $error = "New password must be at least 8 characters!";
        } elseif (!preg_match("/[A-Z]/", $new_password)) {
            $error = "New password must contain at least one uppercase letter!";
        } elseif (!preg_match("/[0-9]/", $new_password)) {
            $error = "New password must contain at least one number!";
        } elseif (!preg_match("/[\W]/", $new_password)) {
            $error = "New password must contain at least one special character!";
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            mysqli_query($conn, "UPDATE users SET role = '$new_role', password_hash = '$hashed' WHERE id = $user_id");
            $success = "User updated successfully!";
        }
    } else {
        mysqli_query($conn, "UPDATE users SET role = '$new_role' WHERE id = $user_id");
        $success = "User role updated successfully!";
    }
}

// DELETE USER
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    if ($delete_id != $_SESSION['user_id']) {
        mysqli_query($conn, "DELETE FROM users WHERE id = $delete_id");
        $success = "User deleted successfully!";
    } else {
        $error = "You cannot delete your own account!";
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
        .btn-warning { background: #e67e22; color: white; padding: 4px 12px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; }
        .success-box { background: #d5f5e3; color: #1e8449; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; }
        .password-hint { font-size: 11px; color: #888; margin-top: 4px; }
        .role-info { background: #f0f7ff; border: 1px solid #c8e1ff; border-radius: 8px; padding: 16px; }
        .role-info h4 { font-size: 13px; color: #185FA5; margin-bottom: 12px; }
        .role-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
        .role-badge { padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; flex-shrink: 0; }
        .badge-admin { background: #e8e3ff; color: #4a3aaa; }
        .badge-pharmacist { background: #d5f5e3; color: #1e8449; }
        .badge-storekeeper { background: #fef9e7; color: #b7950b; }
        .role-desc { font-size: 12px; color: #666; }
        .section-title { font-size: 16px; font-weight: bold; color: #111; margin-bottom: 12px; }
        .table-wrap { background: white; border-radius: 12px; border: 1px solid #e8e8e8; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 12px 16px; text-align: left; font-size: 12px; color: #666; border-bottom: 1px solid #e8e8e8; }
        td { padding: 12px 16px; border-bottom: 1px solid #f5f5f5; color: #333; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fa; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 11px; font-weight: bold; }
        .action-btn { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: none; margin-right: 4px; }
        .btn-delete { background: #fdecea; color: #a93226; }
        .btn-delete:hover { background: #f5b7b1; }
        .empty-state { text-align: center; padding: 30px; color: #aaa; font-size: 13px; }
        /* MODAL */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.open { display: flex; }
        .modal-box { background: white; border-radius: 12px; padding: 28px; width: 400px; }
        .modal-box h3 { font-size: 16px; margin-bottom: 20px; color: #111; }
        .modal-close { float: right; background: none; border: none; font-size: 20px; cursor: pointer; color: #888; margin-top: -5px; }
        /* RESPONSIVE */
        @media (max-width: 768px) {
            .sidebar { width: 0; display: none; }
            .main { margin-left: 0; padding: 16px; }
            .two-col { grid-template-columns: 1fr; }
            table { font-size: 11px; }
            th, td { padding: 8px 10px; }
        }
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
    <a href="reports.php" class="nav-item">Reports</a>
    <?php if ($_SESSION['user_role'] == 'admin') { ?>
    <div class="nav-section">Admin</div>
    <a href="users.php" class="nav-item active">Manage Users</a>
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
                    <input type="text" name="name" placeholder="Enter full name — letters only"
                           autocomplete="off" readonly
                           onfocus="this.removeAttribute('readonly');" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Letters and numbers only"
                           autocomplete="off" readonly
                           onfocus="this.removeAttribute('readonly');" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter email address"
                           autocomplete="off" readonly
                           onfocus="this.removeAttribute('readonly');" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter strong password"
                           autocomplete="new-password" readonly
                           onfocus="this.removeAttribute('readonly');" required>
                    <div class="password-hint">Must be 8+ characters, include uppercase, number and special character e.g. Linet@2026</div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm password"
                           autocomplete="new-password" readonly
                           onfocus="this.removeAttribute('readonly');" required>
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
                <div class="role-item">
                    <span class="role-badge badge-admin">Admin</span>
                    <div class="role-desc">Full access to all pages. Can add, edit and delete users, medicines and transactions. Can view all reports and audits.</div>
                </div>
                <div class="role-item">
                    <span class="role-badge badge-pharmacist">Pharmacist</span>
                    <div class="role-desc">Can record stock out transactions when issuing medicines to patients. Can view stock levels, alerts and reports.</div>
                </div>
                <div class="role-item">
                    <span class="role-badge badge-storekeeper">Storekeeper</span>
                    <div class="role-desc">Can record stock in transactions when receiving medicines from suppliers. Can view stock levels and alerts.</div>
                </div>
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
            $users = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at ASC");
            if (mysqli_num_rows($users) > 0) {
                $i = 1;
                while ($row = mysqli_fetch_assoc($users)) {
                    if ($row['role'] == 'admin') {
                        $badge = "<span class='badge badge-admin'>Admin</span>";
                    } elseif ($row['role'] == 'pharmacist') {
                        $badge = "<span class='badge badge-pharmacist'>Pharmacist</span>";
                    } else {
                        $badge = "<span class='badge badge-storekeeper'>Storekeeper</span>";
                    }
                    $date = date('d M Y', strtotime($row['created_at']));
                    $email_display = $row['email'] ? $row['email'] : '<span style="color:#aaa">No email</span>';
                    $edit_btn = "<button class='action-btn btn-warning' onclick='openEdit({$row['id']}, \"{$row['name']}\", \"{$row['role']}\")'>Edit</button>";
                    $delete_btn = $row['id'] != $_SESSION['user_id']
                        ? "<a href='users.php?delete={$row['id']}' onclick='return confirm(\"Are you sure you want to delete this user?\")'><button class='action-btn btn-delete'>Delete</button></a>"
                        : "<span style='font-size:12px;color:#aaa'>Current user</span>";
                    echo "<tr>
                        <td>{$i}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['username']}</td>
                        <td>{$email_display}</td>
                        <td>{$badge}</td>
                        <td>{$date}</td>
                        <td>{$edit_btn} {$delete_btn}</td>
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

<!-- EDIT USER MODAL -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeEdit()">x</button>
        <h3>Edit User</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="edit_user_name" readonly style="background:#f8f9fa">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="new_role" id="edit_user_role" required>
                    <option value="admin">Admin</option>
                    <option value="pharmacist">Pharmacist</option>
                    <option value="storekeeper">Storekeeper</option>
                </select>
            </div>
            <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password"
                       placeholder="Enter new password or leave blank">
                <div class="password-hint">Must be 8+ characters, uppercase, number and special character</div>
            </div>
            <button type="submit" name="update_user" class="btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_user_name').value = name;
    document.getElementById('edit_user_role').value = role;
    document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
    document.getElementById('editModal').classList.remove('open');
}
</script>

</body>
</html>