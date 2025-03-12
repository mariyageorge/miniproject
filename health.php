<?php
session_start();
include 'connect.php'; // Include database connection
include 'header.php'; // Include header

$user_id = $_SESSION['user_id'];

// Create necessary tables if they don't exist - handling multi_query properly
$createTablesQuery = "
CREATE TABLE IF NOT EXISTS health_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    water_intake INT DEFAULT 0,
    weight DECIMAL(5,2) DEFAULT NULL,
    height DECIMAL(5,2) DEFAULT NULL,
    bmi DECIMAL(5,2) DEFAULT NULL,
    UNIQUE KEY unique_entry (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);";

$conn->query($createTablesQuery);

$createMealTableQuery = "
CREATE TABLE IF NOT EXISTS meal_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    meal_type VARCHAR(50) NOT NULL,
    meal_desc TEXT NOT NULL,
    calories INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);";

$conn->query($createMealTableQuery);

// Handle water intake update
$date = date('Y-m-d');
if (isset($_POST['increase_water'])) {
    $water_query = "INSERT INTO health_tracking (user_id, date, water_intake) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE water_intake = water_intake + 1";
    $water_stmt = $conn->prepare($water_query);
    $water_stmt->bind_param("is", $user_id, $date);
    $water_stmt->execute();
}

// Handle BMI Calculation
if (isset($_POST['calculate_bmi'])) {
    $weight = $_POST['weight'];
    $height = $_POST['height'] / 100; // Convert cm to meters
    $bmi = round($weight / ($height * $height), 2);
    
    // Store BMI in database
    $bmi_query = "INSERT INTO health_tracking (user_id, date, weight, height, bmi) VALUES (?, CURDATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE weight = VALUES(weight), height = VALUES(height), bmi = VALUES(bmi)";
    $bmi_stmt = $conn->prepare($bmi_query);
    $bmi_stmt->bind_param("iddd", $user_id, $weight, $_POST['height'], $bmi);
    $bmi_stmt->execute();

    // Interpret BMI
    if ($bmi < 18.5) {
        $bmi_message = "Underweight - Consider a balanced diet.";
        $diet_type = "weight_gain";
    } elseif ($bmi < 24.9) {
        $bmi_message = "Normal weight - Keep it up!";
        $diet_type = "maintenance";
    } elseif ($bmi < 29.9) {
        $bmi_message = "Overweight - Try increasing physical activity.";
        $diet_type = "weight_loss";
    } else {
        $bmi_message = "Obese - Consult a healthcare provider.";
        $diet_type = "weight_loss";
    }
    
    $_SESSION['diet_type'] = $diet_type;
}

// Handle meal tracking
if (isset($_POST['add_meal'])) {
    $meal_type = $_POST['meal_type'];
    $meal_desc = $_POST['meal_desc'];
    $calories = $_POST['calories'];
    $date = date('Y-m-d');
    
    $meal_query = "INSERT INTO meal_tracking (user_id, date, meal_type, meal_desc, calories) VALUES (?, ?, ?, ?, ?)";
    $meal_stmt = $conn->prepare($meal_query);
    $meal_stmt->bind_param("isssi", $user_id, $date, $meal_type, $meal_desc, $calories);
    $meal_stmt->execute();
}

// Fetch health and meal data for today
$date = date('Y-m-d');

$health_query = "SELECT water_intake, bmi FROM health_tracking WHERE user_id = ? AND date = ?";
$health_stmt = $conn->prepare($health_query);
$health_stmt->bind_param("is", $user_id, $date);
$health_stmt->execute();
$health_result = $health_stmt->get_result()->fetch_assoc();
$water_intake = $health_result['water_intake'] ?? 0;
$bmi = $health_result['bmi'] ?? null;

$meals_query = "SELECT meal_type, meal_desc, calories FROM meal_tracking WHERE user_id = ? AND date = ? ORDER BY meal_type";
$meals_stmt = $conn->prepare($meals_query);
$meals_stmt->bind_param("is", $user_id, $date);
$meals_stmt->execute();
$meals_result = $meals_stmt->get_result();
$today_meals = [];
$total_calories = 0;
while ($meal = $meals_result->fetch_assoc()) {
    $today_meals[] = $meal;
    $total_calories += $meal['calories'];
}

