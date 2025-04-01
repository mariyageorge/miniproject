<?php
include("connect.php");
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => 'Please login to continue']);
    exit();
}

$user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

if (!$group_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid group ID']);
    exit();
}

// Check if user is the group creator
$check_creator = "SELECT created_by FROM expense_groups WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $check_creator);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($result);

if (!$group || $group['created_by'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this group']);
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Delete expense shares first
    $delete_shares = "DELETE es FROM expense_shares es
                     INNER JOIN expenses e ON es.expense_id = e.expense_id
                     WHERE e.group_id = ?";
    $stmt = mysqli_prepare($conn, $delete_shares);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);

    // Delete expenses
    $delete_expenses = "DELETE FROM expenses WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $delete_expenses);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);

    // Delete settlements
    $delete_settlements = "DELETE FROM settlements WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $delete_settlements);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);

    // Delete group members
    $delete_members = "DELETE FROM group_members WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $delete_members);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);

    // Finally, delete the group
    $delete_group = "DELETE FROM expense_groups WHERE group_id = ?";
    $stmt = mysqli_prepare($conn, $delete_group);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);

    // Commit transaction
    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'Failed to delete group: ' . $e->getMessage()]);
}
?> 