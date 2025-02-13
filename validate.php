<?php
include 'connect.php';  
$database_name = "lifesync_db";  
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['field']) && isset($_POST['value'])) {
    $field = $_POST['field'];
    $value = mysqli_real_escape_string($conn, $_POST['value']);
    $error = "";

    if ($field == "username") {
        if (strlen($value) < 3) {
            $error = "Username must be at least 3 characters long,space is not allowed";
        } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $value)) {
            $error = "Username can only contain letters, numbers, and underscores";
        } else {
            $query = "SELECT * FROM users WHERE username = '$value'";
            $result = mysqli_query($conn, $query);
            if (mysqli_num_rows($result) > 0) {
                $error = "Username is already taken";
            }
        }
    } elseif ($field == "email") {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            $query = "SELECT * FROM users WHERE email = '$value'";
            $result = mysqli_query($conn, $query);
           
        }
    } elseif ($field == "password") {
        if (strlen($value) < 8) {
            $error = "Password must be at least 8 characters long";
        } elseif (!preg_match("/[a-z]/", $value) || !preg_match("/[A-Z]/", $value) || !preg_match("/[0-9]/", $value)) {
            $error = "Password must contain at least one lowercase letter, one uppercase letter, and one number";
        }
    }

    echo json_encode(["error" => $error]);
}
mysqli_close($conn);
?>
