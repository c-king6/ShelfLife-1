<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Content safety check ---
// Simple heuristic: the recipe must contain at least 3 food-related words
// somewhere across the name + ingredients. Rejects obvious spam / non-food posts.
function is_food_recipe($name, $ingredients) {
    $keywords = [
        'chicken','pork','beef','fish','shrimp','squid','crab','egg','rice','pasta','noodle','bread',
        'tomato','onion','garlic','ginger','potato','carrot','cabbage','pepper','chili','spinach','kale',
        'salt','oil','vinegar','soy','sugar','water','milk','cheese','butter','flour','cream',
        'banana','apple','mango','lemon','lime','coconut','peanut','nuts',
        'beans','lentils','mushroom','corn','cucumber','eggplant','okra','pumpkin','squash','broccoli',
        'lettuce','celery','radish','bell pepper','bay leaf','ginger','lemongrass',
        'sauce','ketchup','mayo','mustard','honey','syrup','stock','broth'
    ];
    $text = strtolower($name . ' ' . $ingredients);
    $hits = 0;
    foreach ($keywords as $kw) {
        if (strpos($text, $kw) !== false) $hits++;
        if ($hits >= 3) return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recipes.php");
    exit();
}

$recipe_id   = (int)($_POST['recipe_id'] ?? 0);
$name        = trim($_POST['recipe_name'] ?? '');
$ingredients = trim($_POST['ingredients'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$is_public   = isset($_POST['is_public']) ? 1 : 0;

if ($name === '' || $ingredients === '') {
    header("Location: recipes.php?tab=mine&msg=missing");
    exit();
}

if (!is_food_recipe($name, $ingredients)) {
    header("Location: recipes.php?tab=mine&msg=not_food");
    exit();
}

$name_esc    = mysqli_real_escape_string($conn, $name);
$ing_esc     = mysqli_real_escape_string($conn, $ingredients);
$instr_esc   = mysqli_real_escape_string($conn, $instructions);

if ($recipe_id > 0) {
    // EDIT — only the owner can edit
    $check = mysqli_query($conn, "SELECT user_id FROM recipes WHERE id = $recipe_id");
    $row   = mysqli_fetch_assoc($check);
    if (!$row || (int)$row['user_id'] !== (int)$user_id) {
        header("Location: recipes.php?tab=mine");
        exit();
    }
    mysqli_query($conn, "UPDATE recipes
                         SET recipe_name='$name_esc',
                             ingredients='$ing_esc',
                             instructions='$instr_esc',
                             is_public=$is_public
                         WHERE id=$recipe_id AND user_id='$user_id'");
    header("Location: recipes.php?tab=mine&msg=updated");
    exit();
}

// NEW — insert with user_id, is_public, is_approved (auto-publish)
mysqli_query($conn, "INSERT INTO recipes (recipe_name, ingredients, instructions, user_id, is_public, is_approved)
                     VALUES ('$name_esc', '$ing_esc', '$instr_esc', '$user_id', $is_public, 1)");
header("Location: recipes.php?tab=mine&msg=added");
exit();
?>