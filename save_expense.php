<?php
include("connect.php");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $amount = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $paid_by = intval($_POST['paid_by']);
    $group_id = intval($_POST['group_id']);
    $split_method = $_POST['split_method'];
    $date = $_POST['date'];

    // Start transaction
    mysqli_begin_transaction($conn);

    // Insert expense
    $insert_expense = "INSERT INTO expenses (group_id, paid_by, amount, description, date_added) 
                      VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_expense);
    mysqli_stmt_bind_param($stmt, "iidss", $group_id, $paid_by, $amount, $description, $date);
    mysqli_stmt_execute($stmt);
    $expense_id = mysqli_insert_id($conn);

    // Get group members
    $get_members = "SELECT user_id FROM group_members WHERE group_id = ? AND invitation_status = 'accepted'";
    $stmt = mysqli_prepare($conn, $get_members);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $members_result = mysqli_stmt_get_result($stmt);
    $members = [];
    while ($row = mysqli_fetch_assoc($members_result)) {
        $members[] = $row['user_id'];
    }

    // Calculate and insert shares
    switch ($split_method) {
        case 'equally':
            $share_amount = $amount / count($members);
            foreach ($members as $member_id) {
                if ($member_id != $paid_by) { // Skip the payer
                    $insert_share = "INSERT INTO expense_shares (expense_id, user_id, share_amount) 
                                   VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_share);
                    mysqli_stmt_bind_param($stmt, "iid", $expense_id, $member_id, $share_amount);
                    mysqli_stmt_execute($stmt);
                }
            }
            break;

        case 'unequally':
            foreach ($_POST['shares'] as $member_id => $share) {
                if ($member_id != $paid_by && floatval($share) > 0) {
                    $share_amount = floatval($share);
                    $insert_share = "INSERT INTO expense_shares (expense_id, user_id, share_amount) 
                                   VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_share);
                    mysqli_stmt_bind_param($stmt, "iid", $expense_id, $member_id, $share_amount);
                    mysqli_stmt_execute($stmt);
                }
            }
            break;

        case 'percentage':
            foreach ($_POST['shares'] as $member_id => $percentage) {
                if ($member_id != $paid_by && floatval($percentage) > 0) {
                    $share_amount = ($amount * floatval($percentage)) / 100;
                    $insert_share = "INSERT INTO expense_shares (expense_id, user_id, share_amount) 
                                   VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $insert_share);
                    mysqli_stmt_bind_param($stmt, "iid", $expense_id, $member_id, $share_amount);
                    mysqli_stmt_execute($stmt);
                }
            }
            break;
    }

    // Commit transaction
    mysqli_commit($conn);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 