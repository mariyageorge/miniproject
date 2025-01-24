<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #e4d0b5; 
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .forgot-container {
            background-color: #d1b79e; 
            padding: 40px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .forgot-container h2 {
            color: #6f4f31;
            margin-bottom: 30px;
        }

        .input-field {
            margin-bottom: 20px;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #a68c6d; /* Lighter brown border */
            border-radius: 8px;
            background-color: #f9f5e3; /* Off-white background */
        }

        .input-field:focus {
            outline: none;
            border-color: #6f4f31; /* Dark brown border when focused */
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: #6f4f31; /* Dark brown color */
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #4e3a27; /* Darker brown on hover */
        }

        .links {
            margin-top: 20px;
        }

        .links a {
            color: #6f4f31; /* Dark brown for the links */
            font-size: 14px;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .success-message, .error-message {
            font-size: 14px;
            margin-top: 10px;
        }

        .success-message {
            color: #4caf50; /* Green success message */
        }

        .error-message {
            color: #ff4c4c; /* Red error message */
        }
    </style>
</head>
<body>

    <div class="forgot-container">
        <h2>Forgot Your Password?</h2>
        <p>Please enter your email address to reset your password.</p>
        <form id="forgotForm">
            <input type="email" id="email" class="input-field" placeholder="Enter your email" required>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>

        <p class="error-message" id="errorMessage"></p>
        <p class="success-message" id="successMessage"></p>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        const forgotForm = document.getElementById('forgotForm');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');

        forgotForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const email = document.getElementById('email').value;

            if (!email) {
                errorMessage.textContent = "Email address is required!";
                successMessage.textContent = "";
            } else {
        <p class="error-message" id="errorMessage"></p>
        <p class="success-message" id="successMessage"></p>

        <div class="links">
            <a href="login.php">Back to Login</a>
        </div>
    </div>

    <script>
        const forgotForm = document.getElementById('forgotForm');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');

        forgotForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const email = document.getElementById('email').value;

            if (!email) {
                errorMessage.textContent = "Email address is required!";
                successMessage.textContent = "";
            } else {
                errorMessage.textContent = "";
                successMessage.textContent = "A password reset link has been sent to your email.";
                // Add your password reset logic here
            }
        });
    </script>

</body>
</html>
