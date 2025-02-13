<?php
session_start();
include 'connect.php'; // Include database connection
include 'header.php'; // Include header
$user_id = $_SESSION['user_id'];

// Handle water intake update
if (isset($_POST['increase_water'])) {
    $date = date('Y-m-d');
    $query = "INSERT INTO health_tracking (user_id, date, water_intake) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE water_intake = water_intake + 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
}

// Handle BMI Calculation
if (isset($_POST['calculate_bmi'])) {
    $weight = $_POST['weight'];
    $height = $_POST['height'] / 100; // Convert cm to meters
    $bmi = round($weight / ($height * $height), 2);
    
    // Store BMI in database
    $query = "UPDATE health_tracking SET weight = ?, height = ?, bmi = ? WHERE user_id = ? AND date = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dddi", $weight, $_POST['height'], $bmi, $user_id);
    $stmt->execute();

    // Interpret BMI
    if ($bmi < 18.5) {
        $bmi_message = "Underweight - Consider a balanced diet.";
    } elseif ($bmi < 24.9) {
        $bmi_message = "Normal weight - Keep it up!";
    } elseif ($bmi < 29.9) {
        $bmi_message = "Overweight - Try increasing physical activity.";
    } else {
        $bmi_message = "Obese - Consult a healthcare provider.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-brown: #8B4513;
            --light-brown: #DEB887;
            --cream: #FAEBD7;
            --dark-brown: #654321;
            --accent-green: #556B2F;
        }

        body {
            font-family: 'Georgia', serif;
            background-color: var(--cream);
            color: var(--dark-brown);
            text-align: center;
            padding: 20px;
            background-image: url('/api/placeholder/400/400');
            background-blend-mode: overlay;
        }

        h2 {
            color: var(--primary-brown);
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            font-family: 'Palatino', serif;
            margin-bottom: 30px;
            border-bottom: 3px double var(--primary-brown);
            padding-bottom: 10px;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            margin: 20px auto;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.2);
            width: 80%;
            max-width: 600px;
            border: 2px solid var(--light-brown);
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-5px);
        }

        .section h3 {
            color: var(--primary-brown);
            font-size: 1.8em;
            margin-bottom: 20px;
            font-family: 'Palatino', serif;
        }

        button {
            background-color: var(--primary-brown);
            color: var(--cream);
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            margin: 10px;
            font-family: 'Georgia', serif;
        }

        button:hover {
            background-color: var(--dark-brown);
            transform: scale(1.05);
        }

        input {
            padding: 12px;
            margin: 10px;
            border: 2px solid var(--light-brown);
            border-radius: 8px;
            font-size: 16px;
            width: 200px;
            background-color: var(--cream);
            color: var(--dark-brown);
        }

        .water-container {
            position: relative;
            margin: 20px auto;
            width: 100px;
            height: 150px;
            background-color: rgba(255, 255, 255, 0.8);
            border: 3px solid var(--primary-brown);
            border-radius: 0 0 20px 20px;
        }

        .water-level {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: linear-gradient(180deg, #87CEEB, #4682B4);
            border-radius: 0 0 17px 17px;
            transition: height 0.5s ease;
            height: 0%;
        }

        .water-stats {
            margin: 20px 0;
            padding: 15px;
            background-color: var(--cream);
            border-radius: 8px;
            display: inline-block;
        }

        .exercise-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }

        .exercise-icon {
            font-size: 24px;
            color: var(--accent-green);
            transition: transform 0.3s ease;
        }

        .exercise-icon:hover {
            transform: scale(1.2);
        }

        @keyframes ripple {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .ripple {
            position: absolute;
            border: 2px solid var(--primary-brown);
            border-radius: 50%;
            animation: ripple 1s infinite;
        }

        #timerDisplay {
            font-size: 2em;
            color: var(--primary-brown);
            font-family: 'Courier New', monospace;
            margin: 20px 0;
        }

        .health-articles {
            list-style: none;
            padding: 0;
        }

        .health-articles li {
            margin: 15px 0;
        }

        .health-articles a {
            color: var(--primary-brown);
            text-decoration: none;
            transition: color 0.3s ease;
            font-size: 1.1em;
        }

        .health-articles a:hover {
            color: var(--accent-green);
        }

        .water-progress {
            text-align: center;
            font-size: 1.2em;
            margin: 15px 0;
        }
        .modal {
        display: none; 
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        width: 300px;
        margin: 20% auto;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
    }

    .close {
        float: right;
        font-size: 20px;
        cursor: pointer;
    }
    </style>
    <script>
        let waterIntake = 0;
        const MAX_WATER = 8;

        function updateWaterLevel() {
            waterIntake = Math.min(waterIntake + 1, MAX_WATER);
            const percentage = (waterIntake / MAX_WATER) * 100;
            document.querySelector('.water-level').style.height = percentage + '%';
            document.querySelector('.water-progress').textContent = 
                `${waterIntake} / ${MAX_WATER} glasses consumed`;
            
            // Create ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'ripple';
            document.querySelector('.water-container').appendChild(ripple);
            setTimeout(() => ripple.remove(), 1000);
        }
        let interval;
