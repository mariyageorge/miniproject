<?php
include("connect.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$token = isset($_GET['token']) ? mysqli_real_escape_string($conn, $_GET['token']) : '';
$group_id = isset($_GET['group']) ? mysqli_real_escape_string($conn, $_GET['group']) : '';

if (empty($token) || empty($group_id)) {
    $_SESSION['error'] = "Invalid invitation link.";
    header("Location: expense_splitter.php");
    exit();
}

// Check if invitation exists and is valid
$check_invitation = "SELECT * FROM group_members 
                    WHERE group_id = ? 
                    AND user_id = ? 
                    AND invitation_token = ? 
                    AND invitation_status = 'pending'";

$stmt = mysqli_prepare($conn, $check_invitation);
mysqli_stmt_bind_param($stmt, "iis", $group_id, $user_id, $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Invalid or expired invitation.";
    header("Location: expense_splitter.php");
    exit();
}

// Get group details
$group_query = "SELECT group_name FROM expense_groups WHERE group_id = ?";
$stmt = mysqli_prepare($conn, $group_query);
mysqli_stmt_bind_param($stmt, "i", $group_id);
mysqli_stmt_execute($stmt);
$group_result = mysqli_stmt_get_result($stmt);
$group = mysqli_fetch_assoc($group_result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'accept' || $action === 'decline') {
        $status = $action === 'accept' ? 'accepted' : 'declined';
        
        $update_query = "UPDATE group_members 
                        SET invitation_status = ?, 
                            invitation_token = NULL 
                        WHERE group_id = ? 
                        AND user_id = ?";
        
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "sii", $status, $group_id, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = $action === 'accept' 
                ? "You have successfully joined the group: " . htmlspecialchars($group['group_name'])
                : "You have declined the invitation to join: " . htmlspecialchars($group['group_name']);
        } else {
            $_SESSION['error'] = "Failed to process your response. Please try again.";
        }
    }
    
    header("Location: expense_splitter.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Invitation - LIFE-SYNC</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #A0522D;
            --accent-color: #D2691E;
            --success-color: #2ECC71;
            --danger-color: #E74C3C;
            --light-bg: #F5F5DC;
            --white: #FFFFFF;
            --text-primary: #4A3728;
            --text-secondary: #8B7355;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 600px;
            width: 90%;
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
        }

        .logo {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo i {
            font-size: 2.5rem;
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        .button {
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .button-accept {
            background: var(--success-color);
            color: var(--white);
        }

        .button-decline {
            background: var(--danger-color);
            color: var(--white);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        form {
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <i class="fas fa-infinity"></i>
            <span>LIFE-SYNC</span>
        </div>
        
        <h1>Group Invitation</h1>
        <p>You have been invited to join the group: <strong><?php echo htmlspecialchars($group['group_name']); ?></strong></p>
        
        <div class="buttons">
            <form method="post">
                <input type="hidden" name="action" value="accept">
                <button type="submit" class="button button-accept">
                    <i class="fas fa-check"></i> Accept Invitation
                </button>
            </form>
            
            <form method="post">
                <input type="hidden" name="action" value="decline">
                <button type="submit" class="button button-decline">
                    <i class="fas fa-times"></i> Decline
                </button>
            </form>
        </div>
    </div>
</body>
</html> 