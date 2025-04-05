<?php
session_start();
include 'connect.php'; // Include database connection
include 'header.php'; // Include header

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? ''; // Add role from session

$createTablesQuery = "
CREATE TABLE IF NOT EXISTS health_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    water_intake INT DEFAULT 0,
    weight DECIMAL(5,2) DEFAULT NULL,
    height DECIMAL(5,2) DEFAULT NULL,
    age INT DEFAULT NULL,
    gender ENUM('male', 'female') DEFAULT NULL,
    bmi DECIMAL(5,2) DEFAULT NULL,
    UNIQUE KEY unique_entry (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);";

// Execute first table creation
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

// Execute second table creation
$conn->query($createMealTableQuery);

$createPreferencesTableQuery = "
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    weight_goal DECIMAL(5,2) DEFAULT NULL,
    activity_level ENUM('sedentary', 'light', 'moderate', 'active', 'very_active') DEFAULT 'moderate',
    dietary_preferences TEXT,
    restrictions TEXT,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);";

// Execute third table creation
$conn->query($createPreferencesTableQuery);

$createDietPlansTableQuery = "
CREATE TABLE IF NOT EXISTS diet_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snacks') NOT NULL,
    meal_description TEXT NOT NULL,
    calories INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);";

// Execute fourth table creation
$conn->query($createDietPlansTableQuery);

// Handle water intake update
$date = date('Y-m-d');
if (isset($_POST['increase_water'])) {
    $water_query = "INSERT INTO health_tracking (user_id, date, water_intake) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE water_intake = water_intake + 1";
    $water_stmt = $conn->prepare($water_query);
    $water_stmt->bind_param("is", $user_id, $date);
    $water_stmt->execute();
    $water_stmt->close();
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
    $bmi_stmt->close();

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
    $meal_stmt->close();
}

// Fetch health and meal data for today
$date = date('Y-m-d');

$health_query = "SELECT water_intake, bmi FROM health_tracking WHERE user_id = ? AND date = ?";
$health_stmt = $conn->prepare($health_query);
if ($health_stmt) {
$health_stmt->bind_param("is", $user_id, $date);
$health_stmt->execute();
$health_result = $health_stmt->get_result()->fetch_assoc();
$water_intake = $health_result['water_intake'] ?? 0;
$bmi = $health_result['bmi'] ?? null;
    $health_stmt->close();
}

