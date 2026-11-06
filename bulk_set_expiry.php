<?php
/**
 * bulk_set_expiry.php
 *
 * Sets the same expiry date for many items at once.
 *
 * Accepts POST:
 *   - item_ids[]   (array of item IDs)
 *   - mode         "date" | "days"
 *   - expiry_date  YYYY-MM-DD   (when mode = date)
 *   - days         integer       (when mode = days)
 *   - scope        "own" | "household"  (used only for redirect)
 *
 * Only edits the current user's own items (same rule as bulk_edit.php).
 */

require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Preserve scope for redirect
$scope = in_array($_POST['scope'] ?? '', ['own', 'household'], true) ? $_POST['scope'] : 'own';

// ---------- Validate item_ids ----------
if (!isset($_POST['item_ids']) || !is_array($_POST['item_ids']) || empty($_POST['item_ids'])) {
    header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope));
    exit();
}

$safe_ids = array_values(array_filter(array_map('intval', $_POST['item_ids'])));
if (empty($safe_ids)) {
    header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope));
    exit();
}
$ids = implode(',', $safe_ids);

// ---------- Validate mode ----------
$mode = $_POST['mode'] ?? '';
if (!in_array($mode, ['date', 'days'], true)) {
    header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope) . "&msg=invalid_date");
    exit();
}

// ---------- Compute the target expiry date ----------
if ($mode === 'date') {
    $raw = trim($_POST['expiry_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope) . "&msg=invalid_date");
        exit();
    }
    // Verify it's a real calendar date
    list($y, $m, $d) = array_map('intval', explode('-', $raw));
    if (!checkdate($m, $d, $y) || $y < 2000 || $y > 2100) {
        header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope) . "&msg=invalid_date");
        exit();
    }
    $new_expiry = sprintf('%04d-%02d-%02d', $y, $m, $d);
} else {
    // days mode
    $days = (int)($_POST['days'] ?? 0);
    if ($days < 0)    $days = 0;
    if ($days > 3650) $days = 3650; // cap at 10 years
    $new_expiry = date('Y-m-d', strtotime("+$days days"));
}

$expiry_esc = mysqli_real_escape_string($conn, $new_expiry);

// ---------- Update (own items only) ----------
mysqli_begin_transaction($conn);
try {
    // Restrict to this user's own items — household members' items are not editable
    $ok = mysqli_query($conn,
        "UPDATE inventory SET expiry_date = '$expiry_esc'
         WHERE id IN ($ids) AND user_id = '$user_id'"
    );
    if (!$ok) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("bulk_set_expiry failed: " . $e->getMessage());
    header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope) . "&msg=error");
    exit();
}

header("Location: dashboard.php?view=pantry&scope=" . urlencode($scope) . "&msg=bulk_expiry");
exit();