<?php
// Small helper to record admin actions in the admin_logs table.
// Call it from any admin handler when something changes.

function log_admin_action($conn, $admin_id, $action, $target_type = null, $target_id = null, $details = null) {
    $admin_id  = (int)$admin_id;
    $action_e  = mysqli_real_escape_string($conn, $action);

    $tt_sql  = $target_type ? "'" . mysqli_real_escape_string($conn, $target_type) . "'" : "NULL";
    $ti_sql  = $target_id ? (int)$target_id : "NULL";
    $det_sql = $details ? "'" . mysqli_real_escape_string($conn, $details) . "'" : "NULL";

    mysqli_query($conn, "INSERT INTO admin_logs (admin_id, action, target_type, target_id, details)
                         VALUES ($admin_id, '$action_e', $tt_sql, $ti_sql, $det_sql)");
}
?>