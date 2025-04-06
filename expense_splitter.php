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

// Fetch user's currency preference
$currency_query = "SELECT currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $currency_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$currency_result = mysqli_stmt_get_result($stmt);
$currency_pref = mysqli_fetch_assoc($currency_result);
$currency_symbol = $currency_pref['currency_symbol'] ?? '$'; // Default to $ if no preference set

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

// Create user_currency_preferences table
$create_currency_prefs_table = "CREATE TABLE IF NOT EXISTS user_currency_preferences (
    user_id INT(6) UNSIGNED NOT NULL,
    currency_code VARCHAR(3) NOT NULL,
    currency_symbol VARCHAR(5) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if (!mysqli_query($conn, $create_currency_prefs_table)) {
    echo "Error creating currency preferences table: " . mysqli_error($conn);
}

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
                $_SESSION['toast_success'] = "Group created successfully! " . $success_count . " invitation" . ($success_count > 1 ? "s" : "") . " sent.";
            }
            
            if (!empty($error_messages)) {
                $_SESSION['toast_error'] = implode("<br>", $error_messages);
            }
        } else {
            $_SESSION['toast_success'] = "Group created successfully!";
        }
        
        // Redirect after successful creation to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['toast_error'] = "Error creating group: " . mysqli_error($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Display toast messages if they exist
if (isset($_SESSION['toast_success'])) {
    $success_message = $_SESSION['toast_success'];
    unset($_SESSION['toast_success']);
}

if (isset($_SESSION['toast_error'])) {
    $error_message = $_SESSION['toast_error'];
    unset($_SESSION['toast_error']);
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
    // Update PHPMailer includes to prevent duplicate class definition
    require_once 'vendor/autoload.php';

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

// Calculate total pending amount
$total_pending = 0;
$pending_query_total = "SELECT SUM(es.share_amount) as total
                       FROM expense_shares es
                       WHERE es.user_id = '$user_id' 
                       AND es.status = 'pending'";
$pending_total_result = mysqli_query($conn, $pending_query_total);
$total_row = mysqli_fetch_assoc($pending_total_result);
$total_pending = $total_row['total'] ?? 0;

// Handle currency preference update
if (isset($_POST['saveSettings'])) {
    $currency_code = mysqli_real_escape_string($conn, $_POST['defaultCurrency']);
    $currency_symbol = mysqli_real_escape_string($conn, $_POST['currencySymbol']);
    
    // Check if user already has a preference
    $check_query = "SELECT * FROM user_currency_preferences WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        // Update existing preference
        $update_query = "UPDATE user_currency_preferences 
                        SET currency_code = ?, currency_symbol = ? 
                        WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $currency_code, $currency_symbol, $user_id);
    } else {
        // Insert new preference
        $insert_query = "INSERT INTO user_currency_preferences (user_id, currency_code, currency_symbol) 
                        VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $currency_code, $currency_symbol);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Currency settings updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating currency settings: " . mysqli_error($conn);
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#settings");
    exit();
}

