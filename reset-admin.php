<?php

$conn = new mysqli("localhost", "root", "", "chargesure_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$email = "sanjaykumarsk162007@gmail.com";
$new_password = "Admin@123";

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "UPDATE users SET password = ? WHERE email = ? AND role = 'admin'"
);

$stmt->bind_param("ss", $hashed_password, $email);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Admin password reset successfully!";
} else {
    echo "Admin account not found or password unchanged.";
}

$stmt->close();
$conn->close();

?>