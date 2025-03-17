

<?php
// Include necessary files
include 'connect.php';
include 'header.php';

// Start session
session_start();

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Create calendar_events table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS calendar_events (
    event_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";
if (!$conn->query($createTableQuery)) {
    die("Error creating table: " . $conn->error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_event'])) {
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'];
        $event_title = $_POST['event_title'];

        // Insert new event
        $stmt = $conn->prepare("INSERT INTO calendar_events (user_id, event_date, event_time, event_title) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $event_date, $event_time, $event_title);
        if ($stmt->execute()) {
            $success_message = "Event added successfully!";
        } else {
            $error_message = "Error adding event: " . $stmt->error;
        }
    } elseif (isset($_POST['delete_event'])) {
        $event_id = $_POST['event_id'];

        // Delete event and its corresponding notification
        $stmt = $conn->prepare("DELETE FROM calendar_events WHERE event_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $event_id, $user_id);
        if ($stmt->execute()) {
            // Delete corresponding notification
            $deleteNotifStmt = $conn->prepare("DELETE FROM notifications WHERE event_id = ? AND user_id = ?");
            $deleteNotifStmt->bind_param("ii", $event_id, $user_id);
            $deleteNotifStmt->execute();
            $deleteNotifStmt->close();

            $success_message = "Event deleted successfully!";
        } else {
            $error_message = "Error deleting event: " . $stmt->error;
        }
    }
}

// Fetch events for the current user
$events = [];
$stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? ORDER BY event_date, event_time");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vintage Calendar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Georgia', serif;
            background-color: #f5ece5;
            color: #3e2723;
            margin: 0;
            padding: 20px;
        }

        .calendar-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .calendar {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .calendar-navigation button {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }

        .calendar-table {
            width: 100%;
            border-collapse: collapse;
        }

        .calendar-table th, .calendar-table td {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
        }

        .calendar-table th {
            background-color: #8b4513;
            color: #fff;
        }

        .calendar-table td {
            cursor: pointer;
        }

        .calendar-table td:hover {
            background-color: #e8d5c8;
        }

        .sidebar {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .sidebar h3 {
            margin-top: 0;
        }

        .reminders-list {
            list-style: none;
            padding: 0;
        }

        .reminders-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .reminders-list li:last-child {
            border-bottom: none;
        }

        .reminders-list button {
            background: none;
            border: none;
            cursor: pointer;
            color: #8b4513;
        }

        .weather-forecast {
            margin-top: 20px;
        }

        .weather-forecast p {
            margin: 0;
            font-size: 18px;
        }
        
        .event-form {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr 2fr 1fr;
            gap: 10px;
        }
        
        .event-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .event-form button {
            background-color: #8b4513;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .event-form button:hover {
            background-color: #6d3611;
        }
        
        .notification-btn {
            background-color: #8b4513;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .notification-btn:hover {
            background-color: #6d3611;
        }
        
        .status-message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body><br><br><br>
    <div class="calendar-container">
        <!-- Calendar Section -->
        <div class="calendar">
            <div class="calendar-header">
                <h2 id="currentMonth">February 2025</h2>
                <div class="calendar-navigation">
                    <button id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                    <button id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>
            <table class="calendar-table">
                <thead>
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                </thead>
                <tbody id="calendarBody">
                    <!-- Calendar days will be populated here -->
                </tbody>
            </table>
            
            <div style="margin-top: 20px; text-align: right;">
                <a href="notification.php" class="notification-btn">
                    <i class="fas fa-bell"></i> View Notifications
                </a>
            </div>
        </div>

        <!-- Sidebar Section -->
        <div class="sidebar">
            <?php if (isset($success_message)): ?>
                <div class="status-message success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="status-message error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <h3>Upcoming Reminders</h3>
            <ul class="reminders-list" id="remindersList">
                <?php foreach ($events as $event): ?>
                    <li>
                        <span><?php echo htmlspecialchars($event['event_title']); ?> - <?php echo date('M d, Y', strtotime($event['event_date'])); ?> at <?php echo date('h:i A', strtotime($event['event_time'])); ?></span>
                        <div>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                <button type="submit" name="delete_event"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
                
                <?php if (empty($events)): ?>
                    <li><span>No upcoming events. Add one below!</span></li>
                <?php endif; ?>
            </ul>
            
            <h3>Add New Event</h3>
            <form method="POST" class="event-form">
                <input type="date" name="event_date" required>
                <input type="time" name="event_time" required>
                <input type="text" name="event_title" placeholder="Event Title" required>
                <button type="submit" name="add_event">Add Event</button>
            </form>
        </div>
    </div>

    <script>
        let currentDate = new Date(2025, 1); // February 2025

        function renderCalendar() {
            const calendarBody = document.getElementById('calendarBody');
            const currentMonth = document.getElementById('currentMonth');
            const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
            const startDay = firstDay.getDay();
            const endDate = lastDay.getDate();

            currentMonth.textContent = `${firstDay.toLocaleString('default', { month: 'long' })} ${firstDay.getFullYear()}`;
            calendarBody.innerHTML = '';

            let date = 1;
            for (let i = 0; i < 6; i++) {
                const row = document.createElement('tr');
                for (let j = 0; j < 7; j++) {
                    const cell = document.createElement('td');
                    if (i === 0 && j < startDay) {
                        cell.textContent = '';
                    } else if (date > endDate) {
                        cell.textContent = '';
                    } else {
                        cell.textContent = date;
                        date++;
                    }
                    row.appendChild(cell);
                }
                calendarBody.appendChild(row);
                if (date > endDate) break;
            }
        }

        document.getElementById('prevMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() - 1);
            renderCalendar();
        });

        document.getElementById('nextMonth').addEventListener('click', () => {
            currentDate.setMonth(currentDate.getMonth() + 1);
            renderCalendar();
        });

        // Auto-hide status messages after 3 seconds
        const statusMessages = document.querySelectorAll('.status-message');
        if (statusMessages.length > 0) {
            setTimeout(() => {
                statusMessages.forEach(message => {
                    message.style.display = 'none';
                });
            }, 3000);
        }

        renderCalendar();
    </script>
</body>
</html>