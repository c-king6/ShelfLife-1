<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";
require_once "household_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$item_name  = mysqli_real_escape_string($conn, $_POST['item_name'] ?? '');
$is_bought  = (int)($_POST['is_bought'] ?? 1);

if ($item_name === '') {
    header("Location: index.php?msg=empty");
    exit();
}

// Container info
$container_count = (float)($_POST['container_count'] ?? 1);
if ($container_count <= 0) $container_count = 1;

$allowed_containers = ['pcs', 'bottle', 'can', 'pack', 'box', 'jar', 'bulk'];
$unit = in_array($_POST['unit'] ?? '', $allowed_containers, true) ? $_POST['unit'] : 'pcs';
$unit_esc = mysqli_real_escape_string($conn, $unit);

$size_per_unit = (float)($_POST['size_per_unit'] ?? 1);
if ($size_per_unit <= 0) $size_per_unit = 1;

$allowed_size_units = ['pcs', 'ml', 'L', 'g', 'kg'];
$size_unit = in_array($_POST['size_unit'] ?? '', $allowed_size_units, true) ? $_POST['size_unit'] : 'pcs';
$size_unit_esc = mysqli_real_escape_string($conn, $size_unit);

// Normalize: pcs = 1pcs each, bulk = 1 container
if ($unit === 'pcs') {
    $size_per_unit = 1;
    $size_unit = 'pcs';
    $size_unit_esc = 'pcs';
}
if ($unit === 'bulk') {
    $container_count = 1;
}

// Total content
$total_content = $container_count * $size_per_unit;
$qty_int = (int)ceil($total_content);
if ($qty_int < 1) $qty_int = 1;

// Price: total paid (NULL if blank)
$price = null;
if (isset($_POST['price']) && $_POST['price'] !== '' && is_numeric($_POST['price'])) {
    $price = (float)$_POST['price'];
}
$price_sql = ($price !== null) ? "'" . $price . "'" : "NULL";

$expiry_date = !empty($_POST['expiry_date'])
    ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'"
    : "NULL";

$cat = shelflife_categorize($item_name);
$cat_esc = mysqli_real_escape_string($conn, $cat);

$allowed_locations = ['Fridge', 'Freezer', 'Pantry', 'Shelf'];
$location = in_array($_POST['location'] ?? '', $allowed_locations, true) ? $_POST['location'] : null;
$location_sql = ($location !== null) ? "'" . mysqli_real_escape_string($conn, $location) . "'" : "NULL";

// Photo upload
$photo_path_sql = "NULL";
$new_photo_file = null;
if (isset($_FILES['item_photo']) && $_FILES['item_photo']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['item_photo'];
    if ($f['size'] <= 5 * 1024 * 1024 && $f['size'] > 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($f['tmp_name']);
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($ext, $allowed_ext, true) && $info !== false && in_array($info['mime'], $allowed_mime, true)) {
            $dir = __DIR__ . '/uploads/items';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $new_photo_file = 'item_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target = $dir . '/' . $new_photo_file;
            if (@move_uploaded_file($f['tmp_name'], $target)) {
                $photo_path_sql = "'" . mysqli_real_escape_string($conn, 'uploads/items/' . $new_photo_file) . "'";
            } else {
                $new_photo_file = null;
            }
        }
    }
}

// Duplicate check — same name, category, expiry, price, location, container type, size, size unit
$check_sql = "SELECT id, quantity, container_count, initial_quantity, current_quantity FROM inventory
              WHERE user_id = '$user_id'
              AND item_name = '$item_name'
              AND category = '$cat_esc'
              AND expiry_date <=> $expiry_date
              AND price <=> $price_sql
              AND location <=> $location_sql
              AND unit = '$unit_esc'
              AND size_per_unit = $size_per_unit
              AND size_unit = '$size_unit_esc'
              AND is_bought = 1
              LIMIT 1";
$check = mysqli_query($conn, $check_sql);

if ($check && mysqli_num_rows($check) === 1) {
    // Combine
    $existing = mysqli_fetch_assoc($check);
    $new_containers = (float)$existing['container_count'] + $container_count;
    $new_initial    = (float)$existing['initial_quantity'] + $total_content;
    $new_current    = (float)$existing['current_quantity'] + $total_content;
    $new_qty_int    = (int)ceil($new_current);

    if ($new_photo_file !== null) {
        $old_q = mysqli_query($conn, "SELECT photo_path FROM inventory WHERE id = " . (int)$existing['id']);
        $old_row = $old_q ? mysqli_fetch_assoc($old_q) : null;
        if (!empty($old_row['photo_path'])) {
            $old_file = __DIR__ . '/' . $old_row['photo_path'];
            if (is_file($old_file)) @unlink($old_file);
        }
        $rel = 'uploads/items/' . $new_photo_file;
        mysqli_query($conn, "UPDATE inventory SET
                                quantity = $new_qty_int,
                                container_count = $new_containers,
                                initial_quantity = $new_initial,
                                current_quantity = $new_current,
                                photo_path = '" . mysqli_real_escape_string($conn, $rel) . "'
                             WHERE id = " . (int)$existing['id']);
    } else {
        mysqli_query($conn, "UPDATE inventory SET
                                quantity = $new_qty_int,
                                container_count = $new_containers,
                                initial_quantity = $new_initial,
                                current_quantity = $new_current
                             WHERE id = " . (int)$existing['id']);
    }

    log_household_activity($conn, $user_id, $user_id, 'added', $item_name, (int)$existing['id'], null);
    header("Location: dashboard.php?msg=combined");
    exit();
}

// New insert
$sql = "INSERT INTO inventory
            (user_id, item_name, quantity, container_count, unit, size_per_unit, size_unit,
             initial_quantity, current_quantity, price, category, location, photo_path, expiry_date, is_bought)
        VALUES
            ('$user_id', '$item_name', $qty_int, $container_count, '$unit_esc', $size_per_unit, '$size_unit_esc',
             $total_content, $total_content, $price_sql, '$cat_esc', $location_sql, $photo_path_sql, $expiry_date, '$is_bought')";

if (mysqli_query($conn, $sql)) {
    log_household_activity($conn, $user_id, $user_id, 'added', $item_name, (int)mysqli_insert_id($conn), null);
    header("Location: dashboard.php?msg=success");
    exit();
} else {
    error_log("save_food failed: " . mysqli_error($conn));
    header("Location: index.php?msg=error");
    exit();
}
?>