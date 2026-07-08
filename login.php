<?php
session_start();
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: dashboard.php");
    } elseif ($_SESSION['user_role'] == 'pharmacist') {
        header("Location: pharmacist_dashboard.php");
    } elseif ($_SESSION['user_role'] == 'storekeeper') {
        header("Location: storekeeper_dashboard.php");
    }
    exit();
}
include 'db.php';

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] == 'admin') {
            header("Location: dashboard.php");
        } elseif ($user['role'] == 'pharmacist') {
            header("Location: pharmacist_dashboard.php");
        } elseif ($user['role'] == 'storekeeper') {
            header("Location: storekeeper_dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid username or password. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #16213e; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: white; border-radius: 16px; padding: 40px; width: 100%; max-width: 420px; margin: 20px; }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo h1 { font-size: 32px; font-weight: bold; color: #185FA5; }
        .logo p { font-size: 13px; color: #888; margin-top: 4px; }
        .hospital-badge { background: #EEF4FF; border-radius: 10px; padding: 12px 16px; text-align: center; margin-bottom: 28px; }
        .hospital-badge h3 { font-size: 15px; color: #185FA5; font-weight: bold; }
        .hospital-badge p { font-size: 12px; color: #888; margin-top: 2px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 6px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 14px; border: 1.5px solid #e0e0e0; border-radius: 8px; font-size: 14px; color: #333; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #185FA5; }
        .show-password { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 13px; color: #555; cursor: pointer; }
        .show-password input { width: auto; }
        .btn-login { background: #185FA5; color: white; width: 100%; padding: 13px; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 8px; transition: background 0.2s; }
        .btn-login:hover { background: #0C447C; }
        .btn-cancel { background: white; color: #e74c3c; width: 100%; padding: 13px; border: 2px solid #e74c3c; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; margin-top: 8px; }
        .btn-cancel:hover { background: #fdecea; }
        .forgot { text-align: center; margin-top: 16px; }
        .forgot a { font-size: 13px; color: #185FA5; text-decoration: none; }
        .error-box { background: #fdecea; color: #a93226; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; font-weight: bold; text-align: center; }
        .footer-text { text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #f0f0f0; }
        .footer-text span { font-size: 12px; color: #aaa; margin: 0 8px; }
        @media (max-width: 480px) {
            .login-box { padding: 28px 20px; }
            .logo h1 { font-size: 26px; }
        }
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <h1>PharmTrack</h1>
        <p>Electronic Medicine Inventory System</p>
    </div>
    <div class="hospital-badge">
        <h3>Kiambu Sub-County Hospital</h3>
        <p>Pharmacy Department</p>
    </div>
    <?php if ($error != "") { ?>
        <div class="error-box"><?php echo $error; ?></div>
    <?php } ?>
    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter your username" autocomplete="off" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="password" placeholder="Enter your password" autocomplete="new-password" required>
            <label class="show-password">
                <input type="checkbox" onclick="togglePassword()"> Show password
            </label>
        </div>
        <button type="submit" name="login" class="btn-login">Login</button>
        <button type="reset" class="btn-cancel">Cancel</button>
    </form>
    <div class="forgot">
        <a href="forgot_password.php">Forgot password?</a>
    </div>
    <div class="footer-text">
        <span>Secure Login</span>
        <span>Role Based Access</span>
        <span>Hospital System</span>
    </div>
</div>
<script>
function togglePassword() {
    const pwd = document.getElementById('password');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>