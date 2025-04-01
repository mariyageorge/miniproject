<?php
// Start session before any output
session_start();

// Include database connection and header
require_once 'connect.php';
require_once 'header.php';

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure we have a valid database connection
if (!isset($conn) || $conn->connect_error) {
    die("Connection failed: " . ($conn->connect_error ?? "Database connection not established"));
}

$user_id = $_SESSION['user_id'];

// Set timezone
date_default_timezone_set('Asia/Kolkata'); // Change to your timezone

// Ensure notifications table exists with proper error handling
$createTableQuery = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    event_id INT UNSIGNED DEFAULT NULL,
    event_datetime DATETIME DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB";

if (!$conn->query($createTableQuery)) {
    die("Error creating notifications table: " . $conn->error);
}

// AJAX request handling with improved error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Handle clear all notifications request with better error handling
    if (isset($_POST['clear_all'])) {
        try {
            $clear_sql = "DELETE FROM notifications WHERE user_id = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            
            if (!$clear_stmt) {
                throw new Exception("Failed to prepare clear statement: " . $conn->error);
            }
            
            if (!$clear_stmt->bind_param("i", $user_id)) {
                throw new Exception("Failed to bind parameters: " . $clear_stmt->error);
            }
            
            if (!$clear_stmt->execute()) {
                throw new Exception("Failed to execute clear statement: " . $clear_stmt->error);
            }
            
            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            error_log("Clear notifications error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Handle mark as read/unread requests
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        $is_read = intval($_POST['mark_read']); // 1 for read, 0 for unread
        
        $update_sql = "UPDATE notifications SET is_read = ? WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        
        if ($update_stmt) {
            $update_stmt->bind_param("iii", $is_read, $notification_id, $user_id);
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Failed to update notification']);
        exit;
    }
    
    // Handle delete notification request
    if (isset($_POST['delete']) && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        
        $delete_sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        
        if ($delete_stmt) {
            $delete_stmt->bind_param("ii", $notification_id, $user_id);
            if ($delete_stmt->execute()) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'error' => 'Failed to delete notification']);
        exit;
    }
    
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Fetch events in the next 1 hour
$current_time = date('Y-m-d H:i:s');
$one_hour_later = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Fetch upcoming events within the next hour for the logged-in user
$sql = "SELECT event_id, event_title, event_date, event_time, 
        CONCAT(event_date, ' ', event_time) AS event_datetime
        FROM calendar_events 
        WHERE user_id = ? 
        AND CONCAT(event_date, ' ', event_time) BETWEEN ? AND ?
        ORDER BY event_date, event_time";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("iss", $user_id, $current_time, $one_hour_later);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $event_datetime = $row['event_datetime'];
        $time_diff = strtotime($event_datetime) - time();
        $minutes_remaining = round($time_diff / 60);
        
        // Create appropriate message based on time remaining
        if ($minutes_remaining <= 10) {
            $message = "URGENT: Your event '" . $row['event_title'] . "' is starting in less than 10 minutes!";
        } elseif ($minutes_remaining <= 30) {
            $message = "Reminder: Your event '" . $row['event_title'] . "' is starting in " . $minutes_remaining . " minutes.";
        } else {
            $message = "Upcoming: Your event '" . $row['event_title'] . "' is scheduled for " . 
                       date('h:i A', strtotime($row['event_time'])) . " today (" . 
                       date('M d', strtotime($row['event_date'])) . ").";
        }

        // Insert notification into the database if it doesn't already exist
        $checkNotifSQL = "SELECT id FROM notifications 
                          WHERE user_id = ? AND event_id = ? 
                          AND event_datetime = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkNotifSQL);
        $checkStmt->bind_param("iis", $user_id, $row['event_id'], $event_datetime);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows === 0) {
            $notify_sql = "INSERT INTO notifications (user_id, message, event_id, event_datetime, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
            $notify_stmt = $conn->prepare($notify_sql);

            if ($notify_stmt) {
                $notify_stmt->bind_param("isis", $user_id, $message, $row['event_id'], $event_datetime);
                $notify_stmt->execute();
            }
        }
    }
}

// Fetch latest 20 notifications
$fetch_notifications = "SELECT id, message, event_datetime, is_read, created_at FROM notifications 
                        WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$notif_stmt = $conn->prepare($fetch_notifications);
$all_notifications = [];

if ($notif_stmt) {
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();

    while ($notif = $notif_result->fetch_assoc()) {
        $all_notifications[] = $notif;
    }
}

