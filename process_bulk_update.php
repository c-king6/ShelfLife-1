<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_name'])) {
    // 1. Delete the old grouped entries first (but only for THIS user)
    if (isset($_POST['old_ids'])) {
        foreach ($_POST['old_ids'] as $old_id) {
            $old_id = (int)$old_id;
            mysqli_query($conn, "DELETE FROM inventory WHERE id = $old_id AND user_id = '$user_id'");
        }
    }

    // 2. Insert the new individual entries with the user_id attached
    $names = $_POST['item_name'];
    $categories = $_POST['category'];
    $dates = $_POST['expiry_date'];

    for ($i = 0; $i < count($names); $i++) {
        $name = mysqli_real_escape_string($conn, $names[$i]);
        $cat = mysqli_real_escape_string($conn, $categories[$i]);
        $expiry = !empty($dates[$i]) ? "'" . mysqli_real_escape_string($conn, $dates[$i]) . "'" : "NULL";

        // IMPORTANT: user_id = '$user_id' ensures it stays visible on your dashboard
        $query = "INSERT INTO inventory (item_name, quantity, category, expiry_date, is_bought, user_id) 
                  VALUES ('$name', 1, '$cat', $expiry, 1, '$user_id')";
        
        mysqli_query($conn, $query);
    }

    header("Location: dashboard.php?msg=updated");
    exit();
} else {
    header("Location: dashboard.php");
    exit();
}
?>