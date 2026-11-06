<?php
/**
 * upload_chat_image.php — Handles image attachments in the support chat.
 *
 * Accepts multipart POST with:
 *   - image       (file, required)
 *   - receiver_id (int, required)
 *   - caption     (string, optional)
 *
 * Validates size (5MB), extension, and actual image MIME type.
 * Saves to uploads/chat/ with a randomized filename.
 * Inserts a message row with the caption as message_text and the path
 * in attachment_path. Triggers the auto-responder exactly like a
 * text-only message would.
 */

require_once "session_config.php";
include "db_connect.php";
require_once "auto_responder.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit();
}

$sender_id    = (int)$_SESSION['user_id'];
$is_admin     = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$receiver_id  = (int)($_POST['receiver_id'] ?? 0);
$caption      = trim($_POST['caption'] ?? '');

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Missing recipient']);
    exit();
}

// Verify recipient exists
$rcpt = mysqli_query($conn, "SELECT id FROM users WHERE id = $receiver_id");
if (!$rcpt || mysqli_num_rows($rcpt) === 0) {
    echo json_encode(['success' => false, 'error' => 'Recipient not found']);
    exit();
}

// --- File presence ---
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No image uploaded']);
    exit();
}
$file = $_FILES['image'];

// --- Size: 5MB ---
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image must be under 5MB']);
    exit();
}
if ($file['size'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'Empty file']);
    exit();
}

// --- Extension whitelist ---
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
    echo json_encode(['success' => false, 'error' => 'Only JPG, PNG, and GIF are allowed']);
    exit();
}

// --- Real image check (not just the extension) ---
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    echo json_encode(['success' => false, 'error' => 'File is not a valid image']);
    exit();
}
$allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($info['mime'], $allowed_mime, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid image type']);
    exit();
}

// --- Ensure upload dir exists ---
$dir = __DIR__ . '/uploads/chat';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create upload folder']);
        exit();
    }
}

// --- Randomized filename (never trust the client-supplied name) ---
$new_name = 'chat_' . $sender_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$target   = $dir . '/' . $new_name;

if (!move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['success' => false, 'error' => 'Upload failed']);
    exit();
}

$rel_path = 'uploads/chat/' . $new_name;

// --- Insert the message ---
$caption_esc = mysqli_real_escape_string($conn, $caption);
$path_esc    = mysqli_real_escape_string($conn, $rel_path);

$ok = mysqli_query($conn,
    "INSERT INTO messages (sender_id, receiver_id, message_text, attachment_path)
     VALUES ('$sender_id', '$receiver_id', '$caption_esc', '$path_esc')"
);

if (!$ok) {
    @unlink($target); // roll back the file if the insert failed
    echo json_encode(['success' => false, 'error' => 'Could not save message']);
    exit();
}

$new_id = (int)mysqli_insert_id($conn);

// --- Run the auto-responder only when a USER is messaging an admin ---
if (!$is_admin) {
    // text passed to responder: the caption if any, else a placeholder
    $responder_input = $caption !== '' ? $caption : '[image sent]';
    process_auto_reply($conn, $sender_id, $responder_input);
}

echo json_encode([
    'success' => true,
    'message' => [
        'id'          => $new_id,
        'sender_id'   => $sender_id,
        'receiver_id' => $receiver_id,
        'text'        => $caption,
        'attachment'  => $rel_path,
        'is_auto'     => 0,
        'timestamp'   => date('Y-m-d H:i:s'),
    ],
]);