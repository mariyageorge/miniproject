<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require 'connect.php';

date_default_timezone_set("Asia/Kolkata"); // Set your timezone
$timestamp = date("Y-m-d H:i:s");

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!isset($_POST["email"])) {
            throw new Exception("No email provided.");
        }

        $email = trim($_POST["email"]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Verify database connection
        if (!$conn || $conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Database preparation failed: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            throw new Exception("Database execution failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Email not found in our records.");
        }

        // Generate and store token
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        if (!$update_stmt) {
            throw new Exception("Token update preparation failed: " . $conn->error);
        }

        $update_stmt->bind_param("sss", $token, $expires, $email);
        if (!$update_stmt->execute()) {
            throw new Exception("Token update failed: " . $update_stmt->error);
        }

        // Configure PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lifesyncdigital@gmail.com';
        $mail->Password = 'yrpw iqys blcl famq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->SMTPDebug = 2; // Enable debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer debug: $str");
        };

        // Set email content
        $mail->setFrom('lifesyncdigital@gmail.com', 'Life-Sync');
        $mail->addAddress($email);
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/resetpassword.php?token=" . $token;
        
        $mail->isHTML(true);
        $mail->Subject = "Password Reset Request - Life-Sync";
        $mail->Body = "
        <html>
        <body style='font-family: Georgia, serif; background-color: #F5EFE6; padding: 20px; color: #4A3F35;'>
            <div style='max-width: 600px; background-color: #FAF3E0; border: 1px solid #D2B48C; padding: 20px; border-radius: 10px; margin: auto;'>
                <h2 style='color: #6F4E37; text-align: center;'>Password Reset Request</h2>
                <p style='font-size: 16px;'>Hello,</p>
                <p style='font-size: 16px;'>We received a request to reset your password. Click the button below to proceed:</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$reset_link' 
                       style='background-color: #6F4E37; 
                              color: white; 
                              padding: 12px 25px; 
                              font-size: 16px;
                              font-weight: bold;
                              text-decoration: none; 
                              border-radius: 8px;
                              display: inline-block;'>
                        Reset Password
                    </a>
                </div>
    
                <p style='font-size: 16px;'>This link will expire in <strong>1 hour</strong>.</p>
                <p style='font-size: 16px;'>If you did not request a password reset, please ignore this email.</p>
                
                <hr style='border: none; height: 1px; background-color: #D2B48C; margin: 20px 0;'>
    
                <p style='font-size: 14px; text-align: center;'>
                    <strong>Life-Sync Team</strong><br>
                    <span style='font-size: 12px; color: #6F4E37;'>Your trusted personal assistant</span>
                </p>
            </div>
        </body>
        </html>";
    
        $mail->AltBody = "Click the link below to reset your password:\n\n$reset_link\n\nThis link expires in 1 hour.";

        if (!$mail->send()) {
            throw new Exception("Email could not be sent. Mailer Error: " . $mail->ErrorInfo);
        }

        echo json_encode([
            "status" => "success",
            "message" => "A password reset link has been sent to your email."
        ]);

    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        echo json_encode([
            "status" => "error",
            "message" => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
}