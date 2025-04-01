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
$expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;

if (!$expense_id) {
    die(json_encode(['success' => false, 'error' => 'Invalid expense ID']));
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);

    // Check if the expense exists and get group_id
    $expense_query = "SELECT group_id FROM expenses WHERE expense_id = ?";
    $stmt = mysqli_prepare($conn, $expense_query);
    mysqli_stmt_bind_param($stmt, "i", $expense_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $expense = mysqli_fetch_assoc($result);

    if (!$expense) {
        throw new Exception('Expense not found');
    }

    // Check if user is part of the group
    $member_query = "SELECT invitation_status FROM group_members 
                    WHERE group_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $member_query);
    mysqli_stmt_bind_param($stmt, "ii", $expense['group_id'], $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $member = mysqli_fetch_assoc($result);

    if (!$member || $member['invitation_status'] !== 'accepted') {
        throw new Exception('You are not a member of this group');
    }

    // Update the expense share as settled
    $update_query = "UPDATE expense_shares 
                    SET is_settled = 1, settled_at = NOW() 
                    WHERE expense_id = ? AND user_id = ? AND is_settled = 0";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ii", $expense_id, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to settle expense');
    }

    if (mysqli_affected_rows($conn) === 0) {
        throw new Exception('Expense is already settled or does not exist');
    }

    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Expense settled successfully'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
} 