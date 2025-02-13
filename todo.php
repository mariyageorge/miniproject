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

// Create tasks table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS tasks (
    task_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED,
    description VARCHAR(255) NOT NULL,
    status ENUM('incomplete', 'complete') DEFAULT 'incomplete',
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $sql)) {
    echo "Error creating table: " . mysqli_error($conn);
}

// Add a new task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $user_id = $_SESSION['user_id']; // Assuming user_id is stored in session
    $description = $_POST['description'];
    $category = $_POST['category'];

    $add_sql = "INSERT INTO tasks (user_id, description, category) VALUES ('$user_id', '$description', '$category')";
    if (!mysqli_query($conn, $add_sql)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Edit an existing task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    $task_id = $_POST['task_id'];
    $description = $_POST['description'];
    $category = $_POST['category'];

    $edit_sql = "UPDATE tasks SET description='$description', category='$category' WHERE task_id='$task_id'";
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
    $user_id = $_SESSION['user_id'];
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $pattern_lock = mysqli_real_escape_string($conn, $_POST['pattern_lock']);

    $add_note_sql = "INSERT INTO notes (user_id, content, pattern_lock) VALUES ('$user_id', '$content', '$pattern_lock')";
    if (!mysqli_query($conn, $add_note_sql)) {
        echo "Error: " . mysqli_error($conn);
    }
}

// Edit an existing note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = $_POST['note_id'];
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $pattern_lock = mysqli_real_escape_string($conn, $_POST['pattern_lock']);

    $edit_note_sql = "UPDATE notes SET content='$content', pattern_lock='$pattern_lock' WHERE note_id='$note_id'";
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
                background-color: var(--nude-100); 
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
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
        .tab-container {
  max-width: 800px;
  margin: 100px auto 0;
  background: white;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  padding: 20px;
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
    background-color:rgb(197, 130, 82); /* Darker shade on hover */
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
    </header><br><br>
    <div class="tab-container">
    <div class="tab-buttons">
      <button class="tab-button active" data-target="todo-section">
        <i class="fas fa-tasks"></i> Todo List
      </button>
      <button class="tab-button" data-target="notes-section">
        <i class="fas fa-sticky-note"></i> Notes
      </button>
    </div>
    
    <div class="tab-content">
      <div id="todo-section" class="content-section active">
      
    <!-- <h1 class="mt-5 text-center">Todo List</h1> -->
    <form method="POST" class="mb-4 d-flex gap-2">
        <input type="text" name="description" placeholder="Task description" required class="form-control">
        <select name="category" class="form-control">
    <option value="Work">Work</option>
    <option value="Personal">Personal</option>
    <option value="Health">Health</option>
    <option value="Finance">Finance</option>
</select>

        <button type="submit" name="add_task" class="btn btn-primary"><i class="fas fa-plus"></i> </button>
    </form>

    <ul class="list-group">
        <?php while ($task = mysqli_fetch_assoc($tasks_result)): ?>
            <li class="list-group-item task-item <?php echo $task['status'] === 'complete' ? 'complete' : ''; ?>">
                <div>
                    <strong><?php echo $task['description']; ?></strong> - <span class="text-muted"><?php echo $task['category']; ?></span>
                </div>
                <div class="task-actions">
                    <a href="?complete_task=<?php echo $task['task_id']; ?>" class="btn btn-success btn-sm"><i class="fas fa-check"></i></a>
                    <a href="?delete_task=<?php echo $task['task_id']; ?>" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $task['task_id']; ?>"><i class="fas fa-edit"></i></button>
                </div>
            </li>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?php echo $task['task_id']; ?>" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Task</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="task_id" value="<?php echo $task['task_id']; ?>">
                                <input type="text" name="description" value="<?php echo $task['description']; ?>" required class="form-control mb-2">
                            <select name="category" class="form-control mb-2">
                            <option value="Work" <?php if ($task['category'] == 'Work') echo 'selected'; ?>>Work</option>
                            <option value="Personal" <?php if ($task['category'] == 'Personal') echo 'selected'; ?>>Personal</option>
                            <option value="Health" <?php if ($task['category'] == 'Health') echo 'selected'; ?>>Health</option>
                            <option value="Finance" <?php if ($task['category'] == 'Finance') echo 'selected'; ?>>Finance</option>
                        </select>

                                <button type="submit" name="edit_task" class="btn btn-primary">Update Task</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </ul>
</div>

    
        <div id="notes-section" class="content-section">

      <div class="container mt-5">
    <!-- <h1 class="text-center">Notes</h1> -->

    <form method="POST" class="mb-4 d-flex gap-2">
        <input type="text" name="content" placeholder="Write a note..." required class="form-control">
        <!-- <input type="text" name="pattern_lock" placeholder="Pattern Lock (optional)" class="form-control"> -->
        <button type="submit" name="add_note" class="btn btn-primary"><i class="fas fa-plus"></i> Add Note</button>
    </form>

    <ul class="list-group">
        <?php while ($note = mysqli_fetch_assoc($notes_result)): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <strong><?php echo htmlspecialchars($note['content']); ?></strong>
                    <!-- <span class="text-muted">
                        <?php echo $note['pattern_lock'] ?'🔒 Locked' : '📝 Open'; ?>
                    </span> -->
                </div>
                <div class="task-actions">
                    <a href="?delete_note=<?php echo $note['note_id']; ?>" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editNoteModal<?php echo $note['note_id']; ?>"><i class="fas fa-edit"></i></button>
                </div>
            </li>

            <!-- Edit Note Modal -->
            <div class="modal fade" id="editNoteModal<?php echo $note['note_id']; ?>" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Note</h5>
                            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="POST">
                                <input type="hidden" name="note_id" value="<?php echo $note['note_id']; ?>">
                                <input type="text" name="content" value="<?php echo htmlspecialchars($note['content']); ?>" required class="form-control mb-2">
                                <input type="text" name="pattern_lock" value="<?php echo htmlspecialchars($note['pattern_lock']); ?>" class="form-control mb-2" placeholder="Pattern Lock (optional)">
                                <button type="submit" name="edit_note" class="btn btn-primary">Update Note</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </ul>
</div></div>     
  






<script>
  document.querySelectorAll('.task-checkbox').forEach(checkbox => {
      checkbox.addEventListener('change', function () {
          const taskId = this.getAttribute('data-task-id');
          const status = this.checked ? 'complete' : 'incomplete';

          fetch('update_task.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: `task_id=${taskId}&status=${status}`
          });
      });
  });

  // Drag and drop sorting
  document.addEventListener("DOMContentLoaded", function () {
      const taskList = document.querySelector(".list-group");

      new Sortable(taskList, {
          animation: 150,
          onEnd: function (evt) {
              let sortedTasks = [];
              document.querySelectorAll(".task-item").forEach((task, index) => {
                  sortedTasks.push({task_id: task.getAttribute("data-task-id"), position: index});
              });

              fetch('update_task_order.php', {
                  method: 'POST',
                  headers: {'Content-Type': 'application/json'},
                  body: JSON.stringify(sortedTasks)
              });
          }
      });
  });
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>


