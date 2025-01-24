<?php
include 'connect.php';
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name);
$sql = "CREATE TABLE users (
    user_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'premium user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
if (mysqli_query($conn, $sql)) {
echo "Table created successfully";
} else {
echo "Error creating table: " . mysqli_error($conn);
}
mysqli_close($conn);