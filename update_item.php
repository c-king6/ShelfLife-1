<?php
require_once "session_config.php";
include "db_connect.php";
require_once "household_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id   = (int)$_POST['id'];
    $item = mysqli_real_escape_string($conn, $_POST['item_name']);
    $cat  = mysqli_real_escape_string($conn, $_POST['category']);

    // New container fields
    $cc  = (float)($_POST['container_count'] ?? 1);
    if ($cc <= 0) $cc = 1;
    
    $ct  = mysqli_real_escape_string($conn, $_POST['unit'] ?? 'pcs');
    
    $spu = (float)($_POST['size_per_unit'] ?? 1);
    if ($spu <= 0) $spu = 1;
    
    $su  = mysqli_real_escape_string($conn, $_POST['size_unit'] ?? 'pcs');

    // Current amount
    $cq = (float)($_POST['current_quantity'] ?? 1);
    if ($cq <= 0) $cq = 0.01;
    $qty_int = (int)ceil($cq);

    // Price
    $price = null;
    if (isset($_POST['price']) && $_POST['price'] !== '' && is_numeric($_POST['price'])) {
        $price = (float)$_POST['price'];
    }
    $price_sql = ($price !== null) ? "'" . $price . "'" : "NULL";

    // Expiry
    $expiry = !empty($_POST['expiry_date'])
        ? "'" . mysqli_real_escape_string($conn, $_POST['expiry_date']) . "'"
        : "NULL";

    // Location
    $allowed_locations = ['Fridge', 'Freezer', 'Pantry', 'Shelf'];
    $location = in_array($_POST['location'] ?? '', $allowed_locations, true) ? $_POST['location'] : null;
    $location_sql = ($location !== null) ? "'" . mysqli_real_escape_string($conn, $location) . "'" : "NULL";

    // Read existing initial_quantity to see if we need to bump it
    $existing_q = mysqli_query($conn, "SELECT initial_quantity, quantity, photo_path FROM inventory WHERE id = '$id' AND user_id = '$user_id'");
    $existing = $existing_q ? mysqli_fetch_assoc($existing_q) : null;
    if (!$existing) {
        header("Location: dashboard.php");
        exit();
    }

    $initial_qty = (float)($existing['initial_quantity'] ?? $existing['quantity']);
    if ($initial_qty <= 0) $initial_qty = $cq;
    // If the user raised the amount above what was originally bought, bump initial
    if ($cq > $initial_qty) $initial_qty = $cq;

    // Photo logic
    $current_photo = $existing['photo_path'] ?? null;
    $photo_set_sql = '';

    if (!empty($_POST['remove_photo']) && $current_photo) {
        $full = __DIR__ . '/' . $current_photo;
        if (is_file($full)) @unlink($full);
        $photo_set_sql = ", photo_path = NULL";
        $current_photo = null;
    }

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
                    if (!empty($current_photo)) {
                        $old = __DIR__ . '/' . $current_photo;
                        if (is_file($old)) @unlink($old);
                    }
                    $photo_set_sql = ", photo_path = '" . mysqli_real_escape_string($conn, 'uploads/items/' . $new_photo_file) . "'";
                }
            }
        }
    }

    $sql = "UPDATE inventory SET
                item_name = '$item',
                quantity = $qty_int,
                container_count = $cc,
                unit = '$ct',
                size_per_unit = $spu,
                size_unit = '$su',
                current_quantity = $cq,
                initial_quantity = $initial_qty,
                price = $price_sql,
                category = '$cat',
                location = $location_sql,
                expiry_date = $expiry
                $photo_set_sql
            WHERE id = '$id' AND user_id = '$user_id'";

    if (mysqli_query($conn, $sql)) {
        log_household_activity($conn, $user_id, $user_id, 'edited', $item, $id, null);
        header("Location: dashboard.php?msg=updated");
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($conn);
    }
}
?>