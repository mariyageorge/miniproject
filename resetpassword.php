<?php
require 'connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

// Function to safely log debug messages
function debugLog($message, $data = null) {
    error_log("Reset Password Debug - " . $message . ($data ? ": " . print_r($data, true) : ""));
}

// Handle Password Reset Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['token']) || !isset($input['password'])) {
        debugLog("Missing token or password in POST request");
        echo json_encode(["status" => "error", "message" => "Missing required information."]);
        exit;
    }

    $token = trim($input['token']);
    $password = trim($input['password']);
    $current_time = date('Y-m-d H:i:s');

    debugLog("Processing password reset for token", $token);

    // Validate token
    $stmt = $conn->prepare("SELECT email FROM users WHERE reset_token = ? AND reset_expiry > ?");
    if (!$stmt) {
        debugLog("Database error preparing statement", $conn->error);
        echo json_encode(["status" => "error", "message" => "Database error. Please try again."]);
        exit;
    }

    $stmt->bind_param("ss", $token, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        debugLog("Invalid or expired token", $token);
        echo json_encode(["status" => "error", "message" => "Invalid or expired reset token."]);
        exit;
    }

    $user = $result->fetch_assoc();
    $email = $user['email'];
    $stmt->close();

    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Update password and clear reset token
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE email = ?");
    if (!$update_stmt) {
        debugLog("Database error preparing update statement", $conn->error);
        echo json_encode(["status" => "error", "message" => "Database error occurred. Please try again."]);
        exit;
    }

    $update_stmt->bind_param("ss", $hashed_password, $email);
    
    if ($update_stmt->execute()) {
        debugLog("Password successfully reset for", $email);
        echo json_encode(["status" => "success", "message" => "Password has been reset successfully!"]);
    } else {
        debugLog("Error updating password", $conn->error);
        echo json_encode(["status" => "error", "message" => "Error updating password. Try again."]);
    }

    $update_stmt->close();
    exit;
}

