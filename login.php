<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db.php';
    $username = $_POST['username'];
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
        header("Location: dashboard.php");
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            padding: 50px 40px;
            width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            font-size: 28px;
            color: #185FA5;
            font-weight: bold;
        }
        .logo p {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
        }
        .hospital-badge {
            background: #f0f7ff;
            border: 1px solid #c8e1ff;
            border-radius: 8px;
            padding: 10px 16px;
            text-align: center;
            margin-bottom: 28px;
        }
        .hospital-badge p {
            font-size: 13px;
            color: #185FA5;
            font-weight: bold;
        }
        .hospital-badge span {
            font-size: 11px;
            color: #888;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            color: #333;
            margin-bottom: 7px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #185FA5;
            box-shadow: 0 0 0 3px rgba(24,95,165,0.1);
        }
        .password-wrapper input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
        }
        .password-wrapper input:focus {
            outline: none;
            border-color: #185FA5;
        }
        .show-password {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 13px;
            color: #555;
            cursor: pointer;
        }
        .show-password input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .buttons-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }
        .login-btn {
            padding: 13px;
            background: #185FA5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        .login-btn:hover { background: #0C447C; }
        .cancel-btn {
            padding: 13px;
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cancel-btn:hover { background: #fdecea; }
        .forgot-password {
            text-align: center;
            margin-top: 16px;
        }
        .forgot-password a {
            font-size: 13px;
            color: #185FA5;
            text-decoration: none;
        }
        .forgot-password a:hover { text-decoration: underline; }
        .divider {
            border: none;
            border-top: 1px solid #f0f0f0;
            margin: 20px 0;
        }
        .system-info {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .system-info span {
            font-size: 11px;
            color: #aaa;
        }
        .error-box {
            background: #FCEBEB;
            color: #791F1F;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo">
        <h1>PharmTrack</h1>
        <p>Electronic Medicine Inventory System</p>
    </div>

    <div class="hospital-badge">
        <p>Kiambu Sub-County Hospital</p>
        <span>Pharmacy Department</span>
    </div>

    <?php if ($error != "") { ?>
        <div class="error-box"><?php echo $error; ?></div>
    <?php } ?>

    <form method="POST" autocomplete="off">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username"
                   placeholder="Enter your username"
                   autocomplete="off" readonly
                   onfocus="this.removeAttribute('readonly');" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="passwordField"
                       placeholder="Enter your password"
                       autocomplete="new-password" readonly
                       onfocus="this.removeAttribute('readonly');" required>
            </div>
            <label class="show-password">
                <input type="checkbox" onclick="togglePassword()"> Show password
            </label>
        </div>

        <div class="buttons-row">
            <button type="submit" class="login-btn">Login</button>
            <button type="reset" class="cancel-btn">Cancel</button>
        </div>
    </form>

    <div class="forgot-password">
        <a href="forgot_password.php">Forgot password?</a>
    </div>

    <hr class="divider">

    <div class="system-info">
        <span>Secure Login</span>
        <span>Role Based Access</span>
        <span>Hospital System</span>
    </div>
</div>

<script>
    function togglePassword() {
        var field = document.getElementById('passwordField');
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }
</script>

</body>
</html>