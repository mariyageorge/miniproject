<?php
session_start();
require_once 'connect.php';
require_once 'header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$token = $_GET['token'] ?? '';
$message = '';
$status = '';

if (empty($token)) {
    $message = "Invalid invitation link.";
    $status = 'error';
} else {
    // Check if invitation exists and is valid
    $check_sql = "SELECT gm.*, g.group_name 
                  FROM group_members gm 
                  JOIN expense_groups g ON gm.group_id = g.group_id 
                  WHERE gm.invitation_token = ? AND gm.user_id = ? AND gm.invitation_status = 'pending'";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("si", $token, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = "Invalid or expired invitation.";
        $status = 'error';
    } else {
        $invitation = $result->fetch_assoc();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'accept') {
                // Accept invitation
                $update_sql = "UPDATE group_members 
                              SET invitation_status = 'accepted', invitation_token = NULL 
                              WHERE group_id = ? AND user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ii", $invitation['group_id'], $user_id);
                
                if ($update_stmt->execute()) {
                    $message = "You have successfully joined the group!";
                    $status = 'success';
                    header("Refresh: 2; URL=expense_splitter.php");
                } else {
                    $message = "Failed to join the group. Please try again.";
                    $status = 'error';
                }
            } elseif ($action === 'reject') {
                // Reject invitation
                $delete_sql = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("ii", $invitation['group_id'], $user_id);
                
                if ($delete_stmt->execute()) {
                    $message = "You have declined the invitation.";
                    $status = 'info';
                    header("Refresh: 2; URL=expense_splitter.php");
                } else {
                    $message = "Failed to decline invitation. Please try again.";
                    $status = 'error';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Group - LIFE-SYNC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
            --success: #28a745;
            --error: #dc3545;
            --info: #17a2b8;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: var(--nude-100);
            margin: 0;
            padding-top: 80px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 500px;
            width: 90%;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .message.success { background-color: #d4edda; color: var(--success); }
        .message.error { background-color: #f8d7da; color: var(--error); }
        .message.info { background-color: #d1ecf1; color: var(--info); }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 10px;
            transition: background-color 0.3s;
        }

        .btn-accept {
            background-color: var(--success);
            color: white;
        }

        .btn-reject {
            background-color: var(--error);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        h1 {
            color: var(--brown-primary);
            margin-bottom: 20px;
        }

        p {
            color: #666;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $status; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($status !== 'success' && $status !== 'info' && !empty($invitation)): ?>
            <h1>Join <?php echo htmlspecialchars($invitation['group_name']); ?></h1>
            <p>You've been invited to join this expense splitting group. Would you like to accept the invitation?</p>
            
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="accept">
                <button type="submit" class="btn btn-accept">Accept Invitation</button>
            </form>
            
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="reject">
                <button type="submit" class="btn btn-reject">Decline</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 