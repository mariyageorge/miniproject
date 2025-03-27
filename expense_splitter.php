<?php
// File: expense_splitter.php
include("connect.php");
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Check user role for premium status
if (!isset($_SESSION['role'])) {
    $query = "SELECT role FROM users WHERE user_id='$user_id'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $_SESSION['role'] = $row['role'];
}

$role = $_SESSION['role'];
$is_premium = ($role === 'premium user' || $role === 'admin');

// Get profile picture
if (!isset($_SESSION['profile_pic'])) {
    $query = "SELECT profile_pic FROM users WHERE user_id='$user_id'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $_SESSION['profile_pic'] = !empty($row['profile_pic']) ? $row['profile_pic'] : 'images/default-avatar.png';
}
$profile_pic = $_SESSION['profile_pic'];

// Create expense_groups table if it doesn't exist
$create_groups_table = "CREATE TABLE IF NOT EXISTS expense_groups (
    group_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    created_by INT(6) UNSIGNED NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_groups_table);

// Create group_members table if it doesn't exist
$create_members_table = "CREATE TABLE IF NOT EXISTS group_members (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT(6) UNSIGNED NOT NULL,
    user_id INT(6) UNSIGNED NOT NULL,
    invitation_status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
    invitation_token VARCHAR(100),
    FOREIGN KEY (group_id) REFERENCES expense_groups(group_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    UNIQUE KEY unique_group_member (group_id, user_id)
)";

mysqli_query($conn, $create_members_table);

// Create expenses table if it doesn't exist
$create_expenses_table = "CREATE TABLE IF NOT EXISTS expenses (
    expense_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT(6) UNSIGNED NOT NULL,
    paid_by INT(6) UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES expense_groups(group_id),
    FOREIGN KEY (paid_by) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_expenses_table);

// Create expense_shares table if it doesn't exist
$create_shares_table = "CREATE TABLE IF NOT EXISTS expense_shares (
    share_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_id INT(6) UNSIGNED NOT NULL,
    user_id INT(6) UNSIGNED NOT NULL,
    share_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'settled') DEFAULT 'pending',
    FOREIGN KEY (expense_id) REFERENCES expenses(expense_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_shares_table);

// Create settlements table if it doesn't exist
$create_settlements_table = "CREATE TABLE IF NOT EXISTS settlements (
    settlement_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT(6) UNSIGNED NOT NULL,
    payer_id INT(6) UNSIGNED NOT NULL,
    receiver_id INT(6) UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    settled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES expense_groups(group_id),
    FOREIGN KEY (payer_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_settlements_table);

// Create group_messages table if it doesn't exist
$create_group_messages_table = "CREATE TABLE IF NOT EXISTS group_messages (
    message_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id INT(6) UNSIGNED NOT NULL,
    sender_id INT(6) UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES expense_groups(group_id),
    FOREIGN KEY (sender_id) REFERENCES users(user_id)
)";

mysqli_query($conn, $create_group_messages_table);

// Create direct_messages table if it doesn't exist
$create_direct_messages_table = "CREATE TABLE IF NOT EXISTS direct_messages (
    message_id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(6) UNSIGNED NOT NULL,
    recipient_id INT(6) UNSIGNED NOT NULL,
    group_id INT(6) UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (recipient_id) REFERENCES users(user_id),
    FOREIGN KEY (group_id) REFERENCES expense_groups(group_id)
)";

mysqli_query($conn, $create_direct_messages_table);

