<?php
// FIXED: this file had no session/login check, and no ownership check —
// anyone with the form could set the expiry date / bought status on any
// user's item just by posting an id that isn't theirs. Both are fixed
// below.
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['ids'])) {
    $ids = $_POST['ids'];
    $dates = $_POST['dates'];

    for ($i = 0; $i < count($ids); $i++) {
        // FIXED: cast to int instead of using the raw POST value.
        $id = (int)$ids[$i];
        $expiry = mysqli_real_escape_string($conn, $dates[$i]);

        // FIXED: added "AND user_id = '$user_id'" so this can only ever
        // update a row that belongs to the logged-in user.
        mysqli_query($conn, "UPDATE inventory SET expiry_date = '$expiry', is_bought = 1 WHERE id = $id AND user_id = '$user_id'");
    }
}

header("Location: dashboard.php?msg=stocked");
?>