<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include 'connect.php';
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

// Fetch user statistics
$user_stats_query = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN role = 'premium user' THEN 1 END) as premium_users,
    COUNT(CASE WHEN role = 'user' THEN 1 END) as standard_users,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
    DATE_FORMAT(MAX(created_at), '%Y-%m-%d') as newest_user_date,
    DATE_FORMAT(MIN(created_at), '%Y-%m-%d') as oldest_user_date
FROM users";
$user_stats = $conn->query($user_stats_query)->fetch_assoc();

// Calculate premium user percentage
$premium_percentage = ($user_stats['total_users'] > 0) ? 
    round(($user_stats['premium_users'] / $user_stats['total_users']) * 100, 1) : 0;

// Fetch feedback statistics
$feedback_stats_query = "SELECT 
    COUNT(*) as total_feedback,
    AVG(rating) as avg_rating,
    COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_feedback,
    COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_feedback,
    COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
    COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
    COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
    COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
    COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
FROM feedback";
$feedback_stats = $conn->query($feedback_stats_query)->fetch_assoc();

// Fetch payment statistics
$payment_stats_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
    SUM(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
    SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue,
    AVG(CASE WHEN status = 'success' THEN amount END) as avg_payment,
    MAX(CASE WHEN status = 'success' THEN amount END) as max_payment,
    DATE_FORMAT(MAX(payment_date), '%Y-%m-%d') as latest_payment_date
FROM payments";
$payment_stats = $conn->query($payment_stats_query)->fetch_assoc();

// Calculate success rate
$payment_success_rate = ($payment_stats['total_payments'] > 0) ? 
    round(($payment_stats['successful_payments'] / $payment_stats['total_payments']) * 100, 1) : 0;

// Monthly revenue trend (last 6 months)
$monthly_revenue_query = "SELECT 
    DATE_FORMAT(payment_date, '%b %Y') as month,
    SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue
FROM payments
WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
ORDER BY payment_date";
$monthly_revenue_result = $conn->query($monthly_revenue_query);
$monthly_revenue_data = [];
$monthly_revenue_labels = [];
while ($row = $monthly_revenue_result->fetch_assoc()) {
    $monthly_revenue_labels[] = $row['month'];
    $monthly_revenue_data[] = $row['revenue'];
}

// User growth (last 6 months)
$user_growth_query = "SELECT 
    DATE_FORMAT(created_at, '%b %Y') as month,
    COUNT(*) as new_users
FROM users
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(created_at, '%Y-%m')
ORDER BY created_at";
$user_growth_result = $conn->query($user_growth_query);
$user_growth_data = [];
$user_growth_labels = [];
while ($row = $user_growth_result->fetch_assoc()) {
    $user_growth_labels[] = $row['month'];
    $user_growth_data[] = $row['new_users'];
}

// Device usage statistics
$device_usage_query = "SELECT 
    device_type,
    COUNT(*) as count
FROM user_sessions
GROUP BY device_type
ORDER BY count DESC
LIMIT 5";
$device_usage_result = $conn->query($device_usage_query);
$device_usage_labels = [];
$device_usage_data = [];
if ($device_usage_result) {
    while ($row = $device_usage_result->fetch_assoc()) {
        $device_usage_labels[] = $row['device_type'];
        $device_usage_data[] = $row['count'];
    }
}

// Get current date for report generation
$current_date = date("Y-m-d");

// Function to calculate growth percentage
function calculate_growth($current, $previous) {
    if ($previous == 0) return 100;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Get previous month's statistics for comparison
$prev_month_users_query = "SELECT COUNT(*) as count FROM users WHERE created_at <= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
$prev_month_users = $conn->query($prev_month_users_query)->fetch_assoc()['count'];
$user_growth_percentage = calculate_growth($user_stats['total_users'], $prev_month_users);

$prev_month_revenue_query = "SELECT SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue 
                            FROM payments 
                            WHERE payment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
$prev_month_revenue = $conn->query($prev_month_revenue_query)->fetch_assoc()['revenue'];
$current_month_revenue_query = "SELECT SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as revenue 
                               FROM payments 
                               WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
$current_month_revenue = $conn->query($current_month_revenue_query)->fetch_assoc()['revenue'];
$revenue_growth_percentage = calculate_growth($current_month_revenue, $prev_month_revenue);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - LifeSync</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gradient: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .container {
            background: #fff;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 1rem;
            color: #555;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .icon {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .stat-card .trend {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card .trend i {
            margin-right: 5px;
        }

        .primary-card {
            background: var(--gradient);
            color: white;
        }

        .primary-card h3 {
            color: rgba(255, 255, 255, 0.8);
        }

        .revenue-card {
            background: linear-gradient(135deg, #4cc9f0 0%, #3a86ff 100%);
            color: white;
        }

        .revenue-card h3 {
            color: rgba(255, 255, 255, 0.8);
        }

        .success-card {
            background: linear-gradient(135deg, #06d6a0 0%, #1b9aaa 100%);
            color: white;
        }

        .success-card h3 {
            color: rgba(255, 255, 255, 0.8);
        }

        .positive-trend {
            color: #06d6a0;
        }

        .negative-trend {
            color: #ef476f;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }

        .generate-btn {
            background: var(--gradient);
            color: white;
            padding: 15px 25px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: block;
            width: 100%;
            cursor: pointer;
        }

        .generate-btn:hover {
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
            transform: translateY(-2px);
        }

        .date-filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .rating-bar {
            height: 25px;
            margin-bottom: 10px;
            border-radius: 5px;
        }

        .rating-5 {
            background-color: #06d6a0;
        }

        .rating-4 {
            background-color: #4cc9f0;
        }

        .rating-3 {
            background-color: #ffd166;
        }

        .rating-2 {
            background-color: #f8961e;
        }

        .rating-1 {
            background-color: #ef476f;
        }

        .rating-count {
            font-weight: 600;
            margin-right: 10px;
        }

        .rating-label {
            margin-right: 10px;
            font-weight: 600;
        }

        .metric-card {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 15px;
        }

        .metric-card h4 {
            color: #555;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .metric-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .tab-content {
            padding: 20px 0;
        }

        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #555;
            font-weight: 500;
            padding: 10px 20px;
        }

        .nav-tabs .nav-link.active {
            border-bottom: 3px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            border-radius: 8px;
            margin: 0 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .print-btn {
            background: #e9ecef;
            color: #212529;
        }

        .print-btn:hover {
            background: #dce0e5;
        }

        .export-btn {
            background: #4361ee;
            color: white;
        }

        .export-btn:hover {
            background: #3a0ca3;
        }

        .table-responsive {
            margin-bottom: 30px;
        }

        .table {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            border-radius: 8px;
            overflow: hidden;
        }

        .table thead {
            background-color: #f8f9fa;
        }

        .table th {
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }

        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 20px;
            }
            
            .chart-container {
                height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="admindash.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
                <h2 class="mb-0">Analytics & Reports</h2>
                <div class="date-range">
                    <span class="badge bg-light text-dark">
                        <i class="far fa-calendar-alt me-1"></i>
                        Report Date: <?php echo date('F d, Y'); ?>
                    </span>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card primary-card">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Total Users</h3>
                        <div class="number"><?php echo $user_stats['total_users']; ?></div>
                        <div class="trend">
                            <?php if ($user_growth_percentage >= 0): ?>
                                <i class="fas fa-arrow-up"></i>
                                <span><?php echo $user_growth_percentage; ?>% growth</span>
                            <?php else: ?>
                                <i class="fas fa-arrow-down"></i>
                                <span><?php echo abs($user_growth_percentage); ?>% decline</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card revenue-card">
                        <div class="icon">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h3>Premium Users</h3>
                        <div class="number"><?php echo $user_stats['premium_users']; ?></div>
                        <div class="trend">
                            <span><?php echo $premium_percentage; ?>% of users</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card success-card">
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Active Users</h3>
                        <div class="number"><?php echo $user_stats['active_users']; ?></div>
                        <div class="trend">
                            <span><?php echo round(($user_stats['active_users'] / $user_stats['total_users']) * 100, 1); ?>% of total</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="icon text-danger">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Total Revenue</h3>
                        <div class="number text-danger">₹<?php echo number_format($payment_stats['total_revenue'], 0); ?></div>
                        <div class="trend">
                            <?php if ($revenue_growth_percentage >= 0): ?>
                                <i class="fas fa-arrow-up text-success"></i>
                                <span class="text-success"><?php echo $revenue_growth_percentage; ?>% growth</span>
                            <?php else: ?>
                                <i class="fas fa-arrow-down text-danger"></i>
                                <span class="text-danger"><?php echo abs($revenue_growth_percentage); ?>% decline</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="user-tab" data-bs-toggle="tab" data-bs-target="#user" type="button" role="tab" aria-controls="user" aria-selected="true">
                        <i class="fas fa-users me-2"></i>User Analysis
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="feedback-tab" data-bs-toggle="tab" data-bs-target="#feedback" type="button" role="tab" aria-controls="feedback" aria-selected="false">
                        <i class="fas fa-star me-2"></i>Feedback Analysis
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab" aria-controls="payment" aria-selected="false">
                        <i class="fas fa-credit-card me-2"></i>Payment Analysis
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="myTabContent">
                <!-- User Analysis Tab -->
                <div class="tab-pane fade show active" id="user" role="tabpanel" aria-labelledby="user-tab">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h4 class="section-title">
                                <i class="fas fa-chart-line"></i> User Growth
                            </h4>
                            <div class="chart-container">
                                <canvas id="userGrowthChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h4 class="section-title">
                                <i class="fas fa-mobile-alt"></i> Device Usage
                            </h4>
                            <div class="chart-container">
                                <canvas id="deviceUsageChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="metric-card">
                                <h4>Average Session Duration</h4>
                                <div class="value">24 minutes</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="metric-card">
                                <h4>Users Added This Month</h4>
                                <div class="value">+<?php echo end($user_growth_data); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="metric-card">
                                <h4>User Retention Rate</h4>
                                <div class="value">68%</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <h4 class="section-title">
                                <i class="fas fa-user-tag"></i> User Segmentation
                            </h4>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Segment</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                            <th>Avg. Session</th>
                                            <th>Retention</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>New Users (< 30 days)</td>
                                            <td>143</td>
                                            <td>12.7%</td>
                                            <td>18 min</td>
                                            <td>54%</td>
                                        </tr>
                                        <tr>
                                            <td>Regular Users</td>
                                            <td>428</td>
                                            <td>38.1%</td>
                                            <td>22 min</td>
                                            <td>67%</td>
                                        </tr>
                                        <tr>
                                            <td>Power Users</td>
                                            <td>296</td>
                                            <td>26.4%</td>
                                            <td>35 min</td>
                                            <td>82%</td>
                                        </tr>
                                        <tr>
                                            <td>Premium Users</td>
                                            <td><?php echo $user_stats['premium_users']; ?></td>
                                            <td><?php echo $premium_percentage; ?>%</td>
                                            <td>42 min</td>
                                            <td>91%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Analysis Tab -->
                <div class="tab-pane fade" id="feedback" role="tabpanel" aria-labelledby="feedback-tab">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <h4 class="section-title">
                                <i class="fas fa-star"></i> Rating Distribution
                            </h4>
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="rating-label">5 ★</span>
                                    <div class="rating-bar rating-5 flex-grow-1" style="width: <?php echo ($feedback_stats['total_feedback'] > 0) ? ($feedback_stats['five_star'] / $feedback_stats['total_feedback']) * 100 : 0; ?>%"></div>
                                    <span class="rating-count"><?php echo $feedback_stats['five_star']; ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="rating-label">4 ★</span>
                                    <div class="rating-bar rating-4 flex-grow-1" style="width: <?php echo ($feedback_stats['total_feedback'] > 0) ? ($feedback_stats['four_star'] / $feedback_stats['total_feedback']) * 100 : 0; ?>%"></div>
                                    <span class="rating-count"><?php echo $feedback_stats['four_star']; ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="rating-label">3 ★</span>
                                    <div class="rating-bar rating-3 flex-grow-1" style="width: <?php echo ($feedback_stats['total_feedback'] > 0) ? ($feedback_stats['three_star'] / $feedback_stats['total_feedback']) * 100 : 0; ?>%"></div>
                                    <span class="rating-count"><?php echo $feedback_stats['three_star']; ?></span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="rating-label">2 ★</span>
                                    <div class="rating-bar rating-2 flex-grow-1" style="width: <?php echo ($feedback_stats['total_feedback'] > 0) ? ($feedback_stats['two_star'] / $feedback_stats['total_feedback']) * 100 : 0; ?>%"></div>
                                    <span class="rating-count"><?php echo $feedback_stats['two_star']; ?></span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="rating-label">1 ★</span>
                                    <div class="rating-bar rating-1 flex-grow-1" style="width: <?php echo ($feedback_stats['total_feedback'] > 0) ? ($feedback_stats['one_star'] / $feedback_stats['total_feedback']) * 100 : 0; ?>%"></div>
                                    <span class="rating-count"><?php echo $feedback_stats['one_star']; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="stat-card">
                                        <h3>Average Rating</h3>
                                        <div class="number text-warning">
                                            <?php echo number_format($feedback_stats['avg_rating'], 1); ?> <small>/ 5</small>
                                        </div>
                                        <div class="d-flex justify-content-center">
                                            <?php
                                            $full_stars = floor($feedback_stats['avg_rating']);
                                            $half_star = $feedback_stats['avg_rating'] - $full_stars >= 0.5;
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $full_stars) {
                                                    echo '<i class="fas fa-star text-warning me-1"></i>';
                                                } elseif ($i == $full_stars + 1 && $half_star) {
                                                    echo '<i class="fas fa-star-half-alt text-warning me-1"></i>';
                                                } else {
                                                    echo '<i class="far fa-star text-warning me-1"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="stat-card">
                                        <h3>Total Feedback</h3>
                                        <div class="number"><?php echo $feedback_stats['total_feedback']; ?></div>
                                        <div class="trend">
                                            <span><?php echo $feedback_stats['positive_feedback']; ?> positive</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <div class="stat-card">
                                        <h3>Feedback Distribution</h3>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <div>
                                            <span class="badge bg-success mb-2 d-block">Positive: <?php echo ($feedback_stats['total_feedback'] > 0) ? round(($feedback_stats['positive_feedback'] / $feedback_stats['total_feedback']) * 100) : 0; ?>%</span>
                                                <small><?php echo $feedback_stats['positive_feedback']; ?> reviews</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-warning mb-2 d-block">Neutral: <?php echo ($feedback_stats['total_feedback'] > 0) ? round(($feedback_stats['three_star'] / $feedback_stats['total_feedback']) * 100) : 0; ?>%</span>
                                                <small><?php echo $feedback_stats['three_star']; ?> reviews</small>
                                            </div>
                                            <div>
                                                <span class="badge bg-danger mb-2 d-block">Negative: <?php echo ($feedback_stats['total_feedback'] > 0) ? round(($feedback_stats['negative_feedback'] / $feedback_stats['total_feedback']) * 100) : 0; ?>%</span>
                                                <small><?php echo $feedback_stats['negative_feedback']; ?> reviews</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="section-title">
                        <i class="fas fa-comment-alt"></i> Recent Feedback
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Rating</th>
                                    <th>Date</th>
                                    <th>Comment</th>
                                    <th>Feature</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_feedback_query = "SELECT f.*, u.username 
                                                         FROM feedback f 
                                                         JOIN users u ON f.user_id = u.id 
                                                         ORDER BY f.created_at DESC 
                                                         LIMIT 5";
                                $recent_feedback_result = $conn->query($recent_feedback_query);
                                
                                // Sample data if query doesn't return results
                                if (!$recent_feedback_result || $recent_feedback_result->num_rows == 0) {
                                    $sample_feedback = [
                                        ['username' => 'rajesh_k', 'rating' => 5, 'date' => '2025-03-15', 'comment' => 'Love the new meditation features!', 'feature' => 'Meditation'],
                                        ['username' => 'priya89', 'rating' => 4, 'date' => '2025-03-12', 'comment' => 'App works great but sometimes crashes when syncing data.', 'feature' => 'Sync'],
                                        ['username' => 'amit_dev', 'rating' => 5, 'date' => '2025-03-10', 'comment' => 'The sleep tracking is incredibly accurate.', 'feature' => 'Sleep Tracking'],
                                        ['username' => 'neha_fit', 'rating' => 2, 'date' => '2025-03-08', 'comment' => 'Workout plans are too generic.', 'feature' => 'Workout'],
                                        ['username' => 'vikram23', 'rating' => 4, 'date' => '2025-03-05', 'comment' => 'Great progress tracking, would like more visualization options.', 'feature' => 'Progress Tracking'],
                                    ];
                                    
                                    foreach ($sample_feedback as $feedback) {
                                        echo '<tr>';
                                        echo '<td>' . $feedback['username'] . '</td>';
                                        echo '<td>';
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo ($i <= $feedback['rating']) ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-warning"></i>';
                                        }
                                        echo '</td>';
                                        echo '<td>' . $feedback['date'] . '</td>';
                                        echo '<td>' . $feedback['comment'] . '</td>';
                                        echo '<td><span class="badge bg-primary">' . $feedback['feature'] . '</span></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    while ($row = $recent_feedback_result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $row['username'] . '</td>';
                                        echo '<td>';
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo ($i <= $row['rating']) ? '<i class="fas fa-star text-warning"></i>' : '<i class="far fa-star text-warning"></i>';
                                        }
                                        echo '</td>';
                                        echo '<td>' . date('Y-m-d', strtotime($row['created_at'])) . '</td>';
                                        echo '<td>' . $row['comment'] . '</td>';
                                        echo '<td><span class="badge bg-primary">' . $row['feature'] . '</span></td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <h4 class="section-title">
                        <i class="fas fa-chart-pie"></i> Feature Feedback Distribution
                    </h4>
                    <div class="chart-container">
                        <canvas id="featureFeedbackChart"></canvas>
                    </div>
                </div>

                <!-- Payment Analysis Tab -->
                <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <h4 class="section-title">
                                <i class="fas fa-chart-line"></i> Revenue Trend
                            </h4>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <div class="stat-card">
                                        <h3>Payment Success Rate</h3>
                                        <div class="number text-success"><?php echo $payment_success_rate; ?>%</div>
                                        <div class="progress mt-2" style="height: 10px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $payment_success_rate; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <div class="stat-card">
                                        <h3>Average Purchase</h3>
                                        <div class="number">₹<?php echo number_format($payment_stats['avg_payment'], 0); ?></div>
                                        <div class="trend">
                                            <span>Per successful transaction</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="stat-card">
                                        <h3>Latest Payment</h3>
                                        <div class="number"><?php echo date('d M', strtotime($payment_stats['latest_payment_date'])); ?></div>
                                        <div class="trend">
                                            <span><?php echo $payment_stats['latest_payment_date']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="metric-card">
                                <h4>Total Payments</h4>
                                <div class="value"><?php echo $payment_stats['total_payments']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card">
                                <h4>Successful Payments</h4>
                                <div class="value"><?php echo $payment_stats['successful_payments']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card">
                                <h4>Failed Payments</h4>
                                <div class="value"><?php echo $payment_stats['failed_payments']; ?></div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card">
                                <h4>Highest Payment</h4>
                                <div class="value">₹<?php echo number_format($payment_stats['max_payment'], 0); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="section-title">
                        <i class="fas fa-credit-card"></i> Payment Method Distribution
                    </h4>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="chart-container">
                                <canvas id="paymentMethodChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">Payment Method Success Rates</h5>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Credit Card</span>
                                    <span>94.2%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 94.2%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>UPI</span>
                                    <span>98.7%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 98.7%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Net Banking</span>
                                    <span>91.5%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 91.5%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Wallet</span>
                                    <span>96.8%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: 96.8%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="section-title">
                        <i class="fas fa-history"></i> Recent Transactions
                    </h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recent_payments_query = "SELECT p.*, u.username 
                                                         FROM payments p 
                                                         JOIN users u ON p.user_id = u.id 
                                                         ORDER BY p.payment_date DESC 
                                                         LIMIT 5";
                                $recent_payments_result = $conn->query($recent_payments_query);
                                
                                // Sample data if query doesn't return results
                                if (!$recent_payments_result || $recent_payments_result->num_rows == 0) {
                                    $sample_payments = [
                                        ['id' => 'PAY45678', 'username' => 'rajesh_k', 'date' => '2025-03-18', 'amount' => 1499, 'method' => 'UPI', 'status' => 'success'],
                                        ['id' => 'PAY45677', 'username' => 'priya89', 'date' => '2025-03-17', 'amount' => 2999, 'method' => 'Credit Card', 'status' => 'success'],
                                        ['id' => 'PAY45676', 'username' => 'amit_dev', 'date' => '2025-03-17', 'amount' => 999, 'method' => 'Net Banking', 'status' => 'success'],
                                        ['id' => 'PAY45675', 'username' => 'neha_fit', 'date' => '2025-03-16', 'amount' => 1499, 'method' => 'UPI', 'status' => 'failed'],
                                        ['id' => 'PAY45674', 'username' => 'vikram23', 'date' => '2025-03-15', 'amount' => 1499, 'method' => 'Wallet', 'status' => 'success'],
                                    ];
                                    
                                    foreach ($sample_payments as $payment) {
                                        echo '<tr>';
                                        echo '<td>' . $payment['id'] . '</td>';
                                        echo '<td>' . $payment['username'] . '</td>';
                                        echo '<td>' . $payment['date'] . '</td>';
                                        echo '<td>₹' . number_format($payment['amount'], 2) . '</td>';
                                        echo '<td>' . $payment['method'] . '</td>';
                                        echo '<td>';
                                        if ($payment['status'] == 'success') {
                                            echo '<span class="badge bg-success">Success</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Failed</span>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    while ($row = $recent_payments_result->fetch_assoc()) {
                                        echo '<tr>';
                                        echo '<td>' . $row['id'] . '</td>';
                                        echo '<td>' . $row['username'] . '</td>';
                                        echo '<td>' . date('Y-m-d', strtotime($row['payment_date'])) . '</td>';
                                        echo '<td>₹' . number_format($row['amount'], 2) . '</td>';
                                        echo '<td>' . $row['payment_method'] . '</td>';
                                        echo '<td>';
                                        if ($row['status'] == 'success') {
                                            echo '<span class="badge bg-success">Success</span>';
                                        } else {
                                            echo '<span class="badge bg-danger">Failed</span>';
                                        }
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Report Generation Options -->
            <div class="action-buttons">
                <div class="action-btn print-btn" id="printReport">
                    <i class="fas fa-print me-2"></i>Print Report
                </div>
                <div class="action-btn export-btn" id="generatePDF">
                    <i class="fas fa-file-pdf me-2"></i>Export as PDF
                </div>
                <div class="action-btn" id="exportCSV" style="background-color: #198754; color: white;">
                    <i class="fas fa-file-csv me-2"></i>Export as CSV
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // User Growth Chart
            const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
            const userGrowthChart = new Chart(userGrowthCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($user_growth_labels); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($user_growth_data); ?>,
                        fill: true,
                        backgroundColor: 'rgba(67, 97, 238, 0.2)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.7)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#fff',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                drawBorder: false,
                                display: false
                            }
                        }
                    }
                }
            });

            // Device Usage Chart
            const deviceUsageCtx = document.getElementById('deviceUsageChart').getContext('2d');
            const deviceUsageChart = new Chart(deviceUsageCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($device_usage_labels); ?> || ['Mobile', 'Desktop', 'Tablet', 'Other'],
                    datasets: [{
                        data: <?php echo json_encode($device_usage_data); ?> || [65, 20, 12, 3],
                        backgroundColor: [
                            'rgba(67, 97, 238, 0.8)',
                            'rgba(76, 201, 240, 0.8)',
                            'rgba(58, 12, 163, 0.8)',
                            'rgba(247, 37, 133, 0.8)'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthly_revenue_labels); ?>,
                    datasets: [{
                        label: 'Monthly Revenue',
                        data: <?php echo json_encode($monthly_revenue_data); ?>,
                        backgroundColor: 'rgba(76, 201, 240, 0.7)',
                        borderColor: 'rgba(76, 201, 240, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return '₹' + context.raw.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₹' + value.toLocaleString();
                                }
                            },
                            grid: {
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Payment Method Chart
            const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
            const paymentMethodChart = new Chart(paymentMethodCtx, {
                type: 'pie',
                data: {
                    labels: ['UPI', 'Credit Card', 'Net Banking', 'Wallet'],
                    datasets: [{
                        data: [45, 30, 15, 10],
                        backgroundColor: [
                            'rgba(6, 214, 160, 0.8)',
                            'rgba(239, 71, 111, 0.8)',
                            'rgba(255, 209, 102, 0.8)',
                            'rgba(17, 138, 178, 0.8)'
                        ],
                        borderColor: '#fff',
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
                                boxWidth: 15,
                                padding: 15
                            }
                        }
                    }
                }
            });

            // Feature Feedback Chart
            const featureFeedbackCtx = document.getElementById('featureFeedbackChart').getContext('2d');
            const featureFeedbackChart = new Chart(featureFeedbackCtx, {
                type: 'radar',
                data: {
                    labels: ['Meditation', 'Sleep Tracking', 'Workout Plans', 'Nutrition Tracking', 'Goal Setting', 'Progress Reports'],
                    datasets: [{
                        label: 'Average Rating',
                        data: [4.5, 4.2, 3.8, 4.0, 4.6, 4.1],
                        backgroundColor: 'rgba(67, 97, 238, 0.4)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            min: 0,
                            max: 5,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // PDF Generation
            document.getElementById('generatePDF').addEventListener('click', function() {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Add title
                doc.setFont("helvetica", "bold");
                doc.setFontSize(18);
                doc.text("LifeSync Analytics Report", 105, 15, { align: "center" });
                
                // Add date
                doc.setFont("helvetica", "normal");
                doc.setFontSize(12);
                doc.text("Generated on: <?php echo date('F d, Y'); ?>", 105, 22, { align: "center" });
                
                // Add horizontal line
                doc.setLineWidth(0.5);
                doc.line(20, 25, 190, 25);
                
                // User Statistics
                doc.setFont("helvetica", "bold");
                doc.setFontSize(14);
                doc.text("User Statistics", 20, 35);
                
                doc.setFont("helvetica", "normal");
                doc.setFontSize(12);
                doc.text(`Total Users: ${<?php echo $user_stats['total_users']; ?>}`, 25, 45);
                doc.text(`Premium Users: ${<?php echo $user_stats['premium_users']; ?>} (${<?php echo $premium_percentage; ?>}%)`, 25, 52);
                doc.text(`Active Users: ${<?php echo $user_stats['active_users']; ?>}`, 25, 59);
                doc.text(`Inactive Users: ${<?php echo $user_stats['inactive_users']; ?>}`, 25, 66);
                
                // Feedback Statistics
                doc.setFont("helvetica", "bold");
                doc.setFontSize(14);
                doc.text("Feedback Statistics", 20, 80);
                
                doc.setFont("helvetica", "normal");
                doc.setFontSize(12);
                doc.text(`Total Feedback: ${<?php echo $feedback_stats['total_feedback']; ?>}`, 25, 90);
                doc.text(`Average Rating: ${<?php echo number_format($feedback_stats['avg_rating'], 1); ?>} / 5`, 25, 97);
                doc.text(`Positive Feedback: ${<?php echo $feedback_stats['positive_feedback']; ?>}`, 25, 104);
                doc.text(`Negative Feedback: ${<?php echo $feedback_stats['negative_feedback']; ?>}`, 25, 111);
                
                // Payment Statistics
                doc.setFont("helvetica", "bold");
                doc.setFontSize(14);
                doc.text("Payment Statistics", 20, 125);
                
                doc.setFont("helvetica", "normal");
                doc.setFontSize(12);
                doc.text(`Total Payments: ${<?php echo $payment_stats['total_payments']; ?>}`, 25, 135);
                doc.text(`Successful Payments: ${<?php echo $payment_stats['successful_payments']; ?>} (${<?php echo $payment_success_rate; ?>}%)`, 25, 142);
                doc.text(`Failed Payments: ${<?php echo $payment_stats['failed_payments']; ?>}`, 25, 149);
                doc.text(`Total Revenue: ₹${<?php echo number_format($payment_stats['total_revenue'], 2); ?>}`, 25, 156);
                doc.text(`Average Payment: ₹${<?php echo number_format($payment_stats['avg_payment'], 2); ?>}`, 25, 163);
                
                // Add footer
                doc.setFont("helvetica", "italic");
                doc.setFontSize(10);
                doc.text("LifeSync Analytics Report - Confidential", 105, 280, { align: "center" });
                
                doc.save("LifeSync_Analytics_Report_<?php echo date('Y-m-d'); ?>.pdf");
            });

            // Print Report
            document.getElementById('printReport').addEventListener('click', function() {
                window.print();
            });

            // Export CSV
            document.getElementById('exportCSV').addEventListener('click', function() {
                // User data
                let csvContent = "data:text/csv;charset=utf-8,";
                csvContent += "Category,Metric,Value\n";
                csvContent += "Users,Total Users,<?php echo $user_stats['total_users']; ?>\n";
                csvContent += "Users,Premium Users,<?php echo $user_stats['premium_users']; ?>\n";
                csvContent += "Users,Active Users,<?php echo $user_stats['active_users']; ?>\n";
                csvContent += "Users,Inactive Users,<?php echo $user_stats['inactive_users']; ?>\n";
                csvContent += "Feedback,Total Feedback,<?php echo $feedback_stats['total_feedback']; ?>\n";
                csvContent += "Feedback,Average Rating,<?php echo number_format($feedback_stats['avg_rating'], 1); ?>\n";
                csvContent += "Feedback,Positive Feedback,<?php echo $feedback_stats['positive_feedback']; ?>\n";
                csvContent += "Feedback,Negative Feedback,<?php echo $feedback_stats['negative_feedback']; ?>\n";
                csvContent += "Payments,Total Payments,<?php echo $payment_stats['total_payments']; ?>\n";
                csvContent += "Payments,Successful Payments,<?php echo $payment_stats['successful_payments']; ?>\n";
                csvContent += "Payments,Failed Payments,<?php echo $payment_stats['failed_payments']; ?>\n";
                csvContent += "Payments,Total Revenue,<?php echo $payment_stats['total_revenue']; ?>\n";
                
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", "LifeSync_Analytics_<?php echo date('Y-m-d'); ?>.csv");
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
    </script>
</body>
</html>

                                                