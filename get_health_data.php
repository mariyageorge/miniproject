<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once 'connect.php';

if (!isset($_POST['action'])) {
    die(json_encode(['success' => false, 'error' => 'No action specified']));
}

$action = $_POST['action'];

try {
    switch ($action) {
        case 'get_meal_plan':
            getMealPlan($conn);
            break;
        case 'get_analytics':
            getAnalyticsData($conn, $_POST['timeRange'] ?? 'week');
            break;
        default:
            die(json_encode(['success' => false, 'error' => 'Invalid action']));
    }
} catch (Exception $e) {
    die(json_encode(['success' => false, 'error' => $e->getMessage()]));
}

function getMealPlan($conn) {
    try {
        $meals = [
            'breakfast' => [],
            'lunch' => [],
            'dinner' => [],
            'snacks' => []
        ];

        $query = "SELECT meal_type, meal_description, calories FROM diet_plans WHERE is_active = 1";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $conn->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $meal_desc = $row['meal_description'] . ' (' . $row['calories'] . ' cal)';
            $meals[strtolower($row['meal_type'])][] = $meal_desc;
        }

        // If no meals found, use default meals
        if (empty($meals['breakfast']) && empty($meals['lunch']) && 
            empty($meals['dinner']) && empty($meals['snacks'])) {
            $meals = getDefaultMeals();
        }

        die(json_encode([
            'success' => true,
            'data' => $meals
        ]));
    } catch (Exception $e) {
        die(json_encode([
            'success' => false,
            'error' => 'Failed to generate meal plan: ' . $e->getMessage()
        ]));
    }
}

function getAnalyticsData($conn, $timeRange) {
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        if (!$user_id) {
            throw new Exception('User not logged in');
        }

        // Calculate date range
        $end_date = date('Y-m-d');
        switch ($timeRange) {
            case 'today':
                $start_date = $end_date;
                break;
            case 'week':
                $start_date = date('Y-m-d', strtotime('-7 days'));
                break;
            case 'month':
                $start_date = date('Y-m-d', strtotime('-30 days'));
                break;
            case '3months':
                $start_date = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $start_date = date('Y-m-d', strtotime('-365 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-7 days'));
        }

        // Fetch data
        $data = [
            'weight' => getWeightData($conn, $user_id, $start_date, $end_date),
            'calories' => getCaloriesData($conn, $user_id, $start_date, $end_date),
            'water' => getWaterData($conn, $user_id, $start_date, $end_date)
        ];

        die(json_encode([
            'success' => true,
            'data' => $data
        ]));
    } catch (Exception $e) {
        die(json_encode([
            'success' => false,
            'error' => 'Failed to fetch analytics data: ' . $e->getMessage()
        ]));
    }
}

function getWeightData($conn, $user_id, $start_date, $end_date) {
    $query = "SELECT date, weight as value FROM health_tracking 
              WHERE user_id = ? AND date BETWEEN ? AND ? 
              ORDER BY date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCaloriesData($conn, $user_id, $start_date, $end_date) {
    $query = "SELECT date, SUM(calories) as value FROM meal_tracking 
              WHERE user_id = ? AND date BETWEEN ? AND ? 
              GROUP BY date ORDER BY date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getWaterData($conn, $user_id, $start_date, $end_date) {
    $query = "SELECT date, water_intake as value FROM health_tracking 
              WHERE user_id = ? AND date BETWEEN ? AND ? 
              ORDER BY date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getDefaultMeals() {
    return [
        'breakfast' => [
            'Oatmeal with berries (300 cal)',
            'Greek yogurt parfait (250 cal)',
            'Whole grain toast with avocado (280 cal)'
        ],
        'lunch' => [
            'Grilled chicken salad (400 cal)',
            'Quinoa bowl with vegetables (380 cal)',
            'Turkey and avocado wrap (420 cal)'
        ],
        'dinner' => [
            'Baked salmon with vegetables (500 cal)',
            'Lean beef stir-fry with rice (520 cal)',
            'Grilled tofu with quinoa (450 cal)'
        ],
        'snacks' => [
            'Apple with almond butter (180 cal)',
            'Greek yogurt with honey (150 cal)',
            'Mixed nuts and dried fruit (160 cal)'
        ]
    ];
} 