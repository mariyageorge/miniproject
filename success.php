<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success-container {
            max-width: 500px;
            margin: 50px auto;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .success-icon {
            font-size: 50px;
            color: #753D18FF;
        }
    </style>
</head>
<body>
  <br><br><br>
    <div class="container">
        <div class="success-container">
            <div class="success-icon">&#10004;</div>
            <h2 class="mt-3">Payment Successful!</h2>
            <p>Thank you for subscribing to <strong>LifeSync Premium</strong>. Your payment has been processed successfully.</p>
            <a href="dashboard.php" class="btn btn-success mt-3">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>
