<?php
include("header.php");
include("connect.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Create diary table if it doesn't exist
$create_diary_table = "CREATE TABLE IF NOT EXISTS diary_entries (
    entry_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT(6) UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_diary_table);

// Handle form submission for new entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $mood = mysqli_real_escape_string($conn, $_POST['mood']);
    $entry_date = mysqli_real_escape_string($conn, $_POST['entry_date']);
    
    // Check if entry already exists for this date
    $check_query = "SELECT entry_id FROM diary_entries WHERE user_id = '$user_id' AND entry_date = '$entry_date'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing entry
        $entry = mysqli_fetch_assoc($check_result);
        $update_query = "UPDATE diary_entries SET 
                        content = '$content', 
                        mood = '$mood'
                        WHERE entry_id = '{$entry['entry_id']}'";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['message'] = "Entry updated successfully!";
            header("Location: diary.php?date=$entry_date");
            exit();
        } else {
            $error = "Error updating entry: " . mysqli_error($conn);
        }
    } else {
        // Insert new entry
        $insert_query = "INSERT INTO diary_entries (user_id, content, mood, entry_date) 
                        VALUES ('$user_id', '$content', '$mood', '$entry_date')";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['message'] = "Entry saved successfully!";
            header("Location: diary.php?date=$entry_date");
            exit();
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Delete entry
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $entry_id = $_GET['delete'];
    $delete_query = "DELETE FROM diary_entries WHERE entry_id = '$entry_id' AND user_id = '$user_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['message'] = "Entry deleted successfully!";
        header("Location: diary.php");
        exit();
    }
}

// Get selected date from query string
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get entry for selected date
$entry_query = "SELECT * FROM diary_entries WHERE user_id = '$user_id' AND entry_date = '$selected_date'";
$entry_result = mysqli_query($conn, $entry_query);
$has_entry = mysqli_num_rows($entry_result) > 0;
$entry = $has_entry ? mysqli_fetch_assoc($entry_result) : null;

// Get all dates with entries for calendar highlighting
$dates_query = "SELECT entry_date FROM diary_entries WHERE user_id = '$user_id' ORDER BY entry_date DESC";
$dates_result = mysqli_query($conn, $dates_query);
$entry_dates = [];
while ($date_row = mysqli_fetch_assoc($dates_result)) {
    $entry_dates[] = $date_row['entry_date'];
}

// Get previous and next entry dates for navigation
$prev_date_query = "SELECT entry_date FROM diary_entries WHERE user_id = '$user_id' AND entry_date < '$selected_date' ORDER BY entry_date DESC LIMIT 1";
$prev_date_result = mysqli_query($conn, $prev_date_query);
$prev_date = mysqli_num_rows($prev_date_result) > 0 ? mysqli_fetch_assoc($prev_date_result)['entry_date'] : null;

