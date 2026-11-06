<?php
require_once "session_config.php";
include "db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$user_id   = $_SESSION['user_id'];
$recipe_id = (int)($_POST['recipe_id'] ?? 0);

if ($recipe_id <= 0) {
    echo json_encode(['success' => false]);
    exit();
}

// Already saved?
$check = mysqli_query($conn, "SELECT id FROM recipe_saves WHERE user_id='$user_id' AND recipe_id=$recipe_id");
if (mysqli_num_rows($check) > 0) {
    mysqli_query($conn, "DELETE FROM recipe_saves WHERE user_id='$user_id' AND recipe_id=$recipe_id");
    $saved = false;
} else {
    mysqli_query($conn, "INSERT INTO recipe_saves (user_id, recipe_id) VALUES ('$user_id', $recipe_id)");
    $saved = true;
}

$count_q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM recipe_saves WHERE recipe_id=$recipe_id");
$count = (int)mysqli_fetch_assoc($count_q)['c'];

echo json_encode(['success' => true, 'saved' => $saved, 'count' => $count]);
?>