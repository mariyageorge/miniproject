<?php
include("connect.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Ensure user_id is set
if (!isset($_SESSION['user_id'])) {
    die("Error: User ID is not set in session.");
}

$user_id = $_SESSION['user_id'];

// Check if profile picture is already stored in session
if (!isset($_SESSION['profile_pic']) || !isset($_SESSION['role'])) {
    // Corrected SQL query to fetch both profile_pic and role
    $query = "SELECT profile_pic, role FROM users WHERE user_id='$user_id'";
    $result = mysqli_query($conn, $query);

    // Check for query errors
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    // Fetch result
    $row = mysqli_fetch_assoc($result);
    
    // Set profile picture or default avatar
    $_SESSION['profile_pic'] = !empty($row['profile_pic']) ? $row['profile_pic'] : 'images/default-avatar.png';
    $_SESSION['role'] = $row['role'];
}

// Assign profile picture path and role
$profile_pic = $_SESSION['profile_pic'];
$role = $_SESSION['role'];
$notifQuery = "SELECT COUNT(*) AS notif_count FROM notifications WHERE user_id = ?";
$stmt = $conn->prepare($notifQuery);

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifData = $result->fetch_assoc();
    $notif_count = $notifData['notif_count'];
} else {
    $notif_count = 0; // Default to 0 if there's an error
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFE-SYNC Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --nude-300: #DBBFAE;
            --nude-400: #C6A792;
            --nude-500: #B08F78;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
            --brown-light: #DEB887;
            --accent-purple: #9B6B9E;
            --accent-green: #7BA686;
            --accent-blue: #6B94AE;
            --accent-orange: #E6955C;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            background-color: var(--nude-200);
            display: flex;
        }
        /* Header Styles */
.header {
    width: 100%;
    background: white;
    padding: 0.6rem 1.5rem;
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
    border: 2px solid #dc3545;
    color: #dc3545;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.logout-btn:hover {
    background: #dc3545;
    color: white;
}

body {
    padding-top: 55px;
}


.sidebar {
    margin-top: 0;
}


        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: url("images/dashbg.jpg") no-repeat center center/cover; /* Background image */
            padding: 2rem 1.5rem;
            color: white;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.3); /* Slight transparency for profile background */
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .profile-pic {
            width: 50px;
            height: 50px;
            background: var(--nude-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .main-content {
    flex: 1;
    padding: 2rem;
    margin-left: 90px; /* Creates space between sidebar and main content */
    overflow-y: auto;
}




.container {
    display: grid;
    grid-template-columns: repeat(2, 1fr); /* Changed from 2 to 3 columns */
    gap: 2rem;
    max-width: 1200px; /* Increased max-width to fit more cards */
}

        .card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.1);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }
        .container {
    margin-top: 20px;
    margin-left: 20px; /* Add some spacing for better visibility */
}
        .card:nth-child(1)::before { background: var(--accent-purple); }
        .card:nth-child(2)::before { background: var(--accent-green); }
        .card:nth-child(3)::before { background: var(--accent-blue); }
        .card:nth-child(4)::before { background: var(--accent-orange); }
        .card:nth-child(5)::before { 
    background: #9B6B9E; 
}

