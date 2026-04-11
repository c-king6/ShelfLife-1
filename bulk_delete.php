<?php
include "db_connect.php";
if(isset($_POST['item_ids'])) {
    $ids = implode(',', $_POST['item_ids']);
    mysqli_query($conn, "DELETE FROM inventory WHERE id IN ($ids)"); //
}
header("Location: dashboard.php");
?>