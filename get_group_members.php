<?php
include("connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if (!isset($_GET['group_id'])) {
    echo json_encode(['error' => 'Group ID not provided']);
    exit();
}

$group_id = intval($_GET['group_id']);

// Get group members
$query = "SELECT u.user_id, u.username, u.profile_pic 
          FROM users u 
          JOIN group_members gm ON u.user_id = gm.user_id 
          WHERE gm.group_id = ? AND gm.invitation_status = 'accepted'";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$members = [];
while ($row = mysqli_fetch_assoc($result)) {
    $members[] = [
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'profile_pic' => !empty($row['profile_pic']) ? $row['profile_pic'] : 'images/default-avatar.png'
    ];
}

echo json_encode($members);
?> 