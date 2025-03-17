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
$conn->close();
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
            --primary-color: #2C3E50;
            --secondary-color: #34495E;
            --accent-color: #2980B9;
            --bg-color: #F4F6F6;
            --card-bg: #FFFFFF;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-color: #D5DBDB;
            --success-color: #27AE60;
            --danger-color: #E74C3C;
            --warning-color: #F1C40F;
            --info-color: #3498DB;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            color: var(--text-primary);
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
            border-left: 5px solid transparent;
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
            background-color: var(--secondary-color);
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
            background-color: rgba(44, 62, 80, 0.05);
            color: var(--primary-color);
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
            background-color: var(--card-bg);
            padding: 1.5rem 0;
            text-align: center;
            color: var(--text-secondary);
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
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-container">
                <div class="d-flex align-items-center">
                    <button class="hamburger me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMenu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a class="logo" href="index.php">
                        <div class="logo-icon">
                            <i class="fas fa-infinity"></i>
                        </div>
                        <span class="logo-text d-none d-sm-inline">LIFE-SYNC</span>
                    </a>
                </div>
                <div class="admin-controls">
                    <div class="d-none d-md-block">
                        <span class="admin-welcome">Welcome, Admin</span>
                    </div>
                    <a href="logout.php" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="d-none d-sm-inline ms-1">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Admin Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="adminMenu">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Admin Control Panel</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <ul class="admin-menu-list">
                <li class="admin-menu-item">
                    <a href="admindash.php" class="admin-menu-link active">
                        <span class="admin-menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                        Dashboard
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="add_diet_plan.php" class="admin-menu-link">
                        <span class="admin-menu-icon"><i class="fas fa-utensils"></i></span>
                        Manage Diet Plans
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="view_feedback.php" class="admin-menu-link">
                        <span class="admin-menu-icon"><i class="fas fa-comment"></i></span>
                        View Feedbacks
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="reports.php" class="admin-menu-link">
                        <span class="admin-menu-icon"><i class="fas fa-chart-bar"></i></span>
                        Analytics & Reports
                    </a>
                </li>
                <li class="admin-menu-item">
                    <a href="system_settings.php" class="admin-menu-link">
                        <span class="admin-menu-icon"><i class="fas fa-cog"></i></span>
                        System Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <main class="dashboard-container">
        <div class="container">
            <h1 class="section-title">Dashboard Overview</h1>
            
            <!-- User Statistics Cards -->
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

            <!-- Search & Filter Form -->
            <h2 class="section-title">User Management</h2>
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
        </div>
    </main>
            
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 LifeSync. All rights reserved.</p>
        </div>
    </footer>
            
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>