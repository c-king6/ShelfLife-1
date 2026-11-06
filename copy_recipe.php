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

$orig = mysqli_query($conn, "SELECT recipe_name, ingredients, instructions FROM recipes WHERE id=$recipe_id");
$row  = mysqli_fetch_assoc($orig);

if (!$row) {
    echo json_encode(['success' => false]);
    exit();
}

$name  = mysqli_real_escape_string($conn, $row['recipe_name'] . ' (my copy)');
$ing   = mysqli_real_escape_string($conn, $row['ingredients']);
$instr = mysqli_real_escape_string($conn, $row['instructions']);

mysqli_query($conn, "INSERT INTO recipes (recipe_name, ingredients, instructions, user_id, is_public, is_approved)
                     VALUES ('$name', '$ing', '$instr', '$user_id', 0, 1)");

echo json_encode(['success' => true]);
?>