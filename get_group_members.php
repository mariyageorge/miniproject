<?php
session_start();
include("connect.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Get group ID from request
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

if (!$group_id) {
    echo json_encode(['error' => 'Invalid group ID']);
    exit;
}

try {
    // Check if user is member of the group
    $check_member = "SELECT invitation_status FROM group_members 
                    WHERE group_id = ? AND user_id = ? AND invitation_status = 'accepted'";
    $stmt = mysqli_prepare($conn, $check_member);
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['error' => 'You are not a member of this group']);
        exit;
    }

    // Get all accepted members of the group
    $query = "SELECT u.user_id, u.username, u.email, u.profile_pic 
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
            'email' => $row['email'],
            'profile_pic' => !empty($row['profile_pic']) ? $row['profile_pic'] : 'images/default-avatar.png'
        ];
    }

    echo json_encode($members);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

mysqli_close($conn);
?> 