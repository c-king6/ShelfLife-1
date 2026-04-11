<?php
include "db_connect.php";
$today = date("Y-m-d");

$result = mysqli_query($conn, "SELECT * FROM inventory ORDER BY expiry_date ASC");

echo "<h2>My Smart Pantry</h2>";
echo "<table border='1'>
        <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Qty</th>
            <th>Expiry Date</th>
            <th>Status</th>
            <th>Suggestion</th>
        </tr>";

while($row = mysqli_fetch_assoc($result)) {
    // Calculate days left
    $date1 = new DateTime($today);
    $date2 = new DateTime($row['expiry_date']);
    $interval = $date1->diff($date2);
    $days_left = $interval->format('%r%a');

    // Logic for Status and Unique Suggestion
    if ($days_left < 0) {
        $status = "<b style='color:red;'>Expired</b>";
        $suggest = "Item expired. Immediate disposal recommended for food safety.";
    } elseif ($days_left <= 3) {
        $status = "<b style='color:orange;'>Expiring Soon!</b>";
        $search = urlencode("easy recipes with " . $row['item_name']);
        $suggest = "<a href='https://www.google.com/search?q=$search' target='_blank'>Cook this today!</a>";
    } else {
        $status = "<b style='color:green;'>Fresh</b>";
        $suggest = "Wait until closer to expiry";
    }

    echo "<tr>
            <td>{$row['item_name']}</td>
            <td>{$row['category']}</td>
            <td>{$row['quantity']}</td>
            <td>{$row['expiry_date']}</td>
            <td>$status</td>
            <td>$suggest</td>
          </tr>";
}
echo "</table>";
?>