<?php
include 'connect.php';
$database_name = "lifesync_db";  
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

$searchQuery = '';
if (isset($_POST['search'])) {
    $searchQuery = $_POST['search'];
    $userQuery = "SELECT * FROM users WHERE username LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%'";
} else {
    $userQuery = "SELECT * FROM users";
}

$result = mysqli_query($conn, $userQuery);

if (isset($_GET['delete'])) {
    $deleteId = $_GET['delete'];
    $deleteQuery = "DELETE FROM users WHERE user_id = $deleteId";
    if (mysqli_query($conn, $deleteQuery)) {
        header('Location: admindash.php');
    }
}

$countQuery = "SELECT COUNT(*) AS user_count FROM users";
$countResult = mysqli_query($conn, $countQuery);
$countData = mysqli_fetch_assoc($countResult);
$userCount = $countData['user_count'];

// Count users by role
$roleCountQuery = "SELECT 
    COUNT(CASE WHEN role = 'admin' THEN 1 END) AS admin_count,
    COUNT(CASE WHEN role = 'premium user' THEN 1 END) AS premium_count,
    COUNT(CASE WHEN role = 'user' THEN 1 END) AS standard_count
FROM users";
$roleCountResult = mysqli_query($conn, $roleCountQuery);
$roleCountData = mysqli_fetch_assoc($roleCountResult);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6F4E37;
            --secondary-color: #8B4513;
            --accent-color: #D2691E;
            --bg-color: #F4ECD8;
            --card-bg: #F5DEB3;
            --success-color: #2ecc71;
            --danger-color: #8B0000;
            --text-primary: #3E2723;
            --text-secondary: #5D4037;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Animations */
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes wiggle {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .content, .main-header {
                margin-left: 0;
                width: 100%;
            }
        }

        /* Main Header Styles */
        .main-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.5s ease-out;
        }

        /* Sidebar Styles */
        body {
            display: flex;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--primary-color);
            padding-top: 20px;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 2rem;
            overflow-x: hidden;
        }

        .main-header {
            margin-left: 250px;
            width: calc(100% - 250px);
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0.5rem 1rem;
            border-radius: 8px;
        }

        .sidebar-link i {
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        /* Dashboard Cards */
        .dashboard-card {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            text-align: center;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease-out;
        }

        .dashboard-card:hover {
            animation: pulse 0.5s infinite;
        }

        .card-icon {
            font-size: 1.8rem;
            color: var(--accent-color);
            margin-bottom: 0.5rem;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover .card-icon {
            transform: scale(1.2);
        }

        .card-title {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.3rem;
        }

        .card-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* Role Badge Styles */
        .role-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .role-badge-admin {
            background-color: #8B0000;
            color: white;
        }

        .role-badge-premium {
            background-color: #FFD700;
            color: black;
        }

        .role-badge-user {
            background-color: #4682B4;
            color: white;
        }

        /* Rest of the previous styles remain the same */
        /* ... (previous CSS remains unchanged) ... */
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="header-content">
            <h1 class="header-title">
                <i class="fas fa-solar-panel me-2"></i>
                Admin Dashboard
            </h1>
            <div class="header-actions">
                <button class="btn btn-light">
                    <i class="fas fa-bell me-2"></i>
                    Notifications
                </button>
                <button class="btn btn-light ms-2">
                    <i class="fas fa-user-circle me-2"></i>
                    Profile
                </button>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        <a href="#" class="sidebar-link">
            <i class="fas fa-home"></i>
            Dashboard
        </a>
        <a href="#" class="sidebar-link">
            <i class="fas fa-users"></i>
            Users
        </a>
        <a href="#" class="sidebar-link">
            <i class="fas fa-chart-bar"></i>
            Analytics
        </a>
        <a href="#" class="sidebar-link">
            <i class="fas fa-cog"></i>
            Settings
        </a>
        <a href="#" class="sidebar-link">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </nav>

    <!-- Main Content -->
    <div class="content">
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-users card-icon"></i>
                    <div class="card-title">Total Users</div>
                    <div class="card-value"><?php echo $userCount; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-user-shield card-icon"></i>
                    <div class="card-title">Admins</div>
                    <div class="card-value"><?php echo $roleCountData['admin_count']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-star card-icon"></i>
                    <div class="card-title">Premium Users</div>
                    <div class="card-value"><?php echo $roleCountData['premium_count']; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="dashboard-card">
                    <i class="fas fa-user-check card-icon"></i>
                    <div class="card-title">Standard Users</div>
                    <div class="card-value"><?php echo $roleCountData['standard_count']; ?></div>
                </div>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-container">
            <form method="POST" action="admindash.php">
                <input type="text" name="search" class="search-input" placeholder="Search users..." value="<?php echo $searchQuery; ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Users Table -->
        <div class="custom-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><i class="fas fa-hashtag me-2"></i>ID</th>
                        <th><i class="fas fa-user me-2"></i>Username</th>
                        <th><i class="fas fa-envelope me-2"></i>Email</th>
                        <th><i class="fas fa-users-cog me-2"></i>Role</th>
                        <th><i class="fas fa-calendar me-2"></i>Created At</th>
                        <th><i class="fas fa-cogs me-2"></i>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Determine role badge class
                            $roleBadgeClass = match($row['role']) {
                                'admin' => 'role-badge-admin',
                                'premium user' => 'role-badge-premium',
                                default => 'role-badge-user'
                            };

                            echo "<tr>";
                            echo "<td>" . $row['user_id'] . "</td>";
                            echo "<td>" . $row['username'] . "</td>";
                            echo "<td>" . $row['email'] . "</td>";
                            echo "<td>" . 
                                $row['role'] . 
                                "<span class='role-badge $roleBadgeClass'>" . 
                                ucwords($row['role']) . 
                                "</span>" . 
                                "</td>";
                            echo "<td>" . $row['created_at'] . "</td>";
                            echo "<td>
                                    <a href='viewuser.php?id=" . $row['user_id'] . "' class='btn btn-action btn-view me-2'>
                                        <i class='fas fa-eye me-1'></i> View
                                    </a>
                                    <a href='?delete=" . $row['user_id'] . "' class='btn btn-action btn-delete' onclick='return confirm(\"Are you sure you want to delete this user?\")'>
                                        <i class='fas fa-trash-alt me-1'></i> Delete
                                    </a>
                                  </td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center'>No users found</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>