<?php
include 'connect.php';  
$database_name = "lifesync_db";  
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_role'])) {
    $userId = $_POST['user_id'];
    $newRole = $_POST['user_role'];

    $updateRoleQuery = "UPDATE users SET role = '$newRole' WHERE user_id = $userId";
    if (mysqli_query($conn, $updateRoleQuery)) {
        echo "User role updated successfully!";
    } else {
        echo "Error updating role: " . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | LIFE-SYNC</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --brown-primary: #8B4513;
            --brown-dark: #6A2F1B;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--nude-100);
            margin: 0;
            padding: 0;
        }

        /* Header Styling */
        .main-header {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
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
            padding: 0 1.5rem;
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
            background: var(--brown-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            position: relative;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--brown-primary);
        }

        .admin-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn-outline-danger {
            border: 2px solid var(--brown-primary);
            color: var(--brown-primary);
            transition: all 0.3s ease;
        }

        .btn-outline-danger:hover {
            background-color: var(--brown-primary);
            color: white;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background-color: var(--nude-100);
            border-right: 1px solid var(--nude-200);
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            padding: 1rem;
            z-index: 9999;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            text-decoration: none;
            color: var(--brown-primary);
            border-radius: 8px;
            margin-bottom: 8px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: var(--nude-200);
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
            margin-left: auto;
            margin-right: auto;
            max-width: 450px;
        }

        h2 {
            color: var(--brown-primary);
            font-weight: 700;
            text-align: center;
            margin-bottom: 20px;
        }

        form {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        form label {
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            color: var(--brown-dark);
        }

        form input, form select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--nude-200);
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        form input:focus, form select:focus {
            border-color: var(--brown-primary);
            outline: none;
            box-shadow: 0 0 5px rgba(139, 69, 19, 0.5);
        }

        .btn-primary {
            background-color: var(--brown-primary);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--brown-dark);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="header-container">
            <a class="logo" href="index.php">
                <div class="logo-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <span class="logo-text">LIFE-SYNC</span>
            </a>
            <div class="admin-controls">
                <span class="text-muted">Welcome, Admin</span>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <h2>Change User Role</h2>
        <form method="POST">
            <label for="user_id">User ID:</label>
            <input type="number" id="user_id" name="user_id" required>

            <label for="user_role">New Role:</label>
            <select id="user_role" name="user_role" required>
                <option value="user">User</option>
                <option value="premium user">Premium User</option>
                <option value="admin">Admin</option>
            </select>

            <button type="submit" class="btn btn-primary">Update Role</button>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
