<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFE-SYNC - Your Personal Life Assistant</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--nude-100);
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            position: relative;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            overflow: hidden;
        }

        .logo-icon::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.2) 50%, transparent 70%);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--brown-primary);
            margin: 0;
        }

        .hero-section {
            background: linear-gradient(45deg, var(--nude-200), var(--nude-100));
            padding: 120px 0;
        }

        .carousel-inner img {
            border-radius: 15px;
        }

        .features-section {
            padding: 80px 0;
            background: white;
        }

        .feature-card {
            background: var(--nude-100);
            border-radius: 20px;
            padding: 30px;
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--brown-light);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon i {
            font-size: 24px;
            color: var(--brown-primary) !important;
        }

        .btn-primary {
            background-color: var(--brown-primary);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--brown-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.2);
        }


        .footer {
            padding: 60px 0;
            background: white;
        }

        .footer-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--brown-primary);
            margin-bottom: 20px;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--brown-primary);
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            color: #666;
            font-size: 20px;
            transition: color 0.3s ease;
        }

        .social-links a:hover {
            color: var(--brown-primary);
        }
      
    .testimonials-section {
        background: linear-gradient(to right, rgba(240, 240, 240, 0.6), rgba(255, 255, 255, 0.8));
        padding: 80px 0;
        position: relative;
        overflow: hidden;
    }

    .testimonials-section::before {
        content: '';
        position: absolute;
        top: -50px;
        left: 0;
        right: 0;
        height: 100px;
        background: radial-gradient(circle, rgba(240, 240, 240, 0.8) 0%, transparent 100%);
        z-index: 0;
    }

    .testimonials-section blockquote {
        font-size: 18px;
        font-style: italic;
        color: #555;
    }

    .testimonials-section img {
        max-width: 120px;
    }

    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="logo navbar-brand" href="index.php">
                <div class="logo-icon">
                    <i class="fas fa-infinity"></i>
                </div>
                <span class="logo-text">LIFE-SYNC</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#reviews">Reviews</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-2" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-2" href="register.php">Sign-Up</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Sync Your Life, Simplify Your Day</h1>
                    <p class="lead mb-4">One app to manage your tasks, expenses, health, and schedule. Stay organized and focused on what matters most.</p>
                    <button class="btn btn-primary btn-lg" onclick="window.location.href='login.php'">Get Started</button>
                </div>
                <div class="col-lg-6">
                    <div id="carouselExample" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active" data-bs-interval="1900">
                                <img src="images/slide1.jpg" class="d-block w-100" alt="Image 1" width="300" height="390">
                            </div>
                            <div class="carousel-item" data-bs-interval="1900">
                                <img src="images/slide2.jpg" class="d-block w-100" alt="Image 2" width="300" height="390">
                            </div>
                            <div class="carousel-item" data-bs-interval="1900">
                                <img src="images/slide3.jpg" class="d-block w-100" alt="Image 3" width="300" height="390">
                            </div>
                            <div class="carousel-item" data-bs-interval="1900">
                              <img src="images/slide4.webp" class="d-block w-100" alt="Image 4" width="300" height="390">
                          </div>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExample" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carouselExample" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="text-center mb-5">Our Features</h2>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h4>To-Do List</h4>
                        <p>Smart task management with reminders and priorities.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h4>Expense Tracker</h4>
                        <p>Monitor spending and manage budgets efficiently.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <h4>Health Reminders</h4>
                        <p>Stay healthy with timely wellness reminders.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h4>Calendar</h4>
                        <p>Seamlessly sync and manage your schedule.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- Testimonials Section -->
<section id="reviews" class="testimonials-section">
  <div class="container text-center py-5">
      <div class="row align-items-center">
          <div class="col-md-4">
              <blockquote class="blockquote">
                  <p>“Simple, straightforward, and super powerful”</p>
              </blockquote>
              <img src="https://static.vecteezy.com/system/resources/previews/002/820/536/non_2x/fake-tattoo-black-glyph-icon-decorative-image-which-is-applied-to-skin-temporary-special-type-of-body-decals-beautiful-painting-silhouette-symbol-on-white-space-isolated-illustration-vector.jpg" alt="The Verge Logo" class="img-fluid mt-3">
          </div>
          <div class="col-md-4">
              <blockquote class="blockquote">
                  <p>“Effortlessly manage tasks, expenses, and health reminders.”</p>
              </blockquote>
              <img src="https://cdn.shopify.com/app-store/listing_images/05d3dad30666912ea0d52df60e35de42/icon/CKq_tMP0lu8CEAE=.png" alt="PC Mag Logo" class="img-fluid mt-3">
          </div>
          <div class="col-md-4">
              <blockquote class="blockquote">
                  <p>“Nothing short of stellar”</p>
              </blockquote>
              <img src="https://img.freepik.com/free-vector/mail-illustration_24908-54790.jpg" alt="TechRadar Logo" class="img-fluid mt-3">
          </div>
      </div>
  </div>

  <div class="container">
    <div class="row justify-content-end">
        <div class="col-md-3 mb-4">
            <h3 class="footer-title">Resources</h3>
            <ul class="footer-links">
                <li><a href="#">Download Apps</a></li>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Productivity Methods</a></li>
                <li><a href="#">Integrations</a></li>
                <li><a href="#">Channel Partners</a></li>
                <li><a href="#">Developer API</a></li>
                <li><a href="#">Status</a></li>
            </ul>
        </div>
        <div class="col-md-3 mb-4">
            <h3 class="footer-title">Company</h3>
            <ul class="footer-links">
                <li><a href="#">About Us</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="#">Inspiration Hub</a></li>
                <li><a href="#">Press</a></li>
                <li><a href="#">Twist</a></li>
            </ul>
        </div>
        <div class="col-md-3 mb-4">
            <h3 class="footer-title">Connect With Us</h3>
            <div class="social-links">
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-youtube"></i></a>
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </div>
</div>
<p align="center" class="mt-4">&copy; 2025 LIFE-SYNC. All rights reserved.</p>

        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>