// Get current currency preference
$curr_query = "SELECT currency_code, currency_symbol FROM user_currency_preferences WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $curr_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$current_currency = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIFE-SYNC Expense Splitter</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
    <style>
        :root {
            --nude-100: #F5ECE5;
            --nude-200: #E8D5C8;
            --nude-300: #DBBFAE;
            --nude-400: #C6A792;
            --brown-primary: #8B4513;
            --brown-hover: #A0522D;
            --primary-color: var(--brown-primary);
            --secondary-color: var(--brown-hover);
            --accent-color: var(--nude-300);
            --success-color: #4ade80;
            --warning-color: #f59e0b;
            --danger-color: #f87171;
            --light-bg: var(--nude-100);
            --white: #ffffff;
            --text-primary: var(--brown-primary);
            --text-secondary: var(--nude-400);
            --border-radius: 12px;
            --box-shadow: 0 4px 6px rgba(139, 69, 19, 0.1);
            --box-shadow-hover: 0 10px 15px rgba(139, 69, 19, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
            color: var(--white);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(139, 69, 19, 0.2);
            z-index: 100;
            transition: var(--transition);
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-title i {
            font-size: 1.8rem;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 20px 0;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: var(--transition);
            border-radius: 0 30px 30px 0;
            margin-right: 20px;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--nude-100);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: var(--nude-100);
        }

        .nav-link i {
            width: 24px;
            text-align: center;
            font-size: 1.1rem;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            width: calc(100% - 280px);
            transition: var(--transition);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: var(--box-shadow);
        }

        .user-name {
            font-weight: 500;
        }

        .section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }

        .section:hover {
            box-shadow: var(--box-shadow-hover);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .section-title i {
            margin-right: 10px;
        }

        .button {
            background: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .button:hover {
            background: var(--brown-hover);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .button-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .button-outline:hover {
            background: var(--primary-color);
            color: var(--white);
        }

        .button-success {
            background: var(--success-color);
        }

        .button-success:hover {
            background: #22c55e;
        }

        .button-danger {
            background: var(--danger-color);
        }

        .button-danger:hover {
            background: #ef4444;
        }

        .button i {
            font-size: 0.9rem;
        }

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            transition: var(--transition);
            border: 1px solid var(--nude-200);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-hover);
            border-color: var(--nude-300);
        }

        .card-header {
            margin-bottom: 15px;
            position: relative;
        }

        .card-header h3 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-meta {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .card-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-body {
            margin-bottom: 15px;
        }

        .group-description {
            color: var(--text-secondary);
            margin-bottom: 15px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .group-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .stat-item i {
            color: var(--accent-color);
            font-size: 1.1rem;
        }

        .card-footer {
            display: flex;
            justify-content: flex-end;
        }

        .balance-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .balance-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .balance-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(139, 69, 19, 0.15);
        }

        .balance-icon {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .balance-icon i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 0.8rem;
        }

        .balance-positive .balance-icon i {
            background: #4ade8033;
            color: #4ade80;
        }

        .balance-negative .balance-icon i {
            background: #f8717133;
            color: #f87171;
        }

        .balance-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            padding: 5px 0;
        }

        .balance-positive .balance-amount {
            color: #4ade80;
        }

        .balance-negative .balance-amount {
            color: #f87171;
        }

        .balance-name {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
            color: var(--text-primary);
            padding: 5px 0;
        }

        .balance-name i {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .balance-details {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .balance-icon {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
        }

        .balance-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .balance-name, .balance-date {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-expenses {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .pending-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            border: 1px solid var(--nude-200);
            transition: var(--transition);
        }

        .pending-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
            border-color: var(--nude-300);
        }

        .pending-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .pending-group {
            color: var(--primary-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-amount {
            color: var(--danger-color);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .pending-body {
            margin-bottom: 15px;
        }

        .pending-description {
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .pending-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-footer {
            display: flex;
            justify-content: flex-end;
        }

        .settle-form {
            width: 100%;
        }

        .no-balances, .no-groups, .no-pending {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            background: rgba(0, 0, 0, 0.02);
            border-radius: var(--border-radius);
        }

        .no-balances i, .no-groups i, .no-pending i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-color);
            opacity: 0.5;
        }

        .no-balances p, .no-groups p, .no-pending p {
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .premium-feature {
            text-align: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, rgba(67, 97, 238, 0.1), rgba(63, 55, 201, 0.1));
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .premium-icon {
            font-size: 4rem;
            color: var(--accent-color);
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .premium-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .premium-description {
            color: var(--text-secondary);
            margin-bottom: 25px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .premium-button {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
        }

        .premium-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px rgba(67, 97, 238, 0.3);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(20px);
            transition: transform 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .modal.show .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
            color: var(--white);
            padding: 15px 20px;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            background: var(--light-bg);
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--nude-200);
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            transition: var(--transition);
            background: var(--nude-100);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brown-primary);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.2);
            background: var(--white);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            padding-right: 40px;
        }

        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }

        .toast {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: var(--box-shadow-hover);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 350px;
            border-left: 4px solid;
        }

        .toast-success {
            border-left-color: var(--success-color);
        }

        .toast-error {
            border-left-color: var(--danger-color);
        }

        .toast i {
            font-size: 1.3rem;
        }

        .toast-success i {
            color: var(--success-color);
        }

        .toast-error i {
            color: var(--danger-color);
        }

        .toast-message {
            flex-grow: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast.hide {
            animation: slideOut 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Loading states */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .loading i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            text-align: center;
            padding: 40px;
            color: var(--danger-color);
        }

        .error-message i {
            font-size: 2rem;
            margin-bottom: 15px;
        }

        /* Tabs */
        .section-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 5px;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 8px 16px;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 500;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .tab-button:hover {
            background: var(--nude-200);
            color: var(--brown-primary);
        }

        .tab-button.active {
            background: var(--brown-primary);
            color: var(--white);
        }

        .section-content {
            display: none;
        }

        .section-content.active {
            display: block;
        }

        /* Members grid */
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .member-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--nude-200);
            transition: var(--transition);
        }

        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
            border-color: var(--nude-300);
        }

        .member-card.pending {
            background: rgba(245, 158, 11, 0.05);
            border-color: rgba(245, 158, 11, 0.2);
        }

        .member-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.2rem;
            font-weight: 500;
            flex-shrink: 0;
        }

        .member-info {
            flex-grow: 1;
        }

        .member-info h4 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        .member-email {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin: 0 0 10px 0;
        }

        .member-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .member-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #b45309;
        }

        .member-status.accepted {
            background: rgba(74, 222, 128, 0.1);
            color: #047857;
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                overflow: hidden;
            }

            .sidebar-header, .nav-link span {
                display: none;
            }

            .nav-link {
                justify-content: center;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                margin: 0 auto;
                padding: 0;
            }

            .main-content {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
        }

        @media (max-width: 768px) {
            .card-container, .balance-container, .pending-expenses, .members-grid {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                height: auto;
                bottom: 0;
                top: auto;
                padding: 10px 0;
            }

            .nav-links {
                flex-direction: row;
                justify-content: center;
                padding: 0;
                gap: 5px;
            }

            .nav-link {
                width: 40px;
                height: 40px;
            }

            .main-content {
                margin-left: 0;
                margin-bottom: 70px;
                width: 100%;
                padding: 15px;
            }

            .section {
                padding: 20px;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }

        /* Add styles for tag input */
        .tag-input-container {
            border: 1px solid var(--nude-200);
            border-radius: var(--border-radius);
            padding: 5px;
            background: var(--nude-100);
            min-height: 45px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
        }

        .tag {
            background: var(--brown-primary);
            color: white;
            padding: 2px 8px;
            border-radius: 15px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }

        .tag-close {
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .tag-input {
            border: none;
            outline: none;
            padding: 5px;
            flex-grow: 1;
            min-width: 120px;
            background: transparent;
        }

        .tag-input:focus {
            outline: none;
        }

        /* Section visibility */
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
        }

        .groups-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .group-list-item {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid var(--nude-200);
        }

        .group-list-item:hover {
            transform: translateX(5px);
            box-shadow: var(--box-shadow-hover);
            border-color: var(--nude-300);
        }

        .group-list-content {
            flex-grow: 1;
        }

        .group-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .group-list-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-count {
            color: var(--text-secondary);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .group-list-meta {
            display: flex;
            gap: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .group-list-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .group-list-item i.fa-chevron-right {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        #group-details {
            display: none;
        }

        #group-details.active {
            display: block;
        }

        #group-details .section-title {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        #group-details-title {
            margin: 0;
            font-size: 1.3rem;
            color: var(--primary-color);
        }

        /* Settings Styles */
        .settings-container {
            max-width: 600px;
            margin: 40px auto;
        }

        .settings-form {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .settings-form .form-group {
            margin-bottom: 25px;
        }

        .settings-form label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .settings-form label i {
            color: var(--primary-color);
        }

        .settings-form .form-text {
            margin-top: 8px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        #saveSettings {
            width: 100%;
            margin-top: 20px;
        }

        #saveSettings:hover {
            transform: translateY(-2px);
        }

        /* Enhanced Visual Styles */
        .section {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.15);
        }

        .balance-card {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            background: linear-gradient(135deg, var(--white) 0%, var(--nude-100) 100%);
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            transition: all 0.3s ease;
        }

        .balance-card.balance-positive::before {
            background: linear-gradient(to bottom, #4ade80, #22c55e);
        }

        .balance-card.balance-negative::before {
            background: linear-gradient(to bottom, #f87171, #ef4444);
        }

        .balance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(139, 69, 19, 0.2);
        }

        .balance-amount {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .balance-card:hover .balance-amount {
            transform: scale(1.05);
        }

        .group-list-item {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border: none;
            background: linear-gradient(135deg, var(--white) 0%, var(--nude-100) 100%);
            padding: 25px;
        }

        .group-list-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, var(--brown-primary), var(--brown-hover));
            opacity: 0;
            transition: all 0.3s ease;
        }

        .group-list-item:hover::before {
            opacity: 1;
        }

        .group-list-item:hover {
            transform: translateX(10px);
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.15);
            padding-left: 30px;
        }

        .group-list-header h3 {
            transition: all 0.3s ease;
        }

        .group-list-item:hover .group-list-header h3 {
            color: var(--brown-hover);
        }

        .member-count {
            background: var(--nude-100);
            padding: 5px 12px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .group-list-item:hover .member-count {
            background: var(--brown-primary);
            color: var(--white);
        }

        .group-list-meta span {
            padding: 4px 12px;
            border-radius: 15px;
            background: var(--nude-100);
            transition: all 0.3s ease;
        }

        .group-list-item:hover .group-list-meta span {
            background: var(--nude-200);
        }

        /* Pulse Animation for New Items */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .new-item {
            animation: pulse 2s infinite;
        }

        /* Enhanced Toast Notifications */
        .toast {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(139, 69, 19, 0.1);
        }

        /* Section Title Enhancement */
        .section-title {
            position: relative;
            padding-bottom: 15px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, var(--brown-primary), transparent);
        }

        /* Loading State Enhancement */
        .loading {
            background: linear-gradient(135deg, var(--nude-100) 0%, var(--white) 50%, var(--nude-100) 100%);
            background-size: 200% 200%;
            animation: gradient 2s ease infinite;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Add Expense Modal Specific Styles */
        .expense-form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .expense-form-grid {
                grid-template-columns: 1fr;
            }
        }

        .amount-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .currency-symbol {
            position: absolute;
            left: 15px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        #expense_amount {
            padding-left: 30px;
        }

        .split-method-buttons {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        .split-method-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--nude-200);
            background: var(--nude-100);
            border-radius: var(--border-radius);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .split-method-btn:hover {
            background: var(--nude-200);
        }

        .split-method-btn.active {
            background: var(--brown-primary);
            color: var(--white);
            border-color: var(--brown-primary);
        }

        .split-details {
            background: var(--nude-100);
            border-radius: var(--border-radius);
            padding: 15px;
        }

        .split-member {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            padding: 10px;
            background: var(--white);
            border-radius: var(--border-radius);
            border: 1px solid var(--nude-200);
        }

        .split-member:last-child {
            margin-bottom: 0;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--brown-primary);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            font-size: 1.2rem;
        }

        .member-details {
            flex: 1;
        }

        .member-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .split-input {
            width: 100px;
            text-align: right;
        }

        .split-amount {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        #expense_image {
            padding: 10px;
        }

        .image-preview {
            margin-top: 10px;
            max-width: 200px;
            border-radius: var(--border-radius);
            display: none;
        }

        /* Updated Expenses Styles */
        .expenses-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .expense-item {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid var(--brown-primary);
        }

        .expense-item:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 16px rgba(139, 69, 19, 0.1);
        }

        .expense-details {
            flex-grow: 1;
        }

        .expense-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .expense-meta {
            display: flex;
            gap: 15px;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .expense-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: var(--nude-100);
            border-radius: 15px;
        }

        .expense-amount {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--brown-primary);
            padding: 8px 16px;
            background: var(--nude-100);
            border-radius: 20px;
            min-width: 120px;
            text-align: center;
        }

        .no-expenses {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
            background: var(--nude-100);
            border-radius: var(--border-radius);
            margin-top: 20px;
        }

        .no-expenses i {
            font-size: 2rem;
            color: var(--brown-primary);
            margin-bottom: 15px;
        }

        .no-expenses p {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .no-expenses small {
            font-size: 0.9rem;
        }

        .confirmation-modal {
            max-width: 400px;
            text-align: center;
        }

        .confirmation-header {
            margin-bottom: 20px;
        }

        .confirmation-header i {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }

        .confirmation-header h3 {
            color: #dc3545;
            margin: 0;
            font-size: 24px;
        }

        .confirmation-body {
            margin-bottom: 25px;
        }

        .confirmation-body p {
            margin: 10px 0;
            font-size: 16px;
            color: #333;
        }

        .confirmation-warning {
            color: #dc3545;
            font-weight: 500;
        }

        .confirmation-footer {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .button-danger {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .button-danger:hover {
            background-color: #c82333;
        }

        .button-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .button-secondary:hover {
            background-color: #5a6268;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--brown-primary), var(--brown-hover));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header .close {
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
        }

        .modal-body {
            padding: 20px;
        }

        .expense-details-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--nude-200);
        }

        .expense-details-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .expense-details-meta-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .expense-details-meta-item label {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .expense-details-meta-item span {
            font-size: 1rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .shares-list {
            border: 1px solid var(--nude-200);
            border-radius: 8px;
            overflow: hidden;
        }

        .share-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--nude-200);
        }

        .share-item:last-child {
            border-bottom: none;
        }

        .share-item-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .share-item-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .settle-button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            background: var(--brown-primary);
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .settle-button:hover {
            background: var(--brown-hover);
        }

        .expense-receipt {
            margin: 20px 0;
            text-align: center;
        }

        .expense-receipt img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .expense-notes {
            background: var(--nude-100);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .expense-notes label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .expense-notes p {
            margin: 0;
            white-space: pre-wrap;
            color: var(--text-primary);
        }

        .expense-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }

        .expense-card-header {
            background: var(--brown-primary);
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .expense-card-header * {
            color: white !important;
        }

        .expense-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .expense-amount {
            font-weight: 600;
            padding: 4px 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        /* Currency Converter Styles */
.converter-container {
    max-width: 600px;
    margin: 40px auto;
}

.converter-form {
    background: var(--white);
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
}

.currency-input-group {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.currency-input-group .form-control {
    font-size: 1.2rem;
    height: 50px;
}

.currency-input-group input {
    flex: 1;
    font-weight: 600;
}

.currency-select {
    width: 200px;
    background-color: var(--nude-100);
    font-weight: 500;
}

.currency-equals {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px 0;
    color: var(--text-secondary);
    font-size: 1.1rem;
    gap: 10px;
}

.equals-text {
    font-weight: 500;
    color: var(--text-primary);
}

.conversion-info {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px dashed var(--nude-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.update-time, .exchange-rate {
    display: flex;
    align-items: center;
    gap: 8px;
}

.update-time i {
    color: var(--primary-color);
}

#convertedAmount {
    background-color: var(--nude-100);
    color: var(--primary-color);
    font-weight: bold;
}

#convertedAmount:focus {
    background-color: var(--nude-100);
}

@media (max-width: 768px) {
    .currency-input-group {
        flex-direction: column;
    }

    .currency-select {
        width: 100%;
    }
}
    </style>
</head>
<body>
    <!-- Toast notifications container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <i class="fas fa-wallet"></i>
                <span>Expense Splitter</span>
        </div>
            </div>
        <nav class="nav-links">
           
            <a href="#my-groups" class="nav-link active">
                <i class="fas fa-users"></i>
                <span>My Groups</span>
            </a>
            <a href="#pending-expenses" class="nav-link">
                <i class="fas fa-clock"></i>
                <span>Pending Expenses</span>
            </a>
            <a href="#currency-converter" class="nav-link">
        <i class="fas fa-exchange-alt"></i>
        <span>Currency Converter</span>
    </a>
            <a href="#settings" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
    </aside>

    <!-- Create Group Modal -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Create New Group</h2>
                <button type="button" class="modal-close" onclick="closeModal('createGroupModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" id="createGroupForm">
                    <div class="form-group">
                        <label for="group_name">Group Name</label>
                        <input type="text" id="group_name" name="group_name" class="form-control" placeholder="Enter group name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" placeholder="Enter group description"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tagInput">Add Members (Enter email and press Enter)</label>
                        <div class="tag-input-container">
                            <input type="text" id="tagInput" class="tag-input" placeholder="Enter email addresses">
                        </div>
                        <input type="hidden" id="members" name="members">
                    </div>
                    <input type="hidden" name="create_group" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-outline" onclick="closeModal('createGroupModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="button" onclick="document.getElementById('createGroupForm').submit()">
                    <i class="fas fa-check"></i> Create Group
                </button>
            </div>
        </div>
    </div>

    <!-- Invite Members Modal -->
    <div id="inviteMembersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Invite Members</h2>
                <button type="button" class="modal-close" onclick="closeModal('inviteMembersModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" id="inviteMembersForm">
                    <div class="form-group">
                        <label for="inviteTagInput">Add Members (Enter email and press Enter)</label>
                        <div class="tag-input-container" id="inviteTagContainer">
                            <input type="text" id="invite_members" name="members" class="tag-input" placeholder="Enter email addresses" value="">
                        </div>
                        <!-- <input type="hidden" id="invite_members" name="members" value=""/> -->
                        <input type="hidden" id="invite_group_id" name="group_id" value=""/>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-outline" onclick="closeModal('inviteMembersModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="button" onclick="submitInviteMembers()">
                    <i class="fas fa-paper-plane"></i> Send Invitations
                </button>
            </div>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-receipt"></i> Add an expense</h2>
                <button type="button" class="modal-close" onclick="closeModal('addExpenseModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="addExpenseForm" method="post" enctype="multipart/form-data">
                    <div class="expense-form-grid">
                        <div class="form-group">
                            <label for="expense_group">With you and:</label>
                            <select id="expense_group" name="group_id" class="form-control" required>
                                <option value="">Select a group</option>
                                <?php
                                mysqli_data_seek($groups_result, 0);
                                while ($group = mysqli_fetch_assoc($groups_result)) {
                                    echo '<option value="' . htmlspecialchars($group['group_id']) . '">' . 
                                         htmlspecialchars($group['group_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="expense_description">Description</label>
                            <input type="text" id="expense_description" name="description" class="form-control" 
                                   placeholder="Enter expense description" required>
                        </div>

                        <div class="form-group">
                            <label for="expense_amount">Amount</label>
                            <div class="amount-input-group">
                                <span class="currency-symbol"><?php echo $currency_symbol; ?></span>
                                <input type="number" id="expense_amount" name="amount" class="form-control" 
                                       placeholder="0.00" step="0.01" min="0.01" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="paid_by">Paid by</label>
                            <select id="paid_by" name="paid_by" class="form-control" required>
                                <option value="<?php echo $_SESSION['user_id']; ?>">You</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Split Method</label>
                            <div class="split-method-buttons">
                                <button type="button" class="split-method-btn active" data-method="equal">
                                    <i class="fas fa-equals"></i> Equal
                                </button>
                                <button type="button" class="split-method-btn" data-method="exact">
                                    <i class="fas fa-calculator"></i> Exact
                                </button>
                                <button type="button" class="split-method-btn" data-method="percentage">
                                    <i class="fas fa-percent"></i> Percentages
                                </button>
                            </div>
                            <input type="hidden" name="split_method" id="split_method" value="equal">
                        </div>

                        <div class="form-group">
                            <label>Split Details</label>
                            <div id="splitDetailsContainer" class="split-details">
                                <!-- Split details will be populated dynamically -->
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="expense_date">Date</label>
                            <input type="date" id="expense_date" name="date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="expense_image">Add Receipt/Image (optional)</label>
                            <input type="file" id="expense_image" name="receipt_image" class="form-control" 
                                   accept="image/*">
                        </div>

                        <div class="form-group">
                            <label for="expense_notes">Notes (optional)</label>
                            <textarea id="expense_notes" name="notes" class="form-control" 
                                    placeholder="Add notes about this expense"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button-outline" onclick="closeModal('addExpenseModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="button" id="saveExpenseBtn" onclick="submitExpense()">
                    <i class="fas fa-check"></i> Save Expense
                </button>
            </div>
        </div>
    </div>

    <!-- Expense Details Modal -->
    <div id="expenseDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-receipt"></i> Expense Details</h3>
                <span class="close" onclick="closeModal('expenseDetailsModal')">&times;</span>
            </div>
            <div class="modal-body" id="expenseDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header" style="margin-top: 60px; margin-bottom: 40px;">
            <h1 class="page-title">Expense Splitter Dashboard</h1>
            <div class="user-profile">
                <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile Picture" class="user-avatar">
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <script>showToast('<?php echo addslashes($success_message); ?>', 'success');</script>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <script>showToast('<?php echo addslashes($error_message); ?>', 'error');</script>
        <?php endif; ?>

        <?php if (!$is_premium): ?>
            <!-- Premium Feature Lock -->
            <div class="premium-feature">
                <div class="premium-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h2 class="premium-title">Premium Feature Unlock</h2>
                <p class="premium-description">
                    The Expense Splitter helps you easily manage shared expenses with friends, family, or roommates. 
                    Track who owes what, split bills fairly, and settle up with just a few clicks.
                </p>
                <a href="upgrade.php" class="premium-button">
                    <i class="fas fa-gem"></i> Upgrade to Premium
                </a>
            </div>
        <?php else: ?>
            <!-- Content Sections -->
            <div id="my-groups" class="section active">
                <div class="section-title">
                    <i class="fas fa-users"></i> My Groups
                    <button class="button" onclick="openModal('createGroupModal')">
                        <i class="fas fa-plus"></i> New Group
                    </button>
                </div>
                
                <?php if (mysqli_num_rows($groups_result) > 0): ?>
                    <div class="groups-list">
                        <?php 
                        mysqli_data_seek($groups_result, 0);
                        while ($group = mysqli_fetch_assoc($groups_result)): 
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
                            <div class="group-list-item" onclick="showGroupDetails(<?php echo $group['group_id']; ?>)">
                                <div class="group-list-content">
                                    <div class="group-list-header">
                                        <h3><i class="fas fa-users-between-lines"></i> <?php echo htmlspecialchars($group['group_name']); ?></h3>
                                        <span class="member-count"><i class="fas fa-users"></i> <?php echo $member_count; ?> members</span>
                                    </div>
                                    <div class="group-list-meta">
                                        <span><i class="fas fa-receipt"></i> <?php echo $expense_data['count']; ?> expenses</span>
                                        <span><i class="fas fa-money-bill-wave"></i> Total: <?php echo $currency_symbol; ?><?php echo number_format($expense_data['total'] ?? 0, 2); ?></span>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="no-groups">
                        <i class="fas fa-users-slash"></i>
                        <p>You haven't joined any expense groups yet.</p>
                        <button class="button" onclick="openModal('createGroupModal')">
                            <i class="fas fa-plus"></i> Create Your First Group
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <div id="group-details" class="section">
                <div class="section-title">
                    <button class="button button-outline" onclick="showMyGroups()">
                        <i class="fas fa-arrow-left"></i> My Groups
                    </button>
                    <h2 id="group-details-title">Group Details</h2>
                </div>
                <div id="group-details-content">
                    <!-- Group details will be loaded here -->
                </div>
            </div>

            <div id="pending-expenses" class="section">
                <div class="section-title">
                    <i class="fas fa-clock"></i> Pending Expenses
                    <div class="pending-total">
                        <span class="pending-amount">
                            <i class="fas fa-exclamation-circle"></i>
                            Total Pending: <?php echo $currency_symbol; ?><?php echo number_format($total_pending, 2); ?>
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
                                        <?php echo $currency_symbol; ?><?php echo number_format($expense['share_amount'], 2); ?>
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
                                        <div class="meta-item">
                                            <i class="fas fa-info-circle"></i>
                                            Total expense: <?php echo $currency_symbol; ?><?php echo number_format($expense['amount'], 2); ?>
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
                        <p>All your expenses are settled. No pending payments!</p>
                        <small>When you're added to new expenses, they'll appear here.</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

<!-- Add Currency Converter Section -->
<div id="currency-converter" class="section">
    <div class="section-title">
        <i class="fas fa-exchange-alt"></i> Currency Converter
    </div>
    <div class="converter-container">
        <div class="converter-form">
            <div class="currency-input-group">
                <input type="number" id="amount" class="form-control" placeholder="Enter amount" min="0" step="0.01" required>
                <select id="fromCurrency" class="form-control currency-select">
                    <option value="INR">🇮🇳 INR - Indian Rupee</option>
                    <option value="USD" selected>🇺🇸 USD - US Dollar</option>
                    <option value="EUR">🇪🇺 EUR - Euro</option>
                    <option value="GBP">🇬🇧 GBP - British Pound</option>
                    <option value="JPY">🇯🇵 JPY - Japanese Yen</option>
                    <option value="AUD">🇦🇺 AUD - Australian Dollar</option>
                    <option value="CAD">🇨🇦 CAD - Canadian Dollar</option>
                    <option value="CHF">🇨🇭 CHF - Swiss Franc</option>
                    <option value="CNY">🇨🇳 CNY - Chinese Yuan</option>
                </select>
            </div>
            <div class="currency-equals">
                <span class="equals-text">equals</span>
                <i class="fas fa-equals"></i>
            </div>
            <div class="currency-input-group">
                <input type="text" id="convertedAmount" class="form-control" readonly>
                <select id="toCurrency" class="form-control currency-select">
                    <option value="INR" selected>🇮🇳 INR - Indian Rupee</option>
                    <option value="USD">🇺🇸 USD - US Dollar</option>
                    <option value="EUR">🇪🇺 EUR - Euro</option>
                    <option value="GBP">🇬🇧 GBP - British Pound</option>
                    <option value="JPY">🇯🇵 JPY - Japanese Yen</option>
                    <option value="AUD">🇦🇺 AUD - Australian Dollar</option>
                    <option value="CAD">🇨🇦 CAD - Canadian Dollar</option>
                    <option value="CHF">🇨🇭 CHF - Swiss Franc</option>
                    <option value="CNY">🇨🇳 CNY - Chinese Yuan</option>
                </select>
            </div>
            <div class="conversion-info" id="conversionInfo">
                <div class="update-time">
                    <i class="far fa-clock"></i>
                    Last updated: <span id="lastUpdate"></span>
                </div>
                <div class="exchange-rate" id="exchangeRate"></div>
            </div>
        </div>
    </div>
</div>
        <!-- Add Settings Section -->
        <div id="settings" class="section">
            <div class="section-title">
                <i class="fas fa-cog"></i> Settings
            </div>
            <div class="settings-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php 
                $userCurrency = $current_currency['currency_code'] ?? 'INR';
                ?>
                
                <div class="settings-form">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="defaultCurrency">
                                <i class="fas fa-globe"></i> Default Currency
                            </label>
                            <select id="defaultCurrency" name="defaultCurrency" class="form-control currency-select">
                                <option value="INR" data-symbol="₹" <?php echo ($userCurrency == 'INR') ? 'selected' : ''; ?>>🇮🇳 INR - Indian Rupee</option>
                                <option value="USD" data-symbol="$" <?php echo ($userCurrency == 'USD') ? 'selected' : ''; ?>>🇺🇸 USD - US Dollar</option>
                                <option value="EUR" data-symbol="€" <?php echo ($userCurrency == 'EUR') ? 'selected' : ''; ?>>🇪🇺 EUR - Euro</option>
                                <option value="GBP" data-symbol="£" <?php echo ($userCurrency == 'GBP') ? 'selected' : ''; ?>>🇬🇧 GBP - British Pound</option>
                                <option value="JPY" data-symbol="¥" <?php echo ($userCurrency == 'JPY') ? 'selected' : ''; ?>>🇯🇵 JPY - Japanese Yen</option>
                                <option value="AUD" data-symbol="A$" <?php echo ($userCurrency == 'AUD') ? 'selected' : ''; ?>>🇦🇺 AUD - Australian Dollar</option>
                                <option value="CAD" data-symbol="C$" <?php echo ($userCurrency == 'CAD') ? 'selected' : ''; ?>>🇨🇦 CAD - Canadian Dollar</option>
                                <option value="CHF" data-symbol="Fr" <?php echo ($userCurrency == 'CHF') ? 'selected' : ''; ?>>🇨🇭 CHF - Swiss Franc</option>
                                <option value="CNY" data-symbol="¥" <?php echo ($userCurrency == 'CNY') ? 'selected' : ''; ?>>🇨🇳 CNY - Chinese Yuan</option>
                            </select>
                            <input type="hidden" name="currencySymbol" id="currencySymbol" value="<?php echo $current_currency['currency_symbol'] ?? '₹'; ?>">
                            <small class="form-text">This currency will be used as default throughout the application.</small>
                        </div>
                        <button type="submit" name="saveSettings" class="button">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Leave Group Confirmation Modal -->
        <div id="leaveGroupModal" class="modal">
            <div class="modal-content confirmation-modal">
                <div class="confirmation-header">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Leave Group</h3>
                </div>
                <div class="confirmation-body">
                    <p>Are you sure you want to leave this group?</p>
                    <p class="confirmation-warning">This action cannot be undone.</p>
                </div>
                <div class="confirmation-footer">
                    <button class="button button-secondary" onclick="closeLeaveGroupModal()">Cancel</button>
                    <button class="button button-danger" onclick="confirmLeaveGroupAction()">Leave Group</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to open modal with animation
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            }
        }

        // Function to close modal with animation
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        }

        // Toggle split method display
        function toggleSplitMethod() {
            const splitMethod = document.getElementById('split_method').value;
            const customContainer = document.getElementById('customSplitContainer');
            
            if (splitMethod === 'custom') {
                customContainer.style.display = 'block';
                // Here you would populate the custom split inputs based on group members
                // This would typically be done when opening the modal for a specific group
            } else {
                customContainer.style.display = 'none';
            }
        }

        // Toast notification function
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            toast.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <div class="toast-message">${message}</div>
            `;
            
            container.appendChild(toast);
            
            // Remove toast after 5 seconds
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => {
                    container.removeChild(toast);
                }, 300);
            }, 5000);
        }

        // Convert PHP success/error messages to toast notifications
        <?php if (isset($success_message)): ?>
            showToast('<?php echo addslashes($success_message); ?>', 'success');
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            showToast('<?php echo addslashes($error_message); ?>', 'error');
        <?php endif; ?>

        // Function to delete a group
        function deleteGroup(groupId) {
            if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('group_id', groupId);

                fetch('delete_group.php', {
                    method: 'POST',
                body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                    showToast('Group deleted successfully', 'success');
                    closeModal('groupDetailsModal');
                    // Reload the page to update the groups list
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                    } else {
                    showToast(data.error || 'Failed to delete group', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                showToast('Failed to delete group', 'error');
            });
        }

        // Function to open group details modal
        function openGroupDetails(groupId) {
            const modalBody = document.getElementById('groupModalBody');
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading group details...</div>';
            openModal('groupDetailsModal');
            
            fetch(`get_group_details.php?group_id=${groupId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    modalBody.innerHTML = html;
                    
                    // Set the modal title with group name
                    const groupName = document.querySelector('#groupModalBody .group-name')?.textContent;
                    if (groupName) {
                        document.getElementById('groupModalTitle').textContent = groupName;
                    }
                    
                    // Add event listeners for tabs
                    document.querySelectorAll('.tab-button').forEach(button => {
                        button.addEventListener('click', function() {
                            const sectionId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                            showSection(sectionId);
                        });
                    });

                    // Add event listener for delete button
                    const deleteButton = modalBody.querySelector('.delete-group-btn');
                    if (deleteButton) {
                        deleteButton.addEventListener('click', function() {
                            deleteGroup(groupId);
                        });
                    }
                    
                    // Show the first section by default
                    showSection('expenses');
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalBody.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Failed to load group details. Please try again.</p>
                        </div>i
                    `;
                    showToast('Failed to load group details', 'error');
                });
        }

        // Function to show different sections in group details
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section-content').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected section and activate tab
            document.getElementById(`${sectionId}-section`).classList.add('active');
            document.querySelector(`.tab-button[onclick*="${sectionId}"]`).classList.add('active');
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    // Remove active class from all nav links
                    document.querySelectorAll('.nav-link').forEach(navLink => {
                        navLink.classList.remove('active');
                    });
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Scroll to the section
                    targetSection.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Highlight current section in sidebar based on scroll position
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('.section');
            let currentSection = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 100;
                if (window.pageYOffset >= sectionTop) {
                    currentSection = '#' + section.getAttribute('id');
                }
            });
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === currentSection) {
                    link.classList.add('active');
                }
            });
        });

        // Initialize - highlight the first section
        document.querySelector('.nav-link').classList.add('active');

        document.addEventListener('DOMContentLoaded', function() {
            const tagContainer = document.querySelector('.tag-input-container');
            const tagInput = document.querySelector('#tagInput');
            const hiddenInput = document.querySelector('#members');
            const tags = new Set();

            function addTag(email) {
                if (email && isValidEmail(email) && !tags.has(email)) {
                    const tag = document.createElement('span');
                    tag.className = 'tag';
                    tag.innerHTML = `
                        ${email}
                        <span class='tag-close' data-email='${email}'>&times;</span>
                    `;
                    tagContainer.insertBefore(tag, tagInput);
                    tags.add(email);
                    updateHiddenInput();
                }
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function updateHiddenInput() {
                hiddenInput.value = Array.from(tags).join(',');
            }

            tagInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const email = this.value.trim();
                    if (email) {
                        if (isValidEmail(email)) {
                            addTag(email);
                            this.value = '';
                        } else {
                            showToast('Please enter a valid email address', 'error');
                        }
                    }
                }
            });

            tagContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('tag-close')) {
                    const email = e.target.getAttribute('data-email');
                    tags.delete(email);
                    e.target.parentElement.remove();
                    updateHiddenInput();
                }
            });
        });

        // Add this to your existing JavaScript
        function initializeTagInput(containerId, inputId, hiddenInputId) {
            const tagContainer = document.querySelector(containerId);
            const tagInput = document.querySelector(inputId);
            const hiddenInput = document.querySelector(hiddenInputId);
            const tags = new Set();

            function addTag(email) {
                if (email && isValidEmail(email) && !tags.has(email)) {
                    const tag = document.createElement('span');
                    tag.className = 'tag';
                    tag.innerHTML = `
                        ${email}
                        <span class='tag-close' data-email='${email}'>&times;</span>
                    `;
                    tagContainer.insertBefore(tag, tagInput);
                    tags.add(email);
                    updateHiddenInput();
                }
            }

            function isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            }

            function updateHiddenInput() {
                hiddenInput.value = Array.from(tags).join(',');
            }

            tagInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const email = this.value.trim();
                    if (email) {
                        if (isValidEmail(email)) {
                            addTag(email);
                            this.value = '';
                        } else {
                            showToast('Please enter a valid email address', 'error');
                        }
                    }
                }
            });

            tagContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('tag-close')) {
                    const email = e.target.getAttribute('data-email');
                    tags.delete(email);
                    e.target.parentElement.remove();
                    updateHiddenInput();
                }
            });

            return {
                clearTags: function() {
                    tags.clear();
                    const existingTags = tagContainer.querySelectorAll('.tag');
                    existingTags.forEach(tag => tag.remove());
                    updateHiddenInput();
                }
            };
        }

        // Initialize both tag inputs
        document.addEventListener('DOMContentLoaded', function() {
            // For create group modal
            const createGroupTags = initializeTagInput('.tag-input-container', '#tagInput', '#members');
            
            // For invite members modal
            const inviteMembersTags = initializeTagInput('#inviteTagContainer', '#inviteTagInput', '#invite_members');

            // Handle invite members form submission
            document.getElementById('inviteMembersForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('invite_members.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        closeModal('inviteMembersModal');
                        inviteMembersTags.clearTags();
                    } else {
                        showToast(data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to send invitations', 'error');
                });
            });
        });

        // Update section visibility based on navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href').substring(1);
                    
                    // Hide all sections
                    document.querySelectorAll('.section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show target section
                    document.getElementById(targetId).classList.add('active');
                    
                    // Update active state in navigation
                    document.querySelectorAll('.nav-link').forEach(navLink => {
                        navLink.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // Show balance overview by default
        document.getElementById('balance-overview')?.classList.add('active');

        function showGroupDetails(groupId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show group details section
            document.getElementById('group-details').classList.add('active');
            
            // Update active state in navigation
            document.querySelectorAll('.nav-link').forEach(navLink => {
                navLink.classList.remove('active');
            });
            
            // Load group details
            const detailsContent = document.getElementById('group-details-content');
            detailsContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading group details...</div>';
            
            fetch(`get_group_details.php?group_id=${groupId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    detailsContent.innerHTML = html;
                    
                    // Set the group name in the title
                    const groupName = document.querySelector('#group-details-content .group-name')?.textContent;
                    if (groupName) {
                        document.getElementById('group-details-title').textContent = groupName;
                    }
                    
                    // Add event listeners for tabs
                    document.querySelectorAll('.tab-button').forEach(button => {
                        button.addEventListener('click', function() {
                            const sectionId = this.getAttribute('onclick').match(/'([^']+)'/)[1];
                            showSection(sectionId);
                        });
                    });

                    // Add event listener for delete button
                    const deleteButton = detailsContent.querySelector('.delete-group-btn');
                    if (deleteButton) {
                        deleteButton.addEventListener('click', function() {
                            deleteGroup(groupId);
                        });
                    }
                    
                    // Show the first section by default
                    showSection('expenses');
                })
                .catch(error => {
                    console.error('Error:', error);
                    detailsContent.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Failed to load group details. Please try again.</p>
                        </div>
                    `;
                    showToast('Failed to load group details', 'error');
                });
        }

        function showMyGroups() {
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show my groups section
            document.getElementById('my-groups').classList.add('active');
            
            // Update active state in navigation
            document.querySelectorAll('.nav-link').forEach(navLink => {
                if (navLink.getAttribute('href') === '#my-groups') {
                    navLink.classList.add('active');
                } else {
                    navLink.classList.remove('active');
                }
            });
        }

        // Function to handle invite members button click
        function handleInviteMembers(groupId) {
            document.getElementById('invite_group_id').value = groupId;
            openModal('inviteMembersModal');
        }

        let currentGroupId = null;

        function confirmLeaveGroup(groupId) {
            currentGroupId = groupId;
            openModal('leaveGroupModal');
        }

        function closeLeaveGroupModal() {
            closeModal('leaveGroupModal');
            currentGroupId = null;
        }

        function confirmLeaveGroupAction() {
            if (!currentGroupId) return;

            fetch('leave_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${currentGroupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Successfully left the group', 'success');
                    closeLeaveGroupModal();
                    // Refresh the page to update the groups list
                    location.reload();
                } else {
                    showToast(data.error || 'Failed to leave group', 'error');
                    closeLeaveGroupModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to leave group', 'error');
                closeLeaveGroupModal();
            });
        }

        // Function to submit invite members form
        function submitInviteMembers() {
            const form = document.getElementById('inviteMembersForm');
            const formData = new FormData(form);

            fetch('invite_members.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('inviteMembersModal');
                    // Clear the form
                    form.reset();
                    const container = document.getElementById('inviteTagContainer');
                    const existingTags = container.querySelectorAll('.tag');
                    existingTags.forEach(tag => tag.remove());
                } else {
                    showToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to send invitations', 'error');
            });
        }

        // Function to handle split method selection
        function handleSplitMethodChange(method) {
            const buttons = document.querySelectorAll('.split-method-btn');
            buttons.forEach(btn => {
                if (btn.dataset.method === method) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            document.getElementById('split_method').value = method;
            updateSplitDetails();
        }

        // Function to update split details based on selected method
        function updateSplitDetails() {
            const container = document.getElementById('splitDetailsContainer');
            const amount = parseFloat(document.getElementById('expense_amount').value) || 0;
            const method = document.getElementById('split_method').value;
            const groupId = document.getElementById('expense_group').value;

            if (!groupId) {
                container.innerHTML = '<p>Please select a group first</p>';
                return;
            }

            // Fetch group members and update split details
            fetch(`get_group_members.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(members => {
                    let html = '';
                    const memberCount = members.length;

                    members.forEach(member => {
                        const splitAmount = method === 'equal' ? (amount / memberCount).toFixed(2) : '0.00';
                        const inputType = method === 'percentage' ? 'number' : 'text';
                        const inputValue = method === 'equal' ? (100 / memberCount).toFixed(0) : '0';
                        const placeholder = method === 'percentage' ? '%' : '$';

                        html += `
                            <div class="split-member">
                                <div class="member-avatar">
                                    ${member.username.charAt(0).toUpperCase()}
                                </div>
                                <div class="member-details">
                                    <div class="member-name">${member.username}</div>
                                    <div class="split-amount">$${splitAmount}</div>
                                </div>
                                <input type="${inputType}" 
                                       class="form-control split-input" 
                                       name="splits[${member.user_id}]"
                                       value="${inputValue}"
                                       placeholder="${placeholder}"
                                       min="0"
                                       ${method === 'equal' ? 'readonly' : ''}
                                       onchange="updateSplitAmounts(this)">
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p>Error loading group members</p>';
                });
        }

        // Function to update split amounts when values change
        function updateSplitAmounts(input) {
            const amount = parseFloat(document.getElementById('expense_amount').value) || 0;
            const method = document.getElementById('split_method').value;
            const splits = document.querySelectorAll('.split-input');
            const splitAmounts = document.querySelectorAll('.split-amount');

            if (method === 'percentage') {
                let total = 0;
                splits.forEach(split => {
                    total += parseFloat(split.value) || 0;
                });

                splits.forEach((split, index) => {
                    const percentage = parseFloat(split.value) || 0;
                    const splitAmount = (amount * (percentage / 100)).toFixed(2);
                    splitAmounts[index].textContent = `$${splitAmount}`;
                });

                if (Math.abs(total - 100) > 0.01) {
                    showToast('Percentages must add up to 100%', 'error');
                    return false;
                }
            } else if (method === 'exact') {
                let total = 0;
                splits.forEach(split => {
                    total += parseFloat(split.value) || 0;
                });

                if (Math.abs(total - amount) > 0.01) {
                    showToast('Split amounts must equal the total amount', 'error');
                    return false;
                }
            }
            return true;
        }

        // Function to submit expense
        function submitExpense() {
            const saveButton = document.getElementById('saveExpenseBtn');
            
            // Disable the button to prevent double submission
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            // Validate form
            if (!validateExpenseForm()) {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-check"></i> Save Expense';
                return;
            }

            const form = document.getElementById('addExpenseForm');
            const formData = new FormData(form);

            fetch('add_expense.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeModal('addExpenseModal');
                    // Reset form
                    form.reset();
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.error || 'Failed to add expense', 'error');
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="fas fa-check"></i> Save Expense';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to add expense', 'error');
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-check"></i> Save Expense';
            });
        }

        // Function to validate expense form
        function validateExpenseForm() {
            const amount = parseFloat(document.getElementById('expense_amount').value);
            const description = document.getElementById('expense_description').value.trim();
            const groupId = document.getElementById('expense_group').value;
            const splitMethod = document.getElementById('split_method').value;

            if (!groupId) {
                showToast('Please select a group', 'error');
                return false;
            }

            if (!description) {
                showToast('Please enter a description', 'error');
                return false;
            }

            if (!amount || amount <= 0) {
                showToast('Please enter a valid amount', 'error');
                return false;
            }

            // Validate splits based on method
            if (splitMethod === 'percentage') {
                let total = 0;
                document.querySelectorAll('.split-input').forEach(input => {
                    total += parseFloat(input.value) || 0;
                });

                if (Math.abs(total - 100) > 0.01) {
                    showToast('Percentages must add up to 100%', 'error');
                    return false;
                }
            } else if (splitMethod === 'exact') {
                let total = 0;
                document.querySelectorAll('.split-input').forEach(input => {
                    total += parseFloat(input.value) || 0;
                });

                if (Math.abs(total - amount) > 0.01) {
                    showToast('Split amounts must equal the total amount', 'error');
                    return false;
                }
            }

            return true;
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for split method buttons
            document.querySelectorAll('.split-method-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent form submission
                    handleSplitMethodChange(this.dataset.method);
                });
            });

            // Add event listener for group selection
            document.getElementById('expense_group').addEventListener('change', function() {
                updateSplitDetails();
                // Update paid by dropdown
                const groupId = this.value;
                if (groupId) {
                    fetch(`get_group_members.php?group_id=${groupId}`)
                        .then(response => response.json())
                        .then(members => {
                            const paidBySelect = document.getElementById('paid_by');
                            paidBySelect.innerHTML = `<option value="${'<?php echo $_SESSION['user_id']; ?>'}">You</option>`;
                            members.forEach(member => {
                                if (member.user_id !== <?php echo $_SESSION['user_id']; ?>) {
                                    paidBySelect.innerHTML += `
                                        <option value="${member.user_id}">${member.username}</option>
                                    `;
                                }
                            });
                        });
                }
            });

            // Add event listener for amount changes
            document.getElementById('expense_amount').addEventListener('input', function() {
                updateSplitDetails();
            });

            // Add event listeners for split inputs
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('split-input')) {
                    updateSplitAmounts(e.target);
                }
            });

            // Add event listener for form submission
            document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission
                submitExpense();
            });
        });

        function showExpenseDetails(expenseId) {
            fetch(`get_expense_details.php?expense_id=${expenseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const expense = data.expense;
                        const currencySymbol = data.currency_symbol;
                        const content = `
                            <div class="expense-details">
                                <div class="expense-details-header">
                                    <div>
                                        <h3>${expense.description}</h3>
                                        <p class="text-secondary">Added on ${expense.date_added}</p>
                                    </div>
                                    <div class="expense-amount">${currencySymbol}${parseFloat(expense.amount).toFixed(2)}</div>
                                </div>
                                
                                <div class="expense-details-meta">
                                    <div class="expense-details-meta-item">
                                        <label>Paid By</label>
                                        <span>${expense.paid_by_user}</span>
                                    </div>
                                    <div class="expense-details-meta-item">
                                        <label>Group</label>
                                        <span>${expense.group_name}</span>
                                    </div>
                                    ${expense.notes ? `
                                    <div class="expense-details-meta-item">
                                        <label>Notes</label>
                                        <span>${expense.notes}</span>
                                    </div>
                                    ` : ''}
                                </div>

                                ${expense.receipt_image ? `
                                <div class="expense-receipt">
                                    <label>Receipt/Photos</label>
                                    <img src="${expense.receipt_image}" alt="Expense Receipt" onclick="window.open(this.src)">
                                </div>
                                ` : ''}

                                <h4>Shares</h4>
                                <div class="shares-list">
                                    ${expense.shares.map(share => `
                                        <div class="share-item">
                                            <div class="share-item-left">
                                                <i class="fas fa-user"></i>
                                                ${share.username}
                                            </div>
                                            <div class="share-item-right">
                                                <div>${currencySymbol}${parseFloat(share.share_amount).toFixed(2)}</div>
                                                <div class="share-status ${share.status === 'settled' ? 'settled' : 'pending'}">
                                                    <i class="fas ${share.status === 'settled' ? 'fa-check-circle' : 'fa-clock'}"></i>
                                                    ${share.status === 'settled' ? 'Settled' : 'Pending'}
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                        document.getElementById('expenseDetailsContent').innerHTML = content;
                        openModal('expenseDetailsModal');
                    } else {
                        showToast(data.error || 'Failed to load expense details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load expense details', 'error');
                });
        }

        function settleExpense(expenseId, userId) {
            if (!confirm('Are you sure you want to mark this expense as settled?')) {
                return;
            }

            fetch('settle_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `expense_id=${expenseId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Expense settled successfully', 'success');
                    closeModal('expenseDetailsModal');
                    // Refresh the page to update the expense list
                    location.reload();
                } else {
                    showToast(data.error || 'Failed to settle expense', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to settle expense', 'error');
            });
        }
// Currency Converter Functions
let exchangeRates = {};
let lastUpdated = '';

// Fetch exchange rates and initialize converter
async function initCurrencyConverter() {
    await fetchExchangeRates();
    setupConversionListeners();
    convertCurrency(); // Initial conversion if there's a default amount
}

// Fetch exchange rates from API
async function fetchExchangeRates() {
    try {
        // Using a free API (you might want to use a more reliable API in production)
        const response = await fetch('https://api.exchangerate-api.com/v4/latest/USD');
        const data = await response.json();
        exchangeRates = data.rates;
        lastUpdated = new Date(data.date).toLocaleString();
        document.getElementById('lastUpdate').textContent = lastUpdated;
        return true;
    } catch (error) {
        console.error('Error fetching exchange rates:', error);
        // Fallback rates in case API fails
        exchangeRates = {
            USD: 1,
            INR: 83.0,  // Updated rate
            EUR: 0.93,  // Updated rate
            GBP: 0.79,  // Updated rate
            JPY: 151.0, // Updated rate
            AUD: 1.52,  // Updated rate
            CAD: 1.37,  // Updated rate
            CHF: 0.91,  // Updated rate
            CNY: 7.24   // Updated rate
        };
        lastUpdated = new Date().toLocaleString();
        document.getElementById('lastUpdate').textContent = lastUpdated + ' (offline rates)';
        return false;
    }
}

// Set up event listeners for real-time conversion
function setupConversionListeners() {
    const amountInput = document.getElementById('amount');
    const fromCurrency = document.getElementById('fromCurrency');
    const toCurrency = document.getElementById('toCurrency');

    // Real-time conversion on input
    amountInput.addEventListener('input', convertCurrency);
    
    // Also convert when currencies change
    fromCurrency.addEventListener('change', convertCurrency);
    toCurrency.addEventListener('change', convertCurrency);
    
    // Convert when any of these events occur
    amountInput.addEventListener('change', convertCurrency);
    amountInput.addEventListener('keyup', convertCurrency);
}

// Perform the actual conversion
function convertCurrency() {
    const amount = parseFloat(document.getElementById('amount').value);
    const fromCurrency = document.getElementById('fromCurrency').value;
    const toCurrency = document.getElementById('toCurrency').value;
    const convertedAmountInput = document.getElementById('convertedAmount');
    const exchangeRateDiv = document.getElementById('exchangeRate');

    if (!amount || isNaN(amount) || amount <= 0) {
        convertedAmountInput.value = '';
        exchangeRateDiv.textContent = '';
        return;
    }

    // Calculate conversion
    const fromRate = exchangeRates[fromCurrency] || 1;
    const toRate = exchangeRates[toCurrency] || 1;
    const convertedAmount = (amount / fromRate * toRate).toFixed(2);
    
    // Get currency symbol
    const symbol = document.querySelector(`#toCurrency option[value="${toCurrency}"]`)?.textContent.match(/[^\w\s]/)?.[0] || toCurrency;
    
    // Update the UI
    convertedAmountInput.value = `${symbol}${convertedAmount}`;
    exchangeRateDiv.innerHTML = `<i class="fas fa-chart-line"></i> 1 ${fromCurrency} = ${symbol}${(toRate / fromRate).toFixed(4)}`;
}

// Initialize currency converter when DOM is loaded
document.addEventListener('DOMContentLoaded', initCurrencyConverter);
    </script>
</body>
</html>