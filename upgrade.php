<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade to Premium - LifeSync</title>
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

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }

        .premium-section {
            text-align: center;
            padding: 50px 20px;
        }

        .premium-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .premium-text {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-top: 10px;
        }

        .pricing-card {
            background-color: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
            transition: 0.3s;
        }

        .pricing-card:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .pricing-card h2 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: bold;
        }

        .pricing-card .price {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--accent-color);
        }

        .pricing-card ul {
            list-style: none;
            padding: 0;
        }

        .pricing-card ul li {
            font-size: 1.1rem;
            color: var(--text-primary);
            padding: 8px 0;
        }

        .pricing-card ul li i {
            color: green;
            margin-right: 10px;
        }

        .upgrade-btn {
            background-color: var(--primary-color);
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: 0.3s;
        }

        .upgrade-btn:hover {
            background-color: var(--secondary-color);
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
    </style>
</head>
<body>

    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-container">
                <a class="logo" href="index.php">
                    <div class="logo-icon">
                        <i class="fas fa-infinity"></i>
                    </div>
                    <span class="logo-text">LIFE-SYNC</span>
                </a>
                <div>
                <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> 
    </a>
                </div>
            </div>
        </div>
    </header>
    <!-- Premium Section -->
    <div class="premium-section">
        <h1 class="premium-title">Upgrade to LifeSync Premium</h1>
        <p class="premium-text">Unlock exclusive features and elevate your experience.</p>
    </div>

    <!-- Pricing Card -->
    <div class="container d-flex justify-content-center">
        <div class="pricing-card">
            <h2>Premium Plan</h2>
            <p class="price">Rs 199 / month</p>
            <ul>
                <li><i class="fas fa-check-circle"></i> Unlimited Todos</li>
                <li><i class="fas fa-check-circle"></i> Access to Exclusive Content</li>
                <li><i class="fas fa-check-circle"></i> Expense reports</li>
                <li><i class="fas fa-check-circle"></i> Advanced Health & Task Features</li>
            </ul>
            <button class="upgrade-btn" onclick="window.location.href='payment.php';">
                <i class="fas fa-arrow-up"></i> Upgrade Now
            </button>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center mt-4">
        <p>&copy; 2025 LifeSync. All rights reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
