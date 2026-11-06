<?php
// FIXED: this file previously had no session/login check at all, and no
// ownership check on the delete — anyone with the URL could delete any
// user's item just by changing the id. Both are fixed below.
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    // FIXED: cast to int instead of dropping the raw GET value straight
    // into the query — closes the SQL injection hole.
    $id = (int)$_GET['id'];

    // FIXED: added "AND user_id = '$user_id'" so this can only ever
    // delete an item that belongs to the logged-in user. If someone
    // tries to delete another user's item id, this simply matches
    // zero rows and nothing happens.
    $sql = "DELETE FROM inventory WHERE id = $id AND user_id = '$user_id'";

    if (mysqli_query($conn, $sql)) {
        // After deleting, send the user back to the dashboard
        header("Location: dashboard.php?msg=Item Removed");
    } else {
        echo "Error deleting record: " . mysqli_error($conn);
    }
}
?>