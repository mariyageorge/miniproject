<?php
include 'connect.php';  
$database_name = "lifesync_db";  
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

$errors = [
    'username' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

   
    if (strlen($username) < 6) {
        $errors['username'] = 'Username must be at least 6 characters long';
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    } else {
        
        $checkUsernameQuery = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $checkUsernameQuery);
        if (mysqli_num_rows($result) > 0) {
            $errors['username'] = 'Username is already taken';
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        $checkEmailQuery = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $checkEmailQuery);
        if (mysqli_num_rows($result) > 0) {
            $errors['email'] = 'Email is already registered';
        }
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    } elseif (!preg_match("/[a-z]/", $password) || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number';
    }

  
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

  
    if (empty(array_filter($errors))) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $insertQuery = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashedPassword')";
        if ($username) { 
            $errors['username'] = "Username already taken.";
        }
        if (mysqli_query($conn, $insertQuery)) {
           echo "Registration successful! Directing to login page...";
            header("refresh:1;url=login.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | LIFE-SYNC</title>
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

.navbar {
    background-color: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: .5rem 0;
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
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--nude-100);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .register-container {
            background-color: white;
            padding: 22px 17px;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.15);
            width: 100%;
            max-width: 350px;
            transform: translateY(40px);
            opacity: 0;
            animation: slideIn 0.5s ease forwards;
        }

        @keyframes slideIn {
            to {
                transform: translateY(40px);
                opacity: 1;
            }
        }

        .form-title {
            color: var(--brown-primary);
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 16px;
            position: relative;
        }

        .form-control {
            border: 2px solid var(--nude-200);
            border-radius: 8px;
            padding: 10px 10px 10px 40px;
            width: 100%;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brown-primary);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .form-group i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brown-primary);
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        .form-control:focus + i {
            opacity: 1;
        }

        .form-control::placeholder {
            color: var(--nude-500);
        }

        .password-strength {
            height: 3px;
            background-color: var(--nude-200);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .btn-primary {
            background-color: var(--brown-primary);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            background-color: var(--brown-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 69, 19, 0.2);
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

        @keyframes shine {
            to {
                left: 100%;
            }
        }

        .login-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.9rem;
            color: var(--nude-500);
        }

        .login-link a {
            color: var(--brown-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: 40px;
            background-image: none;
        }
</style>
</head>
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

<div class="register-container">
    <h2 class="form-title">Create Account</h2>
    <form method="POST" id="registerForm" novalidate>
    <div class="form-group">
    <input type="text" class="form-control <?php echo !empty($errors['username']) ? 'is-invalid' : ''; ?>" 
           name="username" 
           value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" 
           placeholder="Username" required>
    <i class="fas fa-user"></i>
    <div class="error-message" style="display: <?php echo !empty($errors['username']) ? 'block' : 'none'; ?>;">
        <?php echo $errors['username']; ?>
    </div>
</div>

<div class="form-group">
    <input type="email" class="form-control <?php echo !empty($errors['email']) ? 'is-invalid' : ''; ?>" 
           name="email" 
           value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" 
           placeholder="Email Address" required>
    <i class="fas fa-envelope"></i>
    <div class="error-message" style="display: <?php echo !empty($errors['email']) ? 'block' : 'none'; ?>;">
        <?php echo $errors['email']; ?>
    </div>
</div>


<div class="form-group">
    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" 
           name="password" 
           value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>" 
           placeholder="Password" required>
    <i class="fas fa-lock"></i>
    <div class="password-strength">
        <div class="password-strength-bar" id="password-strength-bar"></div>
    </div>
    <div class="error-message" style="display: <?php echo !empty($errors['password']) ? 'block' : 'none'; ?>;">
        <?php echo $errors['password']; ?>
    </div>
</div>


<div class="form-group">
    <input type="password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
           name="confirm_password" 
           value="<?php echo isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; ?>" 
           placeholder="Confirm Password" required>
    <i class="fas fa-key"></i>
    <div class="error-message" style="display: <?php echo !empty($errors['confirm_password']) ? 'block' : 'none'; ?>;">
      <?php echo $errors['confirm_password']; ?>
    </div>
</div>


        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

</body>
</html>