// Define diet plans
function getDietPlan($type) {
    $plans = [
        'weight_loss' => [
            'daily_calories' => '1500-1800',
            'breakfast' => ['Oatmeal with berries (300 cal)', 'Vegetable omelet with whole grain toast (350 cal)'],
            'lunch' => ['Grilled chicken salad (400 cal)', 'Lentil soup with side salad (300 cal)'],
            'dinner' => ['Baked fish with steamed vegetables (400 cal)', 'Vegetable curry with brown rice (450 cal)'],
            'snacks' => ['Apple with almond butter (150 cal)', 'Greek yogurt with berries (120 cal)'],
            'tips' => ['Focus on protein and fiber', 'Avoid sugary drinks', 'Control portion sizes', 'Stay hydrated']
        ],
        'maintenance' => [
            'daily_calories' => '2000-2200',
            'breakfast' => ['Avocado toast with eggs (400 cal)', 'Smoothie bowl with granola (350 cal)'],
            'lunch' => ['Mediterranean wrap with hummus (450 cal)', 'Grain bowl with protein (500 cal)'],
            'dinner' => ['Grilled salmon with quinoa (550 cal)', 'Lean beef stir fry (500 cal)'],
            'snacks' => ['Trail mix (200 cal)', 'Fruit and cheese (180 cal)'],
            'tips' => ['Eat balanced meals', 'Include a variety of foods', 'Moderate portions', 'Stay active daily']
        ],
        'weight_gain' => [
            'daily_calories' => '2500-3000',
            'breakfast' => ['Protein-packed breakfast burrito (550 cal)', 'Egg sandwich with avocado (600 cal)'],
            'lunch' => ['Chicken pasta with olive oil (700 cal)', 'Loaded baked potato (600 cal)'],
            'dinner' => ['Steak with sweet potato (750 cal)', 'Hearty pasta with meat sauce (700 cal)'],
            'snacks' => ['Protein shake (350 cal)', 'Nuts and dried fruit (300 cal)'],
            'tips' => ['Eat calorie-dense foods', 'Increase portion sizes', 'Have frequent meals', 'Include healthy fats']
        ]
    ];
    return $plans[$type] ?? $plans['maintenance'];
}

