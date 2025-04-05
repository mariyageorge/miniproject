<?php include 'header.php'; ?>
<?php include 'header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - LIFE-SYNC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --brown-dark: #5D4037;
            --brown-primary: #8B4513;
            --brown-accent: #A1887F;
            --brown-light: #D7CCC8;
            --cream: #F5F5DC;
            --vintage-beige: #F5ECE5;
            --vintage-tan: #E8D5C8;
        }
        
        body {
            font-family: 'Playfair Display', Georgia, serif;
            margin: 0;
            padding: 0;
            background-image: url("images/todobg.jpg");
            color: var(--brown-dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
          background-image: url("images/todobg.jpg");
          background-size: cover;
            color: var(--cream);
            text-align: center;
            padding: 60px 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        
        .page-title {
            font-size: 3rem;
            margin-bottom: 15px;
            letter-spacing: 1px;
            color: var(--brown-primary);

        }
        
        .page-subtitle {
            font-size: 1.3rem;
            font-style: italic;
            margin-bottom: 0;
            color: var(--brown-primary);

        }
        
        .content-section {
            background-color: var(--vintage-tan);
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--brown-primary);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            font-size: 1.8rem;
            color: var(--brown-primary);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--brown-accent);
        }
        
        .section-title i {
            margin-right: 12px;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .feature-item {
            background-color: var(--vintage-beige);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }
        
        .feature-item:hover {
            transform: translateY(-3px);
        }
        
        .feature-icon {
            font-size: 2rem;
            color: var(--brown-primary);
            margin-bottom: 10px;
        }
        
        .feature-name {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .additional-features {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .additional-feature {
            display: flex;
            align-items: center;
            background-color: var(--vintage-beige);
            padding: 12px;
            border-radius: 8px;
        }
        
        .additional-feature i {
            font-size: 1.2rem;
            margin-right: 10px;
            color: var(--brown-primary);
        }
        
        .cta-button {
            display: inline-block;
            background-color: var(--brown-primary);
            color: var(--cream);
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 15px;
            transition: background-color 0.3s ease;
        }
        
        .cta-button:hover {
            background-color: var(--brown-dark);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            background-color: var(--brown-dark);
            color: var(--cream);
            border-radius: 8px;
            margin-top: 30px;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
        }
        
        .social-icons a {
            color: var(--cream);
            font-size: 1.3rem;
            transition: color 0.3s ease;
        }
        
        .social-icons a:hover {
            color: var(--vintage-tan);
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2.2rem;
            }
            
            .feature-grid, .additional-features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1 class="page-title">About LIFE-SYNC</h1>
            <p class="page-subtitle">Your All-in-One Personal Life Assistant</p>
        </header>
        
        <section class="content-section">
            <h2 class="section-title"><i class="fas fa-book-open"></i> Our Mission</h2>
            <p>LIFE-SYNC is your all-in-one personal assistant web app designed to simplify and organize your daily life. Whether it's managing tasks, tracking finances, or focusing on your health, LIFE-SYNC offers an elegant, user-friendly experience tailored for you.</p>
            <p>At LIFE-SYNC, our goal is to help you stay organized, healthy, and balanced – all in one place.</p>
        </section>
        
        <section class="content-section">
            <h2 class="section-title"><i class="fas fa-gem"></i> Core Features</h2>
            <div class="feature-grid">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-tasks"></i></div>
                    <h3 class="feature-name">To-Do & Notes ✓</h3>
                    <p>Add up to 5 daily tasks (unlimited for premium) and keep unlimited notes.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-wallet"></i></div>
                    <h3 class="feature-name">Expense Tracker 💰</h3>
                    <p>Monthly limits, category-wise tracking, and visual reports.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-heartbeat"></i></div>
                    <h3 class="feature-name">Health Guide 🥗</h3>
                    <p>Track water & food intake, calculate BMI, and get personalized meal plans.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                    <h3 class="feature-name">Calendar 📅</h3>
                    <p>Set reminders with time and date, receive notifications.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-users"></i></div>
                    <h3 class="feature-name">Split Expenses 👥</h3>
                    <p>Create groups, add shared expenses, and split them fairly.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-book"></i></div>
                    <h3 class="feature-name">Diary 📔</h3>
                    <p>Log daily entries with mood tracking and view them by date.</p>
                </div>
            </div>
        </section>
        
        <section class="content-section">
            <h2 class="section-title"><i class="fas fa-plus-circle"></i> Additional Features</h2>
            <div class="additional-features">
                <div class="additional-feature">
                    <i class="fas fa-language"></i>
                    <span>Language translation</span>
                </div>
                <div class="additional-feature">
                    <i class="fas fa-comment-dots"></i>
                    <span>Feedback submission</span>
                </div>
                <div class="additional-feature">
                    <i class="fas fa-crown"></i>
                    <span>Premium upgrade</span>
                </div>
                <div class="additional-feature">
                    <i class="fas fa-bell"></i>
                    <span>Calendar notifications</span>
                </div>
            </div>
        </section>
        
        <footer class="footer">
            <div class="social-icons">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
            <p>&copy; 2025 LIFE-SYNC. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>