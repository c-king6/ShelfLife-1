<?php
/**
 * stream_messages.php — Server-Sent Events endpoint for live chat.
 *
 * Flow:
 *   - Client opens EventSource('stream_messages.php?with=<id>&after=<lastId>')
 *   - We hold the connection open for up to 60 seconds
 *   - We poll the DB every 500ms and push any new messages instantly
 *   - Before closing, we send 'event: reconnect' so the client reopens
 *     with a fresh `after` value (which keeps us from missing anything)
 *
 * IMPORTANT: session_write_close() is called early so that the user's
 * own send_message.php request isn't blocked by our held session lock.
 */

require_once "session_config.php";
include "db_connect.php";

// --- SSE response headers ---
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');   // disable nginx proxy buffering

// Try to disable Apache gzip/deflate for this stream
@apache_setenv('no-gzip', '1');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
@ob_implicit_flush(true);

// --- Auth ---
if (!isset($_SESSION['user_id'])) {
    echo "event: error\n";
    echo "data: not_logged_in\n\n";
    exit();
}

$user_id  = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$with_id  = (int)($_GET['with'] ?? 0);
$after_id = (int)($_GET['after'] ?? 0);

// --- Resolve the two sides of the conversation ---
$my_id = $user_id;
if ($is_admin) {
    // Admin chats with the user they opened from the inbox
    $other_id = $with_id;
} else {
    // User always chats with the first admin account
    $support_res = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1");
    $support_row = mysqli_fetch_assoc($support_res);
    $support_id  = $support_row ? (int)$support_row['id'] : 1;
    $other_id    = $support_id;
}

if ($other_id <= 0) {
    echo "event: error\n";
    echo "data: invalid_target\n\n";
    exit();
}

// CRITICAL: release the session lock so send_message.php can run in parallel.
session_write_close();

// --- Loop for up to 60 seconds ---
@set_time_limit(90);
@ignore_user_abort(false);

$start          = time();
$max_runtime    = 60;
$last_heartbeat = time();

while ((time() - $start) < $max_runtime) {
    if (connection_aborted()) break;

    $q = mysqli_query($conn, "
        SELECT id, sender_id, receiver_id, message_text, attachment_path, is_read, is_auto_reply, timestamp
        FROM messages
        WHERE ((sender_id = '$my_id' AND receiver_id = '$other_id')
            OR (sender_id = '$other_id' AND receiver_id = '$my_id'))
          AND id > $after_id
        ORDER BY id ASC
    ");

    if ($q && mysqli_num_rows($q) > 0) {
        while ($m = mysqli_fetch_assoc($q)) {
            $payload = [
                'id'          => (int)$m['id'],
                'sender_id'   => (int)$m['sender_id'],
                'receiver_id' => (int)$m['receiver_id'],
                'text'        => $m['message_text'],
                'attachment'  => $m['attachment_path'],
                'is_read'     => (int)$m['is_read'],
                'is_auto'     => (int)$m['is_auto_reply'],
                'timestamp'   => $m['timestamp'],
            ];
            echo "data: " . json_encode($payload) . "\n\n";
            $after_id = (int)$m['id'];
        }
        // Mark the other side's messages as read
        mysqli_query($conn, "
            UPDATE messages SET is_read = 1
            WHERE sender_id = '$other_id' AND receiver_id = '$my_id' AND is_read = 0
        ");
        flush();
    }

    // Heartbeat every 15 seconds so the client knows we're alive
    if ((time() - $last_heartbeat) >= 15) {
        echo ": ping\n\n";
        $last_heartbeat = time();
        flush();
    }

    // 500ms between checks — feels instant, keeps DB load reasonable
    usleep(500000);
}

// Tell the client to reopen with the current after_id
echo "event: reconnect\n";
echo "data: {}\n\n";
flush();