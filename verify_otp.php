<?php
session_start();
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp = $_POST['otp'];

    if (!isset($_SESSION['email']) || !isset($_SESSION['email_otp'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
        exit;
    }

    $email = $_SESSION['email'];
    $sessionOtp = $_SESSION['email_otp'];

    if ($otp == $sessionOtp) {
        // Update the database to mark the email as verified
        $updateQuery = "UPDATE users SET email_verified = 1 WHERE email = '$email'";
        if (mysqli_query($conn, $updateQuery)) {
            $_SESSION['email_verified'] = true; // Set session variable to true
            
            echo json_encode(['success' => true, 'message' => 'Email verified successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed: ' . mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
mysqli_close($conn);
?>