$current_diet_plan = getDietPlan($_SESSION['diet_type'] ?? 'maintenance');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health & Nutrition Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-brown: #8B4513;
            --light-brown: #DEB887;
            --cream: #FAEBD7;
            --dark-brown: #654321;
            --accent-green: #556B2F;
            --accent-blue: #4682B4;
            --light-green: #8FBC8F;
        }

        body {
            font-family: 'Georgia', serif;
            background-color: var(--cream);
            color: var(--dark-brown);
            padding: 20px;
            background-image: url('/api/placeholder/400/400');
            background-blend-mode: overlay;
        }

        h2 {
            color: var(--primary-brown);
            font-size: 2em;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            font-family: 'Palatino', serif;
            margin-bottom: 20px;
            border-bottom: 3px double var(--primary-brown);
            padding-bottom: 10px;
            text-align: center;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            margin: 15px auto;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.2);
            width: 90%;
            max-width: 800px;
            border: 2px solid var(--light-brown);
            transition: transform 0.3s ease;
        }

        .section:hover {
            transform: translateY(-5px);
        }

        .section h3 {
            color: var(--primary-brown);
            font-size: 1.5em;
            margin-bottom: 15px;
            font-family: 'Palatino', serif;
            text-align: center;
        }

        button {
            background-color: var(--primary-brown);
            color: var(--cream);
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin: 10px;
            font-family: 'Georgia', serif;
        }

        button:hover {
            background-color: var(--dark-brown);
            transform: scale(1.05);
        }

        input, select, textarea {
            padding: 10px;
            margin: 10px;
            border: 2px solid var(--light-brown);
            border-radius: 8px;
            font-size: 14px;
            width: calc(100% - 40px);
            max-width: 400px;
            background-color: var(--cream);
            color: var(--dark-brown);
        }

        .water-container {
            position: relative;
            margin: 20px auto;
            width: 80px;
            height: 120px;
            background-color: rgba(255, 255, 255, 0.8);
            border: 3px solid var(--primary-brown);
            border-radius: 0 0 15px 15px;
        }

        .water-level {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: linear-gradient(180deg, #87CEEB, #4682B4);
            border-radius: 0 0 12px 12px;
            transition: height 0.5s ease;
            height: 0%;
        }

        .water-stats {
            margin: 15px 0;
            padding: 10px;
            background-color: var(--cream);
            border-radius: 8px;
            display: inline-block;
        }

        .motivational-quote {
            font-style: italic;
            color: var(--accent-blue);
            margin: 10px 0;
            text-align: center;
        }

        .flex-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .column {
            flex: 1;
            min-width: 300px;
            padding: 10px;
        }

        .meal-list {
            list-style: none;
            padding: 0;
        }

        .meal-list li {
            background-color: var(--cream);
            margin: 10px 0;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid var(--accent-green);
        }

        .diet-suggestion {
            background-color: var(--light-green);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .diet-suggestion h4 {
            margin-top: 0;
            color: var(--dark-brown);
        }

        .progress-container {
            width: 100%;
            background-color: #ddd;
            border-radius: 10px;
            margin: 15px 0;
        }

        .progress-bar {
            height: 20px;
            border-radius: 10px;
            background-color: var(--accent-green);
            text-align: center;
            line-height: 20px;
            color: white;
            font-weight: bold;
        }

        .tips-list {
            background-color: var(--cream);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .tips-list li {
            margin: 8px 0;
        }

        .mini-dashboard {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .stat-box {
            background-color: var(--cream);
            border-radius: 8px;
            padding: 15px;
            margin: 10px;
            min-width: 150px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-title {
            font-size: 0.9em;
            color: var(--accent-blue);
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--dark-brown);
        }

        @media (max-width: 768px) {
            .column {
                flex: 100%;
            }
        }
    </style>
    <script>
        function updateWaterLevel(currentIntake) {
            const MAX_WATER = 8;
            const waterIntake = Math.min(currentIntake, MAX_WATER);
            const percentage = (waterIntake / MAX_WATER) * 100;
            document.querySelector('.water-level').style.height = percentage + '%';
            document.querySelector('.water-progress').textContent = 
                `${waterIntake} / ${MAX_WATER} glasses consumed`;
        }

        function showMealSuggestions(mealType) {
            const suggestions = document.getElementById('meal-suggestions');
            suggestions.style.display = 'block';
            document.getElementById('suggested-meal-type').textContent = mealType;
            
            // Update the meal_type field in the form
            document.getElementById('meal_type').value = mealType.toLowerCase();
        }

        window.onload = function() {
            // Initialize water level
            updateWaterLevel(<?php echo $water_intake; ?>);
            
            // Initialize calorie progress bar
            const totalCalories = <?php echo $total_calories; ?>;
            const targetCalories = <?php echo explode('-', $current_diet_plan['daily_calories'])[0]; ?>;
            const percentage = Math.min((totalCalories / targetCalories) * 100, 100);
            document.querySelector('.progress-bar').style.width = percentage + '%';
            document.querySelector('.progress-bar').textContent = totalCalories + " / " + targetCalories + " cal";
        };
    </script>
</head>
<body>
    <br><br><br><h2>🌱 Health & Nutrition Tracker 🌱</h2>
    
    <!-- Dashboard Overview -->
    <div class="section">
        <h3>Today's Dashboard</h3>
        <div class="mini-dashboard">
            <div class="stat-box">
                <div class="stat-title">Water</div>
                <div class="stat-value"><?php echo $water_intake; ?>/8</div>
                <div>glasses</div>
            </div>
            <div class="stat-box">
                <div class="stat-title">Calories</div>
                <div class="stat-value"><?php echo $total_calories; ?></div>
                <div>consumed</div>
            </div>
            <div class="stat-box">
                <div class="stat-title">Goal</div>
                <div class="stat-value"><?php echo $current_diet_plan['daily_calories']; ?></div>
                <div>calories</div>
            </div>
            <?php if (isset($bmi)): ?>
            <div class="stat-box">
                <div class="stat-title">BMI</div>
                <div class="stat-value"><?php echo $bmi; ?></div>
            </div>
            <?php endif; ?>
        </div>
        <p class="motivational-quote">"Let food be thy medicine and medicine be thy food."</p>
    </div>

    <!-- Water and BMI Section -->
    <div class="section">
        <div class="flex-container">
            <div class="column">
                <h3>💧 Daily Hydration</h3>
                <div class="water-container">
                    <div class="water-level"></div>
                </div>
                <p class="water-progress"><?php echo $water_intake; ?> / 8 glasses consumed</p>
                <form method="POST" style="text-align: center;">
                    <button type="submit" name="increase_water">Drink Water 💧</button>
                </form>
                <div class="water-stats">
                    <p>Daily Goal: 8 glasses</p>
                    <p>Each glass: 250ml</p>
                </div>
            </div>
            
            <div class="column">
                <h3>📊 BMI Calculator</h3>
                <form method="POST">
                    <label>Weight (kg):</label>
                    <input type="number" name="weight" required placeholder="Enter weight"><br>
                    <label>Height (cm):</label>
                    <input type="number" name="height" required placeholder="Enter height"><br>
                    <button type="submit" name="calculate_bmi">Calculate BMI 📐</button>
                </form>

                <?php if (isset($bmi)) : ?>
                    <div class="bmi-result">
                        <h4>Your BMI: <?= $bmi ?></h4>
                        <!-- <p><?= $bmi_message ?></p> -->
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Meal Tracking Section -->
    <div class="section">
        <h3>🍽️ Daily Nutrition Tracker</h3>
        
        <div class="flex-container">
            <div class="column">
                <h4>Today's Meals</h4>
                
                <div class="progress-container">
                    <div class="progress-bar" style="width: 0%">0 / 0 cal</div>
                </div>
                
                <?php if (empty($today_meals)): ?>
                    <p>No meals recorded today. Add your first meal!</p>
                <?php else: ?>
                    <ul class="meal-list">
                    <?php foreach ($today_meals as $meal): ?>
                        <li>
                            <strong><?php echo ucfirst($meal['meal_type']); ?>:</strong> 
                            <?php echo $meal['meal_desc']; ?> 
                            <span style="float: right;"><?php echo $meal['calories']; ?> cal</span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="showMealSuggestions('Breakfast')">Add Breakfast</button>
                    <button onclick="showMealSuggestions('Lunch')">Add Lunch</button>
                    <button onclick="showMealSuggestions('Dinner')">Add Dinner</button>
                    <button onclick="showMealSuggestions('Snack')">Add Snack</button>
                </div>
            </div>
            
            <div class="column" id="meal-suggestions" style="display: none;">
                <h4>Add <span id="suggested-meal-type">Meal</span></h4>
                
                <form method="POST">
                    <input type="hidden" id="meal_type" name="meal_type" value="breakfast">
                    <label>Description:</label>
                    <textarea name="meal_desc" required placeholder="Describe your meal"></textarea><br>
                    <label>Calories:</label>
                    <input type="number" name="calories" required placeholder="Estimated calories"><br>
                    <button type="submit" name="add_meal">Save Meal 📝</button>
                </form>
                
                <div class="diet-suggestion">
                    <h4>Suggested <span id="suggested-meal-type2">Meal</span> Options</h4>
                    <script>
                        document.getElementById('suggested-meal-type2').textContent = 
                            document.getElementById('suggested-meal-type').textContent;
                    </script>
                    <ul class="meal-list" id="meal-suggestions-list">
                        <?php 
                        $meal_type = 'breakfast'; // Default
                        echo "<script>
                            const mealSuggestions = {
                                'Breakfast': " . json_encode($current_diet_plan['breakfast']) . ",
                                'Lunch': " . json_encode($current_diet_plan['lunch']) . ",
                                'Dinner': " . json_encode($current_diet_plan['dinner']) . ",
                                'Snack': " . json_encode($current_diet_plan['snacks']) . "
                            };
                            
                            function updateSuggestions() {
                                const mealType = document.getElementById('suggested-meal-type').textContent;
                                const suggestionsList = document.getElementById('meal-suggestions-list');
                                suggestionsList.innerHTML = '';
                                
                                mealSuggestions[mealType].forEach(suggestion => {
                                    const li = document.createElement('li');
                                    li.textContent = suggestion;
                                    li.style.cursor = 'pointer';
                                    li.onclick = function() {
                                        document.querySelector('textarea[name=\"meal_desc\"]').value = suggestion.split('(')[0].trim();
                                        // Extract calories from the suggestion
                                        const calorieMatch = suggestion.match(/\((\d+) cal\)/);
                                        if (calorieMatch && calorieMatch[1]) {
                                            document.querySelector('input[name=\"calories\"]').value = calorieMatch[1];
                                        }
                                    };
                                    suggestionsList.appendChild(li);
                                });
                            }
                            
                            // Setup event listeners
                            document.getElementById('suggested-meal-type').addEventListener('DOMSubtreeModified', updateSuggestions);
                            
                            // Initial update
                            setTimeout(updateSuggestions, 100);
                        </script>";
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Personalized Diet Plan Section -->
    <div class="section">
        <h3>🥗 Your Personalized Diet Plan</h3>
        
        <div class="flex-container">
            <div class="column">
                <h4>Daily Calorie Target: <?php echo $current_diet_plan['daily_calories']; ?> calories</h4>
                
                <div class="tips-list">
                    <h4>Nutrition Tips:</h4>
                    <ul>
                        <?php foreach ($current_diet_plan['tips'] as $tip): ?>
                            <li><?php echo $tip; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div class="column">
                <div class="diet-suggestion">
                    <h4>Sample Meal Plan</h4>
                    <p><strong>Breakfast Options:</strong></p>
                    <ul>
                        <?php foreach ($current_diet_plan['breakfast'] as $meal): ?>
                            <li><?php echo $meal; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p><strong>Lunch Options:</strong></p>
                    <ul>
                        <?php foreach ($current_diet_plan['lunch'] as $meal): ?>
                            <li><?php echo $meal; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p><strong>Dinner Options:</strong></p>
                    <ul>
                        <?php foreach ($current_diet_plan['dinner'] as $meal): ?>
                            <li><?php echo $meal; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <p><strong>Healthy Snacks:</strong></p>
                    <ul>
                        <?php foreach ($current_diet_plan['snacks'] as $meal): ?>
                            <li><?php echo $meal; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <p class="motivational-quote">"A journey of a thousand miles begins with a single step."</p>
    </div>
</body>
</html>