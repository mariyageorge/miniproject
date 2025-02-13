<?php
session_start();
require 'connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$message = '';

// Fetch user data
$sql = "SELECT username, email, phone, profile_pic, status FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['status'] !== 'active') {
    session_destroy(); // Log out inactive users
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        
        // Handle profile picture upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $upload_path = 'uploads/' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    $sql = "UPDATE users SET username=?, email=?, phone=?, profile_pic=? WHERE username=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssss", $name, $email, $phone, $upload_path, $username);
                } else {
                    $message = "Error uploading file.";
                }
            } else {
                $message = "Invalid file type.";
            }
        } else {
            $sql = "UPDATE users SET username=?, email=?, phone=? WHERE username=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $name, $email, $phone, $username);
        }
        
        if (isset($stmt) && $stmt->execute()) {
            $message = "Profile updated successfully!";
            $_SESSION['username'] = $name; // Update session username
        
            // Fetch updated user data
            $sql = "SELECT username, email, phone, profile_pic FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $message = "Error updating profile.";
        }
        
    }

    if (isset($_POST['delete_account'])) {
        // Set account status to "inactive"
        $sql = "UPDATE users SET status='inactive' WHERE username=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);

        if ($stmt->execute()) {
            session_destroy(); // Log out user immediately
            header("Location: login.php");
            exit();
        } else {
            $message = "Error deactivating account.";
        }
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - LIFE-SYNC</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --primary-color: #8B4513;
            --hover-color: #A0522D;
            --bg-color: #F5ECE5;
            --text-color: #333;
            --success-color: #28a745;
            --error-color: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            min-height: 100vh;
            color: var(--text-color);
        }

        .header {
            background: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
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
            color:  #8B4513;
            letter-spacing: 1px;
        }


        .back-button {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background: var(--hover-color);
        }

        .profile-container {
            max-width: 600px;
            margin: 100px auto 40px;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-pic-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 1.5rem;
        }

        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .camera-icon {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .camera-icon:hover {
            background: var(--hover-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-btn:hover {
            background: var(--hover-color);
        }

        .message {
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
        }

        .message.success {
            background: var(--success-color);
            color: white;
        }

        .message.error {
            background: var(--error-color);
            color: white;
        }

        #profile_pic_input {
            display: none;
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
    <a href="dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i> 
    </a>
</header>

<div class="profile-container">
    <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'Error') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-header">
        <div class="profile-pic-container">
            <img src="<?php echo !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : 'images/default-avatar.png'; ?>" 
                 alt="Profile Picture" 
                 class="profile-pic" 
                 id="profile_pic_preview">
            <label for="profile_pic_input" class="camera-icon">
                <i class="fas fa-camera"></i>
            </label>
        </div>
        <h2>Edit Profile</h2>
    </div>

    <form method="POST" enctype="multipart/form-data" id="profile_form">
        <input type="file" 
               id="profile_pic_input" 
               name="profile_pic" 
               accept="image/*" 
               onchange="previewImage(this)">

        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" 
                   id="name" 
                   name="name" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                   required>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" 
                   id="phone" 
                   name="phone" 
                   class="form-control" 
                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                   pattern="[0-9]{10}">
        </div>

        <button type="submit" name="update_profile" class="submit-btn">
            Save Changes
        </button>
    </form><br>
    <form method="POST">
    <button type="submit" name="delete_account" class="submit-btn" style="background-color: red;">
        Delete Account
    </button>
</form>

</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('profile_pic_preview').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>