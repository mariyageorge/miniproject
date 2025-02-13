
<?php
include 'connect.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));


// Create expenses table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $sql)) {
    echo "Error creating table: " . mysqli_error($conn);
}

// Add a new expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $user_id = $_SESSION['user_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $date = $_POST['date'];

    $add_sql = "INSERT INTO expenses (user_id, amount, description, category, date) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $add_sql);
    mysqli_stmt_bind_param($stmt, 'idsss', $user_id, $amount, $description, $category, $date);

    if (!mysqli_stmt_execute($stmt)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Edit an existing expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_expense'])) {
    $expense_id = $_POST['expense_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $date = $_POST['date'];

    $edit_sql = "UPDATE expenses SET amount=?, description=?, category=?, date=? WHERE expense_id=?";
    $stmt = mysqli_prepare($conn, $edit_sql);
    mysqli_stmt_bind_param($stmt, 'dsssi', $amount, $description, $category, $date, $expense_id);

    if (!mysqli_stmt_execute($stmt)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Delete an expense
if (isset($_GET['delete_expense'])) {
    $expense_id = $_GET['delete_expense'];

    $delete_sql = "DELETE FROM expenses WHERE expense_id=?";
    $stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt, 'i', $expense_id);

    if (!mysqli_stmt_execute($stmt)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Fetch expenses for the user
$expenses_sql = "SELECT * FROM expenses WHERE user_id=?";
$stmt = mysqli_prepare($conn, $expenses_sql);
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$expenses_result = mysqli_stmt_get_result($stmt);

// Calculate total expenses
$total_expenses = 0;
while ($expense = mysqli_fetch_assoc($expenses_result)) {
    $total_expenses += $expense['amount'];
}

// Reset the result pointer
mysqli_stmt_execute($stmt);
$expenses_result = mysqli_stmt_get_result($stmt);

// Group expenses by category
$expenses_by_category = [];
while ($expense = mysqli_fetch_assoc($expenses_result)) {
    $expenses_by_category[$expense['category']][] = $expense;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker - LifeSync</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: var(--bg-color);
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

        .expense-form {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .expense-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .expense-card:hover {
            transform: translateY(-5px);
        }

        .category-header {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .expense-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .expense-item:last-child {
            border-bottom: none;
        }

        .total-expenses {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 2rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .category-icon {
            width: 24px;
            height: 24px;
            filter: brightness(0) invert(1);
        }

        .modal-content {
            background-color: var(--bg-color);
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-body {
            background-color: white;
        }
        .header {
            width: 100%;
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
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
            color: #8B4513;
            letter-spacing: 1px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .back-button {
            padding: 8px 16px;
            background:  #8B4513;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background:  #A0522D;
        }
    </style>
</head>
<body>
    <!-- Main Header -->
      <!-- Header -->
      <header class="header">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-infinity"></i>
            </div>
            <span class="logo-text">LIFE-SYNC</span>
        </div>
        
        <div class="header-right">
        <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> 
    </a>
        </div>
    </header>

  

    <div class="container mt-5">
        <!-- Expense Form -->
        <div class="expense-form">
            <h4 class="mb-4">Add New Expense</h4>
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Amount</label>
                    <input type="number" name="amount" placeholder="Enter amount" step="0.01" required class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" placeholder="Enter description" required class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="Food">Food</option>
                        <option value="Transportation">Transportation</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Entertainment">Entertainment</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" required class="form-control">
                </div>
                <div class="col-12">
                    <button type="submit" name="add_expense" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-2"></i>Add Expense
                    </button>
                </div>
            </form>
        </div>

        <div class="row">
            <!-- Expenses List -->
            <div class="col-md-8">
                <?php foreach ($expenses_by_category as $category => $category_expenses): ?>
                    <div class="expense-card">
                        <div class="category-header">
                            <?php if ($category === 'Food'): ?>
                                <i class="fas fa-utensils fa-lg"></i>
                            <?php elseif ($category === 'Transportation'): ?>
                                <i class="fas fa-car fa-lg"></i>
                            <?php elseif ($category === 'Utilities'): ?>
                                <i class="fas fa-bolt fa-lg"></i>
                            <?php elseif ($category === 'Entertainment'): ?>
                                <i class="fas fa-film fa-lg"></i>
                            <?php else: ?>
                                <i class="fas fa-shopping-bag fa-lg"></i>
                            <?php endif; ?>
                            <h5 class="mb-0"><?php echo htmlspecialchars($category); ?></h5>
                        </div>
                        <?php if (count($category_expenses) > 0): ?>
                            <?php foreach ($category_expenses as $expense): ?>
                                <div class="expense-item">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($expense['description']); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($expense['date'])); ?></small>
                                    </div>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="h5 mb-0">₹<?php echo number_format($expense['amount'], 2); ?></span>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $expense['expense_id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete_expense=<?php echo $expense['expense_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this expense?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $expense['expense_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Expense</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST">
                                                    <input type="hidden" name="expense_id" value="<?php echo $expense['expense_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Amount</label>
                                                        <input type="number" name="amount" value="<?php echo $expense['amount']; ?>" step="0.01" required class="form-control">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <input type="text" name="description" value="<?php echo htmlspecialchars($expense['description']); ?>" required class="form-control">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select name="category" class="form-select" required>
                                                            <option value="Food" <?php echo $expense['category'] === 'Food' ? 'selected' : ''; ?>>Food</option>
                                                            <option value="Transportation" <?php echo $expense['category'] === 'Transportation' ? 'selected' : ''; ?>>Transportation</option>
                                                            <option value="Utilities" <?php echo $expense['category'] === 'Utilities' ? 'selected' : ''; ?>>Utilities</option>
                                                            <option value="Entertainment" <?php echo $expense['category'] === 'Entertainment' ? 'selected' : ''; ?>>Entertainment</option>
                                                            <option value="Other" <?php echo $expense['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Date</label>
                                                        <input type="date" name="date" value="<?php echo $expense['date']; ?>" required class="form-control">
                                                    </div>
                                                    <button type="submit" name="edit_expense" class="btn btn-primary">Update Expense</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-receipt fa-2x mb-3"></i>
                                <p class="mb-0">No expenses in this category</p>
                            </div>
                        <?php endif; ?>
                    </div>
                        </div>
                <?php endforeach; ?>
            </div>

            <!-- Summary and Chart Section -->
            <div class="col-md-4">
                <div class="total-expenses mb-4">
                    <i class="fas fa-money-bill-wave mb-2 fa-2x"></i>
                    <h5 class="mb-3">Total Expenses</h5>
                    <span class="h3">₹<?php echo number_format($total_expenses, 2); ?></span>
                </div>

                <!-- <div class="chart-container">
                    <h5 class="text-center mb-3">Expense Distribution</h5>
                    <canvas id="expenseChart" height="300"></canvas>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-center py-4 mt-4">
        <p class="text-muted">&copy; 2025 LifeSync. All rights reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize the pie chart
        const ctx = document.getElementById('expenseChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: [
                    <?php
                    foreach ($expenses_by_category as $category => $expenses) {
                        echo "'" . $category . "',";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php
                        foreach ($expenses_by_category as $category => $expenses) {
                            $total = array_sum(array_column($expenses, 'amount'));
                            echo $total . ",";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        '#6F4E37',  // Primary color
                        '#8B4513',  // Secondary color
                        '#D2691E',  // Accent color
                        '#A0522D',  // Additional brown shade
                        '#8B7355'   // Another brown shade
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>