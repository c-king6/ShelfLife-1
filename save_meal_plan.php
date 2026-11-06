<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id   = $_SESSION['user_id'];
$plan_date = $_POST['plan_date'] ?? '';
$slot      = $_POST['slot'] ?? '';
$recipe_id = (int)($_POST['recipe_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $plan_date)) {
    echo json_encode(['success' => false, 'error' => 'Invalid date']);
    exit();
}

if (!in_array($slot, ['lunch', 'dinner'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid slot']);
    exit();
}

$date_esc = mysqli_real_escape_string($conn, $plan_date);

if ($recipe_id === 0) {
    mysqli_query($conn, "DELETE FROM meal_plan
                         WHERE user_id='$user_id' AND plan_date='$date_esc' AND meal_slot='$slot'");
    echo json_encode(['success' => true]);
    exit();
}

// Ensure recipe exists and user can access it
$check = mysqli_query($conn, "SELECT id FROM recipes
                              WHERE id=$recipe_id AND is_approved=1
                              AND (is_public=1 OR user_id='$user_id' OR user_id IS NULL)");
if (mysqli_num_rows($check) === 0) {
    echo json_encode(['success' => false, 'error' => 'Recipe not available']);
    exit();
}

mysqli_query($conn, "INSERT INTO meal_plan (user_id, plan_date, meal_slot, recipe_id)
                     VALUES ('$user_id', '$date_esc', '$slot', $recipe_id)
                     ON DUPLICATE KEY UPDATE recipe_id = $recipe_id");

echo json_encode(['success' => true]);
?>