// Process group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group']) && $is_premium) {
    $group_name = mysqli_real_escape_string($conn, $_POST['group_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $insert_query = "INSERT INTO expense_groups (group_name, created_by, description) 
                     VALUES ('$group_name', '$user_id', '$description')";
    
    if (mysqli_query($conn, $insert_query)) {
        $group_id = mysqli_insert_id($conn);
        
        // Add creator as a member with accepted status
        $insert_member = "INSERT INTO group_members (group_id, user_id, invitation_status) 
                          VALUES ('$group_id', '$user_id', 'accepted')";
        mysqli_query($conn, $insert_member);
        
        // Process member invitations
        if (!empty($_POST['members'])) {
            $members = explode(',', $_POST['members']);
            $success_count = 0;
            $error_messages = [];
            
            foreach ($members as $member_email) {
                $member_email = trim($member_email);
                
                // Check if the email exists in users table
                $check_user = "SELECT user_id, username FROM users WHERE email = ?";
                $stmt = mysqli_prepare($conn, $check_user);
                mysqli_stmt_bind_param($stmt, "s", $member_email);
                mysqli_stmt_execute($stmt);
                $user_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($user_result) > 0) {
                    $member_data = mysqli_fetch_assoc($user_result);
                    $member_id = $member_data['user_id'];
                    
                    // Check if user is already a member
                    $check_member = "SELECT invitation_status FROM group_members WHERE group_id = ? AND user_id = ?";
                    $stmt = mysqli_prepare($conn, $check_member);
                    mysqli_stmt_bind_param($stmt, "ii", $group_id, $member_id);
                    mysqli_stmt_execute($stmt);
                    $member_result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($member_result) > 0) {
                        $member_status = mysqli_fetch_assoc($member_result)['invitation_status'];
                        if ($member_status === 'accepted') {
                            $error_messages[] = "$member_email is already a member of this group";
                        } else {
                            $error_messages[] = "$member_email has a pending invitation";
                        }
                        continue;
                    }
                    
                    // Generate invitation token
                    $token = bin2hex(random_bytes(16));
                    
                    // Add to group_members table
                    $insert_invitation = "INSERT INTO group_members (group_id, user_id, invitation_status, invitation_token) 
                                         VALUES (?, ?, 'pending', ?)";
                    $stmt = mysqli_prepare($conn, $insert_invitation);
                    mysqli_stmt_bind_param($stmt, "iis", $group_id, $member_id, $token);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        // Send invitation email
                        if (sendInvitationEmail($member_email, $username, $group_name, $token, $group_id)) {
                            $success_count++;
                        } else {
                            $error_messages[] = "Failed to send invitation email to $member_email";
                        }
                    } else {
                        $error_messages[] = "Failed to create invitation for $member_email";
                    }
                } else {
                    $error_messages[] = "$member_email is not registered in LIFE-SYNC";
                }
            }
            
            if ($success_count > 0) {
                $success_message = "Group created successfully! ";
                $success_message .= $success_count . " invitation" . ($success_count > 1 ? "s" : "") . " sent.";
            }
            
            if (!empty($error_messages)) {
                $error_message = implode("<br>", $error_messages);
            }
        } else {
            $success_message = "Group created successfully!";
        }
    } else {
        $error_message = "Error creating group: " . mysqli_error($conn);
    }
}

// Process expense addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense']) && $is_premium) {
    $group_id = mysqli_real_escape_string($conn, $_POST['group_id']);
    $amount = mysqli_real_escape_string($conn, $_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $split_method = $_POST['split_method'];
    
    // Insert the expense
    $insert_expense = "INSERT INTO expenses (group_id, paid_by, amount, description) 
                      VALUES ('$group_id', '$user_id', '$amount', '$description')";
    
    if (mysqli_query($conn, $insert_expense)) {
        $expense_id = mysqli_insert_id($conn);
        
        // Get group members
        $get_members = "SELECT user_id FROM group_members WHERE group_id = '$group_id' AND invitation_status = 'accepted'";
        $members_result = mysqli_query($conn, $get_members);
        $members = [];
        
        while ($row = mysqli_fetch_assoc($members_result)) {
            $members[] = $row['user_id'];
        }
        
        $member_count = count($members);
        
        if ($member_count > 0) {
            if ($split_method === 'equal') {
                // Equal split
                $share_amount = $amount / $member_count;
                
                foreach ($members as $member_id) {
                    // Skip the payer from owing themselves
                    if ($member_id == $user_id) continue;
                    
                    $insert_share = "INSERT INTO expense_shares (expense_id, user_id, share_amount) 
                                    VALUES ('$expense_id', '$member_id', '$share_amount')";
                    mysqli_query($conn, $insert_share);
                }
            } else if ($split_method === 'custom' && isset($_POST['share'])) {
                // Custom split
                foreach ($_POST['share'] as $member_id => $share) {
                    if (empty($share) || $member_id == $user_id) continue;
                    
                    $share_amount = mysqli_real_escape_string($conn, $share);
                    $member_id = mysqli_real_escape_string($conn, $member_id);
                    
                    $insert_share = "INSERT INTO expense_shares (expense_id, user_id, share_amount) 
                                    VALUES ('$expense_id', '$member_id', '$share_amount')";
                    mysqli_query($conn, $insert_share);
                }
            }
        }
        
        $success_message = "Expense added successfully!";
    } else {
        $error_message = "Error adding expense: " . mysqli_error($conn);
    }
}

