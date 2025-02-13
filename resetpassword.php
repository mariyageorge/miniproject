<?php
require 'connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

// Function to safely debug sensitive information
function debugLog($message, $data = null) {
    error_log("Reset Password Debug - " . $message . ($data ? ": " . print_r($data, true) : ""));
}

// Handle POST request for password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['token']) && isset($_POST['password'])) {
        $token = trim($_POST['token']);
        $password = trim($_POST['password']);
        $current_time = date('Y-m-d H:i:s');
        
        debugLog("Processing password reset POST request");
        
        // Verify token is valid and not expired
        $check_token = $conn->prepare("SELECT email FROM users WHERE reset_token = ? AND reset_expiry > ?");
        $check_token->bind_param("ss", $token, $current_time);
        $check_token->execute();
        $result = $check_token->get_result();
        
        if ($result->num_rows === 0) {
            debugLog("Invalid or expired token in POST request");
            echo "Invalid or expired reset token. Please request a new password reset.";
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
        $update_stmt->bind_param("ss", $hashed_password, $token);
        
        if ($update_stmt->execute()) {
            debugLog("Password successfully reset for user: " . $user['email']);
            echo "success";
        } else {
            debugLog("Error updating password", $conn->error);
            echo "Error updating password. Please try again.";
        }
        
        $update_stmt->close();
        exit;
    } else {
        debugLog("Missing token or password in POST request");
        echo "Missing required information.";
        exit;
    }
}

// For GET request
if (isset($_GET["token"])) {
    $token = trim($_GET["token"]);
    $current_time = date('Y-m-d H:i:s');
    
    debugLog("Received token", $token);
    debugLog("Current time", $current_time);

    // First, let's just check if the token exists at all
    $check_token = $conn->prepare("SELECT email, reset_token, reset_expiry FROM users WHERE reset_token = ?");
    if (!$check_token) {
        debugLog("Database error", $conn->error);
        die("Database error occurred. Please try again.");
    }

    $check_token->bind_param("s", $token);
    $check_token->execute();
    $token_result = $check_token->get_result();

    if ($token_result->num_rows === 0) {
        debugLog("Token not found in database");
        die("This password reset link is invalid. Please request a new one.");
    }

    $token_data = $token_result->fetch_assoc();
    debugLog("Token data found", $token_data);

    // Now check if it's expired
    if ($token_data['reset_expiry'] < $current_time) {
        debugLog("Token expired", [
            'expires' => $token_data['reset_expiry'],
            'current' => $current_time
        ]);
        die("This password reset link has expired. Please request a new one.");
    }

    // If we get here, token is valid
    debugLog("Token is valid and not expired");
} else {
    debugLog("No token provided in GET request");
    die("Invalid request. No token provided.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - LIFE-SYNC</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6F4E37;
            --secondary-color: #8B4513;
            --accent-color: #D2691E;
            --card-bg: #F4ECD8;
            --bg-color: #F5DEB3;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .reset-container {
            background-color: var(--card-bg);
            padding: 30px 20px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin: 50px auto;
        }

        .reset-container h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .input-field {
            margin-bottom: 10px;
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid var(--secondary-color);
            border-radius: 6px;
            background-color: white;
        }

        .btn-submit {
            width: 100%;
            padding: 10px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit.loading {
            background-color: var(--secondary-color);
            cursor: not-allowed;
        }

        .btn-submit i {
            display: none;
            margin-left: 10px;
        }

        .error-message, .success-message, .validation-message {
            font-size: 12px;
            margin-top: 5px;
        }

        .error-message {
            color: #ff4c4c;
        }

        .success-message {
            color: #4caf50;
        }

        .validation-message {
            color: #D2691E;
            display: none;
        }

        .links {
            margin-top: 15px;
        }

        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
        }

        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="header-container">
                <a class="logo" href="index.php">
                    <div class="logo-icon"><i class="fas fa-infinity"></i></div>
                    <span class="logo-text">LIFE-SYNC</span>
                </a>
            </div>
        </div>
    </header>

    <div class="reset-container">
        <h2>Reset Your Password</h2>
        <p>Please enter your new password below.</p>

        <form id="resetForm">
            <input type="hidden" id="token" value="<?= htmlspecialchars($token) ?>">

            <input type="password" id="newPassword" class="input-field" placeholder="Enter new password" required>
            <p class="validation-message" id="passwordValidation"></p>

            <input type="password" id="confirmPassword" class="input-field" placeholder="Confirm new password" required>
            <p class="error-message" id="errorMessage"></p>

            <button type="submit" class="btn-submit">
                Reset Password
                <i class="fas fa-spinner fa-spin"></i>
            </button>
        </form>

        <p class="success-message" id="successMessage"></p>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        const resetForm = document.getElementById('resetForm');
        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');
        const passwordValidation = document.getElementById('passwordValidation');
        const submitButton = document.querySelector('.btn-submit');
        const spinnerIcon = submitButton.querySelector('i');

        newPasswordInput.addEventListener('input', () => {
            const password = newPasswordInput.value;
            let message = "";

            if (password.length < 8) {
                message = "Password must be at least 8 characters.";
            } else if (!/[a-z]/.test(password) || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) {
                message = "Password must contain a lowercase, uppercase, and a number.";
            }

            passwordValidation.textContent = message;
            passwordValidation.style.display = message ? "block" : "none";
        });

        resetForm.addEventListener('submit', function(event) {
            event.preventDefault();

            if (passwordValidation.textContent) return;

            errorMessage.textContent = "";
            successMessage.textContent = "";
            submitButton.classList.add('loading');
            spinnerIcon.style.display = "inline-block";

            setTimeout(() => {
                successMessage.textContent = "Password successfully reset!";
                setTimeout(() => window.location.href = "login.php", 2000);
            }, 2000);
        });
    </script>
</body>
</html>
