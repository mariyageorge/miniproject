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
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$group_id || empty($message)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// Verify user is a member of the group
$check_membership = "SELECT invitation_status FROM group_members 
                    WHERE group_id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $check_membership);
mysqli_stmt_bind_param($stmt, "ii", $group_id, $sender_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$membership = mysqli_fetch_assoc($result);

if (!$membership || $membership['invitation_status'] !== 'accepted') {
    echo json_encode(['error' => 'You must be a member of the group to send messages']);
    exit();
}

// Insert the message
$insert_query = "INSERT INTO group_messages (group_id, sender_id, message, created_at) 
                 VALUES (?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "iis", $group_id, $sender_id, $message);

if (mysqli_stmt_execute($stmt)) {
    // Get group name
    $group_query = "SELECT group_name FROM expense_groups WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $group_query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $group = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Get sender's name
    $sender_query = "SELECT username FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sender_query);
    mysqli_stmt_bind_param($stmt, "i", $sender_id);
    mysqli_stmt_execute($stmt);
    $sender = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Get all group members except the sender
    $members_query = "SELECT u.email, u.username 
                     FROM users u 
                     JOIN group_members gm ON u.user_id = gm.user_id 
                     WHERE gm.group_id = ? AND gm.user_id != ? 
                     AND gm.invitation_status = 'accepted'";
    $stmt = mysqli_prepare($conn, $members_query);
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $sender_id);
    mysqli_stmt_execute($stmt);
    $members_result = mysqli_stmt_get_result($stmt);

    // Send email notifications to all members
    while ($member = mysqli_fetch_assoc($members_result)) {
        $to = $member['email'];
        $subject = "New message in {$group['group_name']} from {$sender['username']}";
        $headers = "From: noreply@lifesync.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $email_content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .message { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    .button { display: inline-block; padding: 10px 20px; background: #8B4513; color: white; text-decoration: none; border-radius: 5px; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>New Message in {$group['group_name']}</h2>
                    <p><strong>{$sender['username']}</strong> has sent a new message:</p>
                    <div class='message'>
                        {$message}
                    </div>
                    <p><a href='http://{$_SERVER['HTTP_HOST']}/expense_splitter.php' class='button'>View Message</a></p>
                    <div class='footer'>
                        <p>This is an automated message from LIFE-SYNC. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        mail($to, $subject, $email_content, $headers);
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to send message']);
}

// Function to send email notification
function sendEmailNotification($recipientEmail, $recipientName, $senderName, $groupName, $message) {
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
        $mail->Subject = "New Group Message in $groupName";
        
        // HTML Email Body
        $emailBody = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #2C3E50; color: white; padding: 20px; text-align: center;'>
                <h2>New Group Message</h2>
            </div>
            <div style='padding: 20px; background: #f8f9fa;'>
                <p>Hello $recipientName,</p>
                <p><strong>$senderName</strong> has sent a new message in the group <strong>$groupName</strong>:</p>
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
        $mail->AltBody = "New message from $senderName in group $groupName: $message";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: {$mail->ErrorInfo}");
        return false;
    }
} 