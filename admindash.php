<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include 'connect.php';
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

// Search and filtering
$searchQuery = filter_input(INPUT_POST, 'search', FILTER_SANITIZE_STRING) ?? '';
$statusFilter = filter_input(INPUT_POST, 'status_filter', FILTER_SANITIZE_STRING) ?? '';
$roleFilter = filter_input(INPUT_POST, 'role_filter', FILTER_SANITIZE_STRING) ?? '';

$whereClause = "WHERE 1=1"; // Always true condition for dynamic filters
$bindTypes = "";
$bindValues = [];

if ($searchQuery) {
    $whereClause .= " AND (username LIKE ? OR email LIKE ?)";
    $searchParam = "%$searchQuery%";
    $bindTypes .= "ss";
    $bindValues[] = $searchParam;
    $bindValues[] = $searchParam;
}

if ($statusFilter) {
    $whereClause .= " AND status = ?";
    $bindTypes .= "s";
    $bindValues[] = $statusFilter;
}

if ($roleFilter) {
    $whereClause .= " AND role = ?";
    $bindTypes .= "s";
    $bindValues[] = $roleFilter;
}

// Prepare and execute user query
$userQuery = "SELECT * FROM users $whereClause";
$stmt = $conn->prepare($userQuery);

if (!empty($bindValues)) {
    $stmt->bind_param($bindTypes, ...$bindValues);
}

$stmt->execute();
$result = $stmt->get_result();

// Handle user deletion
if (isset($_GET['delete'])) {
    $deleteId = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($deleteId) {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
        header('Location: admindash.php');
        exit();
    }
}

// Fetch user statistics
$countQueries = [
    'total' => "SELECT COUNT(*) AS user_count FROM users",
    'roles' => "SELECT 
        COUNT(CASE WHEN role = 'admin' THEN 1 END) AS admin_count,
        COUNT(CASE WHEN role = 'premium user' THEN 1 END) AS premium_count,
        COUNT(CASE WHEN role = 'user' THEN 1 END) AS standard_count
    FROM users"
];

$userStats = [];
foreach ($countQueries as $key => $query) {
    $result_count = $conn->query($query);
    $userStats[$key] = $result_count->fetch_assoc();
}

$stmt->close();

$create_activity_table = "CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50),
    description TEXT,
    user VARCHAR(100),
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_activity_table);

$users_query = "SELECT * FROM users ORDER BY user_id DESC";
$users_result = $conn->query($users_query);

// Get current page from URL
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Create diet_plans table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS diet_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snacks') NOT NULL,
    meal_description TEXT NOT NULL,
    calories INT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($createTableQuery);

// Fetch analytics data
$monthly_reg_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as count
FROM users
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY month";
$monthly_reg = $conn->query($monthly_reg_query);

$monthly_revenue_query = "SELECT 
    DATE_FORMAT(payment_date, '%Y-%m') as month,
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue
FROM payments
WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
ORDER BY month";
$monthly_revenue = $conn->query($monthly_revenue_query);

$diet_usage_query = "SELECT 
    meal_type,
    COUNT(*) as usage_count
FROM diet_plans
GROUP BY meal_type";
$diet_usage = $conn->query($diet_usage_query);

$payment_stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
    SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue
FROM payments";
$payment_stats = $conn->query($payment_stats_query)->fetch_assoc();

// Fetch feedback rating statistics
$ratingStatsQuery = "SELECT 
    COUNT(*) as total_feedback,
    AVG(rating) as average_rating,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star
FROM feedback";
$ratingStatsResult = $conn->query($ratingStatsQuery);
$ratingStats = $ratingStatsResult->fetch_assoc();

// Handle diet plan form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_meal'])) {
        $meal_type = $conn->real_escape_string($_POST['meal_type']);
        $meal_description = $conn->real_escape_string($_POST['meal_description']);
        $calories = intval($_POST['calories']);

        $query = "INSERT INTO diet_plans (meal_type, meal_description, calories) 
                 VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $meal_type, $meal_description, $calories);
        
        if ($stmt->execute()) {
            $success_message = "Meal plan added successfully!";
        } else {
            $error_message = "Error adding meal plan: " . $conn->error;
        }
    }

    if (isset($_POST['delete_meal'])) {
        $meal_id = intval($_POST['meal_id']);
        $query = "DELETE FROM diet_plans WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $meal_id);
        
        if ($stmt->execute()) {
            $success_message = "Meal plan deleted successfully!";
        } else {
            $error_message = "Error deleting meal plan: " . $conn->error;
        }
    }

    // Handle meal plan editing
    if (isset($_POST['edit_meal'])) {
        $meal_id = intval($_POST['meal_id']);
        $meal_type = $conn->real_escape_string($_POST['meal_type']);
        $meal_description = $conn->real_escape_string($_POST['meal_description']);
        $calories = intval($_POST['calories']);

        $query = "UPDATE diet_plans SET meal_type = ?, meal_description = ?, calories = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssii", $meal_type, $meal_description, $calories, $meal_id);
        
        if ($stmt->execute()) {
            $success_message = "Meal plan updated successfully!";
        } else {
            $error_message = "Error updating meal plan: " . $conn->error;
        }
    }
}

