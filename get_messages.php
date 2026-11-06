<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$with_id  = (int)($_GET['with'] ?? 0);
$after_id = (int)($_GET['after'] ?? 0);

if ($with_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing user id']);
    exit();
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Find the admin's id
$support_res = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1");
$support_row = mysqli_fetch_assoc($support_res);
$support_id  = $support_row ? (int)$support_row['id'] : 1;

if ($is_admin) {
    // Admin viewing a specific user's chat
    $target_user_id = $with_id;

    $q = mysqli_query($conn, "SELECT id, sender_id, receiver_id, message_text, attachment_path, is_read, is_auto_reply, timestamp
                              FROM messages
                              WHERE ((sender_id = '$target_user_id' AND receiver_id = '$support_id')
                                  OR (sender_id = '$support_id' AND receiver_id = '$target_user_id'))
                              AND id > $after_id
                              ORDER BY id ASC");

    // Mark user messages as read
    mysqli_query($conn, "UPDATE messages SET is_read = 1
                         WHERE sender_id = '$target_user_id' AND receiver_id = '$support_id' AND is_read = 0");
} else {
    // User chatting with support
    $q = mysqli_query($conn, "SELECT id, sender_id, receiver_id, message_text, attachment_path, is_read, is_auto_reply, timestamp                              WHERE ((sender_id = '$user_id' AND receiver_id = '$support_id')
                                  OR (sender_id = '$support_id' AND receiver_id = '$user_id'))
                              AND id > $after_id
                              ORDER BY id ASC");

    // Mark replies as read
    mysqli_query($conn, "UPDATE messages SET is_read = 1
                         WHERE sender_id = '$support_id' AND receiver_id = '$user_id' AND is_read = 0");
}

$messages = [];
if ($q) {
    while ($m = mysqli_fetch_assoc($q)) {
        $messages[] = [
            'id'          => (int)$m['id'],
            'sender_id'   => (int)$m['sender_id'],
            'receiver_id' => (int)$m['receiver_id'],
            'text'        => $m['message_text'],
            'attachment'  => $m['attachment_path'],
            'is_read'     => (int)$m['is_read'],
            'is_auto'     => (int)$m['is_auto_reply'],
            'timestamp'   => $m['timestamp'],
        ];
    }
}

echo json_encode(['success' => true, 'messages' => $messages]);
?>