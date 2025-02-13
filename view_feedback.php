<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

include 'connect.php';
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

// Fetch feedback data
$feedbackQuery = "SELECT f.*, u.username 
                 FROM feedback f 
                 LEFT JOIN users u ON f.user_id = u.user_id 
                 ORDER BY f.created_at DESC";
$result = $conn->query($feedbackQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - Admin Dashboard</title>
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

        .header {
            width: 100%;
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--secondary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--secondary-color);
            letter-spacing: 1px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .back-button {
            padding: 8px 16px;
            background:  #8B4513;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background:  #A0522D;
        }
        .admin-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

       

        .feedback-card {
            background-color: var(--card-bg);
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
        }

        .feedback-header {
            background-color: var(--secondary-color);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feedback-content {
            padding: 20px;
        }

        .feedback-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 10px;
            border-top: 1px solid rgba(0,0,0,0.1);
            padding-top: 10px;
        }

        .no-feedback {
            text-align: center;
            padding: 40px;
            background-color: var(--card-bg);
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="header">
   
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-infinity"></i>
            </div>
            <span class="logo-text">LIFE-SYNC</span>
        </div>
        
        <div class="header-right">
        <a href="admindash.php" class="back-button">
        <i class="fas fa-arrow-left"></i> 
    </a>
        </div>
    
    </header>
<br><br><br>

    <div class="container mt-5">

    
    <?php if ($result->num_rows > 0): ?>
        <div class="row">
            <?php while ($feedback = $result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="feedback-card">
                        <div class="feedback-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comment me-2"></i>
                                Feedback from <?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?>
                            </h5>
                            <span class="badge bg-light text-dark">
                                <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                            </span>
                        </div>
                        <div class="feedback-content">
                            <p><?php echo htmlspecialchars($feedback['message']); ?></p>
                            <div class="feedback-meta">
                                <i class="fas fa-star text-warning me-1"></i>
                                Rating: <?php echo $feedback['rating']; ?>/5
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-feedback">
            <i class="fas fa-comments fa-3x mb-3 text-muted"></i>
            <h4>No Feedback Yet</h4>
            <p class="text-muted">There are currently no feedback submissions to display.</p>
        </div>
    <?php endif; ?>
</div>


    <!-- Footer -->
    <footer class="text-center mt-4 mb-4">
        <p>&copy; 2025 LifeSync. All rights reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>