let isPaused = false;
let remainingTime = 0;

function startTimer() {
    let duration = parseInt(document.getElementById('duration').value) * 60;
    
    if (isNaN(duration) || duration <= 0) {
        alert("Please enter a valid exercise duration.");
        return;
    }

    clearInterval(interval); // Reset previous timers
    remainingTime = duration;
    isPaused = false;
    document.getElementById("pausePlayButton").disabled = false; // Enable Pause button
    document.getElementById("pausePlayButton").innerHTML = "Pause ⏸️";

    interval = setInterval(updateTimer, 1000);
}

function updateTimer() {
    if (remainingTime > 0) {
        let minutes = Math.floor(remainingTime / 60);
        let seconds = remainingTime % 60;

        document.getElementById("timerDisplay").innerText =
            minutes + ":" + (seconds < 10 ? "0" : "") + seconds;

        remainingTime--;
    } else {
        clearInterval(interval);
        document.getElementById("soundAlert").play();
        document.getElementById("pausePlayButton").disabled = true;
    }
}

function togglePausePlay() {
    let button = document.getElementById("pausePlayButton");

    if (isPaused) {
        interval = setInterval(updateTimer, 1000);
        button.innerHTML = "Pause ⏸️";
    } else {
        clearInterval(interval);
        button.innerHTML = "Play ▶️";
    }

    isPaused = !isPaused;
}
function startTimer() {
        let duration = document.getElementById('duration').value;
        let timeLeft = duration * 60;
        let timerDisplay = document.getElementById('timerDisplay');
        let startButton = document.getElementById('startButton');
        let pausePlayButton = document.getElementById('pausePlayButton');
        let alertSound = document.getElementById('soundAlert');
        let modal = document.getElementById("exerciseModal");

        startButton.disabled = true;
        pausePlayButton.disabled = false;

        let timer = setInterval(function () {
            let minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                timerDisplay.textContent = "00:00";
                alertSound.play();
                modal.style.display = "block"; // Show modal
                startButton.disabled = false;
                pausePlayButton.disabled = true;
            }

            timeLeft--;
        }, 1000);
    }

    function closeModal() {
        document.getElementById("exerciseModal").style.display = "none";
    }
        </script>
</head>
<body><br><br><br>
   
    
    <!-- Water Intake Section -->
    <div class="section">
        <h3>💧 Daily Hydration Tracker 💧</h3>
        <div class="water-container">
            <div class="water-level"></div>
        </div>
        <p class="water-progress">0 / 8 glasses consumed</p>
        <button onclick="updateWaterLevel()">Drink Water 💧</button>
        <div class="water-stats">
            <p>Daily Goal: 8 glasses</p>
            <p>Each glass: 250ml</p>
        </div>
    </div>
    
    <!-- Exercise Tracker -->
    <div class="section">
        <h3>🏃‍♂️ Exercise Journal 🏃‍♀️</h3>
        <div class="exercise-icons">
            <i class="fas fa-running exercise-icon"></i>
            <i class="fas fa-biking exercise-icon"></i>
            <i class="fas fa-swimming-pool exercise-icon"></i>
            <i class="fas fa-dumbbell exercise-icon"></i>
        </div>
        <label for="exercise">Exercise Type:</label>
        <input type="text" id="exercise" placeholder="e.g., Running"><br>
        <label for="duration">Duration (mins):</label>
        <input type="number" id="duration" min="1" max="180"><br>
        <button onclick="startTimer()" id="startButton">Start Workout ⏳</button>
        <button onclick="togglePausePlay()" id="pausePlayButton" disabled>Pause ⏸️</button>
        <audio id="soundAlert" src="images/alert.mp3.wav"></audio>
        <p id="timerDisplay">00:00</p>
    </div>
    <!-- Modal -->
<div id="exerciseModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Workout Completed! 🎉</h2>
        <p>Great job! Keep pushing towards your fitness goals. 💪</p>
    </div>
</div>
    <!-- BMI Calculator -->

<div class="section">
    <h3>📊 BMI Calculator 📊</h3>
    <form method="POST">
        <label>Weight (kg):</label>
        <input type="number" name="weight" required placeholder="Enter weight"><br>
        <label>Height (cm):</label>
        <input type="number" name="height" required placeholder="Enter height">
        <button type="submit" name="calculate_bmi">Calculate BMI 📐</button>
    </form>

    <?php if (isset($bmi)) : ?>
        <div class="bmi-result">
            <h4>Your BMI: <?= $bmi ?></h4>
            <p><?= $bmi_message ?></p>
        </div>
    <?php endif; ?>
</div>

    
    <!-- Health Articles -->
    <div class="section">
        <h3>📚 Wellness Library 📚</h3>
        <ul class="health-articles">
            <li><a href="#"><i class="fas fa-book-open"></i> The Art of Hydration</a></li>
            <li><a href="#"><i class="fas fa-heartbeat"></i> Victorian-Era Exercise Wisdom</a></li>
            <li><a href="#"><i class="fas fa-utensils"></i> Traditional Balanced Diet Guide</a></li>
        </ul>
    </div>
</body>
</html>