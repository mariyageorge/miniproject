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
$whereClause = $searchQuery ? "WHERE username LIKE ? OR email LIKE ?" : "";

// Prepare user query
$userQuery = "SELECT * FROM users $whereClause";
$stmt = $conn->prepare($userQuery);

if ($searchQuery) {
    $searchParam = "%$searchQuery%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
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

// User count queries
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
            --text-primary: #3E2723;
            --text-secondary: #5D4037;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        .main-header {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        @keyframes shine {
            0% {
                transform: translate(-100%, -100%) rotate(45deg);
            }
            100% {
                transform: translate(200%, 200%) rotate(45deg);
            }
        }
        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .admin-controls {
            display: flex;
            gap: 15px;
            align-items: center;
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
            background-color: #dc3545;
            color: white;
        }

        .role-badge.premium {
            background-color: #ffc107;
            color: #000;
        }

        .role-badge.user {
            background-color: #0dcaf0;
            color: white;
        }

        .dropdown-menu {
            background-color: var(--card-bg);
        }

        .hamburger {
            background-color: var(--primary-color);
            border: none;
            border-radius: 4px;
            color: white;
            padding: 5px;
            cursor: pointer;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stats-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .stats-card-icon {
            font-size: 3rem;
            opacity: 0.7;
        }

        .stats-card-content {
            flex-grow: 1;
        }

        .stats-card-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stats-card-title {
            font-size: 1rem;
            color: var(--text-secondary);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255,255,255,0.2);
            transition: background-color 0.3s ease;
        }

        .btn-action {
            margin: 0 5px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: scale(1.1);
        }

        .search-bar {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-bar input {
            flex: 1;
            border-radius: 25px;
            padding: 0.6rem 1rem;
            border: 1px solid var(--secondary-color);
            outline: none;
            font-size: 1rem;
            transition: border 0.3s ease;
        }

        .search-bar input:focus {
            border-color: var(--primary-color);
        }

        .search-bar button {
            border-radius: 25px;
            padding: 0.6rem 1.2rem;
            background-color: var(--primary-color);
            border: none;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-bar button:hover {
            background-color: var(--secondary-color);
        }
        /* Offcanvas Menu Styling */
.offcanvas {
    background-color: var(--bg-color);
}

.offcanvas-header {
    background-color: var(--primary-color);
    color: white;
    border-bottom: 1px solid var(--accent-color);
}

.offcanvas-title {
    font-weight: bold;
}

.btn-close {
    filter: invert(1);
}

.list-group-item {
    background-color: var(--card-bg);
    color: var(--text-primary);
    border: none;
    font-weight: 500;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.list-group-item i {
    color: var(--primary-color);
}

.list-group-item:hover {
    background-color: var(--primary-color);
    color: white;
    transform: translateX(5px);
}

.list-group-item:hover i {
    color: white;
}

.list-group-item.active {
    background-color: var(--primary-color);
    color: white;
    font-weight: bold;
}

.list-group-item.active i {
    color: white;
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
                        <span class="text-muted">Welcome, Admin</span>
                    </div>
                    <a href="logout.php" class="btn btn-outline-danger">
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
            <h5 class="offcanvas-title">Admin Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="list-group">
                <a href="admindash.php" class="list-group-item list-group-item-action active">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="#" class="list-group-item list-group-item-action">
                    <i class="fas fa-bell me-2"></i> View Requests
                </a>
                <a href="view_feedback.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-comment me-2"></i> View Feedbacks
                </a>
            </div>
        </div>
    </div>
<br><br>
    <div class="content container">
        <!-- User Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card">
                <i class="stats-card-icon fas fa-users text-primary"></i>
                <div class="stats-card-content">
                    <div class="stats-card-number"><?php echo $userStats['total']['user_count']; ?></div>
                    <div class="stats-card-title">Total Users</div>
                </div>
            </div>
            <div class="stats-card">
                <i class="stats-card-icon fas fa-user-shield text-danger"></i>
                <div class="stats-card-content">
                    <div class="stats-card-number"><?php echo $userStats['roles']['admin_count']; ?></div>
                    <div class="stats-card-title">Admin Users</div>
                </div>
            </div>
            <div class="stats-card">
                <i class="stats-card-icon fas fa-crown text-warning"></i>
                <div class="stats-card-content">
                    <div class="stats-card-number"><?php echo $userStats['roles']['premium_count']; ?></div>
                    <div class="stats-card-title">Premium Users</div>
                </div>
            </div>
            <div class="stats-card">
                <i class="stats-card-icon fas fa-user text-info"></i>
                <div class="stats-card-content">
                    <div class="stats-card-number"><?php echo $userStats['roles']['standard_count']; ?></div>
                    <div class="stats-card-title">Standard Users</div>
                </div>
            </div>
        </div>


        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="search-bar">
                    <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- User Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['user_id']; ?></td>
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
                                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                                        <td>
                                                    <a href="updaterole.php?id=<?php echo $row['user_id']; ?>" class="btn btn-primary btn-action">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="admindash.php?delete=<?php echo $row['user_id']; ?>" class="btn btn-danger btn-action" onclick="return confirm('Are you sure you want to delete this user?');">
                                                        <i class="fas fa-trash-alt"></i> 
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($result->num_rows === 0): ?>
                                <p class="text-center text-muted mt-3">No users found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            
                <!-- Footer -->
                <footer class="text-center mt-4">
                    <p>&copy; 2025 LifeSync. All rights reserved.</p>
                </footer>
            
                <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
            </body>
            </html>
            