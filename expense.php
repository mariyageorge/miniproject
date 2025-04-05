<?php
include 'connect.php';
include 'header.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Fetch user's currency preference
$currency_query = "SELECT currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $currency_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$currency_result = mysqli_stmt_get_result($stmt);
$currency_pref = mysqli_fetch_assoc($currency_result);
$currency_symbol = $currency_pref['currency_symbol'] ?? '₹'; // Default to ₹ if no preference set

$sql = "CREATE TABLE IF NOT EXISTS p_expenses (
    expense_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $sql)) {
    echo "Error creating table: " . mysqli_error($conn);
}

// Create monthly_limits table if not exists
$sql = "CREATE TABLE IF NOT EXISTS monthly_limits (
    limit_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    month INT(2) NOT NULL,
    year INT(4) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_month_year (user_id, month, year)
)";

if (!mysqli_query($conn, $sql)) {
    echo "Error creating table: " . mysqli_error($conn);
}



// Handle expense submission
if (isset($_POST['add_expense'])) {
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $date = mysqli_real_escape_string($conn, $_POST['date']) ?: date('Y-m-d');
    
    // Validate inputs
    if (empty($amount) || empty($description) || empty($category)) {
        echo "<script>alert('Please fill in all required fields.');</script>";
    } else {
        $sql = "INSERT INTO p_expenses (user_id, amount, description, category, date) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "idsss", $user_id, $amount, $description, $category, $date);
            
            if (!mysqli_stmt_execute($stmt)) {
               
                echo "<script>alert('Error adding expense: " . mysqli_error($conn) . "');</script>";
            }
            mysqli_stmt_close($stmt);
        } else {
            echo "<script>alert('Error preparing statement: " . mysqli_error($conn) . "');</script>";
        }
    }
}



// Handle monthly limit setting
if (isset($_POST['set_limit'])) {
    $limit_amount = $_POST['limit_amount'];
    $month = date('n'); // Current month as number (1-12)
    $year = date('Y');  // Current year
    
    // Check if limit already exists for this month/year
    $check_sql = "SELECT * FROM monthly_limits WHERE user_id = ? AND month = ? AND year = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Update existing limit
        $update_sql = "UPDATE monthly_limits SET amount = ? WHERE user_id = ? AND month = ? AND year = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'diii', $limit_amount, $user_id, $month, $year);
    } else {
        // Insert new limit
        $insert_sql = "INSERT INTO monthly_limits (user_id, amount, month, year) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, 'idii', $user_id, $limit_amount, $month, $year);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $limit_success = "Monthly limit updated successfully!";
    } else {
        $limit_error = "Error updating limit: " . mysqli_error($conn);
    }
}

