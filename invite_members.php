<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please login to continue']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
$emails = isset($_POST['emails']) ? json_decode($_POST['emails'], true) : [];
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (!$group_id) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit();
}

if (empty($emails)) {
    echo json_encode(['error' => 'Please provide at least one email address']);
    exit();
}

// Verify user is the group creator
$check_creator = "SELECT created_by FROM expense_groups WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $check_creator);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group || $group['created_by'] != $user_id) {
    echo json_encode(['error' => 'You do not have permission to invite members to this group']);
    exit();
}

$success_count = 0;
$errors = [];

foreach ($emails as $email) {
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format: $email";
        continue;
    }

    // Check if user exists
    $user_query = "SELECT user_id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($user_result);

    if (!$user) {
        $errors[] = "User not found: $email";
        continue;
    }

    // Check if user is already a member
    $member_query = "SELECT invitation_status FROM group_members WHERE group_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $member_query);
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $user['user_id']);
    mysqli_stmt_execute($stmt);
    $member_result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($member_result);

    if ($member) {
        if ($member['invitation_status'] === 'accepted') {
            $errors[] = "User is already a member: $email";
        } elseif ($member['invitation_status'] === 'pending') {
            $errors[] = "Invitation already sent: $email";
        } elseif ($member['invitation_status'] === 'declined') {
            $errors[] = "User declined previous invitation: $email";
        }
        continue;
    }

    // Generate invitation token
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

    // Insert invitation
    $invite_query = "INSERT INTO group_members (group_id, user_id, invitation_token, invitation_status, expires_at) 
                     VALUES (?, ?, ?, 'pending', ?)";
    $stmt = mysqli_prepare($conn, $invite_query);
    mysqli_stmt_bind_param($stmt, "iiss", $group_id, $user['user_id'], $token, $expires_at);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_count++;

        // Send invitation email
        $group_query = "SELECT group_name FROM expense_groups WHERE group_id = ?";
        $stmt = mysqli_prepare($conn, $group_query);
        mysqli_stmt_bind_param($stmt, "i", $group_id);
        mysqli_stmt_execute($stmt);
        $group_result = mysqli_stmt_get_result($stmt);
        $group_info = mysqli_fetch_assoc($group_result);

        $invite_link = "http://" . $_SERVER['HTTP_HOST'] . "/accept_invitation.php?token=" . $token . "&group_id=" . $group_id;
        
        // Get sender's name
        $sender_query = "SELECT username FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sender_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $sender_result = mysqli_stmt_get_result($stmt);
        $sender = mysqli_fetch_assoc($sender_result);

        $to = $email;
        $subject = "You've been invited to join " . $group_info['group_name'] . " on LIFE-SYNC";
        $headers = "From: noreply@lifesync.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $email_content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background: #8B4513; color: white; text-decoration: none; border-radius: 5px; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <h2>You've been invited to join {$group_info['group_name']}!</h2>
                    <p>{$sender['username']} has invited you to join their expense sharing group on LIFE-SYNC.</p>
                    " . ($message ? "<p><strong>Message from {$sender['username']}:</strong><br>{$message}</p>" : "") . "
                    <p>Click the button below to accept the invitation:</p>
                    <p><a href='{$invite_link}' class='button'>Accept Invitation</a></p>
                    <p>This invitation will expire in 7 days.</p>
                    <p>If you don't want to join this group, you can safely ignore this email.</p>
                    <div class='footer'>
                        <p>This is an automated message from LIFE-SYNC. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        mail($to, $subject, $email_content, $headers);
    } else {
        $errors[] = "Failed to send invitation to: $email";
    }
}

if ($success_count > 0) {
    echo json_encode([
        'success' => true,
        'message' => "Successfully sent $success_count invitation(s)" . 
                    (!empty($errors) ? ". Some errors occurred: " . implode(", ", $errors) : "")
    ]);
} else {
    echo json_encode([
        'error' => 'Failed to send any invitations',
        'details' => $errors
    ]);
} 