// Process settlement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_expense']) && $is_premium) {
    $expense_id = mysqli_real_escape_string($conn, $_POST['expense_id']);
    
    // Update the expense share status
    $update_share = "UPDATE expense_shares SET status = 'settled' WHERE expense_id = '$expense_id' AND user_id = '$user_id'";
    
    if (mysqli_query($conn, $update_share)) {
        // Get expense details for settlement record
        $get_expense = "SELECT e.group_id, e.paid_by, es.share_amount 
                        FROM expenses e 
                        JOIN expense_shares es ON e.expense_id = es.expense_id 
                        WHERE e.expense_id = '$expense_id' AND es.user_id = '$user_id'";
        $expense_result = mysqli_query($conn, $get_expense);
        
        if ($expense_data = mysqli_fetch_assoc($expense_result)) {
            $group_id = $expense_data['group_id'];
            $receiver_id = $expense_data['paid_by'];
            $amount = $expense_data['share_amount'];
            
            // Record the settlement
            $insert_settlement = "INSERT INTO settlements (group_id, payer_id, receiver_id, amount) 
                                 VALUES ('$group_id', '$user_id', '$receiver_id', '$amount')";
            mysqli_query($conn, $insert_settlement);
        }
        
        $success_message = "Payment marked as settled!";
    } else {
        $error_message = "Error settling payment: " . mysqli_error($conn);
    }
}