// Get current month's limit
$month = date('n');
$year = date('Y');
$limit_sql = "SELECT amount FROM monthly_limits WHERE user_id = ? AND month = ? AND year = ?";
$stmt = mysqli_prepare($conn, $limit_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
mysqli_stmt_execute($stmt);
$limit_result = mysqli_stmt_get_result($stmt);
$monthly_limit = 0;

if (mysqli_num_rows($limit_result) > 0) {
    $limit_row = mysqli_fetch_assoc($limit_result);
    $monthly_limit = $limit_row['amount'];
}

// Calculate month-to-date spending
$mtd_sql = "SELECT SUM(amount) as total FROM p_expenses 
            WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
$stmt = mysqli_prepare($conn, $mtd_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
mysqli_stmt_execute($stmt);
$mtd_result = mysqli_stmt_get_result($stmt);
$mtd_row = mysqli_fetch_assoc($mtd_result);
$month_to_date_spending = $mtd_row['total'] ?: 0;

// Get expense categories from database for dropdown
$cat_sql = "SELECT DISTINCT category FROM p_expenses WHERE user_id = ? ORDER BY category";
$stmt = mysqli_prepare($conn, $cat_sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$categories_result = mysqli_stmt_get_result($stmt);
$categories = [];
while ($cat_row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat_row['category'];
}

// Get recent expenses
$recent_expenses_sql = "SELECT * FROM p_expenses 
                       WHERE user_id = ? 
                       ORDER BY date DESC LIMIT 10";
$stmt = mysqli_prepare($conn, $recent_expenses_sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$recent_expenses = mysqli_stmt_get_result($stmt);

// Get expense summary by category for current month
$category_summary_sql = "SELECT category, SUM(amount) as total 
                        FROM p_expenses 
                        WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                        GROUP BY category 
                        ORDER BY total DESC";
$stmt = mysqli_prepare($conn, $category_summary_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
mysqli_stmt_execute($stmt);
$category_summary = mysqli_stmt_get_result($stmt);


// NEW CODE: Get data for charts
// Weekly data for current month
$weekly_data_sql = "SELECT 
                      WEEK(date) as week_num,
                      CONCAT('Week ', WEEK(date) - WEEK(DATE_FORMAT(date, '%Y-%m-01')) + 1) as week_label,
                      SUM(amount) as total 
                    FROM p_expenses 
                    WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                    GROUP BY WEEK(date)
                    ORDER BY WEEK(date)";
$stmt = mysqli_prepare($conn, $weekly_data_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
mysqli_stmt_execute($stmt);
$weekly_result = mysqli_stmt_get_result($stmt);
$weekly_data = [];
$weekly_labels = [];
$weekly_values = [];
while ($row = mysqli_fetch_assoc($weekly_result)) {
    $weekly_labels[] = $row['week_label'];
    $weekly_values[] = floatval($row['total']);
    $weekly_data[] = $row;
}
$weekly_json = json_encode(['labels' => $weekly_labels, 'values' => $weekly_values]);

// Monthly data for current year
$monthly_data_sql = "SELECT 
                      MONTH(date) as month_num,
                      DATE_FORMAT(date, '%b') as month_label,
                      SUM(amount) as total 
                    FROM p_expenses 
                    WHERE user_id = ? AND YEAR(date) = ?
                    GROUP BY MONTH(date)
                    ORDER BY MONTH(date)";
$stmt = mysqli_prepare($conn, $monthly_data_sql);
mysqli_stmt_bind_param($stmt, 'ii', $user_id, $year);
mysqli_stmt_execute($stmt);
$monthly_result = mysqli_stmt_get_result($stmt);
$monthly_data = [];
$monthly_labels = [];
$monthly_values = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_labels[] = $row['month_label'];
    $monthly_values[] = floatval($row['total']);
    $monthly_data[] = $row;
}
$monthly_json = json_encode(['labels' => $monthly_labels, 'values' => $monthly_values]);

// Yearly data for last 5 years
$yearly_data_sql = "SELECT 
                      YEAR(date) as year,
                      SUM(amount) as total 
                    FROM p_expenses 
                    WHERE user_id = ? AND YEAR(date) >= YEAR(NOW()) - 5
                    GROUP BY YEAR(date)
                    ORDER BY YEAR(date)";
$stmt = mysqli_prepare($conn, $yearly_data_sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$yearly_result = mysqli_stmt_get_result($stmt);
$yearly_data = [];
$yearly_labels = [];
$yearly_values = [];
while ($row = mysqli_fetch_assoc($yearly_result)) {
    $yearly_labels[] = $row['year'];
    $yearly_values[] = floatval($row['total']);
    $yearly_data[] = $row;
}
$yearly_json = json_encode(['labels' => $yearly_labels, 'values' => $yearly_values]);

// Category breakdown data for pie chart
$category_data_sql = "SELECT 
                        category,
                        SUM(amount) as total 
                      FROM p_expenses 
                      WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
                      GROUP BY category";
$stmt = mysqli_prepare($conn, $category_data_sql);
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
mysqli_stmt_execute($stmt);
$category_result = mysqli_stmt_get_result($stmt);
$category_labels = [];
$category_values = [];
$category_colors = [];
$color_palette = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', 
    '#FF9F40', '#8AC249', '#EA80FC', '#607D8B', '#00BCD4'
];
$color_index = 0;
while ($row = mysqli_fetch_assoc($category_result)) {
    $category_labels[] = $row['category'];
    $category_values[] = floatval($row['total']);
    $category_colors[] = $color_palette[$color_index % count($color_palette)];
    $color_index++;
}
$category_pie_json = json_encode([
    'labels' => $category_labels, 
    'values' => $category_values,
    'colors' => $category_colors
]);

// Handle Delete Expense
if (isset($_POST['delete_expense'])) {
    $expense_id = mysqli_real_escape_string($conn, $_POST['expense_id']);
    
    // Validate expense belongs to user
    $check_sql = "SELECT * FROM p_expenses WHERE expense_id = ? AND user_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "ii", $expense_id, $user_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $delete_sql = "DELETE FROM p_expenses WHERE expense_id = ? AND user_id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, "ii", $expense_id, $user_id);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    echo "<script>alert('Expense deleted successfully!'); window.location.href = 'expense.php';</script>";
                } else {
                    echo "<script>alert('Error deleting expense: " . mysqli_error($conn) . "');</script>";
                }
                mysqli_stmt_close($delete_stmt);
            }
        } else {
            echo "<script>alert('Expense not found or unauthorized.');</script>";
        }
        mysqli_stmt_close($check_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
    /* Base styling remains similar to original */
    :root {
        --nude-100: #F5ECE5;
        --nude-200: #E8D5C8;
        --nude-300: #DBBFAE;
        --nude-400: #C6A792;
        --nude-500: #B08F78;
        --brown-primary: #8B4513;
        --brown-hover: #A0522D;
        --brown-light: #DEB887;
        --text-dark: #3E2723;
        --text-light: #F5ECE5;
        --accent-gold: #D4AF37;
        --shadow-color: rgba(62, 39, 35, 0.2);
    }

    body {
        font-family: 'Georgia', serif;
        background-color: var(--nude-100);
        color: var(--text-dark);
        line-height: 1.6;
        overflow-x: hidden;
    }

    /* Added animation classes */
    .fade-in {
        animation: fadeIn 0.8s ease-in;
    }

    .slide-in {
        animation: slideIn 0.6s ease-out;
    }

    .pulse {
        animation: pulse 2s infinite;
    }

    .bounce-in {
        animation: bounceIn 0.8s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }

    @keyframes bounceIn {
        0% { transform: scale(0.8); opacity: 0; }
        60% { transform: scale(1.05); opacity: 1; }
        100% { transform: scale(1); }
    }

    /* Improved container */
    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 20px;
        position: relative;
    }

    /* Enhanced header */
    header {
        background-color: var(--brown-primary);
        color: var(--text-light);
        padding: 20px;
        /* border-radius: 12px; */
        margin-bottom: 30px;
        background-image: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
        box-shadow: 0 6px 12px var(--shadow-color);
        /* border-bottom: 3px solid var(--accent-gold); */
        position: relative;
        overflow: hidden;
    }

    header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 60%);
        animation: shine 8s infinite linear;
    }

    @keyframes shine {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    header h1 {
        margin: 0;
        display: flex;
        align-items: center;
        font-family: 'Playfair Display', 'Times New Roman', serif;
        font-weight: 700;
        letter-spacing: 0.5px;
        position: relative;
        z-index: 1;
    }

    header h1 i {
        margin-right: 15px;
        color: var(--accent-gold);
        animation: coinToss 3s infinite;
        transform-origin: center center;
    }

    @keyframes coinToss {
        0% { transform: rotateY(0); }
        50% { transform: rotateY(180deg); }
        100% { transform: rotateY(360deg); }
    }

    /* Enhanced card styling */
    .card {
        background-color: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        overflow: hidden;
        margin-bottom: 30px;
        transition: transform 0.3s, box-shadow 0.3s;
        border: none;
        position: relative;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px rgba(0,0,0,0.15);
    }

    .card-header {
        background-color: var(--brown-light);
        padding: 15px 20px;
        border-bottom: 2px solid var(--brown-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-header h3 {
        margin: 0;
        color: var(--brown-primary);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header i {
        color: var(--brown-primary);
    }

    .card-body {
        padding: 20px;
    }

    /* Tab system for charts */
    .chart-tabs {
        display: flex;
        border-bottom: 2px solid var(--nude-300);
        margin-bottom: 20px;
    }

    .chart-tab {
        padding: 10px 20px;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-family: 'Georgia', serif;
        font-weight: bold;
        color: var(--text-dark);
        opacity: 0.7;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chart-tab:hover {
        opacity: 1;
        background-color: var(--nude-100);
    }

    .chart-tab.active {
        border-bottom: 3px solid var(--brown-primary);
        opacity: 1;
    }

    .chart-container {
        height: 300px;
        position: relative;
        margin-bottom: 20px;
        display: none;
    }

    .chart-container.active {
        display: block;
        animation: fadeIn 0.5s;
    }

    /* Icon hover effects */
    .icon-hover {
        transition: transform 0.3s, color 0.3s;
    }

    .icon-hover:hover {
        transform: scale(1.2);
        color: var(--accent-gold);
    }

    /* Stats card with icons */
    .stats-card {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .stat-item {
        background: linear-gradient(135deg, #fff, var(--nude-100));
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        border-left: 4px solid var(--brown-primary);
        transition: all 0.3s;
    }

    .stat-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.1);
    }

    .stat-icon {
        font-size: 28px;
        color: var(--brown-primary);
        margin-bottom: 10px;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: var(--text-dark);
        margin: 5px 0;
    }

    .stat-label {
        font-size: 14px;
        color: var(--text-dark);
        opacity: 0.7;
    }

    /* Three-column layout for larger screens */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr 3fr 1fr;
        gap: 25px;
    }

    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Expense form enhancements */
    .expense-form {
        background-color: var(--nude-200);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 6px 12px var(--shadow-color);
        border-left: 4px solid var(--brown-primary);
        transition: all 0.3s;
    }

    .expense-form:hover {
        box-shadow: 0 8px 16px var(--shadow-color);
    }

    .form-group {
        margin-bottom: 20px;
        position: relative;
    }

    .form-group i {
        position: absolute;
        left: 10px;
        top: 42px;
        color: var(--brown-primary);
    }

    .input-with-icon {
        padding-left: 35px !important;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        font-weight: bold;
        text-align: center;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        font-family: 'Georgia', serif;
        border: none;
        position: relative;
        overflow: hidden;
    }

    .btn-primary {
        background-color: var(--brown-primary);
        color: var(--text-light);
        box-shadow: 0 4px 8px rgba(139, 69, 19, 0.3);
    }

    .btn-primary:hover {
        background-color: var(--brown-hover);
        transform: translateY(-3px);
        box-shadow: 0 6px 12px rgba(139, 69, 19, 0.4);
    }

    .btn-primary::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 5px;
        height: 5px;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 50%;
        opacity: 0;
        transform: scale(1);
        transition: all 0.6s;
    }

    .btn-primary:active::after {
        opacity: 1;
        transform: scale(20);
        transition: all 0s;
    }

    /* Progress bar enhancements */
    .progress-container {
        width: 100%;
        background-color: var(--nude-200);
        border-radius: 10px;
        margin: 15px 0;
        height: 20px;
        overflow: hidden;
        box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        position: relative;
    }

    .progress-bar {
        height: 100%;
        border-radius: 10px;
        background-color: #4CAF50;
        background-image: linear-gradient(45deg, 
            rgba(255,255,255,0.15) 25%, 
            transparent 25%, 
            transparent 50%, 
            rgba(255,255,255,0.15) 50%, 
            rgba(255,255,255,0.15) 75%, 
            transparent 75%, 
            transparent);
        background-size: 20px 20px;
        text-align: center;
        color: white;
        transition: width 1s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 12px;
        text-shadow: 0 1px 1px rgba(0,0,0,0.3);
        animation: progressAnimation 1s linear, progressStripes 1s linear infinite;
    }

    @keyframes progressAnimation {
        from { width: 0; }
    }

    @keyframes progressStripes {
        from { background-position: 0 0; }
        to { background-position: 20px 0; }
    }

    .progress-bar.warning {
        background-color: #FF9800;
    }

    .progress-bar.danger {
        background-color: #F44336;
    }

    /* Table enhancements */
    .table-container {
        overflow-x: auto;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #fff;
    }

    .table th, 
    .table td {
        padding: 15px;
        text-align: left;
    }

    .table th {
        background-color: var(--brown-light);
        color: var(--text-dark);
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-size: 0.85em;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .table th:first-child {
        border-top-left-radius: 10px;
    }

    .table th:last-child {
        border-top-right-radius: 10px;
    }

    .table tr {
        transition: all 0.3s;
    }

    .table tr:nth-child(even) {
        background-color: var(--nude-100);
    }

    .expense-item:hover {
        background-color: var(--nude-200);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .table tr:last-child td:first-child {
        border-bottom-left-radius: 10px;
    }

    .table tr:last-child td:last-child {
        border-bottom-right-radius: 10px;
    }

    /* Enhanced category-pill */
    .category-pill {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        color: var(--text-light);
        background-color: var(--brown-primary);
        display: inline-flex;
        align-items: center;
        gap: 5px;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        transition: all 0.3s;
    }

    .category-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }

    /* Alerts with animations */
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 10px;
        border-left-width: 4px;
        border-left-style: solid;
        font-family: 'Georgia', serif;
        position: relative;
        animation: slideDown 0.5s ease-out;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .alert-success {
        background-color: rgba(76, 175, 80, 0.1);
        color: #2E7D32;
        border-left-color: #4CAF50;
    }

    .alert-danger {
        background-color: rgba(244, 67, 54, 0.1);
        color: #C62828;
        border-left-color: #F44336;
    }

    .alert i {
        font-size: 20px;
    }

    /* Loading overlay for chart switching */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255,255,255,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 100;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s;
    }

    .loading-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(139, 69, 19, 0.1);
        border-left-color: var(--brown-primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Tooltip styles */
    .tooltip {
        position: relative;
        display: inline-block;
    }

    .tooltip .tooltip-text {
        visibility: hidden;
        width: 120px;
        background-color: var(--brown-primary);
        color: var(--text-light);
        text-align: center;
        border-radius: 6px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        margin-left: -60px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .tooltip .tooltip-text::after {
        content: "";
        position: absolute;
        top: 100%;
        left: 50%;
        margin-left: -5px;
        border-width: 5px;
        border-style: solid;
        border-color: var(--brown-primary) transparent transparent transparent;
    }

    .tooltip:hover .tooltip-text {
        visibility: visible;
        opacity: 1;
    }
    .btn-danger {
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-danger i {
    margin-right: 5px;
}
/* Add this to your existing CSS */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal.show {
    display: block;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 500px;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    background-color: #fff;
    border-radius: 0.3rem;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    border-top-left-radius: 0.3rem;
    border-top-right-radius: 0.3rem;
}

.modal-title {
    margin-bottom: 0;
    line-height: 1.5;
}

.btn-close {
    background: transparent;
    border: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #000;
    opacity: 0.5;
    cursor: pointer;
}

.btn-close:hover {
    opacity: 1;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1rem;
}

.modal-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    padding: 0.75rem;
    border-top: 1px solid #dee2e6;
    border-bottom-right-radius: 0.3rem;
    border-bottom-left-radius: 0.3rem;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1040;
    width: 100vw;
    height: 100vh;
    background-color: #000;
    opacity: 0.5;
}

.btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
    margin-right: 0.5rem;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #545b62;
}
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap');

    /* New sidebar and layout styles */
    .wrapper {
        display: flex;
        min-height: 100vh;
    }

    .sidebar {
        width: 250px;
        background: var(--brown-primary);
        color: var(--text-light);
        padding: 20px;
        position: fixed;
        height: 100vh;
        left: 0;
        top: 0;
        overflow-y: auto;
    }

    .main-content {
        flex: 1;
        margin-left: 250px;
        padding: 20px;
        background-color: var(--nude-100);
    }

    .sidebar-header {
        padding: 20px 0;
        text-align: center;
        border-bottom: 2px solid var(--brown-light);
        margin-bottom: 20px;
    }

    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-menu li {
        margin-bottom: 10px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: var(--text-light);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background: var(--brown-hover);
        transform: translateX(5px);
    }

    .sidebar-menu i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    /* Content section styles */
    .content-section {
        display: none;
        animation: fadeIn 0.5s ease;
    }

    .content-section.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Premium modal styles */
    .premium-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        animation: fadeIn 0.3s;
    }

    .premium-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .premium-modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        text-align: center;
        max-width: 400px;
        width: 90%;
    }

    .premium-icon {
        font-size: 48px;
        color: var(--accent-gold);
        margin-bottom: 20px;
    }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar"><br><br><br>
           
            <ul class="sidebar-menu">
                <li>
                    <a href="#" class="menu-item active" data-section="dashboard">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" data-section="add-expense">
                        <i class="fas fa-plus-circle"></i> Add Expense
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" data-section="recent-expenses">
                        <i class="fas fa-history"></i> Recent Expenses
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" data-section="category-breakdown">
                        <i class="fas fa-chart-pie"></i> Category Breakdown
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" data-section="spending-trends">
                        <i class="fas fa-chart-line"></i> Spending Trends
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" data-section="expense-summary">
                        <i class="fas fa-file-alt"></i> Expense Summary
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Dashboard Section -->
            <section id="dashboard" class="content-section active">
    <br><br><br>
                <!-- Stats Overview -->
                <div class="stats-card slide-in">
                    <div class="stat-item">
                        <div class="stat-icon pulse"><i class="fas fa-calendar-day"></i></div>
                        <div class="stat-value">₹<?php echo number_format($month_to_date_spending, 2); ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
                        <div class="stat-value">₹<?php echo number_format($monthly_limit ?: 0, 2); ?></div>
                        <div class="stat-label">Monthly Limit</div>
                    </div>
                    
                    <?php
                    $remaining = $monthly_limit - $month_to_date_spending;
                    $remaining = $remaining > 0 ? $remaining : 0;
                    ?>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-coins"></i></div>
                        <div class="stat-value">₹<?php echo number_format($remaining, 2); ?></div>
                        <div class="stat-label">Remaining</div>
                    </div>
                    
                    <?php
                    // Get count of expenses this month
                    $count_sql = "SELECT COUNT(*) AS count FROM p_expenses 
                                 WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?";
                    $stmt = mysqli_prepare($conn, $count_sql);
                    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $month, $year);
                    mysqli_stmt_execute($stmt);
                    $count_result = mysqli_stmt_get_result($stmt);
                    $count_row = mysqli_fetch_assoc($count_result);
                    $expense_count = $count_row['count'];
                    ?>
                    <div class="stat-item">
                        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                        <div class="stat-value"><?php echo $expense_count; ?></div>
                        <div class="stat-label">Expenses</div>
                    </div>
                </div>
                
                <div class="dashboard-grid"><br><br>
                    <!-- Monthly Progress -->
                    <div class="card bounce-in">
                        <div class="card-header">
                            <span class="badge"><?php echo date('F Y'); ?></span>
                        </div>
                        <div class="card-body">
                            <?php if($monthly_limit > 0): ?>
                                <div class="progress-container">
                                    <?php 
                                        $percent = ($month_to_date_spending / $monthly_limit) * 100;
                                        $bar_class = 'progress-bar';
                                        if($percent > 90) {
                                            $bar_class .= ' danger';
                                        } else if($percent > 75) {
                                            $bar_class .= ' warning';
                                        }
                                    ?>
                                    <div class="<?php echo $bar_class; ?>" 
                                         style="width: <?php echo min($percent, 100); ?>%">
                                        <?php echo round($percent); ?>%
                                    </div>
                                </div>
                                
                                <?php if($percent > 90): ?>
                                    <p style="color: #f44336; display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-exclamation-triangle fa-pulse"></i>
                                        You're close to your monthly limit!
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p><em>No monthly limit set</em></p>
                            <?php endif; ?>
                            
                            <!-- Set Monthly Limit Form -->
                            <div class="limit-form mt-4">
                                <h3><i class="fas fa-sliders-h"></i> Set Monthly Spending Limit</h3>
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="limit_amount">Limit Amount (₹):</label>
                                        <input type="number" step="0.01" min="0" id="limit_amount" name="limit_amount" 
                                              class="input-with-icon" value="<?php echo $monthly_limit; ?>" required>
                                    </div>
                                    <button type="submit" name="set_limit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Set
                                        </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Add Expense Section -->
            <section id="add-expense" class="content-section"><br><br><br>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle icon-hover"></i> Add Expense</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="expense-form">
                            <div class="form-group">
                                <label for="amount">Amount (Rs):</label>
                                <input type="number" step="0.01" min="0" id="amount" name="amount" 
                                      class="input-with-icon" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description:</label>
                                <input type="text" id="description" name="description" 
                                      class="input-with-icon" required>
                            </div>
                            <div class="form-group">
                                <label for="category">Category:</label>
                                <select id="category" name="category" class="input-with-icon" required>
                                    <option value="Food">Food</option>
                                    <option value="Transport">Transport</option>
                                    <option value="Entertainment">Entertainment</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="date">Date:</label>
                                <input type="date" id="date" name="date" 
                                      class="input-with-icon" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" name="add_expense" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Expense
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Recent Expenses Section -->
            <section id="recent-expenses" class="content-section"><br><br><br>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history icon-hover"></i> Recent Expenses</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($recent_expenses)): ?>
                                        <tr class="expense-item">
                                            <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                            <td><?php echo $currency_symbol; ?><?php echo number_format($row['amount'], 2); ?></td>
                                            <td>
                                                <span class="category-pill">
                                                    <i class="fas fa-tag"></i>
                                                    <?php echo $row['category']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['description']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-expense-id="<?php echo $row['expense_id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Category Breakdown Section -->
            <section id="category-breakdown" class="content-section">
            <br><br><br>                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-pie icon-hover"></i> Expense Categories</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryPieChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- Spending Trends Section -->
            <section id="spending-trends" class="content-section">
            <br><br><br>                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar icon-hover"></i> Spending Analysis</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-tabs">
                            <button class="chart-tab active" data-chart="weeklyChart">Weekly</button>
                            <button class="chart-tab" data-chart="monthlyChart">Monthly</button>
                            <button class="chart-tab" data-chart="yearlyChart">Yearly</button>
                        </div>
                        <div class="chart-container active" id="weeklyChart">
                            <canvas id="weeklyBarChart"></canvas>
                        </div>
                        <div class="chart-container" id="monthlyChart">
                            <canvas id="monthlyBarChart"></canvas>
                        </div>
                        <div class="chart-container" id="yearlyChart">
                            <canvas id="yearlyBarChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Expense Summary Section -->
            <section id="expense-summary" class="content-section">
            <br><br><br>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-search-dollar"></i> Generate Summary Report</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($role === 'premium user'): ?>
                        <form id="summaryForm" class="mb-4">
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="summary-year">Year:</label>
                                        <select id="summary-year" class="form-control">
                                            <?php 
                                            $current_year = date('Y');
                                            for($year = $current_year; $year >= $current_year - 5; $year--) {
                                                echo "<option value='$year'>$year</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="summary-month">Month:</label>
                                        <select id="summary-month" class="form-control">
                                            <?php 
                                            $months = [
                                                1 => 'January', 2 => 'February', 3 => 'March',
                                                4 => 'April', 5 => 'May', 6 => 'June',
                                                7 => 'July', 8 => 'August', 9 => 'September',
                                                10 => 'October', 11 => 'November', 12 => 'December'
                                            ];
                                            foreach($months as $num => $name) {
                                                echo "<option value='$num'>$name</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary d-block w-100">
                                            <i class="fas fa-search"></i> Generate
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        <div id="summary-results" class="mt-4">
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                            <h4>Premium Feature</h4>
                            <p>This feature is only available for premium users.</p>
                            <p>Upgrade now to access detailed expense summaries!</p>
                            <a href="upgrade.php" class="btn btn-primary">
                                <i class="fas fa-arrow-up"></i> Upgrade to Premium
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this expense?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="post" action="" style="display: inline;">
                        <input type="hidden" name="expense_id" id="expenseIdInput">
                        <button type="submit" name="delete_expense" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar Navigation
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const targetSection = this.getAttribute('data-section');
                
                // Update active states
                document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                document.querySelectorAll('.content-section').forEach(section => {
                    section.classList.remove('active');
                });
                document.getElementById(targetSection).classList.add('active');

                // Initialize charts when their sections become active
                setTimeout(() => {
                    if (targetSection === 'category-breakdown') {
                        initializePieChart();
                    } else if (targetSection === 'spending-trends') {
                        initializeSpendingCharts();
                    }
                }, 100);
            });
        });

        // Delete expense confirmation
        const deleteButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const expenseId = this.getAttribute('data-expense-id');
                document.getElementById('expenseIdInput').value = expenseId;
            });
        });

        // Chart initialization functions
        function initializePieChart() {
            const ctx = document.getElementById('categoryPieChart');
            if (!ctx) return;

            // Destroy existing chart if it exists
            if (window.categoryPieChart instanceof Chart) {
                window.categoryPieChart.destroy();
            }

            const data = <?php echo $category_pie_json; ?>;
            
            window.categoryPieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: data.colors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        function initializeSpendingCharts() {
            const weeklyData = <?php echo $weekly_json; ?>;
            const monthlyData = <?php echo $monthly_json; ?>;
            const yearlyData = <?php echo $yearly_json; ?>;

            // Initialize Weekly Chart
            const weeklyCtx = document.getElementById('weeklyBarChart');
            if (weeklyCtx) {
                if (window.weeklyChart instanceof Chart) {
                    window.weeklyChart.destroy();
                }
                window.weeklyChart = new Chart(weeklyCtx, {
                    type: 'bar',
                    data: {
                        labels: weeklyData.labels,
                        datasets: [{
                            label: 'Weekly Spending',
                            data: weeklyData.values,
                            backgroundColor: 'rgba(139, 69, 19, 0.8)',
                            borderColor: 'rgba(139, 69, 19, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Initialize Monthly Chart
            const monthlyCtx = document.getElementById('monthlyBarChart');
            if (monthlyCtx) {
                if (window.monthlyChart instanceof Chart) {
                    window.monthlyChart.destroy();
                }
                window.monthlyChart = new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyData.labels,
                        datasets: [{
                            label: 'Monthly Spending',
                            data: monthlyData.values,
                            backgroundColor: 'rgba(139, 69, 19, 0.8)',
                            borderColor: 'rgba(139, 69, 19, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Initialize Yearly Chart
            const yearlyCtx = document.getElementById('yearlyBarChart');
            if (yearlyCtx) {
                if (window.yearlyChart instanceof Chart) {
                    window.yearlyChart.destroy();
                }
                window.yearlyChart = new Chart(yearlyCtx, {
                    type: 'bar',
                    data: {
                        labels: yearlyData.labels,
                        datasets: [{
                            label: 'Yearly Spending',
                            data: yearlyData.values,
                            backgroundColor: 'rgba(139, 69, 19, 0.8)',
                            borderColor: 'rgba(139, 69, 19, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Initialize charts on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts for the active section
            const activeSection = document.querySelector('.content-section.active');
            if (activeSection) {
                if (activeSection.id === 'category-breakdown') {
                    setTimeout(initializePieChart, 300);
                } else if (activeSection.id === 'spending-trends') {
                    setTimeout(initializeSpendingCharts, 300);
                }
            }

            // Initialize delete modal functionality
            const deleteModal = document.getElementById('deleteModal');
            const deleteButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
            
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const expenseId = this.getAttribute('data-expense-id');
                    document.getElementById('expenseIdInput').value = expenseId;
                    new bootstrap.Modal(deleteModal).show();
                });
            });
        });

        // Chart tab switching
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const targetChart = this.getAttribute('data-chart');
                
                // Update active states
                document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show/hide chart containers
                document.querySelectorAll('.chart-container').forEach(container => {
                    container.classList.remove('active');
                });
                document.getElementById(targetChart).classList.add('active');

                // Reinitialize the specific chart
                if (targetChart === 'monthlyChart') {
                    initializeMonthlyChart();
                } else if (targetChart === 'yearlyChart') {
                    initializeYearlyChart();
                } else if (targetChart === 'weeklyChart') {
                    initializeWeeklyChart();
                }
            });
        });

        // Split chart initialization into separate functions
        function initializeWeeklyChart() {
            const weeklyData = <?php echo $weekly_json; ?>;
            const weeklyCtx = document.getElementById('weeklyBarChart');
            if (!weeklyCtx) return;

            if (window.weeklyChart instanceof Chart) {
                window.weeklyChart.destroy();
            }

            window.weeklyChart = new Chart(weeklyCtx, {
                type: 'bar',
                data: {
                    labels: weeklyData.labels,
                    datasets: [{
                        label: 'Weekly Spending',
                        data: weeklyData.values,
                        backgroundColor: 'rgba(139, 69, 19, 0.8)',
                        borderColor: 'rgba(139, 69, 19, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        function initializeMonthlyChart() {
            const monthlyData = <?php echo $monthly_json; ?>;
            const monthlyCtx = document.getElementById('monthlyBarChart');
            if (!monthlyCtx) return;

            if (window.monthlyChart instanceof Chart) {
                window.monthlyChart.destroy();
            }

            window.monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthlyData.labels,
                    datasets: [{
                        label: 'Monthly Spending',
                        data: monthlyData.values,
                        backgroundColor: 'rgba(139, 69, 19, 0.8)',
                        borderColor: 'rgba(139, 69, 19, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        function initializeYearlyChart() {
            const yearlyData = <?php echo $yearly_json; ?>;
            const yearlyCtx = document.getElementById('yearlyBarChart');
            if (!yearlyCtx) return;

            if (window.yearlyChart instanceof Chart) {
                window.yearlyChart.destroy();
            }

            window.yearlyChart = new Chart(yearlyCtx, {
                type: 'bar',
                data: {
                    labels: yearlyData.labels,
                    datasets: [{
                        label: 'Yearly Spending',
                        data: yearlyData.values,
                        backgroundColor: 'rgba(139, 69, 19, 0.8)',
                        borderColor: 'rgba(139, 69, 19, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Handle expense summary generation
        document.getElementById('summaryForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            <?php if ($role !== 'premium user'): ?>
            // Show premium upgrade modal for non-premium users
            const premiumModal = document.createElement('div');
            premiumModal.className = 'modal fade';
            premiumModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Premium Feature</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <i class="fas fa-crown fa-3x text-warning mb-3"></i>
                            <h4>Upgrade to Premium</h4>
                            <p>This feature is only available for premium users. Upgrade now to access detailed expense summaries!</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <a href="upgrade.php" class="btn btn-primary">Upgrade Now</a>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(premiumModal);
            new bootstrap.Modal(premiumModal).show();
            premiumModal.addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
            return;
            <?php endif; ?>

            const year = document.getElementById('summary-year').value;
            const month = document.getElementById('summary-month').value;
            const resultsDiv = document.getElementById('summary-results');
            
            // Show loading state
            resultsDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Generating summary...</p></div>';
            
            // Create form data
            const formData = new FormData();
            formData.append('year', year);
            formData.append('month', month);
            
            // Fetch summary data
            fetch('get_expense_summary.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.no_data) {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle fa-2x mb-3"></i>
                            <h4>No Data Available</h4>
                            <p>${data.message}</p>
                        </div>`;
                    return;
                }

                // Create summary HTML
                let summaryHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5><i class="fas fa-chart-pie"></i> Overview for ${data.month_year}</h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Total Expenses:</strong> ₹${data.total_amount}</p>
                                    <p><strong>Number of Transactions:</strong> ${data.transaction_count}</p>
                                    <p><strong>Average Expense:</strong> ₹${data.average_expense}</p>
                                    <p><strong>Highest Expense:</strong> ₹${data.highest_expense}</p>
                                    <p><strong>Lowest Expense:</strong> ₹${data.lowest_expense}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5><i class="fas fa-tags"></i> Category Breakdown</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="summaryPieChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-calendar-day"></i> Daily Spending Pattern</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="dailySpendingChart"></canvas>
                        </div>
                    </div>
                `;
                
                resultsDiv.innerHTML = summaryHTML;
                
                // Initialize summary charts
                if (data.categories.length > 0) {
                    new Chart(document.getElementById('summaryPieChart'), {
                        type: 'pie',
                        data: {
                            labels: data.categories.map(c => c.category),
                            datasets: [{
                                data: data.categories.map(c => c.amount),
                                backgroundColor: data.categories.map((_, i) => `hsl(${i * 360 / data.categories.length}, 70%, 50%)`)
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                
                if (data.daily_spending.length > 0) {
                    new Chart(document.getElementById('dailySpendingChart'), {
                        type: 'line',
                        data: {
                            labels: data.daily_spending.map(d => d.date),
                            datasets: [{
                                label: 'Daily Spending',
                                data: data.daily_spending.map(d => d.amount),
                                borderColor: 'rgba(139, 69, 19, 1)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: value => '₹' + value
                                    }
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="alert alert-danger">Error generating summary: ${error.message}</div>`;
            });
        });

        // Initialize spending trends on page load
        document.addEventListener('DOMContentLoaded', function() {
            const activeSection = document.querySelector('.content-section.active');
            if (activeSection && activeSection.id === 'spending-trends') {
                initializeWeeklyChart();
            }
        });
    </script>
    <!-- Add Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>