<?php
session_start();
include 'connect.php';

$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

$errorMessage = '';

// ✅ Handle Traditional Username & Password Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Fetch user details
    $sql = "SELECT * FROM users WHERE username=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $errorMessage = "Username does not exist. Please register.";
    } elseif ($user['status'] !== 'active') {
        $errorMessage = "Account is temporarily deleted. Please contact admin.";
    } elseif (!password_verify($password, $user['password'])) {
        $errorMessage = "Incorrect password. Please try again.";
    } else {
        // ✅ Login successful, set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Role-based redirection
        if ($user['role'] === 'admin') {
            header('Location: admindash.php');
        } else {
            header('Location: dashboard.php');
        }
        exit();
    }
}

// ✅ Handle Google Sign-In
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_signin'])) {
    header('Content-Type: application/json');

    try {
        // Get data from Google sign-in
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $username = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $google_id = filter_var($_POST['google_id'], FILTER_SANITIZE_STRING);

        // Check if user already exists
        $stmt = $conn->prepare("SELECT user_id, username, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // ✅ User exists, log them in
            if (strtolower($user['status']) !== 'active') {
                echo json_encode(["status" => "error", "message" => "Your account is inactive. Please contact the administrator."]);
                exit();
            }

            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user'; // Default to 'user' if role is not set

            // Role-based redirection
            $redirect_page = ($user['role'] === 'admin') ? 'admindash.php' : 'dashboard.php';

            echo json_encode([
                "status" => "success",
                "message" => "Logged in successfully",
                "is_new_user" => false,
                "redirect" => $redirect_page
            ]);
        } else {
            // ❗ New user, register them
            $random_password = bin2hex(random_bytes(16)); // Secure random password
            $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);
            $default_role = 'user';
            $status = 'active';

            $stmt = $conn->prepare("INSERT INTO users (username, password, email, google_id, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $username, $hashed_password, $email, $google_id, $default_role, $status);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $default_role;

                echo json_encode([
                    "status" => "success",
                    "message" => "Account created successfully",
                    "is_new_user" => true,
                    "redirect" => "dashboard.php"
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error creating account: " . $stmt->error]);
            }

            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
    exit();
}

mysqli_close($conn);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | LIFE-SYNC</title>
    <script src="main.js" defer type="module"></script>
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
        .or-divider {
    display: flex;
    align-items: center;
    text-align: center;
    margin: 15px 0;
}

.or-divider::before,
.or-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: var(--nude-200);
    margin: 0 10px;
}

.or-divider span {
    font-size: 14px;
    color: var(--nude-500);
}

.btn-google {
    width: 100%;
    background-color: white;
    border: 2px solid var(--nude-300);
    color: var(--brown-primary);
    padding: 12px;
    border-radius: 8px;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-google:hover {
    background-color: var(--nude-200);
    border-color: var(--brown-hover);
}

.btn-google i {
    font-size: 20px;
    color: #DB4437; /* Google Red */
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
            <div class="or-divider">
    <span>OR</span>
</div>

<button id="googleSignInBtn" class="btn-google">
    <i class="fab fa-google"></i> Sign in with Google
</button>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>