// Function to send invitation email
function sendInvitationEmail($email, $inviter_name, $group_name, $token, $group_id) {
    // Include PHPMailer
    require 'vendor/phpmailer/phpmailer/src/Exception.php';
    require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require 'vendor/phpmailer/phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lifesyncdigital@gmail.com';
        $mail->Password = 'yrpw iqys blcl famq';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('lifesyncdigital@gmail.com', 'LIFE-SYNC');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Invitation to join expense group: ' . $group_name;
        
        $invitation_link = "http://{$_SERVER['HTTP_HOST']}/accept_invitation.php?token=" . $token . "&group=" . $group_id;
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #2C3E50;'>LIFE-SYNC Expense Splitter</h2>
                    <p>Hello,</p>
                    <p><strong>{$inviter_name}</strong> has invited you to join the expense group <strong>'{$group_name}'</strong>.</p>
                    <p>Click the button below to accept the invitation:</p>
                    <p style='text-align: center;'>
                        <a href='{$invitation_link}' style='display: inline-block; padding: 10px 20px; background-color: #2C3E50; color: white; text-decoration: none; border-radius: 5px;'>Accept Invitation</a>
                    </p>
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <p>{$invitation_link}</p>
                    <p>Thank you,<br>The LIFE-SYNC Team</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Hello, {$inviter_name} has invited you to join the expense group '{$group_name}'. To accept the invitation, please visit: {$invitation_link}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Fetch user's groups
$groups_query = "SELECT eg.* FROM expense_groups eg
                JOIN group_members gm ON eg.group_id = gm.group_id
                WHERE gm.user_id = '$user_id' AND gm.invitation_status = 'accepted'
                ORDER BY eg.created_at DESC";
$groups_result = mysqli_query($conn, $groups_query);

// Fetch pending expenses for this user
$pending_query = "SELECT e.expense_id, e.description, e.amount, e.date_added, 
                 es.share_amount, eg.group_name, u.username as paid_by_user
                 FROM expenses e
                 JOIN expense_shares es ON e.expense_id = es.expense_id
                 JOIN expense_groups eg ON e.group_id = eg.group_id
                 JOIN users u ON e.paid_by = u.user_id
                 WHERE es.user_id = '$user_id' AND es.status = 'pending'
                 ORDER BY e.date_added DESC";
$pending_result = mysqli_query($conn, $pending_query);

// Calculate total balances
$balances_query = "SELECT 
                  CASE 
                      WHEN es.user_id = '$user_id' THEN -SUM(es.share_amount)
                      WHEN e.paid_by = '$user_id' THEN SUM(es.share_amount)
                  END as balance,
                  CASE 
                      WHEN es.user_id = '$user_id' THEN e.paid_by
                      WHEN e.paid_by = '$user_id' THEN es.user_id
                  END as other_user_id,
                  u.username as other_username
                  FROM expenses e
                  JOIN expense_shares es ON e.expense_id = es.expense_id
                  JOIN users u ON (es.user_id = u.user_id OR e.paid_by = u.user_id)
                  WHERE (es.user_id = '$user_id' OR e.paid_by = '$user_id')
                  AND es.status = 'pending'
                  AND u.user_id != '$user_id'
                  GROUP BY other_user_id";
$balances_result = mysqli_query($conn, $balances_query);
$balances = [];

while ($row = mysqli_fetch_assoc($balances_result)) {
    $balances[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFE-SYNC Expense Splitter</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --primary-color: #2C3E50;
            --secondary-color: #34495E;
            --accent-color: #3498DB;
            --success-color: #2ECC71;
            --warning-color: #F1C40F;
            --danger-color: #E74C3C;
            --light-bg: #ECF0F1;
            --white: #FFFFFF;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 1rem;
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            border-radius: var(--border-radius);
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
        }

        .button {
            background: var(--accent-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .button:hover {
            background: #2980B9;
            transform: translateY(-1px);
        }

        .button-success {
            background: var(--success-color);
        }

        .button-warning {
            background: var(--warning-color);
        }

        .button-danger {
            background: var(--danger-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #BDC3C7;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #BDC3C7;
        }

        .table th {
            background-color: var(--light-bg);
            font-weight: 600;
        }

        .table tr:hover {
            background-color: #F8F9FA;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-success {
            background: #D5F5E3;
            color: #27AE60;
        }

        .badge-warning {
            background: #FCF3CF;
            color: #F39C12;
        }

        .badge-danger {
            background: #FADBD8;
            color: #C0392B;
        }

        .modal {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 0;
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem;
            background: var(--light-bg);
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--light-bg);
            padding-bottom: 10px;
        }

        .tab {
            padding: 8px 16px;
            cursor: pointer;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }

        .tab.active {
            background: var(--accent-color);
            color: var(--white);
        }

        .alert {
            padding: 12px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #D5F5E3;
            color: #27AE60;
            border: 1px solid #2ECC71;
        }

        .alert-error {
            background: #FADBD8;
            color: #C0392B;
            border: 1px solid #E74C3C;
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: var(--white);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .table {
                display: block;
                overflow-x: auto;
            }

            .button {
                width: 100%;
                margin-bottom: 10px;
            }

            .tabs {
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <header class="header">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-infinity"></i>
            </div>
            <span class="logo-text">LIFE-SYNC</span>
        </div>
        
        <div class="header-right">
            <div class="welcome-section">
                <span class="welcome-sticker">💰</span>
                <div class="welcome-message">
                    Expense Splitter
                </div>
            </div>
            <a href="dashboard.php" class="button button-outline">Back to Dashboard</a>
        </div>
    </header>

    <aside class="sidebar">
        <br><br><br><br>
        <nav class="nav-links">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="expense_splitter.php" class="nav-link">
                <i class="fas fa-receipt"></i>
                <span>Expense Splitter</span>
            </a>
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
        </nav>
    </aside>

    <div class="main-content">
        <h1 class="page-title">Expense Splitter</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (!$is_premium): ?>
            <div class="section">
                <div class="section-title">Premium Feature</div>
                <p>The Expense Splitter is a premium feature that helps you split expenses with friends, family, or roommates.</p>
                <div style="text-align: center; margin: 2rem 0;">
                    <i class="fas fa-lock" style="font-size: 4rem; color: var(--accent-orange); margin-bottom: 1rem;"></i>
                    <h2>Upgrade to Premium to access Expense Splitter</h2>
                    <p style="margin: 1rem 0;">Easily manage shared expenses, track who owes what, and settle up with friends.</p>
                    <a href="upgrade.php" class="button premium-button">Upgrade Now</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Balance Overview -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-wallet"></i> Your Balance Overview
                    <div class="total-balance">
                        <?php
                        $total_balance = 0;
                        foreach ($balances as $balance) {
                            $total_balance += $balance['balance'];
                        }
                        $balance_class = $total_balance > 0 ? 'positive' : ($total_balance < 0 ? 'negative' : 'neutral');
                        ?>
                        <span class="balance-indicator <?php echo $balance_class; ?>">
                            <i class="fas fa-<?php echo $total_balance > 0 ? 'arrow-up' : ($total_balance < 0 ? 'arrow-down' : 'equals'); ?>"></i>
                            Total: $<?php echo number_format(abs($total_balance), 2); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (empty($balances)): ?>
                    <div class="no-balances">
                        <i class="fas fa-check-circle"></i>
                        <p>You don't have any unsettled balances at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="balance-container">
                        <?php foreach ($balances as $balance): ?>
                            <div class="balance-card <?php echo $balance['balance'] > 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                <div class="balance-icon">
                                    <?php if ($balance['balance'] > 0): ?>
                                        <i class="fas fa-arrow-down"></i>
                                        <div>You are owed</div>
                                    <?php else: ?>
                                        <i class="fas fa-arrow-up"></i>
                                        <div>You owe</div>
                                    <?php endif; ?>
                                </div>
                                <div class="balance-amount">
                                    $<?php echo number_format(abs($balance['balance']), 2); ?>
                                </div>
                                <div class="balance-details">
                                    <div class="balance-name">
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($balance['other_username']); ?>
                                    </div>
                                    <div class="balance-date">
                                        <i class="fas fa-clock"></i>
                                        Last updated: <?php echo date('M d, Y'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Groups -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-users"></i> My Groups
                    <button class="button" style="float: right; font-size: 0.9rem;" onclick="openModal('createGroupModal')">
                        <i class="fas fa-plus"></i> Create New Group
                    </button>
                </div>
                
                <?php if (mysqli_num_rows($groups_result) > 0): ?>
                    <div class="card-container">
                        <?php while ($group = mysqli_fetch_assoc($groups_result)): 
                            // Get member count
                            $member_count_query = "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND invitation_status = 'accepted'";
                            $stmt = mysqli_prepare($conn, $member_count_query);
                            mysqli_stmt_bind_param($stmt, "i", $group['group_id']);
                            mysqli_stmt_execute($stmt);
                            $member_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];

                            // Get total expenses
                            $expense_query = "SELECT COUNT(*) as count, SUM(amount) as total FROM expenses WHERE group_id = ?";
                            $stmt = mysqli_prepare($conn, $expense_query);
                            mysqli_stmt_bind_param($stmt, "i", $group['group_id']);
                            mysqli_stmt_execute($stmt);
                            $expense_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                        ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-users-gear"></i> <?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <div class="card-meta">
                                        <span><i class="fas fa-users"></i> <?php echo $member_count; ?> members</span>
                                        <span><i class="fas fa-receipt"></i> <?php echo $expense_data['count']; ?> expenses</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="group-description">
                                        <i class="fas fa-info-circle"></i>
                                        <?php echo htmlspecialchars($group['description'] ?? 'No description'); ?>
                                    </p>
                                    <div class="group-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span>Total: $<?php echo number_format($expense_data['total'] ?? 0, 2); ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Created: <?php echo date('M d, Y', strtotime($group['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button class="button" onclick="openGroupDetails(<?php echo $group['group_id']; ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-groups">
                        <i class="fas fa-users"></i>
                        <p>You don't have any expense groups yet. Create one to get started!</p>
                        <button class="button" onclick="openModal('createGroupModal')">
                            <i class="fas fa-plus"></i> Create Your First Group
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pending Expenses -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-clock"></i> Pending Expenses
                    <div class="pending-total">
                        <?php
                        $total_pending = 0;
                        mysqli_data_seek($pending_result, 0);
                        while ($expense = mysqli_fetch_assoc($pending_result)) {
                            $total_pending += $expense['share_amount'];
                        }
                        ?>
                        <span class="pending-amount">
                            <i class="fas fa-exclamation-circle"></i>
                            Total Pending: $<?php echo number_format($total_pending, 2); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (mysqli_num_rows($pending_result) > 0): ?>
                    <div class="pending-expenses">
                        <?php 
                        mysqli_data_seek($pending_result, 0);
                        while ($expense = mysqli_fetch_assoc($pending_result)): 
                        ?>
                            <div class="pending-card">
                                <div class="pending-header">
                                    <div class="pending-group">
                                        <i class="fas fa-users"></i>
                                        <?php echo htmlspecialchars($expense['group_name']); ?>
                                    </div>
                                    <div class="pending-amount">
                                        <span class="amount-label">Your Share:</span>
                                        <span class="amount-value">$<?php echo number_format($expense['share_amount'], 2); ?></span>
                                    </div>
                                </div>
                                <div class="pending-body">
                                    <div class="pending-description">
                                        <i class="fas fa-receipt"></i>
                                        <?php echo htmlspecialchars($expense['description']); ?>
                                    </div>
                                    <div class="pending-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-user"></i>
                                            Paid by: <?php echo htmlspecialchars($expense['paid_by_user']); ?>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($expense['date_added'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="pending-footer">
                                    <form method="post" class="settle-form">
                                        <input type="hidden" name="expense_id" value="<?php echo $expense['expense_id']; ?>">
                                        <button type="submit" name="settle_expense" class="button button-success">
                                            <i class="fas fa-check"></i> Mark as Settled
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-pending">
                        <i class="fas fa-check-circle"></i>
                        <p>You don't have any pending expenses to settle.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Create Group Modal -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Expense Group</h3>
                <button class="modal-close" onclick="closeModal('createGroupModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" id="group_name" name="group_name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="members">Invite Members (Enter email addresses, separated by commas)</label>
                        <textarea id="members" name="members" rows="3" placeholder="friend@example.com, roommate@example.com"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button-outline" onclick="closeModal('createGroupModal')">Cancel</button>
                        <button type="submit" name="create_group" class="button">Create Group</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Group Details Modal (will be populated dynamically) -->
    <div id="groupDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="groupModalTitle">Group Details</h3>
                <button class="modal-close" onclick="closeModal('groupDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="groupModalBody">
                <!-- Will be populated via AJAX -->
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Expense</h3>
                <button class="modal-close" onclick="closeModal('addExpenseModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" id="expenseForm">
                    <input type="hidden" id="expense_group_id" name="group_id">
                    <div class="form-group">
                        <label for="amount">Amount ($)</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="expense_description">Description</label>
                        <input type="text" id="expense_description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="split_method">Split Method</label>
                        <select id="split_method" name="split_method" onchange="toggleSplitMethod()">
                            <option value="equal">Equal Split</option>
                            <option value="custom">Custom Amounts</option>
                        </select>
                    </div>
                    <div id="customSplitContainer" style="display: none;">
                        <!-- Will be populated dynamically with group members -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="button button-outline" onclick="closeModal('addExpenseModal')">Cancel</button>
                        <button type="submit" name="add_expense" class="button">Add Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to open modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Toggle split method display
        function toggleSplitMethod() {
            const splitMethod = document.getElementById('split_method').value;
            const customContainer = document.getElementById('customSplitContainer');
            
            if (splitMethod === 'custom') {
                customContainer.style.display = 'block';
            } else {
                customContainer.style.display = 'none';
            }
        }

        // Function to open group details modal
        function openGroupDetails(groupId) {
            // AJAX request to get group details
            fetch(`get_group_details.php?group_id=${groupId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('groupModalBody').innerHTML = data;
                    openModal('groupDetailsModal');
                })
                .catch(error => console.error('Error:', error));
        }

        // Function to confirm and delete group
        function confirmDeleteGroup(groupId) {
            if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
                fetch('delete_group.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'group_id=' + groupId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'expense_splitter.php';
                    } else {
                        alert(data.error || 'Failed to delete group');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the group');
                });
            }
        }

        // Function to open add expense modal
        function openAddExpenseModal(groupId, groupName) {
            document.getElementById('expense_group_id').value = groupId;
            document.getElementById('groupModalTitle').textContent = `Add Expense to ${groupName}`;
            
            // Get group members for custom split
            fetch(`get_group_members.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(members => {
                    const container = document.getElementById('customSplitContainer');
                    container.innerHTML = '';
                    
                    members.forEach(member => {
                        if (member.user_id != <?php echo $user_id; ?>) {
                            const div = document.createElement('div');
                            div.className = 'form-group';
                            div.innerHTML = `
                                <label for="share_${member.user_id}">${member.username}</label>
                                <input type="number" id="share_${member.user_id}" name="share[${member.user_id}]" 
                                       step="0.01" min="0" placeholder="Amount for ${member.username}">
                            `;
                            container.appendChild(div);
                        }
                    });
                    
                    closeModal('groupDetailsModal');
                    openModal('addExpenseModal');
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>