.card:nth-child(5) .icon-container { 
    background: linear-gradient(135deg, #9B6B9E, #C490C9); 
}.card:nth-child(6)::before { 
    background: #6B94AE; 
}.card:nth-child(6) .icon-container { 
    background: linear-gradient(135deg, #6B94AE, #9BB7D0); 
}
        .icon-container {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.2rem;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .card:nth-child(1) .icon-container { background: linear-gradient(135deg, var(--accent-purple), #C490C9); }
        .card:nth-child(2) .icon-container { background: linear-gradient(135deg, var(--accent-green), #A3C7AE); }
        .card:nth-child(3) .icon-container { background: linear-gradient(135deg, var(--accent-blue), #9BB7D0); }
        .card:nth-child(4) .icon-container { background: linear-gradient(135deg, var(--accent-orange), #F3B389); }

        .icon {
            font-size: 1.8rem;
            color: white;
        }

        h2 {
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
            color: var(--brown-primary);
        }

        p {
            font-size: 0.9rem;
            color: var(--nude-500);
            margin-bottom: 1.2rem;
        }

        .button {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: var(--brown-primary);
            border-radius: 25px;
            color: white;
            text-decoration: none;
            transition: 0.3s ease;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
        }

        .button:hover {
            background: var(--brown-hover);
            transform: scale(1.05);
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 70px;
                padding: 1rem;
            }

            .profile-section, .nav-link span {
                display: none;
            }

            .container {
                grid-template-columns: 1fr;
            }
        }
        .profile-dropdown {
    position: relative;
    cursor: pointer;
}


.dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 30px;
    background: white;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    overflow: hidden;
    min-width: 140px;
}

.dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 10px; /* Adds space between icon and text */
    padding: 10px;
    color: var(--brown-primary);
    text-decoration: none;
    transition: background 0.3s ease;
}

.dropdown-menu a:hover {
    background: var(--nude-200);
}

.dropdown-menu i {
    font-size: 1rem;
    color: var(--brown-primary);
}


.profile-dropdown .dropdown-menu {
    display: none;
}

.profile-dropdown.active .dropdown-menu {
    display: block;
}

.header-right {
    display: flex;
    align-items: center; /* Ensures all elements align in the center */
    gap: 1.5rem;
}

.welcome-section {
    display: flex;
    align-items: center; 
    gap: 0.8rem;
}

.welcome-sticker {
    font-size: 1.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.welcome-message {
    font-size: 1.4rem;
    color: var(--brown-primary);
}

.profile-icon {
    font-size: 1.8rem;
    color: var(--brown-primary);
    cursor: pointer;
    transition: all 0.3s ease-in-out;
}

.profile-icon:hover {
    color: var(--brown-hover); /* Changes color on hover */
    transform: scale(1.1); /* Slightly enlarges the icon */
}
.profile-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    cursor: pointer;
}

.notification-badge {
    background-color: red;
    color: white;
    font-size: 9px;
    padding: 5px 8px;
    border-radius: 55%;
}

    </style>
</head>
<body>
<header class="header">
    <div class="logo-section">
        <div class="logo-icon">
            <i class="fas fa-infinity"></i>
        </div>
        <span class="logo-text">LIFE-SYNC</span>
    </div>
    
    <div class="header-right">
    <div class="welcome-section">
        <span class="welcome-sticker">🌟</span>
        <div class="welcome-message">
            Welcome back, <?php echo htmlspecialchars($username); ?>!
        </div>
        <div class="profile-dropdown">
    <img src="<?php echo $profile_pic; ?>" alt="User Profile" class="profile-icon">
    <div class="dropdown-menu">
        <a href="profile.php"><i class="fas fa-user"></i> View Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

        </div>
    </div>
</div>

</div>

</header>
    <aside class="sidebar">
       
        <br><br><br><br>
        <nav class="nav-links">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="notification.php" class="nav-link">
                <i class="fas fa-bell"></i>
                <span>Notifications 
                <?php if ($notif_count > 0): ?>
    <span id="notificationCount" class="notification-badge">
        <?php echo $notif_count; ?>
    </span>
<?php endif; ?>

<span>

            </a>
      <a href="#" class="nav-link" onclick="showTranslateWidget()">
    <i class="fas fa-cog"></i>
    <span>Translate</span>
</a>

<!-- Google Translate Widget (Hidden Initially) -->
<div id="google_translate_element" style="display: none;"></div>

<script>
    function googleTranslateElementInit() {
        new google.translate.TranslateElement(
            { pageLanguage: 'en', layout: google.translate.TranslateElement.InlineLayout.SIMPLE },
            'google_translate_element'
        );
    }

    function showTranslateWidget() {
        var widget = document.getElementById("google_translate_element");
        if (widget.style.display === "none") {
            widget.style.display = "block"; 
        } else {
            widget.style.display = "none"; 
        }
    }
</script>

<script src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

            <a href="feedback.php" class="nav-link">
            <i class="fas fa-envelope-open-text"></i>
                <span>Feedback</span>
            </a>
            <?php if ($role !== 'premium user'): ?>
            <a href="upgrade.php" class="nav-link premium">
                <i class="fas fa-star"></i>
                <span>Upgrade to Premium</span>
            </a>
            <?php endif; ?>

        </nav>
    </aside>

 <br><br><br><br><br>
        
        <div class="container">
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-list-check"></i>
                </div>
                <h2>Task Manager</h2>
                <p>Organize your daily tasks efficiently with smart reminders and priority tracking ✨</p>
                <a href="todo.php" class="button">Get Started</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-coins"></i>
                </div>
                <h2>Finance Tracker</h2>
                <p>Take control of your finances with smart budgeting and expense tracking 💰</p>
                <a href="expense.php" class="button">Manage Money</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon fas fa-heart-pulse"></i>
                </div>
                <h2>Wellness Guide</h2>
                <p>Stay healthy and balanced with personalized wellness recommendations 🌿</p>
                <a href="health.php" class="button">Start Journey</a>
            </div>
            
            <div class="card">
                <div class="icon-container">
                    <i class="icon far fa-calendar"></i>
                </div>
                <h2>Time Planner</h2>
                <p>Plan your days effectively with smart scheduling and reminders ⏰</p>
                <a href="calender.php" class="button">Plan Now</a>
            </div>

            <div class="card">
        <div class="icon-container">
            <i class="icon fas fa-money-bill-split"></i>
        </div>
        <h2>Expense Splitter</h2>
        <p>Split expenses with friends, family, or roommates and keep track of who owes what 💵</p>
        <a href="expense_splitter.php" class="button">Split Bills</a>
    </div>
    <div class="card">
        <div class="icon-container">
            <i class="icon fas fa-book"></i>
        </div>
        <h2>Personal Diary</h2>
        <p>Record your thoughts, feelings, and daily experiences in a private journal 📖</p>
        <a href="diary.php" class="button">Start Writing</a>
    </div>
</div>
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const profileDropdown = document.querySelector(".profile-dropdown");

        profileDropdown.addEventListener("click", function (event) {
            event.stopPropagation();
            profileDropdown.classList.toggle("active");
        });

        document.addEventListener("click", function (event) {
            if (!profileDropdown.contains(event.target)) {
                profileDropdown.classList.remove("active");
            }
        });
    });
</script>
<script>
function fetchNotifications() {
    fetch('notification.php')
        .then(response => response.json())
        .then(data => {
            let notificationList = document.getElementById("notificationList");
            let notificationCount = document.getElementById("notificationCount");

            notificationList.innerHTML = "";
            if (data.length > 0) {
                notificationCount.textContent = data.length;
                notificationCount.style.display = "inline";
                
                data.forEach(notification => {
                    let listItem = document.createElement("li");
                    listItem.textContent = notification.message + " - " + notification.created_at;
                    notificationList.appendChild(listItem);
                });
            } else {
                notificationCount.style.display = "none";
            }
        })
        .catch(error => console.error("Error fetching notifications:", error));
}

// Fetch notifications every 5 seconds
setInterval(fetchNotifications, 5000);
fetchNotifications(); // Initial load
</script>

</body>
</html>