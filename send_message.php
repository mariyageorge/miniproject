<?php
session_start();
require_once 'connect.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require 'vendor/autoload.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please login to continue']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$sender_id = $_SESSION['user_id'];
$recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$recipient_id || !$group_id || empty($message)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Verify both users are members of the group
$check_membership = "SELECT COUNT(*) as count FROM group_members 
                    WHERE group_id = ? AND user_id IN (?, ?) 
                    AND invitation_status = 'accepted'";
$stmt = mysqli_prepare($conn, $check_membership);
mysqli_stmt_bind_param($stmt, "iii", $group_id, $sender_id, $recipient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$membership = mysqli_fetch_assoc($result);

if ($membership['count'] != 2) {
    echo json_encode(['error' => 'Both users must be members of the group']);
    exit();
}

// Insert the message
$insert_query = "INSERT INTO direct_messages (sender_id, recipient_id, group_id, message, created_at) 
                 VALUES (?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "iiis", $sender_id, $recipient_id, $group_id, $message);

if (mysqli_stmt_execute($stmt)) {
    // Get recipient's email
    $email_query = "SELECT email, username FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $email_query);
    mysqli_stmt_bind_param($stmt, "i", $recipient_id);
    mysqli_stmt_execute($stmt);
    $recipient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Get sender's name
    $sender_query = "SELECT username FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sender_query);
    mysqli_stmt_bind_param($stmt, "i", $sender_id);
    mysqli_stmt_execute($stmt);
    $sender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Get group name
    $group_query = "SELECT group_name FROM expense_groups WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $group_query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Function to send email notification for direct message
    function sendDirectMessageNotification($recipientEmail, $recipientName, $senderName, $groupName, $message) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'lifesyncdigital@gmail.com';
            $mail->Password = 'yrpw iqys blcl famq';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('lifesyncdigital@gmail.com', 'LIFE-SYNC');
            $mail->addAddress($recipientEmail, $recipientName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "New Direct Message from $senderName";
            
            // HTML Email Body
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #2C3E50; color: white; padding: 20px; text-align: center;'>
                    <h2>New Direct Message</h2>
                </div>
                <div style='padding: 20px; background: #f8f9fa;'>
                    <p>Hello $recipientName,</p>
                    <p>You have received a direct message from <strong>$senderName</strong> in the group <strong>$groupName</strong>:</p>
                    <div style='background: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p style='margin: 0;'>$message</p>
                    </div>
                    <p>Log in to LifeSync to view and respond to this message.</p>
                </div>
                <div style='background: #f1f1f1; padding: 15px; text-align: center; font-size: 12px;'>
                    <p>This is an automated message from LIFE-SYNC. Please do not reply to this email.</p>
                </div>
            </div>";
            
            $mail->Body = $emailBody;
            $mail->AltBody = "New direct message from $senderName: $message";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            return false;
        }
    }

    // Send email using PHPMailer
    if (sendDirectMessageNotification($recipient['email'], $recipient['username'], $sender['username'], $group['group_name'], $message)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to send email notification']);
    }
} else {
    echo json_encode(['error' => 'Failed to send message']);
} 