<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$message = '';
$validation_errors = [];

// Fetch user data
$sql = "SELECT username, email, phone, profile_pic, status FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['status'] !== 'active') {
    session_destroy(); // Log out inactive users
    header("Location: login.php");
    exit();
}

// PHP Validation Functions
function validateUsername($username) {
    if (empty($username)) {
        return "Username is required";
    }
    if (strlen($username) < 3) {
        return "Username must be at least 3 characters long";
    }
    if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        return "Username can only contain letters, numbers, and underscores";
    }
    return "";
}

function validateEmail($email) {
    if (empty($email)) {
        return "Email is required";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address";
    }
    return "";
}

function validatePhone($phone) {
    // Remove any spaces or special characters
    $phone = preg_replace("/[^0-9]/", "", $phone);

    // Ensure exactly 10 digits and starts with 6, 7, 8, or 9
    if (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
        return "Invalid phone number. It must start with 6, 7, 8, or 9 and be exactly 10 digits.";
    }

    // Check for repeated digits (0000000000, 1111111111, etc.)
    if (preg_match("/^(\d)\1{9}$/", $phone)) {
        return "Invalid phone number. All digits cannot be the same.";
    }

    return ""; // Valid phone number
}


function validatePassword($password) {
    if (empty($password)) {
        return "Password is required";
    }
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long";
    }
    if (!preg_match("/[A-Z]/", $password)) {
        return "Password must contain at least one uppercase letter";
    }
    if (!preg_match("/[a-z]/", $password)) {
        return "Password must contain at least one lowercase letter";
    }
    if (!preg_match("/[0-9]/", $password)) {
        return "Password must contain at least one number";
    }
    if (!preg_match("/[^A-Za-z0-9]/", $password)) {
        return "Password must contain at least one special character";
    }
    return "";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Validate input data
        $name_error = validateUsername($name);
        $email_error = validateEmail($email);
        $phone_error = validatePhone($phone);
        
        // Store validation errors
        if (!empty($name_error)) $validation_errors['name'] = $name_error;
        if (!empty($email_error)) $validation_errors['email'] = $email_error;
        if (!empty($phone_error)) $validation_errors['phone'] = $phone_error;
        
        // Only proceed if validation passes
        if (empty($validation_errors)) {
            // Handle profile picture upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['profile_pic']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $upload_path = 'uploads/' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                        $sql = "UPDATE users SET username=?, email=?, phone=?, profile_pic=? WHERE username=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssss", $name, $email, $phone, $upload_path, $username);
                    } else {
                        $message = "Error uploading file.";
                    }
                } else {
                    $message = "Invalid file type.";
                }
            } else {
                $sql = "UPDATE users SET username=?, email=?, phone=? WHERE username=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $name, $email, $phone, $username);
            }
            
            if (isset($stmt) && $stmt->execute()) {
                $message = "Profile updated successfully!";
                $_SESSION['username'] = $name; // Update session username
            
                // Fetch updated user data
                $sql = "SELECT username, email, phone, profile_pic FROM users WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $name);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $message = "Error updating profile.";
            }
        } else {
            $message = "Please correct the errors in the form.";
        }
    }

    if (isset($_POST['change_password'])) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        // Validate passwords
        if (empty($current_password)) {
            $validation_errors['current_password'] = "Current password is required";
        }
        
        $new_password_error = validatePassword($new_password);
        if (!empty($new_password_error)) {
            $validation_errors['new_password'] = $new_password_error;
        }
        
        if ($new_password !== $confirm_password) {
            $validation_errors['confirm_password'] = "Passwords do not match";
        }
        
        // Only proceed if validation passes
        if (empty($validation_errors)) {
            // Verify current password
            $sql = "SELECT password FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            if (password_verify($current_password, $user_data['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE username = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $hashed_password, $username);
                
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                } else {
                    $message = "Error changing password.";
                }
            } else {
                $validation_errors['current_password'] = "Current password is incorrect";
                $message = "Current password is incorrect.";
            }
        } else {
            $message = "Please correct the errors in the form.";
        }
    }

    if (isset($_POST['delete_account'])) {
        // Set account status to "inactive"
        $sql = "UPDATE users SET status='inactive' WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);

        if ($stmt->execute()) {
            session_destroy(); // Log out user immediately
            header("Location: login.php");
            exit();
        } else {
            $message = "Error deactivating account.";
        }
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LIFE-SYNC</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --primary-color: #8B4513;
            --hover-color: #A0522D;
            --bg-color: #F5ECE5;
            --text-color: #333;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            color: var(--text-color);
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: #8B4513;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: bold;
            color:  #8B4513;
            letter-spacing: 1px;
        }


        .back-button {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background: var(--hover-color);
        }

        .profile-container {
            max-width: 600px;
            margin: 100px auto 40px;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-pic-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1.5rem;
        }

        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .camera-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .camera-icon:hover {
            background: var(--hover-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-control.error {
            border-color: var(--error-color);
        }

        .validation-error {
            color: var(--error-color);
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
            margin-bottom: 1rem;
        }

        .submit-btn:hover {
            background: var(--hover-color);
        }

        .message {
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
        }

        .message.success {
            background: var(--success-color);
            color: white;
        }

        .message.error {
            background: var(--error-color);
            color: white;
        }

        #profile_pic_input {
            display: none;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: #777;
        }

        .password-container {
            position: relative;
        }

        .section-divider {
            margin: 2rem 0;
            border-top: 1px solid #ddd;
            position: relative;
        }

        .section-title {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 10px;
            font-weight: 500;
        }

        .password-strength {
            height: 5px;
            background: #ddd;
            margin-top: 5px;
            border-radius: 3px;
        }

        .password-strength-text {
            font-size: 0.8rem;
            margin-top: 5px;
        }

        .password-strength.weak {
            background: var(--error-color);
            width: 25%;
        }

        .password-strength.medium {
            background: #ffc107;
            width: 50%;
        }

        .password-strength.strong {
            background: #17a2b8;
            width: 75%;
        }

        .password-strength.very-strong {
            background: var(--success-color);
            width: 100%;
        }

        .tab-container {
            display: flex;
            margin-bottom: 1.5rem;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            cursor: pointer;
            border-bottom: 2px solid #ddd;
            transition: all 0.3s ease;
        }

        .tab.active {
            border-bottom: 2px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .delete-btn {
            background-color: var(--error-color);
        }

        .delete-btn:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>

<header class="header">
    <div class="logo-section">
        <div class="logo-icon">
            <i class="fas fa-infinity"></i>
        </div>
        <span class="logo-text">LIFE-SYNC</span>
    </div>
    <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> 
    </a>
</header>

<div class="profile-container">
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'correct') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-header">
        <div class="profile-pic-container">
            <img src="<?php echo !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'images/default-avatar.png'; ?>" 
                 alt="Profile Picture" 
                 class="profile-pic" 
                 id="profile_pic_preview">
            <label for="profile_pic_input" class="camera-icon">
                <i class="fas fa-camera"></i>
            </label>
        </div>
        <h2>Edit Profile</h2>
    </div>

    <div class="tab-container">
        <div class="tab active" data-target="profile-form">Profile Information</div>
        <div class="tab" data-target="password-form">Change Password</div>
    </div>

    <div id="profile-form" class="form-section active">
        <form method="POST" enctype="multipart/form-data" id="profile_form">
            <input type="file" 
                   id="profile_pic_input" 
                   name="profile_pic" 
                   accept="image/*" 
                   onchange="previewImage(this)">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-control <?php echo isset($validation_errors['name']) ? 'error' : ''; ?>" 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($user['username']); ?>" 
                       required>
                <?php if (isset($validation_errors['name'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['name']); ?></span>
                <?php endif; ?>
                <span id="name-error" class="validation-error" style="display: none;"></span>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-control <?php echo isset($validation_errors['email']) ? 'error' : ''; ?>" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user['email']); ?>" 
                       required>
                <?php if (isset($validation_errors['email'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['email']); ?></span>
                <?php endif; ?>
                <span id="email-error" class="validation-error" style="display: none;"></span>
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" 
                       id="phone" 
                       name="phone" 
                       class="form-control <?php echo isset($validation_errors['phone']) ? 'error' : ''; ?>" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($user['phone'] ?? ''); ?>" 
                       pattern="[0-9]{10}">
                <?php if (isset($validation_errors['phone'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['phone']); ?></span>
                <?php endif; ?>
                <span id="phone-error" class="validation-error" style="display: none;"></span>
            </div>

            <button type="submit" name="update_profile" class="submit-btn">
                Save Changes
            </button>
        </form>
        
        <form method="POST" id="delete_form" onsubmit="return confirmDelete()">
            <button type="submit" name="delete_account" class="submit-btn delete-btn">
                Delete Account
            </button>
        </form>
    </div>

    <div id="password-form" class="form-section">
        <form method="POST" id="password_form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-container">
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           class="form-control <?php echo isset($validation_errors['current_password']) ? 'error' : ''; ?>" 
                           required>
                    <span class="password-toggle" onclick="togglePassword('current_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <?php if (isset($validation_errors['current_password'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['current_password']); ?></span>
                <?php endif; ?>
                <span id="current-password-error" class="validation-error" style="display: none;"></span>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-container">
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           class="form-control <?php echo isset($validation_errors['new_password']) ? 'error' : ''; ?>" 
                           required>
                    <span class="password-toggle" onclick="togglePassword('new_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="password-strength" id="password-strength"></div>
                <div class="password-strength-text" id="password-strength-text"></div>
                <?php if (isset($validation_errors['new_password'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['new_password']); ?></span>
                <?php endif; ?>
                <span id="new-password-error" class="validation-error" style="display: none;"></span>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-container">
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="form-control <?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>" 
                           required>
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <?php if (isset($validation_errors['confirm_password'])): ?>
                    <span class="validation-error"><?php echo htmlspecialchars($validation_errors['confirm_password']); ?></span>
                <?php endif; ?>
                <span id="confirm-password-error" class="validation-error" style="display: none;"></span>
            </div>

            <button type="submit" name="change_password" class="submit-btn">
                Change Password
            </button>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('profile_pic_preview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function checkPasswordStrength(password) {
    let strength = 0;
    const strengthBar = document.getElementById('password-strength');
    const strengthText = document.getElementById('password-strength-text');
    
    // Empty password
    if (password.length === 0) {
        strengthBar.className = 'password-strength';
        strengthText.textContent = '';
        return;
    }
    
    // Length check
    if (password.length >= 8) strength += 1;
    if (password.length >= 12) strength += 1;
    
    // Character type checks
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^A-Za-z0-9]/.test(password)) strength += 1;
    
    // Set the strength indicator
    if (strength <= 2) {
        strengthBar.className = 'password-strength weak';
        strengthText.textContent = 'Weak';
        strengthText.style.color = '#dc3545';
    } else if (strength <= 4) {
        strengthBar.className = 'password-strength medium';
        strengthText.textContent = 'Medium';
        strengthText.style.color = '#ffc107';
    } else if (strength <= 5) {
        strengthBar.className = 'password-strength strong';
        strengthText.textContent = 'Strong';
        strengthText.style.color = '#17a2b8';
    } else {
        strengthBar.className = 'password-strength very-strong';
        strengthText.textContent = 'Very Strong';
        strengthText.style.color = '#28a745';
    }
}

// Tab functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all form sections
            document.querySelectorAll('.form-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show the corresponding form section
            const targetId = this.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });
    
    // Client-side validation
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    const nameError = document.getElementById('name-error');
    const emailError = document.getElementById('email-error');
    const phoneError = document.getElementById('phone-error');
    const newPasswordError = document.getElementById('new-password-error');
    const confirmPasswordError = document.getElementById('confirm-password-error');
    
    const profileForm = document.getElementById('profile_form');
    const passwordForm = document.getElementById('password_form');
    
    // Validation functions
    function validateUsername(username) {
        if (username === '') {
            return "Username is required";
        }
        
        if (username.length < 3) {
            return "Username must be at least 3 characters long";
        }
        
        // Match PHP validation: only alphanumeric and underscore
        const pattern = /^[a-zA-Z0-9_]+$/;
        if (!pattern.test(username)) {
            return "Username can only contain letters, numbers, and underscores";
        }
        
        return "";
    }
    
    function validateEmail(email) {
        if (email === '') {
            return "Email is required";
        }
        
        // Basic email validation
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!pattern.test(email)) {
            return "Please enter a valid email address";
        }
        
        return "";
    }
    
    function validatePhone(phone) {
        if (phone === '') {
            return ""; // Phone is optional
        }
        
        // Must be exactly 10 digits
        const pattern = /^[0-9]{10}$/;
        if (!pattern.test(phone)) {
            return "Phone number must be exactly 10 digits";
        }
        
        return "";
    }
    
    function validatePassword(password) {
        if (password === '') {
            return "Password is required";
        }
        
        if (password.length < 8) {
            return "Password must be at least 8 characters long";
        }
        
        if (!/[A-Z]/.test(password)) {
            return "Password must contain at least one uppercase letter";
        }
        
        if (!/[a-z]/.test(password)) {
            return "Password must contain at least one lowercase letter";
        }
        
        if (!/[0-9]/.test(password)) {
            return "Password must contain at least one number";
        }
        
        if (!/[^A-Za-z0-9]/.test(password)) {
            return "Password must contain at least one special character";
        }
        
        return "";
    }
    
    function validateConfirmPassword(password, confirmPassword) {
        if (confirmPassword === '') {
            return "Please confirm your password";
        }
        
        if (password !== confirmPassword) {
            return "Passwords do not match";
        }
        
        return "";
    }
    
    // Real-time validation
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            const error = validateUsername(this.value.trim());
            
            if (error) {
                nameError.textContent = error;
                nameError.style.display = 'block';
                this.classList.add('error');
            } else {
                nameError.style.display = 'none';
                this.classList.remove('error');
            }
        });
    }
    
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            const error = validateEmail(this.value.trim());
            
            if (error) {
                emailError.textContent = error;
                emailError.style.display = 'block';
                this.classList.add('error');
            } else {
                emailError.style.display = 'none';
                this.classList.remove('error');
            }
        });
    }
    
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            const error = validatePhone(this.value.trim());
            
            if (error) {
             


                phoneError.textContent = error;
phoneError.style.display = 'block';
this.classList.add('error');
} else {
    phoneError.style.display = 'none';
    this.classList.remove('error');
}
        });
    }
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            
            const error = validatePassword(this.value);
            
            if (error) {
                newPasswordError.textContent = error;
                newPasswordError.style.display = 'block';
                this.classList.add('error');
            } else {
                newPasswordError.style.display = 'none';
                this.classList.remove('error');
            }
            
            // Also check confirm password match
            if (confirmPasswordInput.value) {
                const confirmError = validateConfirmPassword(this.value, confirmPasswordInput.value);
                
                if (confirmError) {
                    confirmPasswordError.textContent = confirmError;
                    confirmPasswordError.style.display = 'block';
                    confirmPasswordInput.classList.add('error');
                } else {
                    confirmPasswordError.style.display = 'none';
                    confirmPasswordInput.classList.remove('error');
                }
            }
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const error = validateConfirmPassword(newPasswordInput.value, this.value);
            
            if (error) {
                confirmPasswordError.textContent = error;
                confirmPasswordError.style.display = 'block';
                this.classList.add('error');
            } else {
                confirmPasswordError.style.display = 'none';
                this.classList.remove('error');
            }
        });
    }
    
    // Form submission validation
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const nameValue = nameInput.value.trim();
            const emailValue = emailInput.value.trim();
            const phoneValue = phoneInput.value.trim();
            
            const nameError = validateUsername(nameValue);
            const emailError = validateEmail(emailValue);
            const phoneError = validatePhone(phoneValue);
            
            if (nameError || emailError || phoneError) {
                e.preventDefault();
                
                if (nameError) {
                    document.getElementById('name-error').textContent = nameError;
                    document.getElementById('name-error').style.display = 'block';
                    nameInput.classList.add('error');
                }
                
                if (emailError) {
                    document.getElementById('email-error').textContent = emailError;
                    document.getElementById('email-error').style.display = 'block';
                    emailInput.classList.add('error');
                }
                
                if (phoneError) {
                    document.getElementById('phone-error').textContent = phoneError;
                    document.getElementById('phone-error').style.display = 'block';
                    phoneInput.classList.add('error');
                }
            }
        });
    }
    
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            const currentPasswordValue = document.getElementById('current_password').value;
            const newPasswordValue = newPasswordInput.value;
            const confirmPasswordValue = confirmPasswordInput.value;
            
            const newPasswordError = validatePassword(newPasswordValue);
            const confirmError = validateConfirmPassword(newPasswordValue, confirmPasswordValue);
            
            if (!currentPasswordValue || newPasswordError || confirmError) {
                e.preventDefault();
                
                if (!currentPasswordValue) {
                    document.getElementById('current-password-error').textContent = "Current password is required";
                    document.getElementById('current-password-error').style.display = 'block';
                    document.getElementById('current_password').classList.add('error');
                }
                
                if (newPasswordError) {
                    document.getElementById('new-password-error').textContent = newPasswordError;
                    document.getElementById('new-password-error').style.display = 'block';
                    newPasswordInput.classList.add('error');
                }
                
                if (confirmError) {
                    document.getElementById('confirm-password-error').textContent = confirmError;
                    document.getElementById('confirm-password-error').style.display = 'block';
                    confirmPasswordInput.classList.add('error');
                }
            }
        });
    }
});

function confirmDelete() {
    return confirm("Are you sure you want to delete your account? This action cannot be undone.");
}
</script>

</body>
</html>