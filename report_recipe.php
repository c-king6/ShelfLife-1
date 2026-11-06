<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$recipe_id = (int)($_POST['recipe_id'] ?? 0);
$reason    = trim($_POST['reason'] ?? '');
$details   = trim($_POST['details'] ?? '');

$allowed_reasons = ['not_food', 'inappropriate', 'spam', 'copyright', 'other'];
if (!in_array($reason, $allowed_reasons, true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid reason']);
    exit();
}

// Fetch the recipe to ensure it exists and is not the user's own
$r = mysqli_query($conn, "SELECT user_id, report_count, is_hidden FROM recipes WHERE id=$recipe_id");
$recipe = mysqli_fetch_assoc($r);

if (!$recipe) {
    echo json_encode(['success' => false, 'error' => 'Recipe not found']);
    exit();
}

if ((int)$recipe['user_id'] === (int)$user_id) {
    echo json_encode(['success' => false, 'error' => 'You cannot report your own recipe']);
    exit();
}

// Already reported?
$check = mysqli_query($conn, "SELECT id FROM recipe_reports WHERE recipe_id=$recipe_id AND reporter_id=$user_id");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'error' => 'You already reported this recipe']);
    exit();
}

$reason_esc  = mysqli_real_escape_string($conn, $reason);
$details_esc = mysqli_real_escape_string($conn, $details);

mysqli_query($conn, "INSERT INTO recipe_reports (recipe_id, reporter_id, reason, details)
                     VALUES ($recipe_id, $user_id, '$reason_esc', '$details_esc')");

// Increment report count, and auto-hide at 3 or more reports
$new_count = (int)$recipe['report_count'] + 1;
$hide_sql = ($new_count >= 3) ? ", is_hidden = 1" : "";
mysqli_query($conn, "UPDATE recipes SET report_count = $new_count $hide_sql WHERE id=$recipe_id");

echo json_encode([
    'success'    => true,
    'new_count'  => $new_count,
    'auto_hidden' => $new_count >= 3
]);
?>