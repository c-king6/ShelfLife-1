<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['bought_ids'])) {
    $ids = $_POST['bought_ids'];
    $action = $_POST['action'];

    if ($action == 'done') {
        foreach ($ids as $id) {
            $id = (int)$id;
            
            $res = mysqli_query($conn, "SELECT item_name, category FROM inventory WHERE id = $id AND user_id = '$user_id'");
            $row = mysqli_fetch_assoc($res);
            
            if ($row) {
                $name = strtolower($row['item_name']);
                $current_cat = $row['category'];

                // SMART SORTER: Categorizing the items into the Dashboard columns
                if ($current_cat == 'General') {
                    if (preg_match('/milk|cheese|egg|butter|yogurt|dairy|cream|sour cream/', $name)) {
                        $new_cat = "Dairy";
                    } elseif (preg_match('/chicken|beef|meat|pork|fish|steak|bacon|ham|turkey|hotdog|sausage|shrimp|seafood|tilapia|bangus/', $name)) {
                        $new_cat = "Meat/Poultry";
                    } elseif (preg_match('/apple|banana|orange|fruit|grape|mango|berry|melon|lemon|lime|pear|peach|plum|calamansi|pineapple|papaya/', $name)) {
                        $new_cat = "Fruits";
                    } elseif (preg_match('/carrot|potato|onion|garlic|tomato|veg|cabbage|lettuce|broccoli|corn|cucumber|ginger|kale|spinach|celery|mushroom|eggplant|kangkong|pechay|malunggay|okra|sitaw/', $name)) {
                        $new_cat = "Vegetables";
                    } elseif (preg_match('/bread|loaf|bun|pastry|cake|cookie|muffin|bagel/', $name)) {
                        $new_cat = "Bakery";
                    } elseif (preg_match('/rice|pasta|noodle|spaghetti|cereal|grain|flour|oats|quinoa/', $name)) {
                        $new_cat = "Pasta/Grains";
                    } elseif (preg_match('/pepper|chili|chilis|vinegar|leaf|leaves|spice|seasoning|herb|salt|oil|cinnamon|powder|clove|thyme|rosemary|patis|toyo/', $name)) {
                        $new_cat = "Spices/Seasonings";
                    } elseif (preg_match('/ketchup|mayo|mustard|sauce|soy sauce|jam|honey|syrup|dressing/', $name)) {
                        $new_cat = "Condiments";
                    } elseif (preg_match('/can|soup|tuna|sardine|beans|lentils/', $name)) {
                        $new_cat = "Grains/Canned";
                    } elseif (preg_match('/chips|soda|juice|coffee|tea|water|snack|chocolate|candy|popcorn/', $name)) {
                        $new_cat = "Snacks/Drinks";
                    } else {
                        $new_cat = "General";
                    }
                } else {
                    $new_cat = $current_cat;
                }

                mysqli_query($conn, "UPDATE inventory SET 
                                        is_bought = 1, 
                                        user_id = '$user_id', 
                                        category = '$new_cat', 
                                        expiry_date = NULL 
                                     WHERE id = $id AND user_id = '$user_id'");
            }
        }
        header("Location: dashboard.php?msg=items_added");
        exit();
    } else if ($action == 'delete') {
        foreach ($ids as $id) {
            $id = (int)$id;
            mysqli_query($conn, "DELETE FROM inventory WHERE id = $id AND user_id = '$user_id'");
        }
        header("Location: shopping_list.php?msg=discarded");
        exit();
    }
} else {
    header("Location: shopping_list.php");
    exit();
}
?>