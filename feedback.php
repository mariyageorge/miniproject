<?php
session_start();
include("connect.php");

// Create feedback table if it doesn't exist
$tableQuery = "CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($tableQuery) === FALSE) {
    die("Error creating feedback table: " . $conn->error);
}

// Handle feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["error" => "User not logged in"]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $rating = $_POST['rating'];
    $message = $_POST['message'];

    if ($rating < 1 || $rating > 5 || empty($message)) {
        echo json_encode(["error" => "Invalid input"]);
        exit;
    }

    $sql = "INSERT INTO feedback (user_id, rating, message) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $user_id, $rating, $message);

    if ($stmt->execute()) {
        echo json_encode(["success" => "Feedback submitted successfully"]);
    } else {
        echo json_encode(["error" => "Failed to submit feedback"]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - LIFE-SYNC</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --primary-color: #8B4513;
            --hover-color: #A0522D;
            --bg-color: #F5ECE5;
            --text-color: #333;
            --success-color:rgb(100, 175, 118);
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

        .feedback-container {
            max-width: 600px;
            margin: 100px auto 40px;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .feedback-header {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }

        .star-rating {
            text-align: center;
            margin: 2rem 0;
        }

        .star {
            font-size: 2.5rem;
            cursor: pointer;
            color: #ddd;
            transition: color 0.3s ease;
            margin: 0 5px;
        }

        .star:hover, .star.selected {
            color: #FFD700;
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
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            resize: vertical;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: var(--hover-color);
        }

        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-top: 1rem;
            text-align: center;
        }

        .alert-success {
            background-color: var(--success-color);
            color: white;
        }

        .alert-danger {
            background-color: var(--error-color);
            color: white;
        }

        .emoji {
            font-size: 2rem;
            margin-bottom: 1rem;
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

<div class="feedback-container">
    <div class="feedback-header">
        <div class="emoji">💭</div>
        <h2>We Value Your Feedback!</h2>
        <p>Help us improve your experience</p>
    </div>

    <form id="feedbackForm">
        <div class="star-rating">
            <i class="fas fa-star star" data-value="1"></i>
            <i class="fas fa-star star" data-value="2"></i>
            <i class="fas fa-star star" data-value="3"></i>
            <i class="fas fa-star star" data-value="4"></i>
            <i class="fas fa-star star" data-value="5"></i>
            <input type="hidden" id="rating" name="rating">
        </div>

        <div class="form-group">
            <label for="message">Your Message</label>
            <textarea class="form-control" 
                      id="message" 
                      name="message" 
                      rows="4" 
                      placeholder="Tell us about your experience..."></textarea>
        </div>

        <button type="submit" class="submit-btn">
            <i class="fas fa-paper-plane"></i>
            Submit Feedback
        </button>
    </form>

    <div id="feedbackResponse"></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let selectedRating = 0;

    $(".star").hover(
        function() {
            $(this).prevAll().addBack().css("color", "#FFD700");
        },
        function() {
            if (!$(this).hasClass("selected")) {
                $(this).prevAll().addBack().css("color", "#ddd");
            }
        }
    );

    $(".star").click(function() {
        selectedRating = $(this).data("value");
        $("#rating").val(selectedRating);
        $(".star").removeClass("selected").css("color", "#ddd");
        $(this).prevAll().addBack().addClass("selected").css("color", "#FFD700");
    });

    $("#feedbackForm").submit(function(e) {
        e.preventDefault();

        let rating = $("#rating").val();
        let message = $("#message").val();

        if (!rating || !message) {
            $("#feedbackResponse").html(
                '<div class="alert alert-danger">' +
                '<i class="fas fa-exclamation-circle"></i> ' +
                'Please provide both a rating and a message.' +
                '</div>'
            );
            return;
        }

        $.ajax({
            url: "feedback.php",
            method: "POST",
            data: { rating: rating, message: message },
            success: function(response) {
                let result = JSON.parse(response);
                if (result.success) {
                    $("#feedbackResponse").html(
                        '<div class="alert alert-success">' +
                        '<i class="fas fa-check-circle"></i> ' +
                        'Thank you for your valuable feedback! 🎉' +
                        '</div>'
                    );
                    $("#feedbackForm")[0].reset();
                    $(".star").removeClass("selected").css("color", "#ddd");
                } else {
                    $("#feedbackResponse").html(
                        '<div class="alert alert-danger">' +
                        '<i class="fas fa-exclamation-circle"></i> ' +
                        result.error +
                        '</div>'
                    );
                }
            },
            error: function() {
                $("#feedbackResponse").html(
                    '<div class="alert alert-danger">' +
                    '<i class="fas fa-exclamation-circle"></i> ' +
                    'An error occurred. Please try again.' +
                    '</div>'
                );
            }
        });
    });
});
</script>

</body>
</html>