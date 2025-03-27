<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$token = isset($_GET['token']) ? $_GET['token'] : '';
$group_id = isset($_GET['group']) ? intval($_GET['group']) : 0;

if (empty($token) || !$group_id) {
    $_SESSION['error'] = "Invalid invitation link.";
    header("Location: expense_splitter.php");
    exit();
}

// Verify the invitation token and update status
$verify_query = "UPDATE group_members 
                SET invitation_status = 'accepted' 
                WHERE user_id = ? AND group_id = ? AND invitation_token = ? AND invitation_status = 'pending'";
$stmt = mysqli_prepare($conn, $verify_query);
mysqli_stmt_bind_param($stmt, "iis", $user_id, $group_id, $token);

if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    // Get group name for success message
    $group_query = "SELECT group_name FROM expense_groups WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $group_query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $group = mysqli_fetch_assoc($result);
    
    $_SESSION['success'] = "You have successfully joined the group: " . htmlspecialchars($group['group_name']);
} else {
    $_SESSION['error'] = "Invalid or expired invitation link.";
}

header("Location: expense_splitter.php");
exit(); 