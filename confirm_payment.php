<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include_once 'connect.php';

// Razorpay credentials
$razorpay_key_id = "rzp_test_j6ZARUQlnkvesy";
$razorpay_key_secret = "o4vFaPvOvANpqF9X8FcyqGka";

// Get payment details
$payment_id = $_POST['payment_id'] ?? '';
$order_id = $_POST['order_id'] ?? '';
$amount = $_POST['amount'] ?? 0;

// Verify payment with Razorpay
if (!empty($payment_id) && !empty($order_id) && $amount > 0) {
    $url = "https://api.razorpay.com/v1/payments/$payment_id";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ":" . $razorpay_key_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $payment = json_decode($response);

        // Check if payment is successful
        if (isset($payment->status) && $payment->status === 'captured') {
            $user_id = $_SESSION['user_id'];
            $status = 'success'; // Payment success status

            // Store payment details in the database
            $stmt = $conn->prepare("INSERT INTO payments (user_id, payment_id, order_id, amount, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issds", $user_id, $payment_id, $order_id, $amount, $status);
            
            if ($stmt->execute()) {
                // **Upgrade User to Premium User**
                $update_stmt = $conn->prepare("UPDATE users SET role = 'premium user' WHERE user_id = ?");
                $update_stmt->bind_param("i", $user_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Update session role
                $_SESSION['role'] = "premium user";

                // Redirect to success page
                header("Location: success.php");
                exit();
            } else {
                echo "<script>alert('Error storing payment details.'); window.location.href = 'payment.php';</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('Payment failed or not captured.'); window.location.href = 'payment.php';</script>";
        }
    } else {
        echo "<script>alert('Error verifying payment with Razorpay.'); window.location.href = 'payment.php';</script>";
    }
} else {
    echo "<script>alert('Invalid payment details.'); window.location.href = 'payment.php';</script>";
}
?>
