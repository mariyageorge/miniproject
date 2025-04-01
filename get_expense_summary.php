<?php
session_start();
include 'connect.php';

// Check if user is logged in and is a premium user
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'premium user') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get POST data
$year = isset($_POST['year']) ? $_POST['year'] : date('Y');
$month = isset($_POST['month']) ? $_POST['month'] : date('n');
$user_id = $_SESSION['user_id'];

// Validate input
if (!is_numeric($year) || !is_numeric($month) || $month < 1 || $month > 12) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Get expense summary for the specified month and year
$summary_sql = "SELECT 
    SUM(amount) as total_amount,
    COUNT(*) as transaction_count,
    AVG(amount) as average_expense,
    MAX(amount) as highest_expense,
    MIN(amount) as lowest_expense
FROM p_expenses 
WHERE user_id = ? 
AND YEAR(date) = ? 
AND MONTH(date) = ?";

$stmt = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $year, $month);
mysqli_stmt_execute($stmt);
$summary_result = mysqli_stmt_get_result($stmt);
$summary_data = mysqli_fetch_assoc($summary_result);

// Check if there's any data for the selected month
if ($summary_data['transaction_count'] == 0) {
    echo json_encode([
        'no_data' => true,
        'message' => "No expense data found for " . date('F Y', strtotime("$year-$month-01"))
    ]);
    exit();
}

// Continue with rest of the code if there is data
// Get category breakdown
$category_sql = "SELECT 
    category,
    COUNT(*) as count,
    SUM(amount) as amount
FROM p_expenses 
WHERE user_id = ? 
AND YEAR(date) = ? 
AND MONTH(date) = ?
GROUP BY category
ORDER BY amount DESC";

$stmt = mysqli_prepare($conn, $category_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $year, $month);
mysqli_stmt_execute($stmt);
$category_result = mysqli_stmt_get_result($stmt);

$categories = [];
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = [
        'category' => $row['category'],
        'count' => $row['count'],
        'amount' => floatval($row['amount'])
    ];
}

// Get daily spending pattern
$daily_sql = "SELECT 
    DATE_FORMAT(date, '%Y-%m-%d') as date,
    SUM(amount) as amount
FROM p_expenses 
WHERE user_id = ? 
AND YEAR(date) = ? 
AND MONTH(date) = ?
GROUP BY date
ORDER BY date";

$stmt = mysqli_prepare($conn, $daily_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $year, $month);
mysqli_stmt_execute($stmt);
$daily_result = mysqli_stmt_get_result($stmt);

$daily_spending = [];
while ($row = mysqli_fetch_assoc($daily_result)) {
    $daily_spending[] = [
        'date' => $row['date'],
        'amount' => floatval($row['amount'])
    ];
}

// Format the response data
$response = [
    'no_data' => false,
    'total_amount' => number_format(floatval($summary_data['total_amount']), 2),
    'transaction_count' => intval($summary_data['transaction_count']),
    'average_expense' => number_format(floatval($summary_data['average_expense']), 2),
    'highest_expense' => number_format(floatval($summary_data['highest_expense']), 2),
    'lowest_expense' => number_format(floatval($summary_data['lowest_expense']), 2),
    'categories' => $categories,
    'daily_spending' => $daily_spending,
    'month_year' => date('F Y', strtotime("$year-$month-01"))
];

// Send response
header('Content-Type: application/json');
echo json_encode($response);
?> 