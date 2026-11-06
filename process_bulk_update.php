<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['item_name'])) {

    mysqli_begin_transaction($conn);

    try {
        $names      = $_POST['item_name'];
        $ccounts    = $_POST['container_count'];
        $units      = $_POST['unit'];
        $sizes      = $_POST['size_per_unit'];
        $size_units = $_POST['size_unit'];
        $currents   = $_POST['current_quantity'];
        $prices     = $_POST['price'];
        $categories = $_POST['category'];
        $old_ids    = $_POST['old_ids'] ?? [];

        for ($i = 0; $i < count($names); $i++) {
            $name = mysqli_real_escape_string($conn, $names[$i]);

            $cc = (float)$ccounts[$i];
            if ($cc <= 0) $cc = 1;
            
            $ct = mysqli_real_escape_string($conn, $units[$i] ?? 'pcs');
            
            $spu = (float)$sizes[$i];
            if ($spu <= 0) $spu = 1;
            
            $su = mysqli_real_escape_string($conn, $size_units[$i] ?? 'pcs');

            $cq = (float)$currents[$i];
            if ($cq <= 0) $cq = 0.01;
            $qty_int = (int)ceil($cq);

            $price = null;
            if (isset($prices[$i]) && $prices[$i] !== '' && is_numeric($prices[$i])) {
                $price = (float)$prices[$i];
            }
            $price_sql = ($price !== null) ? "'" . $price . "'" : "NULL";

            $cat = mysqli_real_escape_string($conn, $categories[$i]);

            $old_id = isset($old_ids[$i]) ? (int)$old_ids[$i] : 0;
            if ($old_id <= 0) continue;

            $rq = mysqli_query($conn, "SELECT initial_quantity, quantity FROM inventory WHERE id = $old_id AND user_id = '$user_id'");
            $rr = $rq ? mysqli_fetch_assoc($rq) : null;
            if (!$rr) continue;

            $initial_qty = (float)($rr['initial_quantity'] ?? $rr['quantity']);
            if ($initial_qty <= 0) $initial_qty = $cq;
            if ($cq > $initial_qty) $initial_qty = $cq;

            $sql = "UPDATE inventory SET
                        item_name = '$name',
                        quantity = $qty_int,
                        container_count = $cc,
                        unit = '$ct',
                        size_per_unit = $spu,
                        size_unit = '$su',
                        current_quantity = $cq,
                        initial_quantity = $initial_qty,
                        price = $price_sql,
                        category = '$cat'
                    WHERE id = $old_id AND user_id = '$user_id'";
            if (!mysqli_query($conn, $sql)) {
                throw new Exception(mysqli_error($conn));
            }
        }

        mysqli_commit($conn);
        header("Location: dashboard.php?msg=updated");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("process_bulk_update failed: " . $e->getMessage());
        header("Location: dashboard.php?msg=error");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>