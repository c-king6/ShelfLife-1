<?php
require_once "session_config.php";
include "db_connect.php";
require_once "household_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_ids'])) {
    $user_id = $_SESSION['user_id'];

    $safe_ids = array_map('intval', $_POST['item_ids']);
    if (empty($safe_ids)) {
        header("Location: dashboard.php");
        exit();
    }
    $ids_to_remove = implode(',', $safe_ids);
    $used_amounts = $_POST['used_amounts'] ?? [];

    mysqli_begin_transaction($conn);

    try {
        $allowed_ids = implode(',', array_map('intval', get_household_user_ids($conn, $user_id)));

        // FIXED: Added size_unit to the SELECT query
        $fetch = mysqli_query($conn, "SELECT id, item_name, category, quantity, unit, size_unit, size_per_unit, initial_quantity, current_quantity, price, user_id
                                       FROM inventory
                                       WHERE id IN ($ids_to_remove) AND user_id IN ($allowed_ids)");

        if (!$fetch) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }

        while ($item = mysqli_fetch_assoc($fetch)) {
            $item_id   = (int)$item['id'];
            $name      = mysqli_real_escape_string($conn, $item['item_name']);
            $cat       = mysqli_real_escape_string($conn, $item['category']);
            $owner_id  = (int)$item['user_id'];
            
            $initial_qty = (float)($item['initial_quantity'] ?? $item['quantity']);
            if ($initial_qty <= 0) $initial_qty = 1;
            
            $current_qty = (float)($item['current_quantity'] ?? $item['quantity']);
            if ($current_qty <= 0) $current_qty = 1;
            
            $size_per_unit = (float)($item['size_per_unit'] ?? 1);
            if ($size_per_unit <= 0) $size_per_unit = 1;

            $price = ($item['price'] !== null) ? (float)$item['price'] : null;

            $used = isset($used_amounts[$item_id]) ? (float)$used_amounts[$item_id] : $current_qty;
            if ($used <= 0) $used = $current_qty;
            if ($used > $current_qty) $used = $current_qty;

            $log_price = null;
            if ($price !== null) {
                $price_per_content_unit = $price / $size_per_unit;
                $log_price = $price_per_content_unit * $used;
            }
            $log_price_sql = ($log_price !== null) ? "'" . round($log_price, 2) . "'" : "NULL";

            mysqli_query($conn, "INSERT INTO item_history (user_id, item_name, category, quantity, price, status)
                                 VALUES ('$owner_id', '$name', '$cat', $used, $log_price_sql, 'consumed')");

            if ($used >= $current_qty) {
                mysqli_query($conn, "DELETE FROM inventory WHERE id = $item_id");
                log_household_activity($conn, $user_id, $owner_id, 'cooked', $item['item_name'], $item_id, null);
            } else {
                $new_current = $current_qty - $used;
                if ($new_current < 0) $new_current = 0;
                $new_qty_int = (int)ceil($new_current);
                if ($new_qty_int < 1 && $new_current > 0) $new_qty_int = 1;
                
                mysqli_query($conn, "UPDATE inventory SET current_quantity = $new_current, quantity = $new_qty_int WHERE id = $item_id");
                log_household_activity($conn, $user_id, $owner_id, 'cooked', $item['item_name'], $item_id, "Partial: {$used}{$item['size_unit']}");
            }
        }

        mysqli_commit($conn);

        $scope = in_array($_POST['scope'] ?? '', ['own', 'household'], true) ? $_POST['scope'] : 'own';
        header("Location: dashboard.php?status=success_cook&scope=" . urlencode($scope));

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("process_cook failed: " . $e->getMessage());
        header("Location: dashboard.php?status=error");
    }
    exit();
}
?>