<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFE-SYNC Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --nude-300: #DBBFAE;
            --nude-400: #C6A792;
            --nude-500: #B08F78;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
            --brown-light: #DEB887;
            --accent-purple: #9B6B9E;
            --accent-green: #7BA686;
            --accent-blue: #6B94AE;
            --accent-orange: #E6955C;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--nude-200);
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: url("images/dashbg.jpg") no-repeat center center/cover; /* Background image */
            padding: 2rem 1.5rem;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.3); /* Slight transparency for profile background */
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .profile-pic {
            width: 50px;
            height: 50px;
            background: var(--nude-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }

        .welcome-message {
            color: var(--brown-primary);
            margin-bottom: 2rem;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-sticker {
            font-size: 2.5rem;
        }

        .container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            max-width: 1000px;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.1);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }

        .card:nth-child(1)::before { background: var(--accent-purple); }
        .card:nth-child(2)::before { background: var(--accent-green); }
        .card:nth-child(3)::before { background: var(--accent-blue); }
        .card:nth-child(4)::before { background: var(--accent-orange); }

        .icon-container {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.2rem;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .card:nth-child(1) .icon-container { background: linear-gradient(135deg, var(--accent-purple), #C490C9); }
        .card:nth-child(2) .icon-container { background: linear-gradient(135deg, var(--accent-green), #A3C7AE); }
        .card:nth-child(3) .icon-container { background: linear-gradient(135deg, var(--accent-blue), #9BB7D0); }
        .card:nth-child(4) .icon-container { background: linear-gradient(135deg, var(--accent-orange), #F3B389); }

        .icon {
            font-size: 1.8rem;
            color: white;
        }

        h2 {
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
            color: var(--brown-primary);
        }

        p {
            font-size: 0.9rem;
            color: var(--nude-500);
            margin-bottom: 1.2rem;
        }

        .button {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: var(--brown-primary);
            border-radius: 25px;
            color: white;
            text-decoration: none;
            transition: 0.3s ease;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background: var(--brown-hover);
            transform: scale(1.05);
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 1rem;
            }

            .profile-section, .nav-link span {
                display: none;
            }

            .container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <aside class="sidebar">
        <div class="profile-section">
            <div class="profile-pic">
                <i class="fas fa-user"></i>
            </div>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        
        <nav class="nav-links">
            <a href="#" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-question-circle"></i>
                <span>Help</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <div class="main-content">
        <div class="welcome-message">
            <span class="welcome-sticker">🌟</span>
            Welcome back, <?php echo htmlspecialchars($username); ?>!
        </div>
        
        <div class="container">
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-list-check"></i>
                </div>
                <h2>Task Manager</h2>
                <p>Organize your daily tasks efficiently with smart reminders and priority tracking ✨</p>
                <a href="#" class="button">Get Started</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-coins"></i>
                </div>
                <h2>Finance Tracker</h2>
                <p>Take control of your finances with smart budgeting and expense tracking 💰</p>
                <a href="#" class="button">Manage Money</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-heart-pulse"></i>
                </div>
                <h2>Wellness Guide</h2>
                <p>Stay healthy and balanced with personalized wellness recommendations 🌿</p>
                <a href="#" class="button">Start Journey</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon far fa-calendar"></i>
                </div>
                <h2>Time Planner</h2>
                <p>Plan your days effectively with smart scheduling and reminders ⏰</p>
                <a href="#" class="button">Plan Now</a>
            </div>
        </div>
    </div>
</body>
</html>
