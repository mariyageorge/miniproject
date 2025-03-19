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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 0;
        }

        .header {
            width: 100%;
            background: var(--primary-color);
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
            background: rgba(255, 255, 255, 0.1);
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
            color: white;
            letter-spacing: 1px;
        }

        .back-button {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }

        .page-title {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .feedback-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .feedback-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .feedback-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feedback-user {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .user-info h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .feedback-date {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .feedback-content {
            padding: 1.5rem;
            flex-grow: 1;
        }

        .feedback-message {
            color: var(--text-primary);
            margin-bottom: 1.25rem;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .feedback-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .feedback-rating i {
            color: #FFD700;
        }

        .no-feedback {
            text-align: center;
            padding: 4rem 2rem;
            background-color: var(--card-bg);
            border-radius: 12px;
            margin-top: 2rem;
        }

        .no-feedback i {
            font-size: 3rem;
            color: var(--text-secondary);
            opacity: 0.5;
            margin-bottom: 1rem;
        }

        .no-feedback h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .no-feedback p {
            color: var(--text-secondary);
            margin: 0;
        }

        @media (max-width: 768px) {
            .feedback-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                margin-top: 80px;
            }
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
        
        <a href="admindash.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </header>

    <div class="container">
        <h1 class="page-title">User Feedback</h1>
        
        <?php if ($result->num_rows > 0): ?>
            <div class="feedback-grid">
                <?php while ($feedback = $result->fetch_assoc()): ?>
                    <div class="feedback-card">
                        <div class="feedback-header">
                            <div class="feedback-user">
                                <div class="user-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="user-info">
                                    <h5><?php echo htmlspecialchars($feedback['username'] ?? 'Anonymous'); ?></h5>
                                    <span class="feedback-date">
                                        <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="feedback-content">
                            <p class="feedback-message"><?php echo htmlspecialchars($feedback['message']); ?></p>
                            <div class="feedback-rating">
                                <i class="fas fa-star"></i>
                                <span>Rating: <?php echo $feedback['rating']; ?>/5</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-feedback">
                <i class="fas fa-comments"></i>
                <h4>No Feedback Yet</h4>
                <p>There are currently no feedback submissions to display.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>