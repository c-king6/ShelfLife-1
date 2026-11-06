<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch every item this user has ever added
$q = mysqli_query($conn, "SELECT item_name, category, quantity, container_count, unit, size_per_unit, size_unit, initial_quantity, current_quantity, price, expiry_date, date_added, is_bought
                          FROM inventory
                          WHERE user_id = '$user_id'
                          ORDER BY date_added DESC");

$filename = 'shelflife-pantry-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, [
    'Item Name',
    'Category',
    'How Many',
    'Container',
    'Each Contains',
    'Content Unit',
    'Current Amount',
    'Price per Container',
    'Expiry Date',
    'Date Added',
    'In Pantry',
]);

while ($row = mysqli_fetch_assoc($q)) {
    fputcsv($output, [
        $row['item_name'],
        $row['category'],
        $row['container_count'] !== null ? $row['container_count'] : $row['quantity'],
        $row['unit'] ?? 'pcs',
        $row['size_per_unit'] !== null ? $row['size_per_unit'] : 1,
        $row['size_unit'] ?? 'pcs',
        $row['current_quantity'] !== null ? $row['current_quantity'] : $row['quantity'],
        $row['price'] !== null ? number_format((float)$row['price'], 2, '.', '') : '',
        $row['expiry_date'] ?? '',
        $row['date_added'],
        $row['is_bought'] ? 'Yes' : 'No',
    ]);
}

fclose($output);
exit();
?>