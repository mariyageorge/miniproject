<?php
session_start();
include 'connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_POST['action'] === 'get_meal_plan') {
    // Get user's health data
    $query = "SELECT * FROM health_tracking WHERE user_id = ? ORDER BY date DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $health_data = $result->fetch_assoc();
    $stmt->close();

    // Get user preferences
    $pref_query = "SELECT * FROM user_preferences WHERE user_id = ?";
    $pref_stmt = $conn->prepare($pref_query);
    $pref_stmt->bind_param("i", $user_id);
    $pref_stmt->execute();
    $pref_result = $pref_stmt->get_result();
    $preferences = $pref_result->fetch_assoc();
    $pref_stmt->close();

    // Get meals from diet_plans table
    $meal_types = ['breakfast', 'lunch', 'dinner', 'snacks'];
    $daily_plan = [];
    
    foreach ($meal_types as $meal_type) {
        $meal_query = "SELECT * FROM diet_plans WHERE meal_type = ? AND is_active = TRUE ORDER BY RAND() LIMIT 1";
        $meal_stmt = $conn->prepare($meal_query);
        $meal_stmt->bind_param("s", $meal_type);
        $meal_stmt->execute();
        $meal_result = $meal_stmt->get_result();
        
        if ($meal = $meal_result->fetch_assoc()) {
            $daily_plan[$meal_type] = [
                'name' => $meal['meal_description'],
                'calories' => $meal['calories']
            ];
        }
        $meal_stmt->close();
    }

    // Calculate total calories
    $total_calories = array_sum(array_map(function($meal) {
        return $meal['calories'];
    }, $daily_plan));

    $response = [
        'success' => true,
        'date' => date('Y-m-d'),
        'meal_plan' => $daily_plan,
        'total_calories' => $total_calories
    ];

    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
