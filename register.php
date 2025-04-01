<?php
session_start(); // Add session_start() at the beginning
include 'connect.php';  
$database_name = "lifesync_db";  
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

$errors = [
    'username' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => ''
];

// Reset email verification status
//$_SESSION['email_verified'] = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Username validation
    if (strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters long';
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $errors['username'] = 'Username can only contain letters, numbers, and underscores';
    } else {
        $checkUsernameQuery = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $checkUsernameQuery);
        if (mysqli_num_rows($result) > 0) {
            $errors['username'] = 'Username is already taken';
        }
    }

    // Email validation
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $value);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    
        if (mysqli_num_rows($result) > 0) {
            $error = "Email already registered";
        }
    }
    

    // Password validation
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long';
    } elseif (!preg_match("/[a-z]/", $password) || !preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter, one uppercase letter, and one number';
    }

    // Confirm password validation
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // Email verification check
    if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== true) {
        $errors['email'] = 'Please verify your email before registering';
    }

    // If no errors, proceed with registration
    if (empty(array_filter($errors))) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $insertQuery = "INSERT INTO users (username, email, password, email_verified) VALUES ('$username', '$email', '$hashedPassword', 1)";
        if (mysqli_query($conn, $insertQuery)) {
            // Reset session variables after successful registration
            unset($_SESSION['email_verified']);
            unset($_SESSION['email_otp']);
            unset($_SESSION['email']);

            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful! Redirecting to login page...'
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Error: ' . mysqli_error($conn)
            ]);
            exit;
        }
    } else {
        // If there are validation errors, return them
        echo json_encode([
            'success' => false, 
            'errors' => $errors
        ]);
        exit;
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
        .input-group {
            display: flex;
            align-items: center;
            width: 100%;
            position: relative;
        }
        
        .input-group .form-control {
            flex-grow: 1; 
            padding-right: 60px; 
        }

        .input-group button {
            position: absolute;
            right: 0;
            height: 100%;
            border: none;
            background-color: var(--brown-primary);
            color: white;
            padding: 10px 20px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            font-size: 1rem;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brown-primary);
            opacity: 0.7;
        }

        .input-group .form-control:focus {
            padding-right: 110px;
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
        
        /* New modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 15px;
            width: 80%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(139, 69, 19, 0.15);
        }

        .modal-content input {
            width: 100%;
            margin: 15px 0;
        }
        #verifyEmailBtn {
    width: 100px; /* Adjust width as needed */
    height: 100%; /* Matches input field height */
    white-space: nowrap; /* Prevents text wrapping */
    
}
.verifyEmailBtn:hover{
    background-color: --nude-500: #B08F78;
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
    <div class="error-message" id="username-error"></div>
</div>

<div class="form-group">
    <div class="input-group">
        <i class="fas fa-envelope"></i>
        <input type="email" class="form-control" name="email" id="email" placeholder="Email Address" required>    <i class="fas fa-envelope"></i>
        <button type="button" id="verifyEmailBtn" class="btn">Verify</button>
    </div>
    <div class="error-message" id="email-error"></div>
</div>

    <div class="form-group" id="otpField" style="display: none;">
        <input type="text" class="form-control" name="otp" id="otp" placeholder="Enter OTP" required>
        <button type="button" id="verifyOtpBtn" class="btn btn-success">Verify OTP</button>
        <div class="error-message" id="otp-error"></div>
    </div>


<div class="form-group">
    <input type="password" class="form-control <?php echo !empty($errors['password']) ? 'is-invalid' : ''; ?>" 
           name="password" 
           value="<?php echo isset($_POST['password']) ? $_POST['password'] : ''; ?>" 
           placeholder="Password" required>
    <i class="fas fa-lock"></i>
  
    <div class="error-message" id="password-error"></div>
</div>


<div class="form-group">
    <input type="password" class="form-control <?php echo !empty($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
           name="confirm_password" 
           value="<?php echo isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; ?>" 
           placeholder="Confirm Password" required>
    <i class="fas fa-key"></i>
    <div class="error-message" id="confirm_password-error"></div>
</div>
        <button type="submit" class="btn btn-primary">Create Account</button>
    </form>
    <div class="login-link">
        Already have an account? <a href="login.php">Login here</a>
    </div>
 
</div>


    <!-- OTP Verification Modal -->
    <div id="otpModal" class="modal">
        <div class="modal-content">
            <h3>Email Verification</h3>
            <input type="text" id="otpInput" placeholder="Enter OTP" class="form-control">
            <button id="verifyOtpModalBtn" class="btn btn-primary">Verify OTP</button>
            <p id="otpModalMessage"></p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        let otpModal = $("#otpModal");

        $("#verifyEmailBtn").click(function() {
            var email = $("#email").val();

            if (email === "") {
                $("#email-error").text("Enter your email").show();
                return;
            }

            $.ajax({
                url: "send_verification.php",
                method: "POST",
                data: { email: email },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        otpModal.show();
                    } else {
                        alert("Error: " + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error: " + error);
                }
            });
        });

        $("#verifyOtpModalBtn").click(function() {
            var otp = $("#otpInput").val();

            $.ajax({
                url: "verify_otp.php",
                method: "POST",
                data: { otp: otp },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        $("#otpModalMessage").text("Email verified successfully!");
                        $("#verifyEmailBtn").prop("disabled", true).text("Verified");
                        setTimeout(() => {
                            otpModal.hide();
                        }, 2000);
                    } else {
                        $("#otpModalMessage").text(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("AJAX Error: " + error);
                }
            });
        });

        // Close modal when clicking outside
        $(window).click(function(event) {
            if (event.target == otpModal[0]) {
                otpModal.hide();
            }
        });
           // Modify form submission to use AJAX
    $("#registerForm").on("submit", function(e) {
        e.preventDefault();

        $.ajax({
            url: "register.php",
            method: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = "login.php";
                } else {
                    // Handle validation errors
                    if (response.errors) {
                        $.each(response.errors, function(field, message) {
                            $("#" + field + "-error").text(message).show();
                            $("input[name='" + field + "']").addClass("is-invalid");
                        });
                    } else {
                        alert(response.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.log("AJAX Error: " + error);
               // alert("An unexpected error occurred.");
            }
        });
    });
});
$(document).ready(function () {
    $("#emailInput").on("input", function () {
        var email = $(this).val();

        $.ajax({
            url: "register.php", // The same file handling registration
            method: "POST",
            data: { email_check: email },
            dataType: "json",
            success: function (response) {
                if (!response.success) {
                    $("#emailError").text(response.message).show();
                    $("#verifyEmailBtn").prop("disabled", true); // Disable Verify Button
                } else {
                    $("#emailError").hide();
                    $("#verifyEmailBtn").prop("disabled", false); // Enable Verify Button
                }
            },
            error: function (xhr, status, error) {
                console.log("AJAX Error: " + error);
            }
        });
    });
});

    // Live validation for username
    $("input[name='username']").on("input", function() {
        validateField("username", $(this).val());
    });
    // Live validation for email
    $("input[name='email']").on("input", function() {
        validateField("email", $(this).val());
    });

    // Live validation for password
    $("input[name='password']").on("input", function() {
        validateField("password", $(this).val());
    });

    // Live validation for confirm password
    $("input[name='confirm_password']").on("input", function() {
        let password = $("input[name='password']").val();
        let confirmPassword = $(this).val();
        if (password !== confirmPassword) {
            $("#confirm_password-error").text("Passwords do not match").show();
            $(this).addClass("is-invalid");
        } else {
            $("#confirm_password-error").hide();
            $(this).removeClass("is-invalid");
        }
    });
    function validateField(fieldName, fieldValue) {
    $.ajax({
        url: "validate.php",
        method: "POST",
        data: { field: fieldName, value: fieldValue },
        success: function(response) {
            let data = JSON.parse(response);
            if (data.error) {
                $("#" + fieldName + "-error").text(data.error).show();
                $("input[name='" + fieldName + "']").addClass("is-invalid");
            } else {
                $("#" + fieldName + "-error").hide();
                $("input[name='" + fieldName + "']").removeClass("is-invalid");
            }
        }
    });
}
    </script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>