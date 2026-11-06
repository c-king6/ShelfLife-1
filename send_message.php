<?php
require_once "session_config.php";
include "db_connect.php";
require_once "auto_responder.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sender_id   = (int)$_SESSION['user_id'];
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $text        = trim($_POST['message_text'] ?? '');

    if ($text === '' || $receiver_id <= 0) {
        header("Location: dashboard.php");
        exit();
    }

    $text_esc = mysqli_real_escape_string($conn, $text);

    // Save the user's message
    mysqli_query($conn, "INSERT INTO messages (sender_id, receiver_id, message_text)
                         VALUES ('$sender_id', '$receiver_id', '$text_esc')");

    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    if (!$is_admin) {
        process_auto_reply($conn, $sender_id, $text);
    }

    if ($is_admin) {
        header("Location: admin_chat.php?user_id=$receiver_id");
    } else {
        header("Location: chat.php");
    }
    exit();
}

header("Location: dashboard.php");
exit();
?>