$meals_query = "SELECT meal_type, meal_desc, calories FROM meal_tracking WHERE user_id = ? AND date = ? ORDER BY meal_type";
$meals_stmt = $conn->prepare($meals_query);
if ($meals_stmt) {
$meals_stmt->bind_param("is", $user_id, $date);
$meals_stmt->execute();
$meals_result = $meals_stmt->get_result();
$today_meals = [];
$total_calories = 0;
while ($meal = $meals_result->fetch_assoc()) {
    $today_meals[] = $meal;
    $total_calories += $meal['calories'];
    }
    $meals_stmt->close();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            margin-left: 250px; /* Add space for sidebar */
            padding-top: 80px; /* Add padding for fixed header */
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
            position: relative;
            z-index: 1; /* Lower z-index than sidebar */
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
            width: 100px;
            height: 150px;
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
            margin: 30px 0;
            gap: 20px;
        }

        .stat-box.large {
            background-color: white;
            border-radius: 15px;
            padding: 25px;
            margin: 10px;
            min-width: 250px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            flex: 1;
        }

        .stat-box.large:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 1.2em;
            color: var(--primary-brown);
            margin-bottom: 10px;
            font-weight: bold;
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--dark-brown);
            margin: 15px 0;
        }

        .stat-subtitle {
            font-size: 1em;
            color: var(--accent-blue);
        }

        @media (max-width: 768px) {
            .stat-box.large {
                min-width: 100%;
            }
        }

        .exercise-container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .timer-display {
            font-size: 4em;
            font-weight: bold;
            margin: 20px 0;
            font-family: monospace;
        }

        .timer-controls {
            margin: 20px 0;
        }

        .timer-btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .timer-btn.start { background: #4CAF50; color: white; }
        .timer-btn.pause { background: #FFC107; color: black; }
        .timer-btn.reset { background: #f44336; color: white; }

        .exercise-presets {
            margin-top: 20px;
        }

        .preset-btn {
            padding: 8px 15px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .preset-btn:hover {
            background: #f0f0f0;
            transform: scale(1.05);
        }

        .recommendations {
            margin-top: 15px;
        }

        .recommendations ul {
            list-style: none;
            padding-left: 0;
        }

        .recommendations li {
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }

        .recommendations li:before {
            content: '•';
            position: absolute;
            left: 0;
            color: #8B4513;
        }

        .calculate-btn {
            background: var(--brown-primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .calculate-btn:hover {
            background: var(--brown-hover);
            transform: scale(1.05);
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100%;
            background: var(--primary-brown);
            color: var(--cream);
            padding-top: 20px;
            z-index: 900; /* Lower z-index than header */
            box-shadow: 2px 0 5px rgba(0,0,0,0.2);
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 2px solid var(--cream);
        }

        .sidebar-header h3 {
            color: var(--cream);
            margin: 0;
            font-size: 1.5em;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .sidebar-menu li {
            margin: 5px 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 15px 25px;
            color: var(--cream);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover {
            background: var(--dark-brown);
            padding-left: 30px;
        }

        .sidebar-menu a.active {
            background: var(--dark-brown);
            border-left: 4px solid var(--cream);
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-style: normal;
        }

        /* Main Content Wrapper */
        .main-content {
            padding: 20px;
            margin-left: -20px;
            position: relative;
            z-index: 1; /* Lower z-index than sidebar */
        }

        .content-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Meal Planner Styles */
        .meal-planner-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .day-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .meal-time {
            margin: 15px 0;
            padding: 10px;
            border-left: 4px solid var(--accent-green);
            background: var(--cream);
        }

        .meal-time h5 {
            color: var(--primary-brown);
            margin-bottom: 8px;
            font-weight: bold;
        }

        .meal-time p {
            margin: 0;
            color: var(--dark-brown);
            font-size: 1.1em;
        }

        .meal-planner-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
        }

        .generate-plan-btn, .save-plan-btn {
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .generate-plan-btn {
            background: var(--accent-green);
        }

        .save-plan-btn {
            background: var(--accent-blue);
        }

        .generate-plan-btn:hover, .save-plan-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .action-btn {
            padding: 12px 20px;
            border-radius: 25px;
            background: var(--primary-brown);
            color: var(--cream);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        /* Profile Styles */
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-info {
            max-width: 500px;
            margin: 0 auto;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid var(--cream);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-row label {
            font-weight: bold;
            color: var(--primary-brown);
        }

        .info-row span {
            color: var(--dark-brown);
        }

        .last-updated {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }

        /* Update BMI form styling */
        .profile-form {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 15px;
        }

        .form-group label {
            flex: 0 0 120px;
            text-align: right;
            color: var(--primary-brown);
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            flex: 1;
            max-width: 250px;
            margin: 0;
        }

        /* Update water tracking styles */
        .water-progress {
            font-size: 1.2em;
            margin-top: 15px;
            color: var(--primary-brown);
            font-weight: bold;
        }

        /* Exercise Timer Styles */
        .preset-timers {
            margin: 20px 0;
            text-align: center;
        }

        .preset-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 15px 0;
            padding: 0 20px;
        }

        .preset-card {
            background: var(--cream);
            border: none;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .preset-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background: var(--light-brown);
        }

        .preset-time {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--primary-brown);
        }

        .preset-label {
            font-size: 0.9em;
            color: var(--dark-brown);
        }

        .custom-timer {
            margin: 20px 0;
            padding: 20px;
            background: var(--cream);
            border-radius: 12px;
            text-align: center;
        }

        .custom-timer-input {
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }

        .custom-timer-input input {
            width: 80px;
            padding: 8px;
            border: 2px solid var(--light-brown);
            border-radius: 8px;
            text-align: center;
            font-size: 1.1em;
        }

        .custom-timer-btn {
            background: var(--primary-brown);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .custom-timer-btn:hover {
            background: var(--dark-brown);
            transform: scale(1.05);
        }

        .timer-controls {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--cream);
        }

        .generate-btn {
            padding: 10px 20px;
            background-color: var(--primary-brown);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .generate-btn:hover {
            background-color: var(--dark-brown);
            transform: scale(1.05);
        }

        .meal-planner-grid {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .day-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin: 20px auto;
        }

        .day-card h4 {
            color: var(--primary-brown);
            text-align: center;
            font-size: 1.4em;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--cream);
        }

        .meal-time {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid var(--accent-green);
            background: var(--cream);
            transition: transform 0.3s ease;
        }

        .meal-time:hover {
            transform: translateX(5px);
        }

        .meal-time h5 {
            color: var(--primary-brown);
            margin-bottom: 8px;
            font-weight: bold;
        }

        .meal-time p {
            margin: 0;
            color: var(--dark-brown);
            font-size: 1.1em;
        }

        .generate-plan-btn {
            background: var(--accent-green);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            font-size: 1.1em;
            display: block;
            margin: 0 auto;
            transition: all 0.3s ease;
        }

        .generate-plan-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: var(--primary-brown);
            font-size: 1.2em;
        }

        .loading i {
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 8px;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .error {
            color: #dc3545;
            text-align: center;
            padding: 20px;
            background: #ffe6e6;
            border-radius: 15px;
            margin: 20px 0;
        }

        .daily-meals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .meal-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s;
        }

        .meal-card:hover {
            transform: translateY(-5px);
        }

        .meal-header {
            background: var(--primary-brown);
            color: white;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meal-header h4 {
            margin: 0;
            font-size: 1.1em;
        }

        .calories {
            background: rgba(255,255,255,0.2);
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .meal-content {
            padding: 15px;
        }

        .meal-content h5 {
            color: var(--dark-brown);
            margin: 0;
            font-size: 1em;
            line-height: 1.4;
        }

        .regenerate-section {
            text-align: center;
            margin-top: 25px;
        }

        .regenerate-btn {
            background: var(--accent-green);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .regenerate-btn:hover {
            background: var(--dark-brown);
            transform: scale(1.05);
        }

        .loading {
            text-align: center;
            padding: 30px;
            color: var(--primary-brown);
        }

        .loading i {
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 6px;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .error {
            color: #dc3545;
            text-align: center;
            padding: 15px;
            background: #ffe6e6;
            border-radius: 8px;
            margin: 15px 0;
        }

        /* BMI Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
            overflow-y: auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: var(--cream);
            margin: 3% auto;
            padding: 40px;
            border-radius: 20px;
            width: 90%;
            max-width: 700px;
            position: relative;
            box-shadow: 0 4px 30px rgba(0,0,0,0.2);
            animation: slideIn 0.4s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-modal {
            position: absolute;
            right: 25px;
            top: 20px;
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-brown);
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            background-color: var(--primary-brown);
            color: white;
        }

        .bmi-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .bmi-header h3 {
            color: var(--primary-brown);
            font-size: 2em;
            margin-bottom: 10px;
        }

        .result-card {
            background: white;
            padding: 25px;
            margin: 20px 0;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .result-card:hover {
            transform: translateY(-5px);
        }

        .main-result {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, var(--cream), white);
        }

        .bmi-value {
            font-size: 3.5em;
            font-weight: bold;
            color: var(--primary-brown);
            margin: 15px 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .bmi-category {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.2em;
            margin: 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .category-underweight { 
            background: linear-gradient(135deg, #FFE0B2, #FFCC80);
            color: #E65100;
        }
        .category-normal { 
            background: linear-gradient(135deg, #C8E6C9, #A5D6A7);
            color: #2E7D32;
        }
        .category-overweight { 
            background: linear-gradient(135deg, #FFECB3, #FFE082);
            color: #FF6F00;
        }
        .category-obese { 
            background: linear-gradient(135deg, #FFCDD2, #EF9A9A);
            color: #C62828;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .metric-item {
            background: var(--cream);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .metric-label {
            color: var(--dark-brown);
            font-size: 0.9em;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            display: inline-block;
        }

        .metric-value {
            font-size: 1.4em;
            font-weight: bold;
            color: var(--primary-brown);
        }

        .recommendations-list {
            list-style: none;
            padding: 0;
        }

        .recommendations-list li {
            padding: 15px;
            margin: 10px 0;
            background: var(--cream);
            border-radius: 10px;
            border-left: 4px solid var(--accent-green);
            display: flex;
            align-items: center;
            transition: transform 0.3s ease;
        }

        .recommendations-list li:hover {
            transform: translateX(5px);
        }

        .recommendations-list li::before {
            content: '👉';
            margin-right: 10px;
            font-size: 1.2em;
        }

        .info-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            background: var(--primary-brown);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 14px;
            margin-left: 5px;
            cursor: help;
        }

        .tooltip {
            position: relative;
            display: inline-block;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: var(--dark-brown);
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.9em;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
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

            // Show dashboard section by default
            showSection('dashboard');
        };

        // Section switching functionality
        function showSection(sectionId) {
            // Hide all sections first
            const sections = {
                'dashboard': ['overview-section'],
                'water-tracking': ['water-tracking-section'],
                'bmi-tracker': ['bmi-section'],
                'nutrition': ['meal-section', 'diet-section'],
                'exercise': ['exercise-section'],
                'advanced-analytics': ['analytics-section'],
                'meal-planner': ['meal-planner-section'],
                'profile': ['profile-section']
            };

            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });

            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Add active class to clicked menu item
            document.querySelector(`[data-section="${sectionId}"]`).classList.add('active');

            // Show sections based on selection
            if (sections[sectionId]) {
                sections[sectionId].forEach(id => {
                    const section = document.getElementById(id);
                    if (section) {
                        section.style.display = 'block';
                    }
                });
            }
        }

        // Add click event listeners to menu items
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.menu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    showSection(section);
                });
            });

            // Show dashboard by default
            showSection('dashboard');
        });

        let timerInterval;
        let timeLeft = 0;
        let isPaused = false;
        const alertSound = new Audio('images/alert.mp3.wav');

        function startTimer() {
            if (!isPaused) {
                timeLeft = parseInt(document.getElementById('minutes').textContent) * 60 +
                         parseInt(document.getElementById('seconds').textContent);
            }
            isPaused = false;
            
            clearInterval(timerInterval);
            timerInterval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    alertSound.play();
                    return;
                }
                timeLeft--;
                updateTimerDisplay();
            }, 1000);
        }

        function pauseTimer() {
            clearInterval(timerInterval);
            isPaused = true;
        }

        function resetTimer() {
            clearInterval(timerInterval);
            timeLeft = 0;
            isPaused = false;
            updateTimerDisplay();
        }

        function setTimer(seconds) {
            timeLeft = seconds;
            updateTimerDisplay();
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }

        async function calculateBMI(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            try {
                const response = await fetch('calculate_bmi.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await response.json();
                if (data.error) {
                    throw new Error(data.error);
                }
                
                const modal = document.getElementById('bmiModal');
                const bmiScore = document.querySelector('.bmi-score');
                const bmiCategory = document.querySelector('.bmi-category');
                const ageAdjustedBMI = document.querySelector('.age-adjusted-bmi .metric-value');
                const idealWeight = document.querySelector('.ideal-weight-range .metric-value');
                const dailyCalories = document.querySelector('.daily-calorie-needs .metric-value');
                const bodyFat = document.querySelector('.estimated-body-fat .metric-value');
                const recommendations = document.querySelector('.recommendations-list');

                // Update BMI Score and Category
                bmiScore.textContent = data.bmi.toFixed(1);
                bmiCategory.textContent = data.category;
                bmiCategory.className = 'bmi-category category-' + data.category.toLowerCase().replace(' ', '-');

                // Update Detailed Metrics
                ageAdjustedBMI.textContent = data.age_adjusted_bmi.toFixed(1);
                idealWeight.textContent = `${data.ideal_weight_range.min.toFixed(1)} - ${data.ideal_weight_range.max.toFixed(1)} kg`;
                dailyCalories.textContent = `${data.daily_calories} kcal`;
                bodyFat.textContent = `${data.body_fat.toFixed(1)}%`;

                // Update Recommendations
                recommendations.innerHTML = '';
                data.recommendations.forEach(rec => {
                    const li = document.createElement('li');
                    li.textContent = rec;
                    recommendations.appendChild(li);
                });

                // Show modal
                modal.style.display = 'block';
            } catch (error) {
                console.error('Error:', error);
                alert('Error calculating BMI: ' + error.message);
            }
        }

        // Report Generation
        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const period = document.getElementById('reportPeriod').value;
            const reportContent = document.getElementById('reportContent');

            reportContent.innerHTML = `<div class="loading">Generating ${reportType} report for ${period}...</div>`;

            const formData = new FormData();
            formData.append('action', 'get_report');
            formData.append('type', reportType);
            formData.append('period', period);

            fetch('get_health_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReport(data.data, reportType, data.period);
                } else {
                    reportContent.innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                reportContent.innerHTML = `<div class="error">Error: ${error.message}</div>`;
            });
        }

        function displayReport(data, type, period) {
            const reportContent = document.getElementById('reportContent');
            let html = `
                <h4>${type.charAt(0).toUpperCase() + type.slice(1)} Report</h4>
                <p>Period: ${formatDate(period.start)} - ${formatDate(period.end)}</p>
                <div class="report-data">
            `;

            switch (type) {
                case 'progress':
                    if (data.weight_progress && data.weight_progress.length > 0) {
                        const chartData = {
                            labels: data.weight_progress.map(entry => formatDate(entry.date)),
                            datasets: [{
                                label: 'Weight (kg)',
                                data: data.weight_progress.map(entry => entry.weight),
                                borderColor: '#4CAF50',
                                fill: false
                            }]
                        };
                        html += '<canvas id="weightChart"></canvas>';
                        reportContent.innerHTML = html;
                        new Chart(document.getElementById('weightChart'), {
                            type: 'line',
                            data: chartData,
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: false
                                    }
                                }
                            }
                        });
                    } else {
                        html += '<p>No weight data available for this period.</p>';
                        reportContent.innerHTML = html;
                    }
                    break;

                case 'nutrition':
                    if (data.nutrition && data.nutrition.length > 0) {
                        const chartData = {
                            labels: data.nutrition.map(entry => entry.date),
                            datasets: [{
                                label: 'Calories',
                                data: data.nutrition.map(entry => entry.calories),
                                borderColor: '#2196F3',
                                fill: false
                            }]
                        };
                        html += '<canvas id="nutritionChart"></canvas>';
                        html += '<div class="nutrition-breakdown">';
                        const averages = calculateNutritionAverages(data.nutrition);
                        html += `
                            <h5>Average Daily Intake</h5>
                            <ul>
                                <li>Calories: ${averages.calories.toFixed(0)} kcal</li>
                                <li>Protein: ${averages.protein.toFixed(1)}g</li>
                                <li>Carbs: ${averages.carbs.toFixed(1)}g</li>
                                <li>Fats: ${averages.fats.toFixed(1)}g</li>
                            </ul>
                        `;
                        html += '</div>';
                        reportContent.innerHTML = html;
                        new Chart(document.getElementById('nutritionChart'), {
                            type: 'line',
                            data: chartData,
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: false
                                    }
                                }
                            }
                        });
                    } else {
                        html += '<p>No nutrition data available for this period.</p>';
                        reportContent.innerHTML = html;
                    }
                    break;

                case 'water':
                    if (data.water_intake && data.water_intake.length > 0) {
                        const chartData = {
                            labels: data.water_intake.map(entry => entry.date),
                            datasets: [{
                                label: 'Water Intake (glasses)',
                                data: data.water_intake.map(entry => entry.amount),
                                borderColor: '#03A9F4',
                                fill: false
                            }]
                        };
                        html += '<canvas id="waterChart"></canvas>';
                        reportContent.innerHTML = html;
                        new Chart(document.getElementById('waterChart'), {
                            type: 'line',
                            data: chartData,
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                }
                            }
                        });
                    } else {
                        html += '<p>No water intake data available for this period.</p>';
                        reportContent.innerHTML = html;
                    }
                    break;

                case 'exercise':
                    if (data.exercise && data.exercise.length > 0) {
                        const exerciseTypes = [...new Set(data.exercise.map(entry => entry.type))];
                        const datasets = exerciseTypes.map(type => ({
                            label: type,
                            data: data.exercise
                                .filter(entry => entry.type === type)
                                .map(entry => ({
                                    x: formatDate(entry.date),
                                    y: entry.duration
                                }))
                        }));
                        
                        const chartData = {
                            datasets: datasets
                        };
                        
                        html += '<canvas id="exerciseChart"></canvas>';
                        html += '<div class="exercise-summary">';
                        const totalDuration = data.exercise.reduce((sum, entry) => sum + parseInt(entry.duration), 0);
                        const totalCalories = data.exercise.reduce((sum, entry) => sum + parseInt(entry.calories_burned), 0);
                        html += `
                            <h5>Exercise Summary</h5>
                            <ul>
                                <li>Total Duration: ${totalDuration} minutes</li>
                                <li>Total Calories Burned: ${totalCalories} kcal</li>
                                <li>Number of Workouts: ${data.exercise.length}</li>
                            </ul>
                        `;
                        html += '</div>';
                        reportContent.innerHTML = html;
                        new Chart(document.getElementById('exerciseChart'), {
                            type: 'scatter',
                            data: chartData,
                            options: {
                                responsive: true,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Duration (minutes)'
                                        }
                                    }
                                }
                            }
                        });
                    } else {
                        html += '<p>No exercise data available for this period.</p>';
                        reportContent.innerHTML = html;
                    }
                    break;
            }
        }

        function calculateNutritionAverages(nutritionData) {
            const totals = nutritionData.reduce((acc, entry) => ({
                calories: acc.calories + parseFloat(entry.calories),
                protein: acc.protein + parseFloat(entry.protein),
                carbs: acc.carbs + parseFloat(entry.carbs),
                fats: acc.fats + parseFloat(entry.fats)
            }), { calories: 0, protein: 0, carbs: 0, fats: 0 });

            const count = nutritionData.length;
            return {
                calories: totals.calories / count,
                protein: totals.protein / count,
                carbs: totals.carbs / count,
                fats: totals.fats / count
            };
        }

        // Meal Plan Generation
        function generateMealPlan() {
            const grid = document.getElementById('mealPlanGrid');
            grid.innerHTML = '<div class="loading">Generating meal plan... <i>🔄</i></div>';

            const formData = new FormData();
            formData.append('action', 'get_meal_plan');

            fetch('get_health_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON Parse Error:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    let html = `<div class="daily-meals">`;
                    
                    // Order of meals
                    const mealOrder = ['breakfast', 'lunch', 'dinner', 'snacks'];
                    
                    for (const mealType of mealOrder) {
                        const meal = data.meal_plan[mealType];
                        if (meal) {
                            html += `
                                <div class="meal-card">
                                    <div class="meal-header">
                                        <h4>${mealType.charAt(0).toUpperCase() + mealType.slice(1)}</h4>
                                        <span class="calories">${meal.calories} cal</span>
                                    </div>
                                    <div class="meal-content">
                                        <h5>${meal.name}</h5>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    html += `
                        </div>
                       
                    `;
                    
                    grid.innerHTML = html;
                } else {
                    grid.innerHTML = '<div class="error">Failed to generate meal plan: ' + data.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                grid.innerHTML = '<div class="error">Error: ' + error.message + '</div>';
            });
        }

        function getRandomMeal(mealArray) {
            if (!mealArray || mealArray.length === 0) {
                return 'No meal available';
            }
            return mealArray[Math.floor(Math.random() * mealArray.length)];
        }

        // Update icons with more appealing emojis
        document.addEventListener('DOMContentLoaded', function() {
            // Update sidebar icons
            const sidebarIcons = {
                'dashboard': '🏠',
                'water-tracking': '💧',
                'bmi-tracker': '⚖️',
                'nutrition': '🍽️',
                'exercise': '🏃‍♂️',
                'advanced-analytics': '📊',
                'meal-planner': '📅',
                'premium': '⭐'
            };

            document.querySelectorAll('.sidebar-menu a').forEach(item => {
                const section = item.getAttribute('data-section');
                if (section && sidebarIcons[section]) {
                    item.querySelector('i').textContent = sidebarIcons[section];
                    item.querySelector('i').className = ''; // Remove FontAwesome classes
                }
            });

            // Update quick action icons
            document.querySelector('.action-btn i.fa-tint').textContent = '💧';
            document.querySelector('.action-btn i.fa-utensils').textContent = '🍴';
            document.querySelector('.action-btn i.fa-running').textContent = '🏃‍♂️';
        });

        function setCustomTimer() {
            const minutes = parseInt(document.getElementById('customMinutes').value) || 0;
            const seconds = parseInt(document.getElementById('customSeconds').value) || 0;
            const totalSeconds = (minutes * 60) + seconds;
            
            if (totalSeconds > 0) {
                setTimer(totalSeconds);
            } else {
                alert('Please enter a valid time');
            }
        }

        // Analytics Generation
        function generateAnalytics() {
            const timeRange = document.getElementById('timeRange').value;
            const charts = ['weightChart', 'calorieChart', 'waterChart'];
            
            // Show loading state
            charts.forEach(chartId => {
                const element = document.getElementById(chartId);
                if (element) {
                    element.innerHTML = '<div class="loading">Loading data...</div>';
                }
            });

            const formData = new FormData();
            formData.append('action', 'get_analytics');
            formData.append('timeRange', timeRange);

            fetch('get_health_data.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON Parse Error:', text);
                        throw new Error('Invalid JSON response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    updateCharts(data.data);
                } else {
                    throw new Error(data.error || 'Failed to load analytics data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                charts.forEach(chartId => {
                    const element = document.getElementById(chartId);
                    if (element) {
                        element.innerHTML = `<div class="error">Error: ${error.message}</div>`;
                    }
                });
            });
        }

        function updateCharts(data) {
            // Weight Chart
            if (data.weight && data.weight.length > 0) {
                new Chart(document.getElementById('weightChart'), {
                    type: 'line',
                    data: {
                        labels: data.weight.map(entry => entry.date),
                        datasets: [{
                            label: 'Weight (kg)',
                            data: data.weight.map(entry => entry.value),
                            borderColor: '#4CAF50',
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: false } }
                    }
                });
            } else {
                document.getElementById('weightChart').innerHTML = 'No weight data available';
            }

            // Calories Chart
            if (data.calories && data.calories.length > 0) {
                new Chart(document.getElementById('calorieChart'), {
                    type: 'line',
                    data: {
                        labels: data.calories.map(entry => entry.date),
                        datasets: [{
                            label: 'Calories (kcal)',
                            data: data.calories.map(entry => entry.value),
                            borderColor: '#FFA500',
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } }
                    }
                });
            } else {
                document.getElementById('calorieChart').innerHTML = 'No calorie data available';
            }

            // Water Chart
            if (data.water && data.water.length > 0) {
                new Chart(document.getElementById('waterChart'), {
                    type: 'line',
                    data: {
                        labels: data.water.map(entry => entry.date),
                        datasets: [{
                            label: 'Water Intake (glasses)',
                            data: data.water.map(entry => entry.value),
                            borderColor: '#2196F3',
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true } }
                    }
                });
            } else {
                document.getElementById('waterChart').innerHTML = 'No water intake data available';
            }
        }

        // Add modal close functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('bmiModal');
            const closeBtn = document.querySelector('.close-modal');

            // Close modal when clicking the close button
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Prevent modal from closing when clicking inside modal content
            modal.querySelector('.modal-content').addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
       <br><br>
        <ul class="sidebar-menu">
            <li><a href="#" class="menu-item active" data-section="dashboard"><i>🏠</i>Dashboard</a></li>
            <li><a href="#" class="menu-item" data-section="water-tracking"><i>💧</i>Water Intake</a></li>
            <li><a href="#" class="menu-item" data-section="bmi-tracker"><i>⚖️</i>BMI Tracker</a></li>
            <li><a href="#" class="menu-item" data-section="nutrition"><i>🍽️</i>Nutrition</a></li>
            <li><a href="#" class="menu-item" data-section="exercise"><i>🏃‍♂️</i>Exercise</a></li>
            <li><a href="#" class="menu-item" data-section="meal-planner"><i>📅</i>Meal Planner</a></li>
            
            <li><a href="#" class="menu-item" data-section="profile"><i>👤</i> Health Profile</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
            <!-- Quick Overview Section -->
            <div class="section" id="overview-section">
                <h3>📊 Quick Overview</h3>
        <div class="mini-dashboard">
                    <div class="stat-box large">
                        <div class="stat-icon">💧</div>
                        <div class="stat-title">Water Intake</div>
                <div class="stat-value"><?php echo $water_intake; ?>/8</div>
                        <div class="stat-subtitle">glasses today</div>
            </div>
                    <div class="stat-box large">
                        <div class="stat-icon">🔥</div>
                        <div class="stat-title">Calories Today</div>
                <div class="stat-value"><?php echo $total_calories; ?></div>
                        <div class="stat-subtitle">kcal consumed</div>
            </div>
                    <div class="stat-box large">
                        <div class="stat-icon">⚖️</div>
                        <div class="stat-title">Current BMI</div>
                        <div class="stat-value"><?php echo $bmi ? number_format($bmi, 1) : '-'; ?></div>
                        <div class="stat-subtitle">kg/m²</div>
            </div>
            </div>
    </div>

            <!-- Water Tracking Section -->
            <div class="section" id="water-tracking-section" style="display: none;">
                <h3>💧 Water Tracking</h3>
                <div class="water-tracking">
                <div class="water-container">
                        <div class="water-level" style="height: <?php echo min(($water_intake / 8) * 100, 100); ?>%"></div>
                </div>
                <p class="water-progress"><?php echo $water_intake; ?> / 8 glasses consumed</p>
                    <form method="post" action="">
                        <button type="submit" name="increase_water" class="water-btn">
                            <i class="fas fa-plus"></i> Add Glass
                        </button>
                </form>
                </div>
            </div>
            
            <!-- BMI Calculator Section -->
            <div class="section" id="bmi-section">
                <h3>⚖️ BMI Calculator</h3>
                <form onsubmit="calculateBMI(event)" class="profile-form">
                    <div class="form-group">
                        <label for="weight">Weight (kg):</label>
                        <input type="number" id="weight" name="weight" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="height">Height (cm):</label>
                        <input type="number" id="height" name="height" required>
                    </div>
                    <div class="form-group">
                        <label for="age">Age:</label>
                        <input type="number" id="age" name="age" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <button type="submit" class="calculate-btn">Calculate BMI</button>
                </form>
            </div>
            
    <!-- Meal Tracking Section -->
        <div class="section" id="meal-section">
            <h3>🍽️ Meal Tracking</h3>
        <div class="flex-container">
            <div class="column">
                <h4>Today's Meals</h4>
                    <ul class="meal-list">
                    <?php foreach ($today_meals as $meal): ?>
                        <li>
                            <strong><?php echo ucfirst($meal['meal_type']); ?>:</strong> 
                                <?php echo $meal['meal_desc']; ?> (<?php echo $meal['calories']; ?> cal)
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php echo min(($total_calories / explode('-', $current_diet_plan['daily_calories'])[0]) * 100, 100); ?>%">
                            <?php echo $total_calories; ?> / <?php echo explode('-', $current_diet_plan['daily_calories'])[0]; ?> cal
                </div>
            </div>
                </div>
                <div class="column">
                    <h4>Add New Meal</h4>
                    <form method="post" action="">
                        <select name="meal_type" id="meal_type" required>
                            <option value="">Select meal type</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                        <input type="text" name="meal_desc" placeholder="Meal description" required>
                        <input type="number" name="calories" placeholder="Calories" required>
                        <button type="submit" name="add_meal">Add Meal</button>
                </form>
            </div>
        </div>
    </div>
                
        <!-- Diet Suggestions Section -->
        <div class="section" id="diet-section">
            <h3>🥗🍎🥦Diet Suggestions</h3>
                <div class="diet-suggestion">
                <h4>Recommended Daily Calories: <?php echo $current_diet_plan['daily_calories']; ?> kcal</h4>
                <div class="tips-list">
                    <h5>Tips for Your Diet Plan:</h5>
                    <ul>
                        <?php foreach ($current_diet_plan['tips'] as $tip): ?>
                            <li><?php echo $tip; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                </div>
            </div>
            
        <!-- Exercise Timer Section -->
        <div class="section" id="exercise-section">
            <h3>🏃‍♂️ Exercise Timer</h3>
            <div class="exercise-container">
                <div class="timer-display">
                    <span id="minutes">00</span>:<span id="seconds">00</span>
                </div>
                
                <div class="preset-timers">
                    <h4>Quick Start</h4>
                    <div class="preset-grid">
                        <button onclick="setTimer(30)" class="preset-card">
                            <div class="preset-time">30s</div>
                            <div class="preset-label">Quick Burst</div>
                        </button>
                        <button onclick="setTimer(60)" class="preset-card">
                            <div class="preset-time">1m</div>
                            <div class="preset-label">Short Break</div>
                        </button>
                        <button onclick="setTimer(300)" class="preset-card">
                            <div class="preset-time">5m</div>
                            <div class="preset-label">Full Exercise</div>
                        </button>
                    </div>
                </div>

                <div class="custom-timer">
                    <h4>Custom Timer</h4>
                    <div class="custom-timer-input">
                        <input type="number" id="customMinutes" min="0" max="60" placeholder="Min">
                        <input type="number" id="customSeconds" min="0" max="59" placeholder="Sec">
                        <button onclick="setCustomTimer()" class="custom-timer-btn">Set Timer</button>
                </div>
            </div>

                <div class="timer-controls">
                    <button onclick="startTimer()" class="timer-btn start">Start</button>
                    <button onclick="pauseTimer()" class="timer-btn pause">Pause</button>
                    <button onclick="resetTimer()" class="timer-btn reset">Reset</button>
            </div>
        </div>
    </div>
    
        
            
        <!-- Meal Planner Section -->
        <div class="section" id="meal-planner-section" style="display: none;">
            <h3>📅 Daily Meal Plan</h3>
            <div class="meal-planner-controls">
                <button onclick="generateMealPlan()" class="generate-plan-btn">
                    <i>🔄</i> Generate New Plan
                </button>
            </div>
            <div class="meal-planner-grid" id="mealPlanGrid">
                <!-- Single day meal plan will be generated here -->
            </div>
        </div>

        <!-- Profile Section -->
        <div class="section" id="profile-section" style="display: none;">
            <h3>👤 Health Profile</h3>
            <?php
            // Fetch user's profile data
            $profile_query = "SELECT height, weight, age, gender, bmi FROM health_tracking WHERE user_id = ? ORDER BY date DESC LIMIT 1";
            $profile_stmt = $conn->prepare($profile_query);
            if ($profile_stmt) {
                $profile_stmt->bind_param("i", $user_id);
                $profile_stmt->execute();
                $profile_result = $profile_stmt->get_result()->fetch_assoc();
                
                // Get BMI category
                $bmi_value = $profile_result['bmi'] ?? 0;
                $bmi_category = '';
                if ($bmi_value < 18.5) {
                    $bmi_category = "Underweight";
                } elseif ($bmi_value < 24.9) {
                    $bmi_category = "Normal weight";
                } elseif ($bmi_value < 29.9) {
                    $bmi_category = "Overweight";
                } else {
                    $bmi_category = "Obese";
                }
            ?>
            <div class="profile-card">
                <div class="profile-info">
                    <div class="info-row">
                        <label>Height:</label>
                        <span><?php echo $profile_result['height'] ?? '-'; ?> cm</span>
                </div>
                    <div class="info-row">
                        <label>Weight:</label>
                        <span><?php echo $profile_result['weight'] ?? '-'; ?> kg</span>
            </div>
                    <div class="info-row">
                        <label>Age:</label>
                        <span><?php echo $profile_result['age'] ?? '-'; ?> years</span>
        </div>
                    <div class="info-row">
                        <label>Gender:</label>
                        <span><?php echo ucfirst($profile_result['gender'] ?? '-'); ?></span>
                    </div>
                    <div class="info-row">
                        <label>BMI:</label>
                        <span><?php echo number_format($bmi_value, 1); ?></span>
                    </div>
                    <div class="info-row">
                        <label>BMI Category:</label>
                        <span><?php echo $bmi_category; ?></span>
                    </div>
                    <div class="last-updated">
                        <small>Last updated: <?php echo date('F j, Y'); ?></small>
                    </div>
                </div>
            </div>
            <?php
                $profile_stmt->close();
            }
            ?>
        </div>
    </div>
    </div>

    <!-- Updated BMI Results Modal -->
    <div id="bmiModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            
            <div class="bmi-header">
                <h3>Your BMI Results</h3>
                <p>Based on your height, weight, age, and gender</p>
            </div>
            
            <div class="result-card main-result">
                <h4>Your BMI Score</h4>
                <div class="bmi-value"><span class="bmi-score"></span></div>
                <div class="bmi-category"></div>
            </div>

            <div class="result-card">
                <h4>Detailed Health Metrics</h4>
                <div class="metrics-grid">
                    <div class="metric-item age-adjusted-bmi">
                        <div class="metric-label">
                            Age-Adjusted BMI
                            <span class="tooltip">
                                <span class="info-icon">i</span>
                                <span class="tooltip-text">BMI adjusted for your age to provide a more accurate assessment</span>
                            </span>
                        </div>
                        <div class="metric-value"></div>
                    </div>
                    
                    <div class="metric-item ideal-weight-range">
                        <div class="metric-label">
                            Ideal Weight Range
                            <span class="tooltip">
                                <span class="info-icon">i</span>
                                <span class="tooltip-text">Healthy weight range based on your height and body structure</span>
                            </span>
                        </div>
                        <div class="metric-value"></div>
                    </div>
                    
                    <div class="metric-item daily-calorie-needs">
                        <div class="metric-label">
                            Daily Calorie Needs
                            <span class="tooltip">
                                <span class="info-icon">i</span>
                                <span class="tooltip-text">Recommended daily calorie intake for maintaining current weight</span>
                            </span>
                        </div>
                        <div class="metric-value"></div>
                    </div>
                    
                    <div class="metric-item estimated-body-fat">
                        <div class="metric-label">
                            Estimated Body Fat
                            <span class="tooltip">
                                <span class="info-icon">i</span>
                                <span class="tooltip-text">Approximate body fat percentage based on BMI and other factors</span>
                            </span>
                        </div>
                        <div class="metric-value"></div>
                    </div>
                </div>
            </div>

            <div class="result-card">
                <h4>Your Personalized Health Recommendations</h4>
                <ul class="recommendations-list"></ul>
            </div>
        </div>
    </div>
</body>
</html>