<?php
include "db_connect.php";

if (isset($_POST['ids'])) {
    $ids = $_POST['ids'];
    $dates = $_POST['dates'];

    for ($i = 0; $i < count($ids); $i++) {
        $id = $ids[$i];
        $expiry = $dates[$i];
        
        // Update both the date and the status
        mysqli_query($conn, "UPDATE inventory SET expiry_date = '$expiry', is_bought = 1 WHERE id = $id");
    }
}

header("Location: dashboard.php?msg=stocked");
?>