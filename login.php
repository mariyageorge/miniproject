<?php
include 'connect.php';
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Fetch user with active status
    $sql = "SELECT * FROM users WHERE username=? AND status='active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        session_start();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Role-based redirection
        if ($user['role'] === 'admin') {
            header('Location: admindash.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        $errorMessage = "Invalid username, password, or account inactive!";
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LIFE-SYNC</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
     :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --nude-300: #DBBFAE;
            --nude-400: #C6A792;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--nude-100);
        }

        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 0.7rem 0;
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

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--brown-primary);
            margin: 0;
        }

        .btn-primary {
            background-color: var(--brown-primary);
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--brown-hover);
            transform: translateY(-2px);
        }

        .login-container {
            background-color: white;
            padding: 30px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 320px;
            margin: 100px auto;
            animation: slideIn 0.6s ease-in-out forwards;
            transform: translateY(-50px);
            opacity: 0;
        }

        @keyframes slideIn {
            0% {
                transform: translateY(-50px);
                opacity: 0;
            }
            100% {
                transform: translateY(20px);
                opacity: 1;
            }
        }

        h3 {
            color: var(--brown-primary);
            margin-bottom: 20px;
        }

        .input-group {
            margin-bottom: 15px;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 10px 40px 10px 12px;
            border: 1px solid var(--nude-300);
            border-radius: 8px;
            background-color: var(--nude-100);
            font-size: 14px;
        }

        .input-group i {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--nude-400);
        }
        @keyframes shine {
            to {
                left: 100%;
            }
        }

        .input-group input:focus {
            outline: none;
            border-color: var(--brown-primary);
        }

        .btn-primary {
            width: 100%;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: -100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shine 2s infinite;
        }
        .links {
            margin-top: 15px;
            text-align: center;
        }

        .links a {
            color: var(--brown-primary);
            font-size: 14px;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #ff4c4c;
            font-size: 14px;
            margin-bottom: 10px;
        }
</style>
<body>
    
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
                    <li class="nav-item"><a class="btn btn-primary ms-2" href="login.php">Login</a></li>&nbsp;
                    <li class="nav-item"><a class="btn btn-primary ms-2" href="register.php">Sign-Up</a></li>
                </ul>
            </div>
        </div>
    </nav>

    
    <div class="login-container">
        <h3>Login to LIFE-SYNC</h3>

       
        <?php if ($errorMessage): ?>
            <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
                <i class="fas fa-user"></i>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fas fa-lock"></i>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <div class="links">
            <a href="forgotpassword.php">Forgot password?</a><br>
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>