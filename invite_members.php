<?php
session_start();
include("connect.php");

// Add these use statements at the top of the file, not inside functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in to invite members']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    $user_id = $_SESSION['user_id'];
    
    // Check if user has permission to invite (is creator or member)
    $check_permission = "SELECT * FROM group_members 
                        WHERE group_id = '$group_id' 
                        AND user_id = '$user_id' 
                        AND invitation_status = 'accepted'";
    $permission_result = mysqli_query($conn, $check_permission);
    
    if (mysqli_num_rows($permission_result) === 0) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to invite members to this group']);
        exit();
    }
    
    // Get group name for invitation email
    $group_query = "SELECT group_name FROM expense_groups WHERE group_id = '$group_id'";
    $group_result = mysqli_query($conn, $group_query);
    $group_data = mysqli_fetch_assoc($group_result);
    $group_name = $group_data['group_name'];
    
    $success_count = 0;
    $error_messages = [];
    
    if (!empty($_POST['members'])) {
        $members = explode(',', $_POST['members']);
        
        foreach ($members as $member_email) {
            $member_email = trim($member_email);
            
            // Check if email exists in users table
            $check_user = "SELECT user_id, username FROM users WHERE email = ?";
            $stmt = mysqli_prepare($conn, $check_user);
            mysqli_stmt_bind_param($stmt, "s", $member_email);
            mysqli_stmt_execute($stmt);
            $user_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($user_result) > 0) {
                $member_data = mysqli_fetch_assoc($user_result);
                $member_id = $member_data['user_id'];
                
                // Check if already a member
                $check_member = "SELECT invitation_status FROM group_members 
                               WHERE group_id = ? AND user_id = ?";
                $stmt = mysqli_prepare($conn, $check_member);
                mysqli_stmt_bind_param($stmt, "ii", $group_id, $member_id);
                mysqli_stmt_execute($stmt);
                $member_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($member_result) > 0) {
                    $member_status = mysqli_fetch_assoc($member_result)['invitation_status'];
                    if ($member_status === 'accepted') {
                        $error_messages[] = "$member_email is already a member of this group";
                    } else {
                        $error_messages[] = "$member_email has a pending invitation";
                    }
                    continue;
                }
                
                // Generate invitation token
                $token = bin2hex(random_bytes(16));
                
                // Add to group_members table
                $insert_invitation = "INSERT INTO group_members (group_id, user_id, invitation_status, invitation_token) 
                                   VALUES (?, ?, 'pending', ?)";
                $stmt = mysqli_prepare($conn, $insert_invitation);
                mysqli_stmt_bind_param($stmt, "iis", $group_id, $member_id, $token);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Send invitation email
                    if (sendInvitationEmail($member_email, $_SESSION['username'], $group_name, $token, $group_id)) {
                        $success_count++;
                    } else {
                        $error_messages[] = "Failed to send invitation email to $member_email";
                    }
                } else {
                    $error_messages[] = "Failed to create invitation for $member_email";
                }
            } else {
                $error_messages[] = "$member_email is not registered in LIFE-SYNC";
            }
        }
    }
    
    $response = [];
    if ($success_count > 0) {
        $response['success'] = true;
        $response['message'] = "Successfully sent " . $success_count . " invitation" . ($success_count > 1 ? "s" : "");
    }
    
    if (!empty($error_messages)) {
        if (!isset($response['success'])) {
            $response['success'] = false;
        }
        $response['error'] = implode("<br>", $error_messages);
    }
    
    echo json_encode($response);
    exit();
}

function sendInvitationEmail($email, $inviter_name, $group_name, $token, $group_id) {
    require 'vendor/autoload.php'; // Make sure you've installed PHPMailer via Composer

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Updated to Gmail SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'lifesyncdigital@gmail.com'; // Your Gmail address
        $mail->Password = 'yrpw iqys blcl famq'; // Your app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('lifesyncdigital@gmail.com', 'LIFE-SYNC');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Invitation to join expense group: ' . $group_name;
        
        $invitation_link = "http://{$_SERVER['HTTP_HOST']}/accept_invitation.php?token=" . $token . "&group=" . $group_id;
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #2C3E50;'>LIFE-SYNC Expense Splitter</h2>
                    <p>Hello,</p>
                    <p><strong>{$inviter_name}</strong> has invited you to join the expense group <strong>'{$group_name}'</strong>.</p>
                    <p>Click the button below to accept the invitation:</p>
                    <p style='text-align: center;'>
                        <a href='{$invitation_link}' style='display: inline-block; padding: 10px 20px; background-color: #2C3E50; color: white; text-decoration: none; border-radius: 5px;'>Accept Invitation</a>
                    </p>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p>{$invitation_link}</p>
                    <p>Thank you,<br>The LIFE-SYNC Team</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hello, {$inviter_name} has invited you to join the expense group '{$group_name}'. To accept the invitation, please visit: {$invitation_link}";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
} 