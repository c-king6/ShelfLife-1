<?php
include "db_connect.php";

if(isset($_GET['id'])){
    $id = $_GET['id'];
    
    // This command deletes the specific item you clicked on
    $sql = "DELETE FROM inventory WHERE id = $id";

    if(mysqli_query($conn, $sql)){
        // After deleting, send the user back to the dashboard
        header("Location: dashboard.php?msg=Item Removed");
    } else {
        echo "Error deleting record: " . mysqli_error($conn);
    }
}
?>