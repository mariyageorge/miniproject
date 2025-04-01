<?php
include("connect.php");
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Please login to continue']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Invalid request method']));
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

if (!$group_id) {
    die(json_encode(['success' => false, 'error' => 'Invalid group ID']));
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);

    // Check if user is the creator of the group
    $creator_check = "SELECT created_by FROM expense_groups WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $creator_check);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $group = mysqli_fetch_assoc($result);

    if (!$group) {
        throw new Exception('Group not found');
    }

    if ($group['created_by'] == $user_id) {
        throw new Exception('Group creator cannot leave the group');
    }

    // Check if user has any pending expenses in the group
    $pending_check = "SELECT COUNT(*) as count 
                     FROM expense_shares es 
                     JOIN expenses e ON es.expense_id = e.expense_id 
                     WHERE e.group_id = ? AND es.user_id = ? AND es.status = 'pending'";
    $stmt = mysqli_prepare($conn, $pending_check);
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pending = mysqli_fetch_assoc($result);

    if ($pending['count'] > 0) {
        throw new Exception('Please settle all pending expenses before leaving the group');
    }

    // Remove user from group
    $delete_query = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "ii", $group_id, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to remove user from group');
    }

    if (mysqli_affected_rows($conn) === 0) {
        throw new Exception('You are not a member of this group');
    }

    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully left the group'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
} 