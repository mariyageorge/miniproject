<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
include 'header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5D4037;
            --secondary-color: #8D6E63;
            --accent-color: #FF9800;
            --bg-color: #F5F5F5;
            --text-primary: #3E2723;
            --text-secondary: #6D4C41;
        }
        
        body {
            background-color: #F8F5F2;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .success-container {
            max-width: 500px;
            margin: 50px auto;
            text-align: center;
            padding: 30px;
            background: #FFFFFF;
            border-radius: 15px;
            box-shadow: 0px 10px 30px rgba(93, 64, 55, 0.15);
            position: relative;
            overflow: hidden;
            border-top: 5px solid var(--accent-color);
        }
        
        .success-icon {
            font-size: 60px;
            color: #4CAF50;
            margin-bottom: 15px;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .success-container h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .success-container p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .success-container strong {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .dashboard-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 10px rgba(93, 64, 55, 0.2);
        }
        
        .dashboard-btn:hover {
            background-color: #4E342E;
            transform: translateY(-3px);
            box-shadow: 0px 6px 15px rgba(93, 64, 55, 0.3);
        }
        
        .premium-badge {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 15px;
            background-color: rgba(255, 152, 0, 0.15);
            color: var(--accent-color);
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .premium-badge i {
            margin-right: 5px;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #f2d74e;
            opacity: 0;
        }
        
        @keyframes confetti-fall {
            0% {
                opacity: 1;
                top: -10%;
                transform: translateY(0) rotate(0deg);
            }
            100% {
                opacity: 0.2;
                top: 100%;
                transform: translateY(1000px) rotate(720deg);
            }
        }
        
        .thank-you-msg {
            margin-top: 20px;
            font-style: italic;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <br><br><br>
    <div class="container">
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="mt-3">Payment Successful!</h2>
            <p>Thank you for subscribing to <strong>LifeSync Premium</strong>. Your payment has been processed successfully.</p>
            <div class="premium-badge">
                <i class="fas fa-crown"></i> Premium Member
            </div>
            <div class="mt-4">
                <a href="dashboard.php" class="btn dashboard-btn mt-3">
                    <i class="fas fa-home me-2"></i>Go to Dashboard
                </a>
            </div>
            <p class="thank-you-msg">We appreciate your trust in LifeSync!</p>
            
            <!-- Confetti Elements will be added here by JS -->
        </div>
    </div>
    
    <script>
        // Create confetti effect when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.success-container');
            const colors = ['#5D4037', '#8D6E63', '#FF9800', '#FFCC80', '#4CAF50', '#A1887F'];
            
            // Create multiple confetti elements
            for (let i = 0; i < 100; i++) {
                createConfetti(container, colors);
            }
            
            // Initial burst of confetti
            burstConfetti();
        });
        
        function createConfetti(container, colors) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            
            // Random size between 5px and 10px
            const size = Math.floor(Math.random() * 6) + 5;
            confetti.style.width = `${size}px`;
            confetti.style.height = `${size}px`;
            
            // Random color from the colors array
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            
            // Random rotation
            confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
            
            // Random position horizontally
            confetti.style.left = `${Math.random() * 100}%`;
            
            // Set up the animation but don't start it yet
            confetti.style.position = 'absolute';
            confetti.style.top = '-10%';
            
            // Add to container
            container.appendChild(confetti);
            
            return confetti;
        }
        
        function burstConfetti() {
            const confettis = document.querySelectorAll('.confetti');
            
            confettis.forEach((confetti, index) => {
                // Stagger the animations
                setTimeout(() => {
                    // Random animation duration between 2s and 5s
                    const duration = (Math.random() * 3) + 2;
                    
                    confetti.style.animation = `confetti-fall ${duration}s ease forwards`;
                    
                    // Remove after animation is complete
                    setTimeout(() => {
                        confetti.remove();
                    }, duration * 1000);
                }, index * 20); // Stagger effect
            });
        }
    </script>
</body>
</html>