<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$recipe_id = (int)($_GET['id'] ?? 0);

if ($recipe_id > 0) {
    mysqli_query($conn, "DELETE FROM recipes WHERE id=$recipe_id AND user_id='$user_id'");
    mysqli_query($conn, "DELETE FROM recipe_saves WHERE recipe_id=$recipe_id");
    mysqli_query($conn, "DELETE FROM meal_plan WHERE recipe_id=$recipe_id");
}

header("Location: recipes.php?tab=mine");
exit();
?>