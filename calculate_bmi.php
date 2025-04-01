<?php
session_start();
include 'connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get POST data
$weight = floatval($_POST['weight'] ?? 0);
$height = floatval($_POST['height'] ?? 0); // height in cm
$age = intval($_POST['age'] ?? 0);
$gender = $_POST['gender'] ?? '';

// Validate input
if ($weight <= 0 || $height <= 0 || $age <= 0 || !in_array($gender, ['male', 'female'])) {
    echo json_encode(['error' => 'Invalid input parameters']);
    exit;
}

// Convert height to meters for BMI calculation
$height_m = $height / 100;

// Calculate BMI
$bmi = round($weight / ($height_m * $height_m), 2);

// Determine BMI category
function getBMICategory($bmi) {
    if ($bmi < 18.5) return "Underweight";
    if ($bmi < 24.9) return "Normal weight";
    if ($bmi < 29.9) return "Overweight";
    return "Obese";
}

// Calculate age-adjusted BMI
$age_factor = $age >= 65 ? 1.1 : 1.0;
$age_adjusted_bmi = round($bmi * $age_factor, 2);

// Calculate ideal weight range (using Hamwi formula)
function calculateIdealWeight($height_cm, $gender) {
    $height_inches = $height_cm / 2.54;
    $base_height = 60; // 5 feet in inches
    
    if ($gender === 'male') {
        $base_weight = 48.0; // kg
        $weight_per_inch = 2.7; // kg
    } else {
        $base_weight = 45.5; // kg
        $weight_per_inch = 2.2; // kg
    }
    
    $additional_inches = max(0, $height_inches - $base_height);
    $ideal_weight = $base_weight + ($additional_inches * $weight_per_inch);
    
    return [
        'min' => round($ideal_weight * 0.95, 1),
        'max' => round($ideal_weight * 1.05, 1)
    ];
}

// Calculate body fat estimate using BMI
function estimateBodyFat($bmi, $age, $gender) {
    $base_fat = ($gender === 'male') ? -16 : -5;
    $fat_estimate = (1.20 * $bmi) + (0.23 * $age) + $base_fat;
    return max(min(round($fat_estimate, 1), 50), 5); // Limit between 5% and 50%
}

// Calculate daily calorie needs (using Mifflin-St Jeor equation)
function calculateDailyCalories($weight, $height_cm, $age, $gender) {
    if ($gender === 'male') {
        $bmr = (10 * $weight) + (6.25 * $height_cm) - (5 * $age) + 5;
    } else {
        $bmr = (10 * $weight) + (6.25 * $height_cm) - (5 * $age) - 161;
    }
    
    // Assuming moderate activity level (multiply by 1.55)
    return round($bmr * 1.55);
}

// Generate personalized recommendations
function getRecommendations($bmi, $category) {
    $recommendations = [];
    
    switch ($category) {
        case 'Underweight':
            $recommendations = [
                "Gradually increase your caloric intake with nutrient-rich foods",
                "Include protein-rich foods in every meal",
                "Add healthy fats like nuts, avocados, and olive oil to your diet",
                "Consider strength training to build muscle mass",
                "Consult with a healthcare provider about your weight gain plan"
            ];
            break;
        case 'Normal weight':
            $recommendations = [
                "Maintain your balanced diet and healthy eating habits",
                "Stay physically active with regular exercise",
                "Focus on nutrient-rich whole foods",
                "Keep monitoring your weight monthly",
                "Consider incorporating strength training if not already doing so"
            ];
            break;
        case 'Overweight':
            $recommendations = [
                "Create a moderate calorie deficit through diet and exercise",
                "Increase physical activity to at least 150 minutes per week",
                "Focus on portion control and mindful eating",
                "Include more fruits, vegetables, and lean proteins",
                "Consider keeping a food diary to track intake"
            ];
            break;
        case 'Obese':
            $recommendations = [
                "Consult with a healthcare provider about a safe weight loss plan",
                "Start with gentle, low-impact exercises like walking or swimming",
                "Focus on gradual, sustainable dietary changes",
                "Consider working with a registered dietitian",
                "Monitor other health markers besides weight"
            ];
            break;
    }
    
    return $recommendations;
}

// Prepare response data
$bmi_category = getBMICategory($bmi);
$ideal_weight_range = calculateIdealWeight($height, $gender);
$body_fat_estimate = estimateBodyFat($bmi, $age, $gender);
$daily_calories = calculateDailyCalories($weight, $height, $age, $gender);
$detailed_recommendation = getRecommendations($bmi, $bmi_category);

$response = [
    'bmi' => $bmi,
    'category' => $bmi_category,
    'age_adjusted_bmi' => $age_adjusted_bmi,
    'ideal_weight_range' => $ideal_weight_range,
    'body_fat' => $body_fat_estimate,
    'daily_calories' => $daily_calories,
    'recommendations' => $detailed_recommendation
];

// Store the results in the database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $date = date('Y-m-d');
    
    $stmt = $conn->prepare("INSERT INTO health_tracking (user_id, date, weight, height, age, gender, bmi) 
                           VALUES (?, ?, ?, ?, ?, ?, ?) 
                           ON DUPLICATE KEY UPDATE 
                           weight = VALUES(weight), 
                           height = VALUES(height), 
                           age = VALUES(age), 
                           gender = VALUES(gender), 
                           bmi = VALUES(bmi)");
                           
    $stmt->bind_param("isddisd", $user_id, $date, $weight, $height, $age, $gender, $bmi);
    $stmt->execute();
    $stmt->close();
}

echo json_encode($response); 