// Convert notifications to JSON for JavaScript
$notifications_json = json_encode($all_notifications);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
         body {
            font-family: 'Georgia', serif;
            background-color: #f5ece5;
            color: #3e2723;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        .notification {
            background: #ffeeba;
            padding: 15px;
            margin: 15px 0;
            border-left: 5px solid #ffcc00;
            border-radius: 5px;
            position: relative;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .notification:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .notification.read {
            background: #f8f9fa;
            border-left: 5px solid #ced4da;
            opacity: 0.7;
        }
        .notification.urgent {
            background: #ffebeb;
            border-left: 5px solid #dc3545;
        }
        .notification.upcoming {
            background: #e3f2fd;
            border-left: 5px solid #007bff;
        }
        .timer {
            font-weight: bold;
            color: #dc3545;
            margin-top: 5px;
        }
        .event-time {
            display: block;
            font-weight: bold;
            margin-top: 5px;
            color: #495057;
        }
        .notification-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        .mark-read-btn {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 3px 8px;
            font-size: 12px;
            cursor: pointer;
        }
        .mark-read-btn:hover {
            background: #218838;
        }
        .mark-read-btn.disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        .notification-badge {
            display: inline-block;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 12px;
            margin-left: 5px;
        }
        .refresh-btn {
            background: rgb(114, 160, 209);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
            margin-left: 10px;
        }
        .refresh-btn:hover {
            background: #0069d9;
        }
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
        }
        .back-btn:hover {
            background: #5a6268;
        }
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        .clear-all-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 15px;
            cursor: pointer;
        }
        .clear-all-btn:hover {
            background: #c82333;
        }
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 3px 8px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 5px;
        }
        .delete-btn:hover {
            background: #c82333;
        }
        .notification.event-started {
            animation: pulse 2s infinite;
            background: #ffe8e8;
            border-left: 5px solid #dc3545;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }

    </style>
</head>
<body>
    <br><br><br>
    <div class="container">
        <div class="notification-header">
            <div>
                <h2>Notifications <span id="notificationCount" class="notification-badge"><?php echo count($all_notifications); ?></span></h2>
            </div>
            <div class="notification-actions">
                <?php if (!empty($all_notifications)): ?>
                    <button id="clearAllBtn" class="clear-all-btn">Clear All</button>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="notificationContainer">
            <?php if (empty($all_notifications)): ?>
                <div class="empty-state">
                    <p>You have no notifications at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_notifications as $notif): ?>
                    <?php 
                        $is_event_started = false;
                        $notification_class = 'notification';
                        
                        if ($notif['is_read']) {
                            $notification_class .= ' read';
                        }
                        
                        if (strpos($notif['message'], 'URGENT') !== false) {
                            $notification_class .= ' urgent';
                        } elseif (strpos($notif['message'], 'Upcoming') !== false) {
                            $notification_class .= ' upcoming';
                        }
                        
                        if (!empty($notif['event_datetime'])) {
                            $event_time = strtotime($notif['event_datetime']);
                            $current_time = time();
                            if ($event_time <= $current_time) {
                                $is_event_started = true;
                                $notification_class .= ' event-started';
                            }
                        }
                    ?>
                    <div class="<?php echo $notification_class; ?>" data-id="<?php echo $notif['id']; ?>">
                        <div class="notification-controls">
                            <?php if (!$notif['is_read'] && !$is_event_started): ?>
                                <button class="mark-read-btn" data-id="<?php echo $notif['id']; ?>" data-action="1">
                                    Mark as read
                                </button>
                            <?php elseif ($notif['is_read'] && !$is_event_started): ?>
                                <button class="mark-read-btn" data-id="<?php echo $notif['id']; ?>" data-action="0">
                                    Mark as unread
                                </button>
                            <?php elseif (!$notif['is_read'] && $is_event_started): ?>
                                <button class="mark-read-btn disabled" disabled>
                                    Event started
                                </button>
                            <?php endif; ?>
                            <button class="delete-btn" data-id="<?php echo $notif['id']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php echo htmlspecialchars($notif['message']); ?>
                        
                        <?php if (!empty($notif['event_datetime'])): ?>
                            <span class="event-time">
                                Event time: <?php echo date('M d, Y h:i A', strtotime($notif['event_datetime'])); ?>
                            </span>
                            
                            <div class="timer" data-datetime="<?php echo $notif['event_datetime']; ?>">
                                <?php if ($is_event_started): ?>
                                    Event has started
                                <?php else: ?>
                                    Loading time remaining...
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Store notifications data
        const notifications = <?php echo $notifications_json; ?>;

        // Function to update timers
        function updateTimers() {
            const timerElements = document.querySelectorAll('.timer');
            const now = new Date();
            
            timerElements.forEach(timer => {
                const eventDatetime = new Date(timer.dataset.datetime);
                const timeDiff = eventDatetime - now;
                const notificationElement = timer.closest('.notification');
                const notificationId = notificationElement.dataset.id;
                
                if (timeDiff > 0) {
                    // Calculate hours, minutes, seconds
                    const hours = Math.floor(timeDiff / (1000 * 60 * 60));
                    const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                    
                    // Format time remaining
                    let timeRemaining = '';
                    if (hours > 0) {
                        timeRemaining += `${hours}h `;
                    }
                    timeRemaining += `${minutes}m ${seconds}s remaining`;
                    
                    // Update timer text
                    timer.textContent = timeRemaining;
                    
                    // Add urgency class if less than 5 minutes
                    if (timeDiff < 5 * 60 * 1000) {
                        timer.style.color = '#dc3545'; // Red
                        if (!notificationElement.classList.contains('urgent')) {
                            notificationElement.classList.add('urgent');
                        }
                    } else if (timeDiff < 15 * 60 * 1000) {
                        timer.style.color = '#fd7e14'; // Orange
                    }
                } else {
                    timer.textContent = 'Event has started';
                    timer.style.color = '#dc3545'; // Red
                    
                    // Add event-started class
                    notificationElement.classList.add('event-started');
                    
                    // Disable mark as read button
                    const markReadBtn = notificationElement.querySelector('.mark-read-btn');
                    if (markReadBtn && !markReadBtn.classList.contains('disabled')) {
                        markReadBtn.classList.add('disabled');
                        markReadBtn.disabled = true;
                        markReadBtn.textContent = 'Event started';
                    }
                }
            });
        }

        // Mark notification as read/unread
        document.querySelectorAll('.mark-read-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const notificationId = this.dataset.id;
                const action = this.dataset.action; // 1 for read, 0 for unread
                const notification = this.closest('.notification');
                
                // Send AJAX request to mark as read/unread
                fetch('notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `mark_read=${action}&notification_id=${notificationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Toggle the read/unread state in the DOM
                        if (action === '1') {
                            notification.classList.add('read');
                            this.textContent = 'Mark as unread';
                            this.dataset.action = '0';
                        } else {
                            notification.classList.remove('read');
                            this.textContent = 'Mark as read';
                            this.dataset.action = '1';
                        }
                    } else {
                        console.error('Error:', data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });

        // Update timers every second
        updateTimers();
        setInterval(updateTimers, 1000);

        // Function to update notification count
        function updateNotificationCount() {
            const count = document.querySelectorAll('.notification').length;
            const countBadge = document.getElementById('notificationCount');
            const clearAllBtn = document.getElementById('clearAllBtn');
            
            if (countBadge) {
                countBadge.textContent = count;
            }
            
            if (count === 0) {
                // Show empty state
                document.getElementById('notificationContainer').innerHTML = `
                    <div class="empty-state">
                        <p>You have no notifications at this time.</p>
                    </div>
                `;
                // Remove clear all button
                if (clearAllBtn) {
                    clearAllBtn.remove();
                }
            }
        }

        // Delete notification
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const notificationId = this.dataset.id;
                const notification = this.closest('.notification');
                
                if (confirm('Are you sure you want to delete this notification?')) {
                    fetch('notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `delete=1&notification_id=${notificationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notification.remove();
                            updateNotificationCount();
                        } else {
                            console.error('Error:', data.error);
                            alert('Failed to delete notification');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to delete notification');
                    });
                }
            });
        });

        // Clear all notifications
        const clearAllBtn = document.getElementById('clearAllBtn');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                
                if (confirm('Are you sure you want to clear all notifications?')) {
                    try {
                        const response = await fetch('notification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'clear_all=1'
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const data = await response.json();
                        
                        if (data.success) {
                            document.querySelectorAll('.notification').forEach(notif => {
                                notif.remove();
                            });
                            updateNotificationCount();
                        } else {
                            throw new Error(data.error || 'Failed to clear notifications');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert(`Failed to clear notifications: ${error.message}`);
                        
                        // Reload the page if there might be a session issue
                        if (error.message.includes('session') || error.message.includes('login')) {
                            window.location.reload();
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
       