<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "error" => "Invalid email format."]);
        exit;
    }

    $otp = rand(100000, 999999);
    $_SESSION['email_otp'] = $otp;
    $_SESSION['email'] = $email;
    $_SESSION['email_verified'] = false;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lifesyncdigital@gmail.com';
        $mail->Password = 'yrpw iqys blcl famq'; // Replace with App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('lifesyncdigital@gmail.com', 'Life-Sync');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your Life-Sync OTP Verification Code';
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; padding: 20px; text-align: center; background-color: #f4f4f4; border-radius: 10px;'>
                <h2 style='color: #4CAF50;'>Life-Sync Verification</h2>
                <p style='font-size: 16px; color: #333;'>Use the following One-Time Password (OTP) to verify your email:</p>
                <p style='font-size: 24px; font-weight: bold; color: #e63946; background: #fff; padding: 10px; display: inline-block; border-radius: 5px;'>
                    $otp
                </p>
                <p style='font-size: 14px; color: #555;'>This OTP is valid for a limited time. Please do not share it with anyone.</p>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                <p style='font-size: 12px; color: #777;'>If you did not request this, please ignore this email.</p>
            </div>";
        

        if ($mail->send()) {
            echo json_encode(["success" => true, "message" => "OTP sent successfully."]);
        } else {
            echo json_encode(["success" => false, "error" => "Email sending failed."]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => "Mail Error: " . $mail->ErrorInfo]);
    }
}
?>