<?php
session_start();
include 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get POST data
$weight = floatval($_POST['weight'] ?? 0);
$height = floatval($_POST['height'] ?? 0);
$age = intval($_POST['age'] ?? 0);
$gender = $_POST['gender'] ?? 'male';

if ($weight <= 0 || $height <= 0 || $age <= 0) {
    echo json_encode(['error' => 'Invalid input parameters']);
    exit;
}

// Convert height from cm to meters
$height_m = $height / 100;

// Calculate basic BMI
$bmi = $weight / ($height_m * $height_m);
$bmi = round($bmi, 2);

// Store data in the database
$query = "INSERT INTO health_tracking (user_id, date, weight, height, age, gender, bmi) 
          VALUES (?, CURDATE(), ?, ?, ?, ?, ?) 
          ON DUPLICATE KEY UPDATE 
          weight = VALUES(weight), 
          height = VALUES(height), 
          age = VALUES(age), 
          gender = VALUES(gender), 
          bmi = VALUES(bmi)";

$stmt = $conn->prepare($query);
$stmt->bind_param("iddisd", $user_id, $weight, $height, $age, $gender, $bmi);
$stmt->execute();

// Initialize response array
$response = [
    'bmi' => $bmi,
    'basic_category' => getBMICategory($bmi)
];

// Premium features
if ($role === 'premium user') {
    // Age-adjusted BMI calculation
    $age_adjusted_bmi = calculateAgeAdjustedBMI($bmi, $age);
    
    // Get detailed health metrics
    $health_metrics = calculateHealthMetrics($weight, $height_m, $age, $gender);
    
    // Add premium data to response
    $response['premium_data'] = [
        'age_adjusted_bmi' => $age_adjusted_bmi,
        'ideal_weight_range' => $health_metrics['ideal_weight_range'],
        'daily_calories' => $health_metrics['daily_calories'],
        'body_fat_estimate' => $health_metrics['body_fat_estimate'],
        'detailed_recommendation' => getDetailedRecommendation($bmi, $age, $gender)
    ];
}

echo json_encode($response);

// Helper functions
function getBMICategory($bmi) {
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

function calculateAgeAdjustedBMI($bmi, $age) {
    // Age adjustment factors based on research
    if ($age < 25) return $bmi;
    if ($age < 35) return $bmi * 1.02;
    if ($age < 45) return $bmi * 1.03;
    if ($age < 55) return $bmi * 1.04;
    if ($age < 65) return $bmi * 1.05;
    return $bmi * 1.06;
}

function calculateHealthMetrics($weight, $height, $age, $gender) {
    // Calculate ideal weight range (Hamwi formula)
    $base_weight = ($gender === 'male') ? 48 : 45.5;
    $height_cm = $height * 100;
    $height_over_152cm = max(0, $height_cm - 152.4);
    $weight_adjustment = ($gender === 'male') ? 2.7 : 2.2;
    
    $ideal_weight = $base_weight + ($height_over_152cm / 2.54 * $weight_adjustment);
    $ideal_weight_range = [
        'min' => round($ideal_weight * 0.9, 1),
        'max' => round($ideal_weight * 1.1, 1)
    ];

    // Calculate daily calorie needs (Harris-Benedict equation)
    $bmr = ($gender === 'male')
        ? 88.362 + (13.397 * $weight) + (4.799 * $height * 100) - (5.677 * $age)
        : 447.593 + (9.247 * $weight) + (3.098 * $height * 100) - (4.330 * $age);
    
    // Estimate body fat percentage
    $bmi = $weight / ($height * $height);
    $body_fat = ($gender === 'male')
        ? (1.20 * $bmi) + (0.23 * $age) - 16.2
        : (1.20 * $bmi) + (0.23 * $age) - 5.4;

    return [
        'ideal_weight_range' => $ideal_weight_range,
        'daily_calories' => round($bmr * 1.2), // Assuming light activity
        'body_fat_estimate' => max(min(round($body_fat, 1), 50), 5) // Capped between 5% and 50%
    ];
}

function getDetailedRecommendation($bmi, $age, $gender) {
    $recommendations = [];
    
    // BMI-based recommendations
    if ($bmi < 18.5) {
        $recommendations[] = "Your BMI indicates you're underweight. Focus on nutrient-dense foods and strength training.";
        $recommendations[] = "Aim to gain 0.5-1 kg per week through healthy eating.";
    } elseif ($bmi < 25) {
        $recommendations[] = "You're at a healthy weight. Maintain your current habits while focusing on nutrition quality.";
        $recommendations[] = "Regular exercise will help maintain your healthy weight.";
    } elseif ($bmi < 30) {
        $recommendations[] = "You're in the overweight range. Consider moderate calorie reduction and increased activity.";
        $recommendations[] = "Aim for 150 minutes of moderate exercise per week.";
    } else {
        $recommendations[] = "Your BMI indicates obesity. Consult a healthcare provider for personalized advice.";
        $recommendations[] = "Focus on sustainable lifestyle changes rather than rapid weight loss.";
    }
    
    // Age-specific recommendations
    if ($age < 30) {
        $recommendations[] = "Build healthy habits now to prevent future health issues.";
    } elseif ($age < 50) {
        $recommendations[] = "Focus on maintaining muscle mass through strength training.";
    } else {
        $recommendations[] = "Include balance exercises to maintain stability and prevent falls.";
    }
    
    // Gender-specific recommendations
    if ($gender === 'male') {
        $recommendations[] = "Include adequate protein for muscle maintenance.";
    } else {
        $recommendations[] = "Ensure adequate calcium and iron intake.";
    }
    
    return $recommendations;
} 