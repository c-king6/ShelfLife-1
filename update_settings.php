<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$email_notify = (int)($_POST['email_notify'] ?? 1);
$notify_days  = (int)($_POST['notify_days']  ?? 3);

// Sanity checks
$email_notify = $email_notify ? 1 : 0;
if ($notify_days < 1)  $notify_days = 1;
if ($notify_days > 30) $notify_days = 30;

$sql = "UPDATE users SET email_notify = $email_notify, notify_days = $notify_days
        WHERE id = '$user_id'";

if (mysqli_query($conn, $sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
?>