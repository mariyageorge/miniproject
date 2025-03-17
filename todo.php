<?php
include 'connect.php';
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // Get the user's role from the session

$database_name = "lifesync_db";
mysqli_select_db($conn, $database_name) or die("Database not found: " . mysqli_error($conn));

// Create tasks table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS tasks (
    task_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    description VARCHAR(255) NOT NULL,
    status ENUM('incomplete', 'complete') DEFAULT 'incomplete',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $sql)) {
    echo "Error creating table: " . mysqli_error($conn);
}

// Add a new task with a limit of 5 per day for regular users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    // Check if the user is a premium user
    if ($role === 'premium user') {
        // Premium users can add unlimited tasks
        $add_sql = "INSERT INTO tasks (user_id, description) VALUES ('$user_id', '$description')";
        if (!mysqli_query($conn, $add_sql)) {
            echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        // Regular users are limited to 5 tasks per day
        $today = date('Y-m-d');
        $task_count_query = "SELECT COUNT(*) AS task_count FROM tasks WHERE user_id='$user_id' AND DATE(created_at) = '$today'";
        $task_count_result = mysqli_query($conn, $task_count_query);
        $task_count_row = mysqli_fetch_assoc($task_count_result);
        $task_count = $task_count_row['task_count'];

        if ($task_count >= 5) {
            echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var taskLimitModal = new bootstrap.Modal(document.getElementById('taskLimitModal'));
                        taskLimitModal.show();
                    });
                  </script>";
        } else {
            // Proceed with inserting a new task
            $add_sql = "INSERT INTO tasks (user_id, description) VALUES ('$user_id', '$description')";
            if (!mysqli_query($conn, $add_sql)) {
                echo "<script>alert('Error: " . mysqli_error($conn) . "');</script>";
            }
        }
    }
}

