<?php
include("connect.php");
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'error' => 'Please login to continue']));
}

$user_id = $_SESSION['user_id'];
$expense_id = isset($_GET['expense_id']) ? intval($_GET['expense_id']) : 0;

// Fetch user's currency preference
$currency_query = "SELECT currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $currency_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$currency_result = mysqli_stmt_get_result($stmt);
$currency_pref = mysqli_fetch_assoc($currency_result);
$currency_symbol = $currency_pref['currency_symbol'] ?? '$'; // Default to $ if no preference set

if (!$expense_id) {
    die(json_encode(['success' => false, 'error' => 'Invalid expense ID']));
}

try {
    // Get expense details
    $expense_query = "SELECT e.*, u.username as paid_by_user, g.group_name
                     FROM expenses e
                     JOIN users u ON e.paid_by = u.user_id
                     JOIN expense_groups g ON e.group_id = g.group_id
                     WHERE e.expense_id = ?";
    $stmt = mysqli_prepare($conn, $expense_query);
    mysqli_stmt_bind_param($stmt, "i", $expense_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $expense = mysqli_fetch_assoc($result);

    if (!$expense) {
        die(json_encode(['success' => false, 'error' => 'Expense not found']));
    }

    // Get shares details
    $shares_query = "SELECT es.*, u.username, u.user_id
                    FROM expense_shares es
                    JOIN users u ON es.user_id = u.user_id
                    WHERE es.expense_id = ?";
    $stmt = mysqli_prepare($conn, $shares_query);
    mysqli_stmt_bind_param($stmt, "i", $expense_id);
    mysqli_stmt_execute($stmt);
    $shares_result = mysqli_stmt_get_result($stmt);
    
    $shares = [];
    while ($share = mysqli_fetch_assoc($shares_result)) {
        $shares[] = $share;
    }

    $expense['shares'] = $shares;
    
    echo json_encode([
        'success' => true,
        'expense' => $expense,
        'currency_symbol' => $currency_symbol
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} finally {
    mysqli_close($conn);
} 