$next_date_query = "SELECT entry_date FROM diary_entries WHERE user_id = '$user_id' AND entry_date > '$selected_date' ORDER BY entry_date ASC LIMIT 1";
$next_date_result = mysqli_query($conn, $next_date_query);
$next_date = mysqli_num_rows($next_date_result) > 0 ? mysqli_fetch_assoc($next_date_result)['entry_date'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Diary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-image: url('images/todobg.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
       
        
        .diary-title {
            font-size: 2.2em;
            color: #8B4513;
            margin: 0;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .diary-main {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 70px;
        }
        
        .diary-sidebar {
    background-color: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    height: fit-content;
    position: sticky;
    top: 20px;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    width: 300px; /* Change from original 250px to 300px */
}
        
        .calendar-container {
            margin-bottom: 25px;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            text-align: center;
            color: #8B4513;
            margin-bottom: 10px;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .flatpickr-calendar {
            width: 100% !important;
            box-shadow: none !important;
            background: rgba(255, 255, 255, 0.9) !important;
            border: none !important;
        }
        
        .flatpickr-day.has-entry {
            background-color: #8B4513 !important;
            color: white !important;
            border-color: #8B4513 !important;
        }
        
        .flatpickr-day.today {
            border-color: #8B4513 !important;
        }
        
        .flatpickr-day.selected, .flatpickr-day.selected:hover {
            background-color: #8B4513 !important;
            border-color: #8B4513 !important;
        }
        
        .flatpickr-day:hover {
            background-color: rgba(139, 69, 19, 0.1) !important;
        }
        
        .flatpickr-months .flatpickr-month {
            color: #333 !important;
            fill: #333 !important;
        }
        
        .flatpickr-weekday {
            color: #333 !important;
        }
        
        .flatpickr-day {
            color: #333 !important;
        }
        
        .diary-nav {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
        }
        
        .nav-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background-color: rgba(255, 255, 255, 0.7);
            color: #8B4513;
            border: 1px solid #8B4513;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 0.9em;
        }
        
        .nav-btn:hover {
            background-color: #8B4513;
            color: white;
        }
        
        .nav-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .recent-entries {
            margin-top: 25px;
        }
        
        .recent-entries h3 {
            color: #8B4513;
            border-bottom: 1px solid #8B4513;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        .recent-entry-link {
            display: block;
            padding: 8px 10px;
            margin-bottom: 8px;
            background-color: rgba(255, 255, 255, 0.7);
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.2s;
            font-size: 0.9em;
        }
        
        .recent-entry-link:hover {
            background-color: #8B4513;
            color: white;
        }
        
        .entry-date-small {
            font-size: 0.8em;
            opacity: 0.8;
        }
        
        .diary-content {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .current-date {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .date-display {
            font-size: 1.5em;
            font-weight: bold;
            color: #8B4513;
        }
        
        .date-picker-wrapper {
            position: relative;
        }
        
        #datePicker {
            background-color: rgba(255, 255, 255, 0.7);
            color: #333;
            border: 1px solid #8B4513;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .diary-entry-form {
            margin-top: 20px;
        }
        
        .diary-textarea {
    width: 100%;
    min-height: 300px;
    padding: 15px;
    background-color: rgba(255, 255, 255, 0.7);
    color: #333;
    border: 1px solid #DBBFAE;
    border-radius: 8px;
    resize: vertical;
    font-size: 1.05em;
    line-height: 1.6;
    transition: all 0.2s;
    margin-bottom: 20px;
    font-family: 'Segoe Print', 'Bradley Hand', 'Chilanka', cursive, sans-serif; /* Add handwriting fonts */
}
        
        .diary-textarea:focus {
            outline: none;
            border-color: #8B4513;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
            background-color: white;
        }
        
        .entry-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .mood-selector-label {
            color: #8B4513;
            font-weight: bold;
            margin-right: 10px;
            font-size: 0.9em;
        }
        
        .mood-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .mood-option {
            padding: 5px 10px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.8em;
        }
        
        .mood-option:hover, .mood-option.selected {
            background-color: #8B4513;
            color: white;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .word-count {
            color: #333;
            opacity: 0.7;
            font-size: 0.9em;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #8B4513;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #A0522D;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .entry-view {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            white-space: pre-wrap;
            line-height: 1.8;
        }
        
        .entry-mood {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
            background-color: rgba(255, 255, 255, 0.7);
            color: #8B4513;
        }
        
        .message {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            animation: fadeOut 5s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        .alert {
            background-color: #f44336;
            color: white;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .diary-main {
                grid-template-columns: 1fr;
            }
            
            .diary-sidebar {
                position: static;
            }
            
            .entry-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 15px;
            }
            
            .btn {
                width: 100%;
            }
        }
        .today-indicator {
    font-size: 0.8em;
    color: #8B4513;
    opacity: 0.8;
}
    </style>
</head>
<body><br><br><br>
    <div class="container">
       
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message alert"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="diary-main">
            <div class="diary-sidebar">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <i class="fas fa-calendar-alt"></i> Calendar
                    </div>
                    <input type="text" id="calendarPicker" placeholder="Select date" style="display: none;" />
                    <div id="calendarInline"></div>
                </div>
                
                <div class="diary-nav">
                    <?php if ($prev_date): ?>
                        <a href="?date=<?php echo $prev_date; ?>" class="nav-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php else: ?>
                        <span class="nav-btn disabled">
                            <i class="fas fa-chevron-left"></i> Previous
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($next_date): ?>
                        <a href="?date=<?php echo $next_date; ?>" class="nav-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn disabled">
                            Next <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="recent-entries">
                    <h3>Recent Entries</h3>
                    <?php
                    $recent_entries_query = "SELECT entry_id, entry_date FROM diary_entries WHERE user_id = '$user_id' ORDER BY entry_date DESC LIMIT 5";
                    $recent_entries_result = mysqli_query($conn, $recent_entries_query);
                    
                    if (mysqli_num_rows($recent_entries_result) > 0): ?>
                        <?php while ($recent_entry = mysqli_fetch_assoc($recent_entries_result)): ?>
                            <a href="?date=<?php echo $recent_entry['entry_date']; ?>" class="recent-entry-link">
                                <div class="entry-date-small">
                                    <i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($recent_entry['entry_date'])); ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: #333; opacity: 0.7;">No entries yet</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="diary-content">
                <div class="current-date">
                <div class="date-display">
    <?php 
    echo date('l, F j, Y', strtotime($selected_date));
    if ($selected_date == date('Y-m-d')) {
        echo ' <span class="today-indicator">(Today)</span>';
    }
    ?>
</div>
                    <div class="date-picker-wrapper">
                        <input type="text" id="datePicker" value="<?php echo $selected_date; ?>" />
                    </div>
                </div>
                
                <?php if ($has_entry && !isset($_GET['edit'])): ?>
                    <div class="entry-view">
                        <?php echo nl2br(htmlspecialchars($entry['content'])); ?>
                        
                        <?php if (!empty($entry['mood'])): ?>
                            <div class="entry-mood">
                                <i class="fas fa-heart"></i> <?php echo htmlspecialchars($entry['mood']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?date=<?php echo $selected_date; ?>&edit=1" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Entry
                        </a>
                        <a href="?date=<?php echo $selected_date; ?>&delete=<?php echo $entry['entry_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this entry?');">
                            <i class="fas fa-trash"></i> Delete Entry
                        </a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" class="diary-entry-form">
                        <textarea name="content" id="diary-content" class="diary-textarea" placeholder="Dear Diary..." required><?php echo ($has_entry && isset($_GET['edit'])) ? htmlspecialchars($entry['content']) : (isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''); ?></textarea>
                        
                        <div class="entry-options">
                            <div>
                                <span class="mood-selector-label">How are you feeling today?</span>
                                <div class="mood-selector">
                                    <div class="mood-option <?php echo ($has_entry && isset($_GET['edit']) && $entry['mood'] === 'Happy') ? 'selected' : ''; ?>" data-mood="Happy">😊 Happy</div>
                                    <div class="mood-option <?php echo ($has_entry && isset($_GET['edit']) && $entry['mood'] === 'Sad') ? 'selected' : ''; ?>" data-mood="Sad">😢 Sad</div>
                                    <div class="mood-option <?php echo ($has_entry && isset($_GET['edit']) && $entry['mood'] === 'Excited') ? 'selected' : ''; ?>" data-mood="Excited">🤩 Excited</div>
                                    <div class="mood-option <?php echo ($has_entry && isset($_GET['edit']) && $entry['mood'] === 'Relaxed') ? 'selected' : ''; ?>" data-mood="Relaxed">😌 Relaxed</div>
                                    <div class="mood-option <?php echo ($has_entry && isset($_GET['edit']) && $entry['mood'] === 'Grateful') ? 'selected' : ''; ?>" data-mood="Grateful">🙏 Grateful</div>
                                </div>
                                <input type="hidden" id="mood" name="mood" value="<?php echo ($has_entry && isset($_GET['edit'])) ? htmlspecialchars($entry['mood']) : ''; ?>">
                            </div>
                        </div>
                        
                        <input type="hidden" name="entry_date" value="<?php echo $selected_date; ?>">
                        
                        <div class="form-actions">
                            <div class="word-count">
                                <span id="wordCount">0</span> words
                            </div>
                            <button type="submit" name="submit_entry" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Entry
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mood selector
        const moodOptions = document.querySelectorAll('.mood-option');
        moodOptions.forEach(option => {
            option.addEventListener('click', function() {
                moodOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('mood').value = this.getAttribute('data-mood');
            });
        });

        // Date picker
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $selected_date; ?>",
            onChange: function(selectedDates, dateStr, instance) {
                window.location.href = "?date=" + dateStr;
            }
        });

        // Inline calendar with marked dates
        flatpickr("#calendarInline", {
            inline: true,
            dateFormat: "Y-m-d",
            defaultDate: "<?php echo $selected_date; ?>",
            onChange: function(selectedDates, dateStr, instance) {
                window.location.href = "?date=" + dateStr;
            },
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                const date = dayElem.dateObj;
                const dateStr = fp.formatDate(date, "Y-m-d");
                
                <?php foreach ($entry_dates as $date): ?>
                    if (dateStr === "<?php echo $date; ?>") {
                        dayElem.classList.add("has-entry");
                    }
                <?php endforeach; ?>
                
                if (date.toDateString() === new Date().toDateString()) {
                    dayElem.classList.add("today");
                }
            }
        });

        // Word count
        const textarea = document.getElementById('diary-content');
        if (textarea) {
            const wordCount = document.getElementById('wordCount');
            
            function updateWordCount() {
                const text = textarea.value.trim();
                const words = text ? text.split(/\s+/).length : 0;
                wordCount.textContent = words;
            }
            
            textarea.addEventListener('input', updateWordCount);
            updateWordCount(); // Initial count
        }

        // Auto-resize textarea
        function adjustTextareaHeight() {
            if (textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = (textarea.scrollHeight) + 'px';
            }
        }
        
        if (textarea) {
            textarea.addEventListener('input', adjustTextareaHeight);
            adjustTextareaHeight(); // Initial adjustment
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Chilanka&display=swap" rel="stylesheet">
</body>
</html>

<?php
// Close database connection
mysqli_close($conn);
?>