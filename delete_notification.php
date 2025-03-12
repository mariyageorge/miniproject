<?php
// Include database connection
include 'connect.php';
session_start();

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if notification_id is set
if (!isset($_POST['notification_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
    exit();
}

$notification_id = (int)$_POST['notification_id'];

// Delete notification
$sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("ii", $notification_id, $user_id);
$result = $stmt->execute();

if ($result) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
}

$stmt->close();
$conn->close();
?>