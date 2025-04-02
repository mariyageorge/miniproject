<?php
session_start();
include("connect.php");

// Debug logging
error_log("Expense submission received");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));
error_log("SESSION data: " . print_r($_SESSION, true));

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    // Get form data
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $amount = floatval($_POST['amount']);
    $paid_by = mysqli_real_escape_string($conn, $_POST['paid_by']);
    $split_method = mysqli_real_escape_string($conn, $_POST['split_method']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';

    // Validate required fields
    if (empty($group_id) || empty($description) || $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields']);
        exit;
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    // Handle file upload if present
    $receipt_path = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/receipts/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.');
        }

        $receipt_path = $upload_dir . uniqid('receipt_') . '.' . $file_extension;
        if (!move_uploaded_file($_FILES['receipt_image']['tmp_name'], $receipt_path)) {
            throw new Exception('Failed to upload receipt image');
        }
    }

    // Insert expense record
    $query = "INSERT INTO expenses (group_id, description, amount, paid_by, date_added, notes, receipt_image) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "isdisss", $group_id, $description, $amount, $paid_by, $date, $notes, $receipt_path);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to add expense: ' . mysqli_error($conn));
    }

    $expense_id = mysqli_insert_id($conn);

    // Process splits based on method
    $splits = [];
    if ($split_method === 'equal') {
        // Get number of group members
        $member_query = "SELECT user_id FROM group_members WHERE group_id = ? AND invitation_status = 'accepted'";
        $stmt = mysqli_prepare($conn, $member_query);
        mysqli_stmt_bind_param($stmt, "i", $group_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $member_count = mysqli_num_rows($result);

        if ($member_count === 0) {
            throw new Exception('No members found in the group');
        }

        $share_amount = $amount / $member_count;
        while ($member = mysqli_fetch_assoc($result)) {
            if ($member['user_id'] != $paid_by) { // Don't create share for the payer
                $splits[] = [
                    'user_id' => $member['user_id'],
                    'amount' => $share_amount
                ];
            }
        }
    } else if ($split_method === 'percentage') {
        // Process percentage splits
        foreach ($_POST['splits'] as $user_id => $percentage) {
            if ($user_id != $paid_by && is_numeric($percentage) && $percentage > 0) {
                $splits[] = [
                    'user_id' => $user_id,
                    'amount' => ($amount * floatval($percentage)) / 100
                ];
            }
        }
    } else if ($split_method === 'exact') {
        // Process exact amount splits
        foreach ($_POST['splits'] as $user_id => $split_amount) {
            if ($user_id != $paid_by && is_numeric($split_amount) && floatval($split_amount) > 0) {
                $splits[] = [
                    'user_id' => $user_id,
                    'amount' => floatval($split_amount)
                ];
            }
        }
    }

    // Validate splits
    if (empty($splits)) {
        throw new Exception('No valid splits provided');
    }

    // For percentage splits, verify total is 100%
    if ($split_method === 'percentage') {
        $total_percentage = 0;
        foreach ($_POST['splits'] as $percentage) {
            $total_percentage += floatval($percentage);
        }
        if (abs($total_percentage - 100) > 0.01) {
            throw new Exception('Percentage splits must total 100%');
        }
    }

    // For exact splits, verify total matches expense amount
    if ($split_method === 'exact') {
        $total_split = 0;
        foreach ($splits as $split) {
            $total_split += $split['amount'];
        }
        if (abs($total_split - $amount) > 0.01) {
            throw new Exception('Split amounts must equal the total expense amount');
        }
    }

    // Insert expense shares
    $share_query = "INSERT INTO expense_shares (expense_id, user_id, share_amount, status) VALUES (?, ?, ?, 'pending')";
    $stmt = mysqli_prepare($conn, $share_query);

    foreach ($splits as $split) {
        mysqli_stmt_bind_param($stmt, "iid", $expense_id, $split['user_id'], $split['amount']);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to add expense shares');
        }
    }

    // Commit transaction
    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Expense added successfully',
        'expense_id' => $expense_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);

    // Delete uploaded file if exists
    if (isset($receipt_path) && file_exists($receipt_path)) {
        unlink($receipt_path);
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Close database connection
mysqli_close($conn);
?> 