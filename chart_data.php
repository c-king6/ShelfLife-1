<?php
/**
 * chart_data.php — JSON endpoints for the dashboard + waste log charts.
 *
 * Usage:
 *   chart_data.php?type=trends&scope=own|household
 *     → returns last 6 months of {month, saved, wasted}
 *
 *   chart_data.php?type=waste_categories&scope=own|household
 *     → returns wasted items grouped by category
 */

require_once "session_config.php";
include "db_connect.php";
require_once "household_helper.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$type    = $_GET['type']  ?? '';
$scope   = $_GET['scope'] ?? 'own';
if (!in_array($scope, ['own', 'household'], true)) $scope = 'own';

// Resolve which user IDs to include
$household    = get_household_info($conn, $user_id);
$in_household = (bool)$household;
if (!$in_household) $scope = 'own';

$scope_ids  = ($scope === 'household') ? get_household_user_ids($conn, $user_id) : [$user_id];
$scope_list = implode(',', array_map('intval', $scope_ids));

// ============================================================
// 1. TRENDS — last 6 months of savings vs waste
// ============================================================
if ($type === 'trends') {
    // Build the last 6 months (including the current one)
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
        $months[date('Y-m', $ts)] = [
            'label'  => date('M Y', $ts),
            'saved'  => 0.0,
            'wasted' => 0.0,
        ];
    }

    $earliest = array_key_first($months) . '-01 00:00:00';

    $q = mysqli_query($conn, "
        SELECT
            DATE_FORMAT(logged_at, '%Y-%m') AS ym,
            COALESCE(SUM(CASE WHEN status='consumed' AND price IS NOT NULL THEN price ELSE 0 END), 0) AS saved,
            COALESCE(SUM(CASE WHEN status='wasted'   AND price IS NOT NULL THEN price ELSE 0 END), 0) AS wasted
        FROM item_history
        WHERE user_id IN ($scope_list)
          AND logged_at >= '$earliest'
        GROUP BY ym
    ");

    while ($r = mysqli_fetch_assoc($q)) {
        if (isset($months[$r['ym']])) {
            $months[$r['ym']]['saved']  = (float)$r['saved'];
            $months[$r['ym']]['wasted'] = (float)$r['wasted'];
        }
    }

    $labels = [];
    $saved  = [];
    $wasted = [];
    foreach ($months as $m) {
        $labels[] = $m['label'];
        $saved[]  = round($m['saved'], 2);
        $wasted[] = round($m['wasted'], 2);
    }

    echo json_encode([
        'success' => true,
        'labels'  => $labels,
        'saved'   => $saved,
        'wasted'  => $wasted,
    ]);
    exit();
}

// ============================================================
// 2. WASTE BY CATEGORY — top categories you waste
// ============================================================
if ($type === 'waste_categories') {
    $q = mysqli_query($conn, "
        SELECT
            category,
            COUNT(*) AS cnt,
            COALESCE(SUM(CASE WHEN price IS NOT NULL THEN price ELSE 0 END), 0) AS value
        FROM item_history
        WHERE user_id IN ($scope_list)
          AND status = 'wasted'
        GROUP BY category
        ORDER BY cnt DESC
        LIMIT 8
    ");

    $labels = [];
    $counts = [];
    $values = [];
    while ($r = mysqli_fetch_assoc($q)) {
        $labels[] = $r['category'] ?: 'Uncategorized';
        $counts[] = (int)$r['cnt'];
        $values[] = round((float)$r['value'], 2);
    }

    echo json_encode([
        'success' => true,
        'labels'  => $labels,
        'counts'  => $counts,
        'values'  => $values,
    ]);
    exit();
}

// Unknown type
echo json_encode(['success' => false, 'error' => 'Unknown chart type']);