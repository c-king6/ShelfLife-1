<?php
include "db_connect.php";

if (isset($_POST['item_ids'])) {
    foreach ($_POST['item_ids'] as $id) {
        $id = (int)$id;
        
        // 1. Get the name of the item first
        $res = mysqli_query($conn, "SELECT item_name FROM inventory WHERE id = $id");
        $item = mysqli_fetch_assoc($res);
        $name = strtolower($item['item_name']);

        // 2. Smart Categorizer
        $cat = "General";
        if (preg_match('/milk|cheese|egg|dairy|butter|yogurt/', $name)) $cat = "Dairy";
        elseif (preg_match('/chicken|beef|meat|pork|fish|steak/', $name)) $cat = "Meat/Poultry";
        elseif (preg_match('/apple|banana|fruit|orange|grape/', $name)) $cat = "Fruits";
        elseif (preg_match('/carrot|potato|onion|garlic|pepper|tomato/', $name)) $cat = "Vegetables";
        elseif (preg_match('/bread|loaf|bakery|bun/', $name)) $cat = "Bakery";

        // 3. Update the item and move it to Pantry
        mysqli_query($conn, "UPDATE inventory SET is_bought = 1, category = '$cat' WHERE id = $id");
    }
}

header("Location: dashboard.php");
?>