<?php
// 1. START THE SESSION (Must be at the very top)
session_start();
include "db_connect.php";

// 2. SECURITY CHECK: If not logged in, stop the script
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. GET THE LOGGED-IN USER'S ID
$user_id = $_SESSION['user_id'];

$item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
$quantity = (int)$_POST['quantity'];
$is_bought = (int)$_POST['is_bought'];
$expiry_date = !empty($_POST['expiry_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'" : "NULL";

$item_lower = strtolower($item_name);
$cat = "General";

// --- SMART CATEGORIZER LOGIC ---
if (preg_match('/milk|cheese|egg|dairy|butter|yogurt/', $item_lower)) $cat = "Dairy";
elseif (preg_match('/chicken|beef|meat|pork|fish|steak|bacon/', $item_lower)) $cat = "Meat/Poultry";
elseif (preg_match('/bread|loaf|bakery|flour|pastry|bun/', $item_lower)) $cat = "Bakery";
elseif (preg_match('/apple|banana|fruit|orange|grape|mango/', $item_lower)) $cat = "Fruits";
elseif (preg_match('/carrot|potato|vegetable|onion|garlic/', $item_lower)) $cat = "Vegetables";
elseif (preg_match('/can|canned|soup|spam|tuna|sardines/', $item_lower)) $cat = "Grains/Canned";
elseif (preg_match('/pasta|rice|noodle|spaghetti/', $item_lower)) $cat = "Pasta/Grains";
elseif (preg_match('/sauce|ketchup|mayo|mustard/', $item_lower)) $cat = "Condiments";
elseif (preg_match('/pepper|vinegar|salt|spice|leaf|leaves|seasoning|herb|oil/', $item_lower)) $cat = "Spices/Seasonings";
elseif (preg_match('/chips|coffee|soda|snack|chocolate|candy/', $item_lower)) $cat = "Snacks/Drinks";

// 4. UPDATED SQL: Now saves the user_id into the inventory table
$sql = "INSERT INTO inventory (user_id, item_name, quantity, category, expiry_date, is_bought) 
        VALUES ('$user_id', '$item_name', '$quantity', '$cat', $expiry_date, '$is_bought')";

if (mysqli_query($conn, $sql)) {
    header("Location: dashboard.php?msg=success");
} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conn);
}
?>