// Validate Token Before Showing Form
if (isset($_GET["token"])) {
    $token = trim($_GET["token"]);
    $current_time = date('Y-m-d H:i:s');

    debugLog("Checking reset token", $token);

    $stmt = $conn->prepare("SELECT reset_expiry FROM users WHERE reset_token = ?");
    if (!$stmt) {
        debugLog("Database error", $conn->error);
        die("Database error occurred. Please try again.");
    }

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        debugLog("Invalid or expired token", $token);
        die("This password reset link is invalid. Please request a new one.");
    }

    $data = $result->fetch_assoc();
    $stmt->close();

    // Check if the token is expired
    if ($data['reset_expiry'] < $current_time) {
        debugLog("Token expired", ["expires" => $data['reset_expiry'], "current" => $current_time]);
        die("This password reset link has expired. Please request a new one.");
    }

    debugLog("Token is valid, displaying reset form");
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
            min-height: 100vh;
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
            max-width: 280px;
            height: 32px;
            margin: 8px auto;
            width: 100%;
            padding: 8px;
            font-size: 14px;
            border: 1px solid var(--secondary-color);
            border-radius: 6px;
            background-color: white;
            transition: border-color 0.3s ease;
        }

        .input-group {
            position: relative;
            margin-bottom: 16px;
            width: 100%;
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
        }

        .validation-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
        }

        .input-field.valid {
            border-color: #4caf50;
        }

        .input-field.invalid {
            border-color: #ff4c4c;
        }

        .validation-message {
            margin-bottom: 12px;
            font-size: 11px;
            text-align: left;
            padding-left: 10px;
            color: #ff4c4c;
            min-height: 15px;
        }

        .btn-submit {
            width: 100%;
            max-width: 280px;
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
            margin: 20px auto;
        }

        .btn-submit.loading {
            background-color: var(--secondary-color);
            cursor: not-allowed;
        }

        .btn-submit i {
            display: none;
            margin-left: 10px;
        }

        .error-message, .success-message {
            margin: 10px 0;
            font-size: 14px;
            text-align: center;
            min-height: 20px;
        }

        .error-message {
            color: #ff4c4c;
        }

        .success-message {
            color: #4caf50;
        }

        .links {
            margin-top: 15px;
        }

        .links a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: var(--accent-color);
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
            
            <div class="input-group">
                <input type="password" id="newPassword" class="input-field" placeholder="Enter new password" required>
                <i class="validation-icon fas"></i>
                <div class="validation-message" id="passwordValidation"></div>
            </div>

            <div class="input-group">
                <input type="password" id="confirmPassword" class="input-field" placeholder="Confirm new password" required>
                <i class="validation-icon fas"></i>
                <div class="validation-message" id="confirmPasswordValidation"></div>
            </div>

            <div class="error-message" id="errorMessage"></div>
            <div class="success-message" id="successMessage"></div>

            <button type="submit" class="btn-submit">
                Reset Password
                <i class="fas fa-spinner fa-spin"></i>
            </button>
        </form>

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
        const confirmPasswordValidation = document.getElementById('confirmPasswordValidation');
        const submitButton = document.querySelector('.btn-submit');
        const spinnerIcon = submitButton.querySelector('i');

        function validatePassword(password) {
            const conditions = {
                length: password.length >= 8,
                lowercase: /[a-z]/.test(password),
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*]/.test(password)
            };

            let messages = [];
            if (!conditions.length) messages.push("At least 8 characters");
            if (!conditions.lowercase) messages.push("One lowercase letter");
            if (!conditions.uppercase) messages.push("One uppercase letter");
            if (!conditions.number) messages.push("One number");
            if (!conditions.special) messages.push("One special character (!@#$%^&*)");

            return {
                isValid: Object.values(conditions).every(condition => condition),
                messages: messages
            };
        }

        newPasswordInput.addEventListener('input', () => {
            const validation = validatePassword(newPasswordInput.value);
            
            // Update validation message immediately
            passwordValidation.innerHTML = validation.messages.length ? 
                "Required: " + validation.messages.join(", ") : "";
            
            // Update input styling
            newPasswordInput.classList.toggle('valid', validation.isValid);
            newPasswordInput.classList.toggle('invalid', !validation.isValid && newPasswordInput.value.length > 0);

            // Check confirm password match if it has a value
            if (confirmPasswordInput.value) {
                validateConfirmPassword();
            }
        });

        function validateConfirmPassword() {
            const isMatch = newPasswordInput.value === confirmPasswordInput.value;
            
            // Update validation message immediately
            confirmPasswordValidation.textContent = !isMatch && confirmPasswordInput.value ? 
                "Passwords do not match" : "";
            
            // Update input styling
            confirmPasswordInput.classList.toggle('valid', isMatch && confirmPasswordInput.value.length > 0);
            confirmPasswordInput.classList.toggle('invalid', !isMatch && confirmPasswordInput.value.length > 0);
            
            return isMatch;
        }

        confirmPasswordInput.addEventListener('input', validateConfirmPassword);

        resetForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const passwordValidation = validatePassword(newPasswordInput.value);
            const passwordsMatch = validateConfirmPassword();

            if (!passwordValidation.isValid || !passwordsMatch) {
                errorMessage.textContent = "Please fix the validation errors before submitting.";
                return;
            }

            errorMessage.textContent = "";
            successMessage.textContent = "";
            submitButton.classList.add('loading');
            spinnerIcon.style.display = "inline-block";

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        token: document.getElementById('token').value,
                        password: confirmPasswordInput.value
                    })

                });

const data = await response.json();

if (data.status === 'success') {
    successMessage.textContent = data.message;
    setTimeout(() => window.location.href = "login.php", 2000);
} else {
    errorMessage.textContent = data.message || "An error occurred. Please try again.";
    submitButton.classList.remove('loading');
    spinnerIcon.style.display = "none";
}
} catch (error) {
errorMessage.textContent = "An error occurred. Please try again.";
submitButton.classList.remove('loading');
spinnerIcon.style.display = "none";
}
});
    </script>
</body>
</html>
