<?php
include 'db.php';

$name = 'Linet Kipkemboi';
$username = 'linet';
$password = 'linet@5788';
$role = 'admin';
$hashed = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssss", $name, $username, $hashed, $role);

if (mysqli_stmt_execute($stmt)) {
    echo "User created successfully! Username: linet | Password: linet@5788";
} else {
    echo "Error: " . mysqli_error($conn);
}
?>