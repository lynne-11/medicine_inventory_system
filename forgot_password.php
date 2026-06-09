<?php
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

$success = "";
$error = "";

if (isset($_POST['send_reset'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    $sql = "SELECT * FROM users WHERE username = ? AND email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $username, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $sql2 = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?";
        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "ssi", $token, $expires, $user['id']);
        mysqli_stmt_execute($stmt2);

        $reset_link = "http://localhost/medicine_inventory_system/reset_password.php?token=" . $token;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jerutolinet41@gmail.com';
            $mail->Password = 'ajfaajnxgxqkverq';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('jerutolinet41@gmail.com', 'PharmTrack System');
            $mail->addAddress($user['email'], $user['name']);
            $mail->Subject = 'PharmTrack Password Reset Request';
            $mail->isHTML(true);
            $mail->Body = "
                <div style='font-family:Arial;max-width:500px;margin:auto;padding:20px;border:1px solid #e0e0e0;border-radius:10px'>
                    <h2 style='color:#185FA5'>PharmTrack Password Reset</h2>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    <p>You requested a password reset for your PharmTrack account.</p>
                    <p>Click the button below to reset your password:</p>
                    <a href='$reset_link' style='display:inline-block;padding:12px 24px;background:#185FA5;color:white;border-radius:8px;text-decoration:none;font-weight:bold;margin:16px 0'>Reset My Password</a>
                    <p style='color:#555;font-size:13px;margin-top:16px'>If the button does not work copy and paste this link into your browser:</p>
                    <p style='background:#f4f6f8;padding:10px;border-radius:6px;font-size:12px;word-break:break-all;color:#185FA5'>$reset_link</p>
                    <p style='color:#888;font-size:12px;margin-top:16px'>This link expires in <strong>1 hour</strong>. If you did not request this reset ignore this email.</p>
                    <hr style='border:none;border-top:1px solid #e0e0e0;margin:20px 0'>
                    <p style='color:#aaa;font-size:11px'>PharmTrack — Kiambu Sub-County Hospital Pharmacy</p>
                </div>
            ";

            $mail->send();
            $success = "Password reset link sent to your email. Please check your inbox!";

        } catch (Exception $e) {
            $error = "Email could not be sent. Error: " . $mail->ErrorInfo;
        }

    } else {
        $error = "No account found with that username and email combination.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmTrack - Forgot Password</title>
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
        <p>Forgot Password</p>
    </div>

    <div class="info-box">
        Enter your username and email address. We will send you a password reset link.
    </div>

    <?php if ($success != "") { ?>
        <div class="success-box"><?php echo $success; ?></div>
        <div class="back-link">
            <a href="login.php">Back to Login</a>
        </div>
    <?php } else { ?>

        <?php if ($error != "") { ?>
            <div class="error-box"><?php echo $error; ?></div>
        <?php } ?>

        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username"
                       placeholder="Enter your username" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email"
                       placeholder="Enter your email address" required>
            </div>
            <button type="submit" name="send_reset" class="reset-btn">
                Send Reset Link
            </button>
        </form>

    <?php } ?>

    <hr class="divider">
    <div class="back-link">
        <a href="login.php">Back to Login</a>
    </div>
</div>
</body>
</html>