<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include 'connect.php';

// Create diet_plans table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS diet_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_type ENUM('breakfast', 'lunch', 'dinner', 'snacks') NOT NULL,
    meal_description TEXT NOT NULL,
    calories INT NOT NULL,
    dietary_type VARCHAR(50),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$conn->query($createTableQuery);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_meal'])) {
        $meal_type = $conn->real_escape_string($_POST['meal_type']);
        $meal_description = $conn->real_escape_string($_POST['meal_description']);
        $calories = intval($_POST['calories']);
        $dietary_type = $conn->real_escape_string($_POST['dietary_type']);

        $query = "INSERT INTO diet_plans (meal_type, meal_description, calories, dietary_type) 
                 VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssis", $meal_type, $meal_description, $calories, $dietary_type);
        
        if ($stmt->execute()) {
            $success_message = "Meal plan added successfully!";
        } else {
            $error_message = "Error adding meal plan: " . $conn->error;
        }
    }

    if (isset($_POST['delete_meal'])) {
        $meal_id = intval($_POST['meal_id']);
        $query = "DELETE FROM diet_plans WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $meal_id);
        
        if ($stmt->execute()) {
            $success_message = "Meal plan deleted successfully!";
        } else {
            $error_message = "Error deleting meal plan: " . $conn->error;
        }
    }
}

// Fetch existing meal plans
$query = "SELECT * FROM diet_plans ORDER BY meal_type, created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Diet Plans - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6F4E37;
            --secondary-color: #8B4513;
            --accent-color: #D2691E;
            --bg-color: #F4ECD8;
            --card-bg: #F5DEB3;
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Poppins', sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
        }

        .card {
            background-color: var(--card-bg);
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .meal-type-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .breakfast { background-color: #FFB74D; }
        .lunch { background-color: #81C784; }
        .dinner { background-color: #7986CB; }
        .snacks { background-color: #F06292; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-utensils"></i> Manage Diet Plans</h2>
            <a href="admindash.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Add New Meal Plan Form -->
        <div class="card mb-4">
            <div class="card-body">
                <h4 class="card-title">Add New Meal Plan</h4>
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Meal Type</label>
                        <select name="meal_type" class="form-select" required>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snacks">Snacks</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" name="meal_description" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Calories</label>
                        <input type="number" name="calories" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dietary Type</label>
                        <select name="dietary_type" class="form-select" required>
                            <option value="regular">Regular</option>
                            <option value="vegetarian">Vegetarian</option>
                            <option value="vegan">Vegan</option>
                            <option value="keto">Keto</option>
                            <option value="paleo">Paleo</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_meal" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Meal Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing Meal Plans -->
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Existing Meal Plans</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Meal Type</th>
                                <th>Description</th>
                                <th>Calories</th>
                                <th>Dietary Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="meal-type-badge <?php echo $row['meal_type']; ?>">
                                            <?php echo ucfirst($row['meal_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['meal_description']); ?></td>
                                    <td><?php echo $row['calories']; ?> cal</td>
                                    <td><?php echo ucfirst($row['dietary_type']); ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="meal_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" name="delete_meal" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this meal plan?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html> 