<?php
include 'db.php';

$success = "";
$error = "";
$valid_token = false;
$user = null;
$token = "";

// CHECK TOKEN FROM URL
if (isset($_GET['token'])) {
    $token = urldecode($_GET['token']);
    $token_safe = mysqli_real_escape_string($conn, $token);
    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE reset_token = '$token_safe' AND reset_expires > NOW()"));

    if ($user) {
        $valid_token = true;
    } else {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    }
}

// HANDLE PASSWORD RESET
if (isset($_POST['reset_password'])) {
    $token = urldecode($_POST['token']);
    $token_safe = mysqli_real_escape_string($conn, $token);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE reset_token = '$token_safe' AND reset_expires > NOW()"));

    if (!$user) {
        $error = "This reset link is invalid or has expired!";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters!";
        $valid_token = true;
    } elseif (!preg_match("/[A-Z]/", $new_password)) {
        $error = "Password must contain at least one uppercase letter!";
        $valid_token = true;
    } elseif (!preg_match("/[0-9]/", $new_password)) {
        $error = "Password must contain at least one number!";
        $valid_token = true;
    } elseif (!preg_match("/[\W]/", $new_password)) {
        $error = "Password must contain at least one special character like @ # $ !";
        $valid_token = true;
    } elseif ($new_password != $confirm_password) {
        $error = "Passwords do not match!";
        $valid_token = true;
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $user['id'];
        mysqli_query($conn, "UPDATE users SET password_hash = '$hashed', reset_token = NULL, reset_expires = NULL WHERE id = $user_id");
        $success = "Password reset successfully! You can now login with your new password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Reset Password</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 50px 40px;
            width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { font-size: 28px; color: #185FA5; font-weight: bold; }
        .logo p { font-size: 13px; color: #888; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 7px; }
        input {
            width: 100%; padding: 12px 16px;
            border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; color: #333;
        }
        input:focus { outline: none; border-color: #185FA5; }
        .password-hint { font-size: 11px; color: #888; margin-top: 4px; }
        .reset-btn {
            width: 100%; padding: 13px;
            background: #185FA5; color: white;
            border: none; border-radius: 8px;
            font-size: 15px; font-weight: bold;
            cursor: pointer; margin-top: 8px;
        }
        .reset-btn:hover { background: #0C447C; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { font-size: 13px; color: #185FA5; text-decoration: none; }
        .success-box {
            background: #d5f5e3; color: #1e8449;
            padding: 12px 16px; border-radius: 8px;
            font-size: 13px; margin-bottom: 20px;
            text-align: center; font-weight: bold;
        }
        .error-box {
            background: #FCEBEB; color: #791F1F;
            padding: 12px 16px; border-radius: 8px;
            font-size: 13px; margin-bottom: 20px;
            text-align: center;
        }
        .info-box {
            background: #f0f7ff; border: 1px solid #c8e1ff;
            border-radius: 8px; padding: 12px 16px;
            font-size: 12px; color: #185FA5;
            margin-bottom: 20px; text-align: center;
        }
        .divider { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">
        <h1>PharmTrack</h1>
        <p>Reset Your Password</p>
    </div>

    <?php if ($success != "") { ?>
        <div class="success-box"><?php echo $success; ?></div>
        <div style="text-align:center;margin-top:16px">
            <a href="login.php" style="display:inline-block;padding:10px 24px;background:#185FA5;color:white;border-radius:8px;text-decoration:none;font-size:14px;font-weight:bold">Go to Login</a>
        </div>

    <?php } elseif ($valid_token) { ?>

        <div class="info-box">
            Hello <strong><?php echo $user['name']; ?></strong>! Enter your new password below.
        </div>

        <?php if ($error != "") { ?>
            <div class="error-box"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password"
                       placeholder="Enter new strong password" required>
                <div class="password-hint">Must be 8+ characters, include uppercase, number and special character e.g. Linet@2026</div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password"
                       placeholder="Confirm new password" required>
            </div>
            <button type="submit" name="reset_password" class="reset-btn">
                Reset Password
            </button>
        </form>

    <?php } else { ?>

        <div class="error-box"><?php echo $error; ?></div>
        <div class="back-link">
            <a href="forgot_password.php">Request a new reset link</a>
        </div>

    <?php } ?>

    <hr class="divider">
    <div class="back-link">
        <a href="login.php">Back to Login</a>
    </div>
</div>
</body>
</html>