// Edit an existing task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id = $_POST['task_id'];
    $description = $_POST['description'];

    $edit_sql = "UPDATE tasks SET description='$description' WHERE task_id='$task_id'";
    if (mysqli_query($conn, $edit_sql)) {
        echo "Task updated successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// Delete a task
if (isset($_GET['delete_task'])) {
    $task_id = $_GET['delete_task'];

    $delete_sql = "DELETE FROM tasks WHERE task_id='$task_id'";
    if (!mysqli_query($conn, $delete_sql)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Mark a task as complete
if (isset($_GET['complete_task'])) {
    $task_id = $_GET['complete_task'];

    $complete_sql = "UPDATE tasks SET status='complete' WHERE task_id='$task_id'";
    if (!mysqli_query($conn, $complete_sql)) {
        echo "Task marked as complete";
        echo "Error: " . mysqli_error($conn);
    }
}

// Fetch tasks for display
$tasks_sql = "SELECT * FROM tasks WHERE user_id='" . $_SESSION['user_id'] . "'";
$tasks_result = mysqli_query($conn, $tasks_sql);

$notes_sql = "CREATE TABLE IF NOT EXISTS notes (
    note_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    content TEXT NOT NULL,
    is_locked BOOLEAN DEFAULT FALSE,
    lock_pattern VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $notes_sql)) {
    echo "Error creating notes table: " . mysqli_error($conn);
}

// Add a new note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);

    $add_note_sql = "INSERT INTO notes (user_id, content) VALUES ('$user_id', '$content')";
    if (!mysqli_query($conn, $add_note_sql)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Edit an existing note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = $_POST['note_id'];
    $content = mysqli_real_escape_string($conn, $_POST['content']);

    $edit_note_sql = "UPDATE notes SET content='$content' WHERE note_id='$note_id'";
    if (!mysqli_query($conn, $edit_note_sql)) {
        echo "Error updating note: " . mysqli_error($conn);
    }
}

// Delete a note
if (isset($_GET['delete_note'])) {
    $note_id = $_GET['delete_note'];

    $delete_note_sql = "DELETE FROM notes WHERE note_id='$note_id'";
    if (!mysqli_query($conn, $delete_note_sql)) {
        echo "Error deleting note: " . mysqli_error($conn);
    }
}

// Fetch notes for display
$notes_sql = "SELECT * FROM notes WHERE user_id='" . $_SESSION['user_id'] . "'";
$notes_result = mysqli_query($conn, $notes_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Todo List</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --nude-300: #DBBFAE;
            --nude-400: #C6A792;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
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
            background: var(--brown-primary);
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
            color: var(--brown-primary);
            letter-spacing: 1px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .logout-btn {
            padding: 0.5rem 1.2rem;
            background: white;
            border: 2px solid var(--brown-primary);
            color: var(--brown-primary);
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: var(--brown-primary);
            color: white;
        }

        body {
            background-image: url("images/todobg.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed; /* This is the key property */
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden; /* Prevent body scrolling */
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .tab-container {
            max-width: 800px;
            margin: 100px auto 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-height: calc(100vh - 140px); /* Adjust based on your header height + margins */
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden;
        }
        
        /* Ensure the tab content areas have appropriate heights */
        .tab-content {
            min-height: 300px;
            height: auto;
        }
        
        /* Smooth scrollbar for modern browsers */
        .tab-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .tab-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .tab-container::-webkit-scrollbar-thumb {
            background: var(--nude-300);
            border-radius: 10px;
        }
        
        .tab-container::-webkit-scrollbar-thumb:hover {
            background: var(--nude-400);
        }
        
        .task-item {
            display: flex;
            justify-content: space-between; 
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #fff;
        }
        
        .task-item.complete {
            background-color: #e9ecef;
            text-decoration: line-through;
        }
        
        .task-actions {
            display: flex;
            gap: 10px;
        }
        
        .modal-content {
            padding: 20px;
        }
        
        .back-button {
            padding: 8px 16px;
            background: #8B4513;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background: #A0522D;
        }
        
        .tab-buttons {
            display: flex;
            gap: 400px;
            margin-bottom: 30px;
            border-bottom: 2px solid #F5ECE5;
            padding-bottom: 10px;
        }
        
        .tab-button {
            padding: 12px 24px;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .tab-button::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #8B4513;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .tab-button.active {
            color: #8B4513;
        }
        
        .tab-button.active::after {
            transform: scaleX(1);
        }
        
        .tab-content {
            background: white;
            border-radius: 12px;
            min-height: 300px;
        }
        
        .content-section {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .content-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .btn-primary {
            background-color: var(--brown-primary);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: rgb(197, 130, 82); /* Darker shade on hover */
        }
        
        /* New styles for note cards */
        .notes-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .note-card {
            background-color: var(--nude-100);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            min-height: 120px;
        }
        
        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .note-content {
            margin-bottom: 30px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .note-actions {
            position: absolute;
            bottom: 10px;
            right: 10px;
            display: flex;
            gap: 8px;
        }
        
        .note-date {
            position: absolute;
            bottom: 10px;
            left: 10px;
            font-size: 12px;
            color: #888;
        }
        
        /* Upgrade button styles */
        .upgrade-btn {
            background-color: #FFD700;
            color: #8B4513;
            border: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .upgrade-btn:hover {
            background-color: #FFC107;
            transform: scale(1.05);
        }
        
        /* Modal styles */
        .premium-modal .modal-content {
            border-radius: 15px;
            border: none;
        }
        
        .premium-modal .modal-header {
            background-color: var(--nude-200);
            border-radius: 15px 15px 0 0;
            border-bottom: none;
        }
        
        .premium-icon {
            font-size: 42px;
            color: #FFD700;
            margin-bottom: 15px;
        }
        
        .premium-message {
            font-size: 18px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
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

    <!-- Task Limit Modal -->
    <div class="modal fade premium-modal" id="taskLimitModal" tabindex="-1" aria-labelledby="taskLimitModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskLimitModalLabel">Daily Task Limit Reached</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="premium-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="premium-message">
                        <p>🚫 You've reached your daily limit of 5 tasks!</p>
                        <p>💎 Upgrade to premium for unlimited tasks and more features.</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <a href="upgrade.php" class="btn upgrade-btn">
                        <i class="fas fa-gem"></i> Upgrade to Premium
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-button active" data-target="todo-section">
                <i class="fas fa-check-circle"></i> Todo List
            </button>
            <button class="tab-button" data-target="notes-section">
                <i class="fas fa-sticky-note"></i> Notes
            </button>
        </div>
        <div class="tab-content">
            <div id="todo-section" class="content-section active">
                <form method="POST" class="mb-4 d-flex gap-2">
                    <input type="text" name="description" placeholder="✏️ Add a new task..." required class="form-control">
                    <button type="submit" name="add_task" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
                <ul class="list-group">
                    <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
                        <li class="list-group-item task-item <?php echo $task['status'] === 'complete' ? 'complete' : ''; ?>">
                            <div>
                                <?php if ($task['status'] === 'complete'): ?>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                <?php else: ?>
                                    <i class="far fa-circle me-2"></i>
                                <?php endif; ?>
                                <strong><?php echo $task['description']; ?></strong>
                            </div>
                            <div class="task-actions">
                                <?php if ($task['status'] === 'incomplete'): ?>
                                    <a href="?complete_task=<?php echo $task['task_id']; ?>" class="btn btn-success btn-sm" title="Mark as complete">
                                        <i class="fas fa-check"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="?delete_task=<?php echo $task['task_id']; ?>" class="btn btn-danger btn-sm" title="Delete task">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $task['task_id']; ?>" title="Edit task">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </div>
                        </li>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?php echo $task['task_id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">✏️ Edit Task</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST">
                                            <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                            <input type="text" name="description" value="<?php echo $task['description']; ?>" required class="form-control mb-2">
                                            <button type="submit" name="edit_task" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Update Task
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </ul>
            </div>
            <div id="notes-section" class="content-section">
                <form method="POST" class="mb-4 d-flex gap-2">
                    <input type="text" name="content" placeholder="📝 Write a note..." required class="form-control">
                    <button type="submit" name="add_note" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
                <div class="notes-container">
                    <?php while ($note = mysqli_fetch_assoc($notes_result)): ?>
                        <div class="note-card">
                            <div class="note-content">
                                <?php echo htmlspecialchars($note['content']); ?>
                            </div>
                            <div class="note-date">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo date('M d, Y', strtotime($note['created_at'])); ?>
                            </div>
                            <div class="note-actions">
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editNoteModal<?php echo $note['note_id']; ?>" title="Edit note">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <a href="?delete_note=<?php echo $note['note_id']; ?>" class="btn btn-danger btn-sm" title="Delete note">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Edit Note Modal -->
                        <div class="modal fade" id="editNoteModal<?php echo $note['note_id']; ?>" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">📝 Edit Note</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST">
                                            <input type="hidden" name="note_id" value="<?php echo $note['note_id']; ?>">
                                            <textarea name="content" class="form-control mb-2" rows="4" required><?php echo htmlspecialchars($note['content']); ?></textarea>
                                            <button type="submit" name="edit_note" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Update Note
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center py-4 mt-4">
        <p class="text-muted">&copy; 2025 LifeSync. All rights reserved.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                // Remove active class from all buttons and sections
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Show corresponding section
                const target = this.getAttribute('data-target');
                document.getElementById(target).classList.add('active');
            });
        });
    </script>
</body>
</html>