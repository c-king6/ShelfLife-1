<?php
session_start();
include "db_connect.php";

// FIXED: this file had its own inline categorizer regex which could
// disagree with save_food.php / process_shopping.php. Now uses the
// shared function so all paths categorize identically.
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['item_ids'])) {
    foreach ($_POST['item_ids'] as $id) {
        $id = (int)$id;

        $res = mysqli_query($conn, "SELECT item_name FROM inventory WHERE id = $id AND user_id = '$user_id'");
        $item = mysqli_fetch_assoc($res);

        if (!$item) {
            continue;
        }

        // FIXED: single shared categorizer
        $cat = shelflife_categorize($item['item_name']);
        $cat_safe = mysqli_real_escape_string($conn, $cat);

        mysqli_query($conn, "UPDATE inventory SET is_bought = 1, category = '$cat_safe' WHERE id = $id AND user_id = '$user_id'");
    }
}

header("Location: dashboard.php");
exit();
?>