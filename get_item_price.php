<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit();
}

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id'];
$item_name = trim($_POST['item_name'] ?? '');

if ($item_name === '') {
    echo json_encode(['last_price' => null, 'all_prices' => []]);
    exit();
}

$name_esc = mysqli_real_escape_string($conn, $item_name);

// Most recent price for this item name (for auto-fill)
$last_q = mysqli_query($conn, "SELECT price FROM inventory
                               WHERE user_id = '$user_id'
                               AND item_name = '$name_esc'
                               AND price IS NOT NULL
                               ORDER BY id DESC LIMIT 1");
$last_row = mysqli_fetch_assoc($last_q);
$last_price = $last_row ? (float)$last_row['price'] : null;

// All distinct prices ever used for this item name (for the dropdown)
$all_q = mysqli_query($conn, "SELECT DISTINCT price FROM inventory
                              WHERE user_id = '$user_id'
                              AND item_name = '$name_esc'
                              AND price IS NOT NULL
                              ORDER BY price ASC");
$all_prices = [];
while ($r = mysqli_fetch_assoc($all_q)) {
    $all_prices[] = (float)$r['price'];
}

echo json_encode([
    'last_price' => $last_price,
    'all_prices' => $all_prices
]);
?>