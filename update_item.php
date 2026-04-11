<?php
include "db_connect.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $item = mysqli_real_escape_string($conn, $_POST['item_name']);
    $qty = (int)$_POST['quantity'];
    $cat = mysqli_real_escape_string($conn, $_POST['category']);
    $expiry = !empty($_POST['expiry_date']) ? "'".$_POST['expiry_date']."'" : "NULL";

    // SQL Update Query
    $sql = "UPDATE inventory SET 
            item_name = '$item', 
            quantity = '$qty', 
            category = '$cat', 
            expiry_date = $expiry 
            WHERE id = '$id'";

    if (mysqli_query($conn, $sql)) {
        header("Location: dashboard.php?msg=updated");
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($conn);
    }
}
?>