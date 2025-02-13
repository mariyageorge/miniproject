<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LIFE-SYNC</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <style>
            :root {
            --primary-color: #6F4E37;
            --secondary-color: #8B4513;
            --accent-color: #D2691E;
            --bg-color: #F4ECD8;
            --card-bg: #F5DEB3;
            --text-primary: #3E2723;
            --text-secondary: #5D4037;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--card-bg);
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

        .admin-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .forgot-container {
            background-color: var(--bg-color);
            padding: 40px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            margin: 50px auto;
        }

        .forgot-container h2 {
            color: var(--primary-color);
            margin-bottom: 30px;
        }

        .input-field {
            margin-bottom: 20px;
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid var(--secondary-color);
            border-radius: 8px;
            background-color: white;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background-color: var(--secondary-color);
        }

        .links {
            margin-top: 20px;
        }

        .links a {
            color: var(--primary-color);
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
            color: #4caf50;
        }

        .error-message {
            color: #ff4c4c;
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-container">
                <div class="d-flex align-items-center">
                    <a class="logo" href="index.php">
                        <div class="logo-icon">
                            <i class="fas fa-infinity"></i>
                        </div>
                        <span class="logo-text d-none d-sm-inline">LIFE-SYNC</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="container">
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
    </div>

    <script>
        document.getElementById("forgotForm").addEventListener("submit", function(event) {
            event.preventDefault();

            var email = document.getElementById("email").value;
            var errorMessage = document.getElementById("errorMessage");
            var successMessage = document.getElementById("successMessage");

            errorMessage.textContent = "";
            successMessage.textContent = "";

            var formData = new FormData();
            formData.append("email", email);

            fetch("send_reset_email.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    successMessage.textContent = data.message;
                } else {
                    errorMessage.textContent = data.message;
                }
            })
            .catch(error => {
                errorMessage.textContent = "An error occurred. Please try again.";
            });
        });
       
    document.getElementById("forgotForm").addEventListener("submit", function(event) {
        event.preventDefault();

        var email = document.getElementById("email").value;
        var errorMessage = document.getElementById("errorMessage");
        var successMessage = document.getElementById("successMessage");
        var submitButton = document.querySelector(".btn-submit");

        // Reset messages
        errorMessage.textContent = "";
        successMessage.textContent = "";

        // Change button text and disable it
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        submitButton.disabled = true;

        var formData = new FormData();
        formData.append("email", email);

        fetch("send_reset_email.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                successMessage.textContent = data.message;
            } else {
                errorMessage.textContent = data.message;
            }
        })
        .catch(error => {
            errorMessage.textContent = "An error occurred. Please try again.";
        })
        .finally(() => {
            // Restore button text and enable it
            submitButton.innerHTML = "Send Reset Link";
            submitButton.disabled = false;
        });
    });


    </script>
</body>
</html>