// Fetch existing meal plans
$diet_plans_query = "SELECT * FROM diet_plans ORDER BY meal_type, created_at DESC";
$diet_plans_result = $conn->query($diet_plans_query);

$user_id = $_SESSION['user_id'];

// Fetch user's currency preference
$currency_query = "SELECT currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $currency_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$currency_result = mysqli_stmt_get_result($stmt);
$currency_pref = mysqli_fetch_assoc($currency_result);
$currency_symbol = $currency_pref['currency_symbol'] ?? '₹'; // Default to ₹ if no preference set
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeSync Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #DEB887;
            --accent-color: #A0522D;
            --bg-color: #FAEBD7;
            --card-bg: #FFFFFF;
            --text-primary: #8B4513;
            --text-secondary: #A0522D;
            --border-color: #DEB887;
            --success-color: #556B2F;
            --danger-color: #8B0000;
            --warning-color: #CD853F;
            --info-color: #4682B4;
            --sidebar-width: 240px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background-color: var(--primary-color);
            position: fixed;
            left: 0;
            top: 0;
            padding: 1rem 0;
            display: flex;
            flex-direction: column;
            color: white;
        }

        .sidebar-header {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            color: white;
            margin-bottom: 1rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
            flex-grow: 1;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--accent-color);
        }

        .nav-link i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 0.75rem;
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        /* Main Content Styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            width: calc(100% - var(--sidebar-width));
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        .main-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-color);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .admin-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .admin-welcome {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
        }

        .btn-logout {
            background-color: transparent;
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: white;
            color: white;
        }

        .role-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .role-badge.admin {
            background-color: var(--danger-color);
            color: white;
        }

        .role-badge.premium {
            background-color: var(--warning-color);
            color: var(--text-primary);
        }

        .role-badge.user {
            background-color: var(--info-color);
            color: white;
        }

        .hamburger {
            background-color: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 4px;
            color: white;
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .hamburger:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .dashboard-container {
            padding: 2rem 0;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 5px solid var(--accent-color);
        }

        .stats-card:nth-child(1) {
            border-left-color: var(--info-color);
        }

        .stats-card:nth-child(2) {
            border-left-color: var(--danger-color);
        }

        .stats-card:nth-child(3) {
            border-left-color: var(--warning-color);
        }

        .stats-card:nth-child(4) {
            border-left-color: var(--success-color);
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stats-card-icon {
            font-size: 2.5rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.05);
        }

        .stats-card-content {
            flex-grow: 1;
        }

        .stats-card-number {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .stats-card-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .search-filters-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-form .form-group {
            margin-bottom: 0;
        }

        .filter-form label {
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: block;
        }

        .filter-input {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 10px 15px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .filter-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(44, 62, 80, 0.2);
            outline: none;
        }

        .filter-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            min-height: 45px;
        }

        .filter-btn:hover {
            background-color: var(--accent-color);
        }

        .users-table-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .users-table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
        }

        .users-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .users-table tbody tr:hover {
            background-color: rgba(44, 62, 80, 0.03);
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-table-action {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background-color: rgba(52, 152, 219, 0.1);
            color: var(--info-color);
        }

        .btn-edit:hover {
            background-color: var(--info-color);
            color: white;
        }

        .btn-delete {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: var(--danger-color);
            color: white;
        }

        .badge {
            padding: 6px 10px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
        }

        .badge-active {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .badge-inactive {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .offcanvas {
            background-color: var(--secondary-color);
            color: white;
            width: 280px;
        }

        .offcanvas-header {
            background-color: var(--primary-color);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
        }

        .offcanvas-title {
            font-weight: 600;
            color: white;
        }

        .btn-close {
            filter: invert(1) brightness(5);
        }

        .offcanvas-body {
            padding: 1.5rem 0;
        }

        .admin-menu-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .admin-menu-item {
            padding: 0;
            margin-bottom: 5px;
        }

        .admin-menu-link {
            display: flex;
            align-items: center;
            padding: 12px 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .admin-menu-link:hover, 
        .admin-menu-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--accent-color);
        }

        .admin-menu-icon {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .footer {
            background-color: var(--primary-color);
            padding: 1.5rem 0;
            text-align: center;
            color: white;
            font-size: 0.9rem;
            margin-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

        .dashboard-section {
            margin-bottom: 2rem;
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .system-health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .health-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color);
        }

        .health-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .health-card-header i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .health-card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-primary);
        }

        .health-metric {
            margin-bottom: 1rem;
        }

        .health-metric:last-child {
            margin-bottom: 0;
        }

        .metric-label {
            display: block;
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .metric-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .progress {
            height: 8px;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .activities-card {
            background-color: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }

        .activities-list {
            padding: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .activity-icon.login { background-color: var(--info-color); }
        .activity-icon.update { background-color: var(--success-color); }
        .activity-icon.delete { background-color: var(--danger-color); }
        .activity-icon.create { background-color: var(--warning-color); }

        .activity-content {
            flex-grow: 1;
        }

        .activity-text {
            margin: 0;
            color: var(--text-primary);
        }

        .activity-time {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .no-activities {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .quick-action-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.5rem;
            background-color: var(--card-bg);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border-color: var(--accent-color);
        }

        .quick-action-card i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .quick-action-card span {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .welcome-header {
            background-color: var(--card-bg);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .welcome-text {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            padding: 1rem;
            min-width: 200px;
        }

        .stats-card-icon {
            font-size: 2rem;
            width: 50px;
            height: 50px;
        }

        .stats-card-number {
            font-size: 1.5rem;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .meal-type-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }

        .meal-type-badge.breakfast { 
            background-color: rgba(255, 183, 77, 0.2);
            color: #F57C00;
        }
        .meal-type-badge.lunch { 
            background-color: rgba(129, 199, 132, 0.2);
            color: #388E3C;
        }
        .meal-type-badge.dinner { 
            background-color: rgba(121, 134, 203, 0.2);
            color: #3949AB;
        }
        .meal-type-badge.snacks { 
            background-color: rgba(240, 98, 146, 0.2);
            color: #D81B60;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }

        .btn-primary:focus {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(139, 69, 19, 0.25);
        }

        .btn-primary:active {
            background-color: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: #C8A27C;
            border-color: #C8A27C;
            color: var(--primary-color);
        }

        .btn-secondary:focus {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(222, 184, 135, 0.25);
        }

        .btn-secondary:active {
            background-color: #C8A27C !important;
            border-color: #C8A27C !important;
            color: var(--primary-color) !important;
        }

        .feedback-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
        }

        .feedback-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feedback-header h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .feedback-date {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .feedback-content {
            padding: 1.25rem;
        }

        .feedback-message {
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .feedback-rating i {
            color: #FFD700;
        }

        @media (max-width: 768px) {
            .col-md-4 {
                margin-bottom: 1rem;
            }
        }

        /* Payment Section Styles */
        .payment-filters {
            display: flex;
            gap: 1rem;
        }

        .payment-filters .form-select {
            min-width: 150px;
            border-color: var(--border-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
        }

        .payment-filters .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(139, 69, 19, 0.25);
        }

        .payment-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .detail-value {
            color: var(--text-primary);
            font-weight: 600;
        }

        .badge-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(39, 174, 96, 0.2);
        }

        .badge-warning {
            background-color: rgba(255, 152, 0, 0.1);
            color: #f57c00;
            border: 1px solid rgba(255, 152, 0, 0.2);
        }

        .badge-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: #d32f2f;
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        .btn-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: #2e7d32;
        }

        .btn-success:hover {
            background-color: #2e7d32;
            color: white;
        }

        /* Analytics Section Styles */
        .chart-grid {
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        /* Reports Section Styles */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .btn-download, .btn-export {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-download {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-export {
            background-color: #1f7244;
            color: white;
        }

        .btn-download:hover {
            background-color: var(--accent-color);
        }

        .btn-export:hover {
            background-color: #155724;
        }

        @media print {
            .sidebar, .welcome-header, .search-filters-card, .action-buttons {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .dashboard-section {
                box-shadow: none !important;
            }
        }
        .chart-box {
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .chart-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        /* New styles for feedback rating statistics */
        .feedback-rating i {
            margin-right: 2px;
        }
        .progress-bar.bg-warning {
            background-color: #FFD700 !important;
        }
        .stats-card-icon.text-warning {
            color: #FFD700 !important;
        }
        .health-card-header .text-warning {
            color: #FFD700 !important;
        }
        .rating-distribution {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        .star-rating {
            display: flex;
            align-items: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <span>LIFE-SYNC</span>
            </a>
        </div>
        
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="?page=dashboard" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=users" class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=diet_plans" class="nav-link <?php echo $current_page === 'diet_plans' ? 'active' : ''; ?>">
                    <i class="fas fa-utensils"></i>
                    <span>Manage Diet Plans</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=feedback" class="nav-link <?php echo $current_page === 'feedback' ? 'active' : ''; ?>">
                    <i class="fas fa-comment"></i>
                    <span>View Feedbacks</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=analytics" class="nav-link <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=payments" class="nav-link <?php echo $current_page === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment Details</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?page=reports" class="nav-link <?php echo $current_page === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1 class="welcome-text">Welcome, Admin</h1>
        </div>

        <!-- Dashboard Section -->
        <section class="section <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" id="dashboard">
            <h1 class="section-title">Dashboard Overview</h1>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-card-icon text-info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php echo $userStats['total']['user_count']; ?></div>
                        <div class="stats-card-title">Total Users</div>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-icon text-danger">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php echo $userStats['roles']['admin_count']; ?></div>
                        <div class="stats-card-title">Admin Users</div>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-icon text-warning">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php echo $userStats['roles']['premium_count']; ?></div>
                        <div class="stats-card-title">Premium Users</div>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-icon text-success">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php echo $userStats['roles']['standard_count']; ?></div>
                        <div class="stats-card-title">Standard Users</div>
                    </div>
                </div>
            </div>

            <!-- System Health Section -->
            <div class="dashboard-section">
                <h2 class="section-title">System Health</h2>
                <div class="system-health-grid">
                    <div class="health-card">
                        <div class="health-card-header">
                            <i class="fas fa-server text-primary"></i>
                            <h3>Server Status</h3>
                        </div>
                        <div class="health-card-body">
                            <div class="health-metric">
                                <span class="metric-label">CPU Usage</span>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: 45%">45%</div>
                                </div>
                            </div>
                            <div class="health-metric">
                                <span class="metric-label">Memory Usage</span>
                                <div class="progress">
                                    <div class="progress-bar bg-warning" style="width: 60%">60%</div>
                                </div>
                            </div>
                            <div class="health-metric">
                                <span class="metric-label">Disk Space</span>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 75%">75%</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="health-card">
                        <div class="health-card-header">
                            <i class="fas fa-database text-success"></i>
                            <h3>Database Status</h3>
                        </div>
                        <div class="health-card-body">
                            <div class="health-metric">
                                <span class="metric-label">Active Connections</span>
                                <span class="metric-value">12</span>
                            </div>
                            <div class="health-metric">
                                <span class="metric-label">Query Performance</span>
                                <span class="metric-value">98%</span>
                            </div>
                            <div class="health-metric">
                                <span class="metric-label">Backup Status</span>
                                <span class="metric-value text-success">Last: 2h ago</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Section -->
            <div class="dashboard-section">
                <h2 class="section-title">Recent Activities</h2>
                <div class="activities-card">
                    <div class="activities-list">
                        <?php
                        // Fetch recent activities
                        $activities_query = "SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT 5";
                        $activities_result = $conn->query($activities_query);
                        
                        if ($activities_result && $activities_result->num_rows > 0) {
                            while ($activity = $activities_result->fetch_assoc()) {
                                echo '<div class="activity-item">';
                                echo '<div class="activity-icon ' . $activity['type'] . '">';
                                echo '<i class="fas ' . getActivityIcon($activity['type']) . '"></i>';
                                echo '</div>';
                                echo '<div class="activity-content">';
                                echo '<p class="activity-text">' . htmlspecialchars($activity['description']) . '</p>';
                                echo '<span class="activity-time">' . formatTimeAgo($activity['timestamp']) . '</span>';
                                echo '</div>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="no-activities">No recent activities</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- User Management Section -->
        <section class="section <?php echo $current_page === 'users' ? 'active' : ''; ?>" id="users">
            <h1 class="section-title">User Management</h1>

            <!-- Search & Filter Form -->
            <div class="search-filters-card">
                <form method="POST" class="filter-form">
                    <div class="form-group">
                        <label for="search">Search Users</label>
                        <input type="text" id="search" name="search" class="filter-input" placeholder="Username or email..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role_filter">User Role</label>
                        <select id="role_filter" name="role_filter" class="filter-input">
                            <option value="">All Roles</option>
                            <option value="admin" <?php if (isset($_POST['role_filter']) && $_POST['role_filter'] == 'admin') echo 'selected'; ?>>Admin</option>
                            <option value="premium user" <?php if (isset($_POST['role_filter']) && $_POST['role_filter'] == 'premium user') echo 'selected'; ?>>Premium User</option>
                            <option value="user" <?php if (isset($_POST['role_filter']) && $_POST['role_filter'] == 'user') echo 'selected'; ?>>Standard User</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_filter">Status</label>
                        <select id="status_filter" name="status_filter" class="filter-input">
                            <option value="">All Status</option>
                            <option value="active" <?php if (isset($_POST['status_filter']) && $_POST['status_filter'] == 'active') echo 'selected'; ?>>Active</option>
                            <option value="inactive" <?php if (isset($_POST['status_filter']) && $_POST['status_filter'] == 'inactive') echo 'selected'; ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search me-2"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </div>

            <!-- User Table -->
            <div class="users-table-card">
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td>
                                            <?php
                                            switch($row['role']) {
                                                case 'admin':
                                                    echo '<span class="role-badge admin"><i class="fas fa-user-shield"></i> Admin</span>';
                                                    break;
                                                case 'premium user':
                                                    echo '<span class="role-badge premium"><i class="fas fa-crown"></i> Premium</span>';
                                                    break;
                                                default:
                                                    echo '<span class="role-badge user"><i class="fas fa-user"></i> User</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] == 'active'): ?>
                                                <span class="badge badge-active"><i class="fas fa-check-circle me-1"></i> Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive"><i class="fas fa-times-circle me-1"></i> Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="updaterole.php?id=<?php echo $row['user_id']; ?>" class="btn-table-action btn-edit" title="Edit User">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="admindash.php?delete=<?php echo $row['user_id']; ?>" class="btn-table-action btn-delete" title="Delete User" onclick="return confirm('Are you sure you want to delete this user?');">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="fas fa-users-slash"></i>
                                            </div>
                                            <p>No users found. Try adjusting your search filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Diet Plans Section -->
        <section class="section <?php echo $current_page === 'diet_plans' ? 'active' : ''; ?>" id="diet_plans">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="section-title mb-0">Manage Diet Plans</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMealModal">
                    <i class="fas fa-plus"></i> Add New Meal
                </button>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Existing Meal Plans -->
            <div class="dashboard-section">
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Meal Type</th>
                                <th>Description</th>
                                <th>Calories</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($diet_plans_result && $diet_plans_result->num_rows > 0): ?>
                                <?php while ($row = $diet_plans_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="badge meal-type-badge <?php echo $row['meal_type']; ?>">
                                                <?php echo ucfirst($row['meal_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['meal_description']); ?></td>
                                        <td><?php echo $row['calories']; ?> cal</td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" class="btn-table-action btn-edit" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editMealModal<?php echo $row['id']; ?>"
                                                        title="Edit Meal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="meal_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="delete_meal" class="btn-table-action btn-delete" 
                                                            onclick="return confirm('Are you sure you want to delete this meal plan?');"
                                                            title="Delete Meal">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Edit Meal Modal for each row -->
                                    <div class="modal fade" id="editMealModal<?php echo $row['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Meal Plan</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="meal_id" value="<?php echo $row['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Meal Type</label>
                                                            <select name="meal_type" class="form-select" required>
                                                                <option value="breakfast" <?php echo $row['meal_type'] === 'breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                                                                <option value="lunch" <?php echo $row['meal_type'] === 'lunch' ? 'selected' : ''; ?>>Lunch</option>
                                                                <option value="dinner" <?php echo $row['meal_type'] === 'dinner' ? 'selected' : ''; ?>>Dinner</option>
                                                                <option value="snacks" <?php echo $row['meal_type'] === 'snacks' ? 'selected' : ''; ?>>Snacks</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Description</label>
                                                            <input type="text" name="meal_description" class="form-control" 
                                                                   value="<?php echo htmlspecialchars($row['meal_description']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Calories</label>
                                                            <input type="number" name="calories" class="form-control" 
                                                                   value="<?php echo $row['calories']; ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="edit_meal" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="fas fa-utensils"></i>
                                            </div>
                                            <p>No meal plans found. Add your first meal plan using the button above.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Feedback Section -->
        <section class="section <?php echo $current_page === 'feedback' ? 'active' : ''; ?>" id="feedback">
            <h1 class="section-title">User Feedback</h1>

            <!-- Rating Statistics Card -->
            <div class="dashboard-section mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-card-icon text-warning">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stats-card-content">
                                <div class="stats-card-number"><?php echo number_format($ratingStats['average_rating'], 1); ?>/5</div>
                                <div class="stats-card-title">Average Rating</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="health-card">
                            <div class="health-card-header">
                                <i class="fas fa-star-half-alt text-warning"></i>
                                <h3>Rating Distribution</h3>
                            </div>
                            <div class="health-card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <?php 
                                        $total = $ratingStats['total_feedback'];
                                        $stars = [
                                            '5' => $ratingStats['five_star'],
                                            '4' => $ratingStats['four_star'],
                                            '3' => $ratingStats['three_star'],
                                            '2' => $ratingStats['two_star'],
                                            '1' => $ratingStats['one_star']
                                        ];
                                        
                                        foreach ($stars as $rating => $count): 
                                            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
                                        ?>
                                        <div class="health-metric">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span class="metric-label">
                                                    <?php echo str_repeat('<i class="fas fa-star text-warning"></i>', $rating); ?>
                                                    <?php if ($rating < 5) echo str_repeat('<i class="far fa-star text-warning"></i>', 5 - $rating); ?>
                                                </span>
                                                <span class="metric-value"><?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Feedback Cards -->
            <?php
            // Fetch feedback data
            $feedbackQuery = "SELECT f.*, u.username 
                            FROM feedback f 
                            LEFT JOIN users u ON f.user_id = u.user_id 
                            ORDER BY f.created_at DESC";
            $feedback_result = $conn->query($feedbackQuery);
            ?>

            <?php if ($feedback_result && $feedback_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($feedback = $feedback_result->fetch_assoc()): ?>
                        <div class="col-md-4 mb-4">
                            <div class="feedback-card">
                                <div class="feedback-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-comment me-2"></i>
                                        <?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?>
                                    </h5>
                                    <span class="feedback-date">
                                        <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="feedback-content">
                                    <p class="feedback-message"><?php echo htmlspecialchars($feedback['message']); ?></p>
                                    <div class="feedback-rating">
                                        <?php echo str_repeat('<i class="fas fa-star text-warning"></i>', $feedback['rating']); ?>
                                        <?php echo str_repeat('<i class="far fa-star text-warning"></i>', 5 - $feedback['rating']); ?>
                                        <span>(<?php echo $feedback['rating']; ?>/5)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <p>No feedback submissions yet.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Payment Details Section -->
        <section class="section <?php echo $current_page === 'payments' ? 'active' : ''; ?>" id="payments">
            <h1 class="section-title">Payment Details</h1>

            <!-- Payment Statistics -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-card-icon text-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number">₹<?php 
                            $total_query = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'success'";
                            $total_result = $conn->query($total_query);
                            echo number_format($total_result->fetch_assoc()['total'], 2);
                        ?></div>
                        <div class="stats-card-title">Total Revenue</div>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php 
                            $premium_query = "SELECT COUNT(DISTINCT user_id) as count FROM payments WHERE status = 'success'";
                            $premium_result = $conn->query($premium_query);
                            echo $premium_result->fetch_assoc()['count'];
                        ?></div>
                        <div class="stats-card-title">Premium Users</div>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-card-icon text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-card-content">
                        <div class="stats-card-number"><?php 
                            $failed_query = "SELECT COUNT(*) as count FROM payments WHERE status = 'failed'";
                            $failed_result = $conn->query($failed_query);
                            echo $failed_result->fetch_assoc()['count'];
                        ?></div>
                        <div class="stats-card-title">Failed Payments</div>
                    </div>
                </div>
            </div>

            <!-- Payment Table -->
            <div class="dashboard-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0">Recent Transactions</h2>
                    <div class="payment-filters">
                        <select class="form-select" id="paymentStatusFilter">
                            <option value="">All Status</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $payments_query = "SELECT p.*, u.username 
                                            FROM payments p 
                                            LEFT JOIN users u ON p.user_id = u.user_id 
                                            ORDER BY p.payment_date DESC 
                                            LIMIT 10";
                            $payments_result = $conn->query($payments_query);

                            if ($payments_result && $payments_result->num_rows > 0):
                                while ($payment = $payments_result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td>#<?php echo str_pad($payment['id'], 8, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($payment['username']); ?></td>
                                    <td><?php echo $currency_symbol; ?><?php echo number_format($payment['amount'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        switch($payment['status']) {
                                            case 'success':
                                                $status_class = 'badge-success';
                                                break;
                                            case 'failed':
                                                $status_class = 'badge-danger';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button type="button" class="btn-table-action btn-edit" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewPaymentModal<?php echo $payment['id']; ?>"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- View Payment Modal -->
                                <div class="modal fade" id="viewPaymentModal<?php echo $payment['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Payment Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="payment-details">
                                                    <div class="detail-row">
                                                        <span class="detail-label">Transaction ID:</span>
                                                        <span class="detail-value">#<?php echo str_pad($payment['id'], 8, '0', STR_PAD_LEFT); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Payment ID:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($payment['payment_id']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Order ID:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($payment['order_id']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">User:</span>
                                                        <span class="detail-value"><?php echo htmlspecialchars($payment['username']); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Amount:</span>
                                                        <span class="detail-value"><?php echo $currency_symbol; ?><?php echo number_format($payment['amount'], 2); ?></span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Status:</span>
                                                        <span class="detail-value">
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($payment['status']); ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Date:</span>
                                                        <span class="detail-value"><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="fas fa-credit-card"></i>
                                            </div>
                                            <p>No payment records found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Reports Section -->
        <section class="section <?php echo $current_page === 'reports' ? 'active' : ''; ?>" id="reports">
            <h1 class="section-title">Reports</h1>

            <!-- Report Filters -->
            <div class="search-filters-card">
                <form method="POST" class="filter-form">
                    <div class="form-group">
                        <label for="report_type">Report Type</label>
                        <select name="report_type" id="report_type" class="filter-input" required>
                            <option value="">Select Report Type</option>
                            <option value="user_details">User Management Details</option>
                            <option value="payment_details">Payment Transaction Details</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="filter-input" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="filter-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="generate_report" class="filter-btn">
                            <i class="fas fa-file-alt me-2"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>

            <!-- Report Results -->
            <div class="dashboard-section">
                <?php if (isset($_POST['generate_report'])): ?>
                    <?php
                    $report_type = $_POST['report_type'];
                    $start_date = $_POST['start_date'];
                    $end_date = $_POST['end_date'];
                    $report_data = [];

                    switch($report_type) {
                        case 'user_details':
                            $query = "SELECT 
                                     user_id,
                                     username,
                                     email,
                                     role,
                                     status,
                                     created_at
                                     FROM users 
                                     WHERE created_at BETWEEN ? AND ?
                                     ORDER BY created_at DESC";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $start_date, $end_date);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $report_data[] = $row;
                            }
                            break;

                        case 'payment_details':
                            $query = "SELECT 
                                     p.id,
                                     p.payment_id,
                                     p.order_id,
                                     u.username,
                                     p.amount,
                                     p.status,
                                     p.payment_date
                                     FROM payments p 
                                     LEFT JOIN users u ON p.user_id = u.user_id 
                                     WHERE p.payment_date BETWEEN ? AND ?
                                     ORDER BY p.payment_date DESC";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ss", $start_date, $end_date);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            while ($row = $result->fetch_assoc()) {
                                $report_data[] = $row;
                            }
                            break;
                    }

                    if (!empty($report_data) && count($report_data) > 0): ?>
                        <div class="action-buttons mb-3">
                            <button class="btn btn-download" onclick="window.print()">
                                <i class="fas fa-file-pdf me-2"></i>Download PDF
                            </button>
                            <button class="btn btn-export" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Export to Excel
                            </button>
                        </div>

                        <?php if ($report_type === 'user_details'): ?>
                            <h2 class="section-title mb-4">User Management Report</h2>
                            <div class="table-responsive">
                                <table class="users-table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td>
                                                    <?php
                                                    switch($row['role']) {
                                                        case 'admin':
                                                            echo '<span class="role-badge admin"><i class="fas fa-user-shield"></i> Admin</span>';
                                                            break;
                                                        case 'premium user':
                                                            echo '<span class="role-badge premium"><i class="fas fa-crown"></i> Premium</span>';
                                                            break;
                                                        default:
                                                            echo '<span class="role-badge user"><i class="fas fa-user"></i> User</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] == 'active'): ?>
                                                        <span class="badge badge-active"><i class="fas fa-check-circle me-1"></i> Active</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-inactive"><i class="fas fa-times-circle me-1"></i> Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <h2 class="section-title mb-4">Payment Transaction Report</h2>
                            <div class="table-responsive">
                                <table class="users-table" id="reportTable">
                                    <thead>
                                        <tr>
                                            <th>Transaction ID</th>
                                            <th>User</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data as $row): ?>
                                            <tr>
                                                <td>#<?php echo str_pad($row['id'], 8, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo $currency_symbol; ?><?php echo number_format($row['amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch($row['status']) {
                                                        case 'success':
                                                            $status_class = 'badge-success';
                                                            break;
                                                        case 'failed':
                                                            $status_class = 'badge-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <p>No data found for the selected criteria. Please try a different date range or report type.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <p>Select report type and date range to generate a report.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Analytics Section -->
        <section class="section <?php echo $current_page === 'analytics' ? 'active' : ''; ?>" id="analytics">
            <h1 class="section-title">Analytics Dashboard</h1>

            <!-- Charts Grid - 4 equal boxes -->
            <div class="row g-4">
                <!-- Box 1: Monthly User Registrations -->
                <div class="col-md-6 col-lg-3">
                    <div class="chart-box bg-white p-3 rounded shadow-sm h-100">
                        <h5 class="chart-title mb-3 text-center">User Registrations</h5>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="userRegChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Box 2: Monthly Revenue -->
                <div class="col-md-6 col-lg-3">
                    <div class="chart-box bg-white p-3 rounded shadow-sm h-100">
                        <h5 class="chart-title mb-3 text-center">Monthly Revenue</h5>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Box 3: Diet Plan Usage -->
                <div class="col-md-6 col-lg-3">
                    <div class="chart-box bg-white p-3 rounded shadow-sm h-100">
                        <h5 class="chart-title mb-3 text-center">Diet Plans</h5>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="dietUsageChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Box 4: Payment Statistics -->
                <div class="col-md-6 col-lg-3">
                    <div class="chart-box bg-white p-3 rounded shadow-sm h-100">
                        <h5 class="chart-title mb-3 text-center">Payments</h5>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="paymentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add Meal Modal -->
        <div class="modal fade" id="addMealModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Meal Plan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Meal Type</label>
                                <select name="meal_type" class="form-select" required>
                                    <option value="breakfast">Breakfast</option>
                                    <option value="lunch">Lunch</option>
                                    <option value="dinner">Dinner</option>
                                    <option value="snacks">Snacks</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="meal_description" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Calories</label>
                                <input type="number" name="calories" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_meal" class="btn btn-primary">Add Meal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
            
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Payment Status Filter
    document.getElementById('paymentStatusFilter').addEventListener('change', function() {
        const status = this.value;
        const rows = document.querySelectorAll('#payments table tbody tr');
        
        rows.forEach(row => {
            if (!status) {
                row.style.display = '';
            } else {
                const statusCell = row.querySelector('td:nth-child(4) .badge');
                if (statusCell) {
                    row.style.display = statusCell.textContent.toLowerCase() === status ? '' : 'none';
                }
            }
        });
    });

    // Analytics Charts with compact styling
    <?php if ($current_page === 'analytics'): ?>
    // Monthly User Registrations Chart
    const userRegCtx = document.getElementById('userRegChart').getContext('2d');
    new Chart(userRegCtx, {
        type: 'line',
        data: {
            labels: <?php 
                $labels = [];
                $data = [];
                $monthly_reg->data_seek(0);
                while($row = $monthly_reg->fetch_assoc()) {
                    $labels[] = date('M Y', strtotime($row['month']));
                    $data[] = $row['count'];
                }
                echo json_encode($labels);
            ?>,
            datasets: [{
                label: 'Users',
                data: <?php echo json_encode($data); ?>,
                borderColor: '#8B4513',
                backgroundColor: 'rgba(139, 69, 19, 0.1)',
                tension: 0.3,
                borderWidth: 1.5,
                pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 8,
                        font: {
                            size: 10
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                },
                y: {
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });

    // Monthly Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php 
                $labels = [];
                $data = [];
                $monthly_revenue->data_seek(0);
                while($row = $monthly_revenue->fetch_assoc()) {
                    $labels[] = date('M Y', strtotime($row['month']));
                    $data[] = $row['revenue'];
                }
                echo json_encode($labels);
            ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?php echo json_encode($data); ?>,
                backgroundColor: 'rgba(139, 69, 19, 0.7)',
                borderColor: '#8B4513',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 8,
                        font: {
                            size: 10
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                },
                y: {
                    ticks: {
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });

    // Diet Plan Usage Chart
    const dietUsageCtx = document.getElementById('dietUsageChart').getContext('2d');
    new Chart(dietUsageCtx, {
        type: 'doughnut',
        data: {
            labels: <?php 
                $labels = [];
                $data = [];
                $diet_usage->data_seek(0);
                while($row = $diet_usage->fetch_assoc()) {
                    $labels[] = ucfirst($row['meal_type']);
                    $data[] = $row['usage_count'];
                }
                echo json_encode($labels);
            ?>,
            datasets: [{
                data: <?php echo json_encode($data); ?>,
                backgroundColor: [
                    '#8B4513',
                    '#DEB887',
                    '#A0522D',
                    '#CD853F'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });

    // Payment Statistics Chart
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'pie',
        data: {
            labels: ['Successful', 'Failed'],
            datasets: [{
                data: [
                    <?php echo $payment_stats['successful_payments']; ?>,
                    <?php echo $payment_stats['failed_payments']; ?>
                ],
                backgroundColor: [
                    'rgba(85, 107, 47, 0.7)',
                    'rgba(139, 0, 0, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 10,
                        padding: 8,
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Excel Export Function
    function exportToExcel() {
        const table = document.getElementById('reportTable');
        if (!table) return;

        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (const row of rows) {
            const cols = row.querySelectorAll('td,th');
            const rowData = Array.from(cols).map(col => {
                let text = col.innerText;
                // Escape quotes and wrap in quotes if contains comma
                if (text.includes(',') || text.includes('"')) {
                    text = '"' + text.replace(/"/g, '""') + '"';
                }
                return text;
            });
            csv.push(rowData.join(','));
        }

        const csvContent = csv.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'report.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    </script>
</body>
</html>

<?php
// Helper functions
function getActivityIcon($type) {
    switch ($type) {
        case 'login':
            return 'fa-sign-in-alt';
        case 'update':
            return 'fa-edit';
        case 'delete':
            return 'fa-trash-alt';
        case 'create':
            return 'fa-plus-circle';
        default:
            return 'fa-info-circle';
    }
}

function formatTimeAgo($timestamp) {
    $datetime = new DateTime($timestamp);
    $now = new DateTime();
    $interval = $now->diff($datetime);
    
    if ($interval->y > 0) return $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
    if ($interval->m > 0) return $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
    if ($interval->d > 0) return $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
    if ($interval->h > 0) return $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
    if ($interval->i > 0) return $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

$conn->close();
?>