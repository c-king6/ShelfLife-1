<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

$current = $_POST['current_password'] ?? '';
$new     = $_POST['new_password'] ?? '';

if ($current === '' || $new === '') {
    echo json_encode(['success' => false, 'error' => 'Please fill in both fields']);
    exit();
}

if (strlen($new) < 6) {
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
    exit();
}

// Fetch the current hash
$q = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
$user = mysqli_fetch_assoc($q);

if (!$user || !password_verify($current, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit();
}

// Save the new password
$hash = password_hash($new, PASSWORD_DEFAULT);
mysqli_query($conn, "UPDATE users SET password = '$hash' WHERE id = '$user_id'");

// Log the change as a security event (admin can see it)
include "log_helper.php";
log_admin_action($conn, $user_id, 'user.password_change', 'user', $user_id, 'Changed own password');

echo json_encode(['success' => true]);
?>