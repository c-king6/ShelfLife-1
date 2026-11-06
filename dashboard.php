<?php
require_once "session_config.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
include "db_connect.php";
require_once "categories_config.php";
require_once "household_helper.php";

$today = date("Y-m-d");

// ---------- Household context ----------
$household      = get_household_info($conn, $user_id);
$in_household   = (bool)$household;
$household_ids  = $in_household ? get_household_user_ids($conn, $user_id) : [$user_id];
$has_unread_hh  = $in_household ? has_unread_household_activity($conn, $user_id) : false;

// View scope: 'own' (default) or 'household'
$scope = $_GET['scope'] ?? 'own';
if (!in_array($scope, ['own', 'household'], true)) $scope = 'own';
if (!$in_household) $scope = 'own';

// Which user IDs appear in pantry/item queries
$scope_ids  = ($scope === 'household') ? $household_ids : [$user_id];
$scope_list = implode(',', array_map('intval', $scope_ids));

// Location filter (whitelisted)
$allowed_locations = ['Fridge', 'Freezer', 'Pantry', 'Shelf'];
$loc_filter = $_GET['loc'] ?? '';
if (!in_array($loc_filter, $allowed_locations, true)) $loc_filter = '';
$loc_where  = ($loc_filter !== '') ? "AND i.location = '" . mysqli_real_escape_string($conn, $loc_filter) . "'" : '';

$admin_res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$admin_row = mysqli_fetch_assoc($admin_res);
$admin_id  = $admin_row ? (int)$admin_row['id'] : 1;

$unread_res    = mysqli_query($conn, "SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$user_id' AND sender_id = '$admin_id' AND is_read = 0");
$has_new_reply = $unread_res && (int)mysqli_fetch_assoc($unread_res)['total'] > 0;

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$view   = isset($_GET['view']) ? $_GET['view'] : 'pantry';

$categories = $SHELFLIFE_CATEGORIES;

function normalize($string) {
    return strtolower(trim($string));
}

$user_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, profile_pic FROM users WHERE id = '$user_id'"));
$stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id IN ($scope_list)"))['total'];
$stat_urgent  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id IN ($scope_list) AND expiry_date IS NOT NULL AND DATEDIFF(expiry_date, '$today') <= 3 AND DATEDIFF(expiry_date, '$today') >= 0"))['total'];
$stat_expired = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory WHERE is_bought=1 AND user_id IN ($scope_list) AND expiry_date < '$today'"))['total'];

    // ---------- Savings tracker (this month) ----------
$month_start = date('Y-m-01 00:00:00');

$consumed_q = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt
                                    FROM item_history
                                    WHERE user_id IN ($scope_list) AND status = 'consumed'
                                    AND logged_at >= '$month_start' AND price IS NOT NULL");
$consumed_row = mysqli_fetch_assoc($consumed_q);
$saved_amount   = (float)$consumed_row['total'];
$consumed_count = (int)$consumed_row['cnt'];

$wasted_q = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt
                                  FROM item_history
                                  WHERE user_id IN ($scope_list) AND status = 'wasted'
                                  AND logged_at >= '$month_start' AND price IS NOT NULL");
$wasted_row = mysqli_fetch_assoc($wasted_q);
$wasted_amount = (float)$wasted_row['total'];
$wasted_count  = (int)$wasted_row['cnt'];

$no_price_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM item_history
                                    WHERE user_id IN ($scope_list)
                                    AND logged_at >= '$month_start' AND price IS NULL");
$no_price_count = (int)mysqli_fetch_assoc($no_price_q)['cnt'];

$total_value = $saved_amount + $wasted_amount;
$efficiency  = $total_value > 0 ? ($saved_amount / $total_value) * 100 : 0;

// ---------- Insights card ----------
$insight_headline = '';
$insight_subtext  = '';
$insight_tone     = 'neutral';
$saved_label      = ($scope === 'household') ? 'Your household saved' : "You've saved";

if ($saved_amount > 0 || $wasted_amount > 0) {
    $lm_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
    $lm_end   = date('Y-m-t 23:59:59', strtotime('-1 month'));
    $lm_q = mysqli_query($conn, "SELECT
        COALESCE(SUM(CASE WHEN status='consumed' AND price IS NOT NULL THEN price ELSE 0 END), 0) AS saved
        FROM item_history WHERE user_id IN ($scope_list)
        AND logged_at BETWEEN '$lm_start' AND '$lm_end'");
    $last_month_saved = $lm_q ? (float)mysqli_fetch_assoc($lm_q)['saved'] : 0;

    $cur_fmt = '₱' . number_format($saved_amount, 2);

    if ($last_month_saved <= 0) {
        $insight_headline = "$saved_label $cur_fmt this month";
        $insight_subtext  = "Great start — you're tracking your groceries and reducing waste.";
        $insight_tone     = 'good';
    } else {
        $pct = (($saved_amount - $last_month_saved) / $last_month_saved) * 100;

        if ($pct >= 10) {
            $insight_headline = "$saved_label $cur_fmt this month";
            $insight_subtext  = "That's " . round($pct) . "% better than last month — keep it up.";
            $insight_tone     = 'good';
        } elseif ($pct <= -10) {
            $insight_headline = "$saved_label $cur_fmt this month";
            $insight_subtext  = "Last month was ₱" . number_format($last_month_saved, 2) . ". Try using items closer to their expiry.";
            $insight_tone     = 'warn';
        } else {
            $insight_headline = "$saved_label $cur_fmt this month";
            $insight_subtext  = "About the same as last month — steady and balanced.";
            $insight_tone     = 'neutral';
        }
    }
} else {
    $insight_headline = "Track your savings";
    $insight_subtext  = "Add prices when you stock items to see how much you save every month.";
    $insight_tone     = 'neutral';
}

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShelfLife · Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* ---------- Top nav ---------- */
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: white; border-bottom: 1px solid var(--sand-200);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            font-family: var(--font-display); font-weight: 800;
            font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900);
        }
        .brand .logo-icon {
            width: 32px; height: 32px; border-radius: var(--r-sm);
            background: var(--green-500);
            display: flex; align-items: center; justify-content: center; color: white;
        }
        .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .user-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 12px 6px 6px;
            border-radius: var(--r-full);
            border: 1px solid var(--sand-200);
            background: white; cursor: pointer;
            transition: border-color 0.15s;
        }
        .user-chip:hover { border-color: var(--green-500); }
        .user-chip .avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
        .user-chip .name { font-family: var(--font-display); font-weight: 700; font-size: 13px; color: var(--ink-800); }
        .dot-badge { width: 8px; height: 8px; background: var(--red-500); border-radius: 50%; display: inline-block; }

        /* ---------- Layout ---------- */
        main { max-width: 1200px; margin: 0 auto; padding: 32px 24px 120px; }
        .page-header { margin-bottom: 28px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        /* ---------- Tabs ---------- */
        .tabs {
            display: flex; gap: 4px; margin-bottom: 24px;
            border-bottom: 1px solid var(--sand-200);
        }
        .tab {
            padding: 12px 20px;
            font-family: var(--font-display); font-weight: 700; font-size: 13px;
            color: var(--ink-500);
            border-bottom: 2px solid transparent; margin-bottom: -1px;
            transition: color 0.15s, border-color 0.15s;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .tab:hover { color: var(--ink-800); }
        .tab.active { color: var(--green-700); border-bottom-color: var(--green-500); }
        .tab.danger.active { color: var(--red-600); border-bottom-color: var(--red-500); }
        .tab-count { background: var(--sand-100); color: var(--ink-600); padding: 2px 8px; border-radius: var(--r-full); font-size: 11px; }
        .tab.active .tab-count { background: var(--green-100); color: var(--green-700); }
        .tab.danger.active .tab-count { background: var(--red-50); color: var(--red-600); }

        /* ---------- Stats ---------- */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px; margin-bottom: 24px;
        }
        .stat {
            background: white; border: 1px solid var(--sand-200);
            border-radius: var(--r-lg); padding: 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .stat-icon {
            width: 44px; height: 44px; border-radius: var(--r-md);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .stat-icon.green { background: var(--green-100); color: var(--green-700); }
        .stat-icon.amber { background: var(--amber-50); color: #b45309; }
        .stat-icon.red   { background: var(--red-50);   color: var(--red-600); }
        .stat-icon .lucide { width: 22px; height: 22px; }
        .stat-value { font-family: var(--font-display); font-size: 24px; font-weight: 800; color: var(--ink-900); line-height: 1; }
        .stat-label { font-size: 12px; color: var(--ink-500); margin-top: 4px; font-weight: 600; }

        /* ---------- Search ---------- */
        .search-bar { position: relative; max-width: 420px; margin-bottom: 16px; }
        .search-bar input { padding-left: 42px; padding-right: 42px; }
        .search-bar .search-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--ink-400); pointer-events: none;
        }
        .search-bar .clear-icon {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            color: var(--ink-400); padding: 4px; border-radius: var(--r-sm);
        }
        .search-bar .clear-icon:hover { color: var(--ink-700); background: var(--sand-100); }

        /* ---------- Location filter chips ---------- */
        .location-filter {
            display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px;
        }
        .loc-chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 6px 12px; border-radius: var(--r-full);
            background: var(--surface); border: 1px solid var(--border);
            color: var(--ink-600); font-size: 12px; font-weight: 600;
            text-decoration: none; transition: 0.15s; font-family: var(--font-body);
        }
        .loc-chip:hover { border-color: var(--green-500); color: var(--green-700); }
        .loc-chip.active { background: var(--green-500); border-color: var(--green-500); color: white; }

        /* ---------- Category grid ---------- */
        .pantry-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px; margin-bottom: 40px;
        }
        .category-column {
            background: white; border: 1px solid var(--sand-200);
            border-radius: var(--r-lg); padding: 16px;
            display: flex; flex-direction: column; max-height: 460px;
        }
        .column-header {
            font-family: var(--font-display); font-weight: 700; font-size: 12px;
            letter-spacing: 0.04em; text-transform: uppercase;
            color: var(--ink-600);
            padding-bottom: 12px; border-bottom: 1px solid var(--sand-200);
            margin-bottom: 12px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .column-header .count {
            background: var(--sand-100); color: var(--ink-600);
            padding: 2px 8px; border-radius: var(--r-full); font-size: 11px;
        }
        .shelf-content {
            overflow-y: auto; flex-grow: 1;
            display: flex; flex-direction: column; gap: 8px; padding-right: 4px;
        }
        .shelf-content::-webkit-scrollbar { width: 4px; }
        .shelf-content::-webkit-scrollbar-thumb { background: var(--sand-300); border-radius: 4px; }

        .food-card {
            background: white; border: 1px solid var(--sand-200);
            border-radius: var(--r-md); padding: 12px;
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; transition: all 0.15s; text-transform: capitalize;
        }
        .food-card:hover { border-color: var(--green-500); box-shadow: var(--sh-sm); }
        .food-card.selected { background: var(--green-50); border-color: var(--green-500); }
        .food-card .status-bar { width: 3px; align-self: stretch; border-radius: 2px; background: var(--sand-300); }
        .food-card.fresh .status-bar   { background: var(--green-500); }
        .food-card.warning .status-bar { background: var(--amber-500); }
        .food-card.expired .status-bar { background: var(--red-500); }

        .food-card .item-thumb {
            width: 40px; height: 40px;
            border-radius: var(--r-sm);
            object-fit: cover;
            flex-shrink: 0;
            cursor: zoom-in;
            background: var(--sand-100);
            transition: transform 0.15s;
        }
        .food-card .item-thumb:hover { transform: scale(1.08); }

        .food-card .info { flex-grow: 1; min-width: 0; }
        .food-card .name {
            font-weight: 600; font-size: 13px; color: var(--ink-800);
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .food-card .meta { font-size: 11px; color: var(--ink-500); margin-top: 2px; }
        .food-card.expired .meta { color: var(--red-600); font-weight: 600; }
        .food-card.warning .meta { color: #b45309; font-weight: 600; }

        .empty-state {
            grid-column: 1 / -1; text-align: center;
            padding: 60px 20px; background: white;
            border: 1px dashed var(--sand-300); border-radius: var(--r-lg);
        }
        .empty-state .empty-icon {
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--sand-100); color: var(--ink-400);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .empty-state .empty-icon .lucide { width: 26px; height: 26px; }
        .empty-state h3 { margin-bottom: 6px; }
        .empty-state p { color: var(--ink-500); margin-bottom: 20px; font-size: 13px; }

        .empty-column { text-align: center; color: var(--ink-400); font-size: 12px; padding: 20px 0; }

        /* ---------- Suggestions ---------- */
        .section-title { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .section-title h2 { font-size: 16px; }
        .suggestions-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px; margin-bottom: 40px;
        }
        .suggestion-block {
            background: white; border: 1px solid var(--sand-200);
            border-radius: var(--r-lg); padding: 20px;
        }
        .suggestion-block h3 {
            font-size: 12px; text-transform: uppercase;
            letter-spacing: 0.05em; color: var(--ink-500);
            margin-bottom: 14px; display: flex; align-items: center; gap: 6px;
        }
        .suggestion-block h3.danger { color: var(--red-600); }
        .suggestion-block h3.success { color: var(--green-700); }

        .recipe-item {
            display: block; padding: 12px 14px;
            border: 1px solid var(--sand-200); border-radius: var(--r-md);
            margin-bottom: 8px; transition: all 0.15s; cursor: pointer;
        }
        .recipe-item:hover { border-color: var(--green-500); background: var(--green-50); }
        .recipe-item:last-child { margin-bottom: 0; }
        .recipe-item .title { font-weight: 700; font-size: 13px; color: var(--ink-800); margin-bottom: 4px; }
        .recipe-item .status { font-size: 11px; font-weight: 700; color: var(--green-700); }
        .recipe-item .status.warn { color: #b45309; }

        .soon-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-bottom: 1px solid var(--sand-200);
        }
        .soon-item:last-child { border-bottom: none; }
        .soon-item .soon-bar { width: 3px; height: 32px; border-radius: 2px; background: var(--red-500); }
        .soon-item .name { font-weight: 600; font-size: 13px; color: var(--ink-800); }
        .soon-item .days { font-size: 11px; color: var(--red-600); font-weight: 700; margin-top: 2px; }

        .empty-hint { color: var(--ink-400); font-size: 12px; font-style: italic; padding: 8px 0; }

        /* ---------- Floating action bar ---------- */
        .action-bar {
            position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%);
            background: var(--ink-900); color: white;
            padding: 12px 20px; border-radius: var(--r-full);
            display: flex; align-items: center; gap: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            transition: bottom 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
        }
        .action-bar.active { bottom: 24px; }
        .action-bar .count {
            font-family: var(--font-display); font-weight: 700;
            font-size: 12px; letter-spacing: 0.03em; color: white;
        }
        .bar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: var(--r-full);
            font-weight: 700; font-size: 12px; color: white;
            transition: opacity 0.15s;
        }
        .bar-btn:hover { opacity: 0.85; }
        .bar-btn.cook   { background: var(--amber-500); }
        .bar-btn.edit   { background: var(--green-500); }
        .bar-btn.delete { background: var(--red-500); }
        .bar-btn.eat    { background: var(--green-500); }
        .bar-btn.expiry { background: var(--blue-500); }
        .bar-btn.expiry:hover { opacity: 0.85; }

        /* ---------- Modals ---------- */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center;
            z-index: 2000; padding: 20px;
        }
        .modal-card {
            background: white; width: 100%; max-width: 600px;
            max-height: 85vh; border-radius: var(--r-xl);
            position: relative; padding: 32px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            overflow-y: auto; animation: modalIn 0.2s ease;
        }
        @keyframes modalIn {
            from { transform: translateY(12px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        .close-modal {
            position: absolute; top: 16px; right: 16px;
            width: 32px; height: 32px; border-radius: var(--r-sm);
            color: var(--ink-500);
            display: flex; align-items: center; justify-content: center;
        }
        .close-modal:hover { background: var(--sand-100); color: var(--ink-800); }

        .recipe-scroll { max-height: 380px; overflow-y: auto; padding-right: 4px; }
        .recipe-scroll::-webkit-scrollbar { width: 4px; }
        .recipe-scroll::-webkit-scrollbar-thumb { background: var(--sand-300); border-radius: 4px; }

        /* ---------- Waste bin ---------- */
        .waste-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
        .waste-card {
            background: white; border: 1px solid var(--sand-200);
            border-left: 3px solid var(--red-500);
            border-radius: var(--r-md); padding: 12px 14px;
            display: flex; align-items: center; gap: 10px; cursor: pointer;
        }
        .waste-card.selected { background: var(--red-50); border-color: var(--red-500); }

        /* ---------- Stock banner ---------- */
        .stock-banner {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--amber-50) 0%, #fef7ec 100%);
            border: 1px solid rgba(199, 123, 63, 0.25);
            border-left: 3px solid var(--amber-500);
            border-radius: var(--r-lg); margin-bottom: 20px;
            text-decoration: none; color: var(--ink-800);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .stock-banner:hover { transform: translateY(-1px); box-shadow: var(--sh-sm); }
        .stock-banner-icon {
            width: 40px; height: 40px; border-radius: var(--r-sm);
            background: rgba(199, 123, 63, 0.15); color: var(--amber-700);
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .stock-banner-icon .lucide { width: 20px; height: 20px; }
        .stock-banner-body { flex-grow: 1; min-width: 0; }
        .stock-banner-title { font-weight: 700; font-size: 13.5px; color: #7a4a1e; margin-bottom: 2px; }
        .stock-banner-sub { font-size: 11.5px; color: #9c5a26; }
        .stock-banner-arrow { color: var(--amber-700); flex-shrink: 0; }

        /* ---------- Household scope toggle ---------- */
        .scope-toggle {
            display: flex; gap: 4px; background: var(--sand-100);
            padding: 4px; border-radius: var(--r-full);
            margin-bottom: 20px; max-width: 480px; align-items: center;
        }
        .scope-btn {
            flex: 1; display: inline-flex; align-items: center; justify-content: center;
            gap: 6px; padding: 8px 14px; border-radius: var(--r-full);
            font-size: 12.5px; font-weight: 700; color: var(--ink-500);
            text-decoration: none; transition: 0.15s; position: relative;
            font-family: var(--font-body); white-space: nowrap;
        }
        .scope-btn:hover { color: var(--ink-800); }
        .scope-btn.active { background: var(--surface); color: var(--green-700); box-shadow: var(--sh-xs); }
        .scope-dot { width: 7px; height: 7px; background: var(--red-500); border-radius: 50%; display: inline-block; }
        .scope-manage .scope-dot { margin-left: 2px; vertical-align: middle; animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .scope-manage {
            padding: 8px 12px; font-size: 11.5px; font-weight: 600;
            color: var(--ink-500); text-decoration: none;
            border-radius: var(--r-full);
            display: inline-flex; align-items: center; gap: 5px; transition: 0.15s;
        }
        .scope-manage:hover { color: var(--ink-800); background: var(--sand-200); }

        .food-card .owner-chip {
            display: inline-flex; align-items: center;
            font-size: 10px; font-weight: 700; color: var(--ink-500);
            background: var(--sand-100); padding: 1px 6px; border-radius: var(--r-full);
            margin-left: 6px; vertical-align: middle; text-transform: none;
        }
        .food-card .owner-chip.mine { background: var(--green-50); color: var(--green-700); }

        @media (max-width: 640px) {
            .scope-toggle { max-width: 100%; flex-wrap: wrap; }
            .scope-manage { padding: 8px 10px; font-size: 11px; }
        }

        /* ---------- Insights hero card ---------- */
        .insights-hero {
            background: linear-gradient(135deg, var(--green-50) 0%, #f6faf4 50%, var(--sand-50) 100%);
            border: 1px solid var(--green-200);
            border-radius: var(--r-lg); padding: 24px; margin-bottom: 20px;
            position: relative; overflow: hidden;
        }
        .insights-hero::before {
            content: ""; position: absolute; top: -60px; right: -60px;
            width: 220px; height: 220px;
            background: radial-gradient(circle, rgba(45,90,61,0.08) 0%, transparent 70%);
            border-radius: 50%; pointer-events: none;
        }
        .insights-hero .hero-top {
            display: flex; align-items: flex-start; gap: 14px;
            margin-bottom: 18px; position: relative; z-index: 1;
        }
        .insights-hero .hero-icon {
            width: 44px; height: 44px; border-radius: var(--r-md);
            background: var(--green-500); color: white;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; box-shadow: 0 3px 10px rgba(45,90,61,0.25);
        }
        .insights-hero .hero-icon .lucide { width: 22px; height: 22px; }
        .insights-hero .hero-icon.warn { background: var(--amber-500); box-shadow: 0 3px 10px rgba(199,123,63,0.25); }
        .insights-hero .hero-body { flex-grow: 1; min-width: 0; }
        .insights-hero .hero-headline {
            font-family: var(--font-display); font-weight: 700; font-size: 22px;
            letter-spacing: -0.02em; color: var(--ink-900); line-height: 1.2; margin-bottom: 4px;
        }
        .insights-hero .hero-subtext { font-size: 13px; color: var(--ink-600); line-height: 1.5; }
        .insights-hero .hero-stats {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 20px; padding-top: 18px;
            border-top: 1px solid rgba(45,90,61,0.12);
            position: relative; z-index: 1;
        }
        .insights-hero .hero-stat-label {
            font-size: 10.5px; text-transform: uppercase;
            letter-spacing: 0.06em; font-weight: 700; color: var(--ink-500); margin-bottom: 4px;
        }
        .insights-hero .hero-stat-value { font-family: var(--font-display); font-weight: 700; font-size: 22px; line-height: 1; }
        .insights-hero .hero-stat-sub { font-size: 11px; color: var(--ink-500); margin-top: 4px; }
        .insights-hero .hero-footer {
            margin-top: 16px; padding-top: 14px;
            border-top: 1px solid rgba(45,90,61,0.12);
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; position: relative; z-index: 1;
        }
        .insights-hero .hero-noprice {
            font-size: 11.5px; color: var(--ink-500);
            display: flex; align-items: center; gap: 6px;
        }
        @media (max-width: 640px) {
            .insights-hero { padding: 20px; }
            .insights-hero .hero-headline { font-size: 19px; }
            .insights-hero .hero-stats { gap: 16px; }
            .insights-hero .hero-stat-value { font-size: 20px; }
        }

        /* ---------- Expiry mode toggle ---------- */
        .expiry-mode-opt {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 6px; padding: 10px 14px;
            border: 1px solid var(--border); border-radius: var(--r-sm);
            cursor: pointer; font-size: 13px; font-weight: 600;
            color: var(--ink-700); background: var(--surface);
            transition: 0.15s; font-family: var(--font-body);
            text-align: center; user-select: none;
        }
        .expiry-mode-opt:hover { border-color: var(--green-500); color: var(--green-700); }
        .expiry-mode-opt input { display: none; }
        .expiry-mode-opt.selected { border-color: var(--green-500); background: var(--green-50); color: var(--green-700); }

        /* ---------- Item photo lightbox ---------- */
        .item-lightbox {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none; justify-content: center; align-items: center;
            z-index: 5000; padding: 40px; cursor: zoom-out;
        }
        .item-lightbox.open { display: flex; }
        .item-lightbox img { max-width: 95%; max-height: 95%; border-radius: 8px; }
        /* ---------- Trends chart card ---------- */
        .trends-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px 22px;
            margin-bottom: 20px;
        }
        .trends-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .trends-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--ink-500);
            font-size: 13px;
        }
        @media (max-width: 640px) {
            .trends-card { padding: 16px 14px; }
        }

        /* ---------- Use-amount modal rows (M3) ---------- */
        .use-amount-row {
            display: flex; align-items: center; gap: 10px;
        }
        .use-amount-row .ua-name {
            flex-grow: 1; font-size: 13px; font-weight: 600; color: var(--ink-800);
        }
        .use-amount-row input {
            width: 90px; padding: 6px 10px; text-align: right;
        }
        .use-amount-row .ua-unit {
            font-size: 12px; color: var(--ink-500); min-width: 30px;
        }
    </style>
</head>
<body class="page page--dashboard">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>
<nav class="topnav">
    <a href="dashboard.php" class="brand">
        <div class="logo-icon"><i data-lucide="leaf"></i></div>
        ShelfLife
    </a>

    <div class="nav-actions">
            <a href="index.php" class="btn btn-primary btn-sm">
                <i data-lucide="plus" class="lucide-sm"></i>
                Add item
            </a>
            <a href="shopping_list.php" class="btn btn-secondary btn-sm">
                <i data-lucide="list-checks" class="lucide-sm"></i>
                Shopping list
            </a>
            <a href="incoming_groceries.php" class="btn btn-secondary btn-sm">
                <i data-lucide="shopping-bag" class="lucide-sm"></i>
                New groceries
            </a>
            <a href="recipes.php" class="btn btn-secondary btn-sm">
                <i data-lucide="chef-hat" class="lucide-sm"></i>
                Recipes
            </a>
            <a href="meal_planner.php" class="btn btn-secondary btn-sm">
                <i data-lucide="calendar" class="lucide-sm"></i>
                Meal plan
            </a>
        <?php if ($is_admin): ?>
            <a href="admin.php" class="btn btn-primary btn-sm">
                <i data-lucide="shield" class="lucide-sm"></i>
                Admin panel
            </a>
        <?php else: ?>
            <a href="chat.php" class="btn btn-secondary btn-sm" style="position: relative;">
                <i data-lucide="message-circle" class="lucide-sm"></i>
                Support
                <?php if ($has_new_reply): ?>
                    <span class="dot-badge" style="position:absolute; top:-2px; right:-2px;"></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <a href="profile.php" class="user-chip" id="profileTrigger">
            <img src="uploads/<?php echo htmlspecialchars($user_info['profile_pic']); ?>" class="avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_info['username']); ?>&background=6B8E23&color=fff'" alt="">
            <span class="name"><?php echo htmlspecialchars($user_info['username']); ?></span>
            <?php if ($is_admin): ?>
                <span class="badge badge-red" style="font-size: 10px;">Admin</span>
            <?php endif; ?>
        </a>
    </div>
</nav>

<main>

    <header class="page-header">
        <h1>My Pantry</h1>
        <p>Track groceries, reduce waste, cook smarter.</p>
    </header>

    <?php if ($in_household): ?>
    <div class="scope-toggle">
        <a href="?scope=own<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $view !== 'pantry' ? '&view=' . urlencode($view) : ''; ?><?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>"
           class="scope-btn <?php echo $scope === 'own' ? 'active' : ''; ?>">
            <i data-lucide="user" class="lucide-sm"></i>
            My pantry
        </a>
        <a href="?scope=household<?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?><?php echo $view !== 'pantry' ? '&view=' . urlencode($view) : ''; ?><?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>"
           class="scope-btn <?php echo $scope === 'household' ? 'active' : ''; ?>">
            <i data-lucide="users" class="lucide-sm"></i>
            Household
        </a>
        <a href="household.php" class="scope-manage">
            <i data-lucide="settings-2" class="lucide-sm"></i>
            Manage
            <?php if ($has_unread_hh): ?>
                <span class="scope-dot"></span>
            <?php endif; ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="insights-hero">
        <div class="hero-top">
            <div class="hero-icon <?php echo $insight_tone === 'warn' ? 'warn' : ''; ?>">
                <i data-lucide="<?php echo $insight_tone === 'warn' ? 'alert-circle' : 'piggy-bank'; ?>"></i>
            </div>
            <div class="hero-body">
                <div class="hero-headline"><?php echo htmlspecialchars($insight_headline); ?></div>
                <div class="hero-subtext"><?php echo htmlspecialchars($insight_subtext); ?></div>
            </div>
        </div>

        <div class="hero-stats">
            <div>
                <div class="hero-stat-label">Saved</div>
                <div class="hero-stat-value" style="color: var(--green-700);">₱<?php echo number_format($saved_amount, 2); ?></div>
                <div class="hero-stat-sub"><?php echo $consumed_count; ?> item<?php echo $consumed_count === 1 ? '' : 's'; ?> eaten in time</div>
            </div>
            <div>
                <div class="hero-stat-label">Wasted</div>
                <div class="hero-stat-value" style="color: var(--red-600);">₱<?php echo number_format($wasted_amount, 2); ?></div>
                <div class="hero-stat-sub"><?php echo $wasted_count; ?> item<?php echo $wasted_count === 1 ? '' : 's'; ?> expired</div>
            </div>
            <div>
                <div class="hero-stat-label">Efficiency</div>
                <div class="hero-stat-value" style="color: var(--ink-900);"><?php echo round($efficiency); ?>%</div>
                <div class="hero-stat-sub">of grocery value used</div>
            </div>
        </div>

        <div class="hero-footer">
            <a href="waste_log.php?scope=<?php echo $scope; ?>" class="btn btn-secondary btn-sm">
                <i data-lucide="book-open" class="lucide-sm"></i> View full history
            </a>
            <?php if ($no_price_count > 0): ?>
                <div class="hero-noprice">
                    <i data-lucide="info" style="width: 13px; height: 13px;"></i>
                    <?php echo $no_price_count; ?> item<?php echo $no_price_count === 1 ? '' : 's'; ?> had no price — not counted above.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ---------- Trends chart ---------- -->
    <div class="trends-card">
        <div class="trends-header">
            <div style="display:flex; align-items:center; gap:8px;">
                <i data-lucide="trending-up" style="color: var(--green-500);"></i>
                <h2 style="font-size: 15px;">Savings vs waste over time</h2>
            </div>
            <span class="text-xs text-muted" id="trendsSub">Last 6 months</span>
        </div>
        <div style="position: relative; height: 240px;">
            <canvas id="trendsChart"></canvas>
        </div>
        <div class="trends-empty" id="trendsEmpty" style="display:none;">
            <i data-lucide="line-chart" style="width:28px;height:28px;opacity:0.4;margin-bottom:8px;"></i>
            <div>Not enough history yet — track a few more items and this will fill in.</div>
        </div>
    </div>

    <?php
    // Count items waiting to be stocked (bought but no expiry set)
    $pending_stock_q = mysqli_query($conn,
        "SELECT COUNT(*) AS c FROM inventory
         WHERE user_id IN ($scope_list) AND is_bought = 1 AND expiry_date IS NULL");
    $pending_stock = $pending_stock_q ? (int)mysqli_fetch_assoc($pending_stock_q)['c'] : 0;
    ?>
    <?php if ($pending_stock > 0): ?>
    <a href="incoming_groceries.php" class="stock-banner">
        <div class="stock-banner-icon"><i data-lucide="shopping-bag"></i></div>
        <div class="stock-banner-body">
            <div class="stock-banner-title"><?php echo $pending_stock; ?> item<?php echo $pending_stock === 1 ? '' : 's'; ?> waiting to be stocked</div>
            <div class="stock-banner-sub">Set expiry dates to add to your pantry</div>
        </div>
        <i data-lucide="chevron-right" class="stock-banner-arrow"></i>
    </a>
    <?php endif; ?>

    <div class="tabs">
        <a href="?view=pantry&scope=<?php echo $scope; ?><?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>" class="tab <?php echo $view == 'pantry' ? 'active' : ''; ?>">
            <i data-lucide="package" class="lucide-sm"></i>
            My shelves
        </a>
        <a href="?view=expired&scope=<?php echo $scope; ?>" class="tab danger <?php echo $view == 'expired' ? 'active' : ''; ?>">
            <i data-lucide="trash-2" class="lucide-sm"></i>
            Waste bin
            <span class="tab-count"><?php echo (int)$stat_expired; ?></span>
        </a>
    </div>

    <?php if ($view == 'pantry'): ?>
        <div class="stats-row">
            <div class="stat">
                <div class="stat-icon green"><i data-lucide="package"></i></div>
                <div>
                    <div class="stat-value"><?php echo (int)$stat_total; ?></div>
                    <div class="stat-label">Items in pantry</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-icon amber"><i data-lucide="clock"></i></div>
                <div>
                    <div class="stat-value"><?php echo (int)$stat_urgent; ?></div>
                    <div class="stat-label">Expiring soon</div>
                </div>
            </div>
            <div class="stat">
                <div class="stat-icon red"><i data-lucide="alert-triangle"></i></div>
                <div>
                    <div class="stat-value"><?php echo (int)$stat_expired; ?></div>
                    <div class="stat-label">Expired items</div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 16px;">
            <a href="export_pantry.php" class="btn btn-secondary btn-sm">
                <i data-lucide="download" class="lucide-sm"></i> Export my pantry
            </a>
        </div>

        <div class="search-bar">
            <i data-lucide="search" class="lucide-sm search-icon"></i>
            <form action="dashboard.php" method="GET" style="margin:0;">
                <input type="hidden" name="view" value="pantry">
                <input type="hidden" name="scope" value="<?php echo $scope; ?>">
                <?php if ($loc_filter !== ''): ?>
                    <input type="hidden" name="loc" value="<?php echo htmlspecialchars($loc_filter); ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search your shelves..." value="<?php echo htmlspecialchars($search); ?>">
                <?php if ($search): ?>
                    <a href="dashboard.php?view=pantry&scope=<?php echo $scope; ?><?php echo $loc_filter !== '' ? '&loc=' . urlencode($loc_filter) : ''; ?>" class="clear-icon"><i data-lucide="x" class="lucide-sm"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="location-filter">
            <?php
                $loc_base = "dashboard.php?view=pantry&scope=" . urlencode($scope);
                if ($search !== '') $loc_base .= "&search=" . urlencode($search);
            ?>
            <a href="<?php echo $loc_base; ?>" class="loc-chip <?php echo $loc_filter === '' ? 'active' : ''; ?>">All</a>
            <a href="<?php echo $loc_base . '&loc=Fridge'; ?>"  class="loc-chip <?php echo $loc_filter === 'Fridge' ? 'active' : ''; ?>">🧊 Fridge</a>
            <a href="<?php echo $loc_base . '&loc=Freezer'; ?>" class="loc-chip <?php echo $loc_filter === 'Freezer' ? 'active' : ''; ?>">❄️ Freezer</a>
            <a href="<?php echo $loc_base . '&loc=Pantry'; ?>"  class="loc-chip <?php echo $loc_filter === 'Pantry' ? 'active' : ''; ?>">📦 Pantry</a>
            <a href="<?php echo $loc_base . '&loc=Shelf'; ?>"   class="loc-chip <?php echo $loc_filter === 'Shelf' ? 'active' : ''; ?>">🗄️ Shelf</a>
        </div>
    <?php endif; ?>

    <form id="bulkForm" method="POST">
        <?php if ($view == 'pantry'): ?>

            <?php if ($stat_total == 0 && $search == ''): ?>
                <div class="empty-state">
                    <div class="empty-icon"><i data-lucide="package-open"></i></div>
                    <h3>Your pantry is empty</h3>
                    <p>Add your first item to start tracking what you have at home.</p>
                    <a href="index.php" class="btn btn-primary">
                        <i data-lucide="plus" class="lucide-sm"></i> Add first item
                    </a>
                </div>
            <?php else: ?>
                <div class="pantry-grid">
                    <?php foreach ($categories as $cat):
                        $cat_esc = mysqli_real_escape_string($conn, $cat);
                        $query = "SELECT i.id, i.item_name, i.quantity, i.unit, i.initial_quantity, i.current_quantity, i.price, i.expiry_date, i.location, i.photo_path, i.user_id, u.username AS owner_name
                            FROM inventory i
                            LEFT JOIN users u ON u.id = i.user_id
                            WHERE i.user_id IN ($scope_list) AND i.category = '$cat_esc' AND i.is_bought = 1
                            AND i.expiry_date IS NOT NULL AND i.expiry_date >= '$today'
                            AND i.item_name LIKE '%$search%'
                            $loc_where
                            ORDER BY i.expiry_date ASC";
                        $items = mysqli_query($conn, $query);
                        $row_count = mysqli_num_rows($items);

                        if ($search !== '' && $row_count === 0) continue;
                    ?>
                        <div class="category-column">
                            <div class="column-header">
                                <span><?php echo htmlspecialchars($cat); ?></span>
                                <span class="count"><?php echo $row_count; ?></span>
                            </div>
                            <div class="shelf-content">
                                <?php if ($row_count > 0):
                                    while ($row = mysqli_fetch_assoc($items)):
                                        $diff = ($row['expiry_date']) ? (strtotime($row['expiry_date']) - strtotime($today)) / 86400 : 999;
                                        if ($diff < 0)       $status_class = "expired";
                                        elseif ($diff <= 3)  $status_class = "warning";
                                        else                 $status_class = "fresh";
                                        $cq = (float)($row['current_quantity'] ?? $row['quantity']);
                                ?>
<label class="food-card <?php echo $status_class; ?>">
    <input type="checkbox" name="item_ids[]" value="<?php echo (int)$row['id']; ?>" class="item-checkbox"
           data-mine="<?php echo ((int)$row['user_id'] === (int)$user_id) ? '1' : '0'; ?>"
           data-name="<?php echo htmlspecialchars($row['item_name']); ?>"
           data-qty="<?php echo $cq; ?>"
           data-unit="<?php echo htmlspecialchars($row['unit'] ?? 'pcs'); ?>"
           style="accent-color: var(--green-500);">
    <div class="status-bar"></div>
    <?php if (!empty($row['photo_path']) && file_exists($row['photo_path'])): ?>
        <img src="<?php echo htmlspecialchars($row['photo_path']); ?>"
             class="item-thumb"
             alt=""
             onclick="event.preventDefault(); event.stopPropagation(); openItemLightbox('<?php echo htmlspecialchars($row['photo_path']); ?>');">
    <?php endif; ?>
    <div class="info">
        <div class="name">
            <?php echo htmlspecialchars($row['item_name']); ?> (<?php echo rtrim(rtrim(number_format($cq, 2, '.', ''), '0'), '.'); ?><?php echo htmlspecialchars($row['unit'] ?? 'pcs'); ?> left)
            <?php if ($scope === 'household' && !empty($row['owner_name'])): ?>
                <?php if ((int)$row['user_id'] === $user_id): ?>
                    <span class="owner-chip mine">you</span>
                <?php else: ?>
                    <span class="owner-chip"><?php echo htmlspecialchars($row['owner_name']); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="meta">
            <?php
                if ($diff < 0) echo "Expired";
                elseif ($diff < 1) echo "Expires today";
                else echo floor($diff) . " days left";
            ?>
            <?php if (!empty($row['location'])): ?>
                · <span style="color: var(--ink-500);"><?php echo htmlspecialchars($row['location']); ?></span>
            <?php endif; ?>
            <?php if ($row['price'] !== null): ?>
                · <span style="color: var(--green-700); font-weight: 600;">₱<?php echo number_format((float)$row['price'], 2); ?></span>
            <?php endif; ?>
        </div>
    </div>
</label>
                                    <?php endwhile;
                                else: ?>
                                    <div class="empty-column">No items yet</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="card">
                <div class="section-title" style="justify-content: space-between;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i data-lucide="trash-2" style="color: var(--red-500);"></i>
                        <h2>Waste bin</h2>
                    </div>
                    <?php if ($stat_expired > 0): ?>
                        <button type="button" onclick="toggleAll(true)" class="btn btn-secondary btn-sm">
                            Select all
                        </button>
                    <?php endif; ?>
                </div>

                <p class="text-muted text-sm" style="margin-bottom: 20px;">Select expired items and discard them to keep your pantry clean.</p>

                <div class="waste-grid">
                    <?php
                    $expired_q = mysqli_query($conn, "SELECT i.*, u.username AS owner_name FROM inventory i LEFT JOIN users u ON u.id = i.user_id WHERE i.is_bought=1 AND i.user_id IN ($scope_list) AND i.expiry_date IS NOT NULL AND i.expiry_date < '$today'");
                    if (mysqli_num_rows($expired_q) > 0):
                        while ($exp = mysqli_fetch_assoc($expired_q)):
                            $cq = (float)($exp['current_quantity'] ?? $exp['quantity']);
                    ?>
                            <label class="waste-card">
                              <input type="checkbox" name="item_ids[]" value="<?php echo (int)$exp['id']; ?>" class="item-checkbox"
                                     data-mine="<?php echo ((int)$exp['user_id'] === (int)$user_id) ? '1' : '0'; ?>"
                                     data-name="<?php echo htmlspecialchars($exp['item_name']); ?>"
                                     data-qty="<?php echo $cq; ?>"
                                     data-unit="<?php echo htmlspecialchars($exp['unit'] ?? 'pcs'); ?>"
                                     style="accent-color: var(--red-500);">
                                <div style="flex-grow:1;">
                                    <div style="font-weight:700; font-size: 13px;">
                                        <?php echo htmlspecialchars($exp['item_name']); ?>
                                        <?php if ($scope === 'household' && !empty($exp['owner_name']) && (int)$exp['user_id'] !== $user_id): ?>
                                            <span class="owner-chip"><?php echo htmlspecialchars($exp['owner_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--red-600); font-weight: 700; margin-top: 2px;">Expired <?php echo date('M d', strtotime($exp['expiry_date'])); ?></div>
                                </div>
                            </label>
                        <?php endwhile;
                    else: ?>
                        <div style="grid-column: 1/-1; text-align: center; padding: 40px 20px;">
                            <div class="empty-icon" style="background: var(--green-100); color: var(--green-700);"><i data-lucide="sparkles" class="lucide-lg"></i></div>
                            <h3 style="margin-bottom: 4px;">No expired food</h3>
                            <p class="text-muted text-sm">Nice work keeping your pantry tidy.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>

        <?php if ($view == 'pantry'): ?>
            <div class="suggestions-grid" style="margin-top: 40px;">

                <div class="suggestion-block">
                    <h3 class="danger">
                        <i data-lucide="alert-circle" class="lucide-sm"></i> Use these soon
                    </h3>
                    <?php
                    $q_waste = mysqli_query($conn, "SELECT id, item_name, expiry_date, user_id, current_quantity, unit FROM inventory
                                                    WHERE is_bought=1 AND user_id IN ($scope_list) AND expiry_date IS NOT NULL
                                                    AND DATEDIFF(expiry_date, '$today') <= 3
                                                    AND DATEDIFF(expiry_date, '$today') >= 0
                                                    ORDER BY expiry_date ASC");
                    if (mysqli_num_rows($q_waste) > 0):
                        while ($w = mysqli_fetch_assoc($q_waste)):
                            $d = (strtotime($w['expiry_date']) - strtotime($today)) / 86400;
                            $wq = (float)($w['current_quantity'] ?? 1);
                    ?>
                        <label class="soon-item" style="cursor: pointer;">
                            <input type="checkbox" name="item_ids[]" value="<?php echo (int)$w['id']; ?>" class="item-checkbox"
                                   data-mine="<?php echo ((int)$w['user_id'] === (int)$user_id) ? '1' : '0'; ?>"
                                   data-name="<?php echo htmlspecialchars($w['item_name']); ?>"
                                   data-qty="<?php echo $wq; ?>"
                                   data-unit="<?php echo htmlspecialchars($w['unit'] ?? 'pcs'); ?>"
                                   style="accent-color: var(--red-500);">
                            <div class="soon-bar"></div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($w['item_name']); ?></div>
                                <div class="days"><?php echo floor($d); ?> days until expiry</div>
                            </div>
                        </label>
                    <?php
                        endwhile;
                    else:
                        echo '<p class="empty-hint">Nothing urgent right now — good job!</p>';
                    endif;
                    ?>
                </div>

                <div class="suggestion-block">
                    <h3 class="success">
                        <i data-lucide="chef-hat" class="lucide-sm"></i> Recipes you can make
                    </h3>
                    <div class="recipe-scroll">
                        <?php
                        $q_all = mysqli_query($conn, "SELECT DISTINCT item_name FROM inventory WHERE is_bought=1 AND expiry_date IS NOT NULL AND user_id IN ($scope_list) AND (expiry_date >= '$today' OR expiry_date IS NULL)");
                        $all_norm = [];
                        while ($r = mysqli_fetch_assoc($q_all)) { $all_norm[] = normalize($r['item_name']); }

                        if (!empty($all_norm)) {
                            $recipes = mysqli_query($conn, "SELECT * FROM recipes");
                            $recipe_found = false;

                            while ($rec = mysqli_fetch_assoc($recipes)) {
                                $split = shelflife_split_ingredients($rec['ingredients']);
                                $needed = array_map('normalize', $split['needed']);
                                $matches = array_intersect($needed, $all_norm);
                                $count_matches = count($matches);
                                $count_needed  = count($needed);
                                $missing_count = $count_needed - $count_matches;

                                if ($count_needed > 0 && $count_matches > 0 && $missing_count <= 3) {
                                    $recipe_found = true;
                                    $ready = ($missing_count == 0) ? "Ready to cook" : "Needs " . $missing_count . " more";
                                    $warn  = ($missing_count == 0) ? "" : "warn";
                                    echo '<a href="recipe_recommendations.php?recipe_id=' . (int)$rec['id'] . '" class="recipe-link">';
                                    echo '<div class="recipe-item">';
                                    echo '<div class="title">' . htmlspecialchars($rec['recipe_name']) . '</div>';
                                    echo '<div class="status ' . $warn . '">' . $ready . '</div>';
                                    echo '</div></a>';
                                }
                            }
                            if (!$recipe_found) echo '<p class="empty-hint">No recipes match your inventory yet.</p>';
                        } else {
                            echo '<p class="empty-hint">Add food to see what you can cook.</p>';
                        }
                        ?>
                    </div>
                </div>

            </div>
        <?php endif; ?>

        <div id="action-bar" class="action-bar">
            <span class="count" id="selected-count">0 selected</span>
            <div style="display: flex; gap: 8px;">
                <?php if ($view == 'expired'): ?>
                    <input type="hidden" name="history_status" id="history_status" value="">
                    <button type="button" onclick="showDeleteDialog()" class="bar-btn delete">
                        <i data-lucide="trash-2" class="lucide-sm"></i> Delete
                    </button>
                    <input type="submit" id="deleteSubmitBtn" formaction="bulk_delete.php" style="display: none;">
                <?php else: ?>
                    <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                    <button type="submit" formaction="recipe_recommendations.php" class="bar-btn cook">
                        <i data-lucide="chef-hat" class="lucide-sm"></i> Cook
                    </button>
                    <button type="submit" formaction="bulk_edit.php" class="bar-btn edit">
                        <i data-lucide="pencil" class="lucide-sm"></i> Edit
                    </button>
                    <button type="button" id="setExpiryBtn" onclick="showExpiryDialog()" class="bar-btn expiry" disabled style="opacity:0.5; cursor:not-allowed;" title="Select at least one of your own items">
                        <i data-lucide="calendar" class="lucide-sm"></i> Set expiry
                    </button>
                    <input type="hidden" name="history_status" id="history_status" value="">
                    <button type="button" onclick="openUseAmountModal('consumed')" class="bar-btn eat">
                        <i data-lucide="utensils" class="lucide-sm"></i> Ate it
                    </button>
                    <button type="button" onclick="openUseAmountModal('wasted')" class="bar-btn delete">
                        <i data-lucide="trash-2" class="lucide-sm"></i> Threw away
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- M3: Use-amount modal -->
    <div id="useAmountModal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <h3 style="margin-bottom: 6px;">How much did you use?</h3>
            <p class="text-muted text-sm" style="margin-bottom: 20px;">Enter the amount used for each item. If you used it all, just leave the max amount.</p>

            <div id="useAmountList" style="display: flex; flex-direction: column; gap: 12px; max-height: 300px; overflow-y: auto; margin-bottom: 20px;"></div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary" style="flex:1;" onclick="closeUseAmountModal()">Cancel</button>
                <button type="button" class="btn btn-primary" style="flex:1;" onclick="submitUseAmount()">Confirm</button>
            </div>
        </div>
    </div>

    <div id="deleteDialog" class="modal-overlay">
        <div class="modal-card" style="max-width: 420px;">
            <h3 style="margin-bottom: 6px;">What happened to these items?</h3>
            <p class="text-muted text-sm" style="margin-bottom: 20px;">This helps track your savings.</p>

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-primary" onclick="confirmDeleteStatus('consumed')" style="flex: 1;">
                    <i data-lucide="check" class="lucide-sm"></i> I used them
                </button>
                <button type="button" class="btn" onclick="confirmDeleteStatus('wasted')" style="flex: 1; background: var(--red-50); color: var(--red-700);">
                    <i data-lucide="x" class="lucide-sm"></i> They went bad
                </button>
            </div>

            <button type="button" onclick="closeDeleteDialog()" class="btn btn-ghost" style="width: 100%; margin-top: 12px;">
                Cancel
            </button>
        </div>
    </div>

    <!-- Bulk expiry dialog -->
    <div id="expiryDialog" class="modal-overlay">
        <div class="modal-card" style="max-width: 460px;">
            <h3 style="margin-bottom: 6px;">Set expiry date</h3>
            <p class="text-muted text-sm" style="margin-bottom: 20px;" id="expirySummary">Choose a date for the selected items.</p>

            <div style="display:flex; gap:8px; margin-bottom:16px;">
                <label class="expiry-mode-opt selected" onclick="selectMode('date')">
                    <input type="radio" name="expiry_mode" value="date" checked>
                    <span>Specific date</span>
                </label>
                <label class="expiry-mode-opt" onclick="selectMode('days')">
                    <input type="radio" name="expiry_mode" value="days">
                    <span>Days from today</span>
                </label>
            </div>

            <div id="modeDateBlock">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--ink-700); margin-bottom:6px;">Expires on</label>
                <input type="date" id="expiry_date_input"
                       style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--r-sm); font-family:var(--font-body); font-size:14px; box-sizing:border-box;">
            </div>

            <div id="modeDaysBlock" style="display:none;">
                <label style="display:block; font-size:12px; font-weight:600; color:var(--ink-700); margin-bottom:6px;">Days from today</label>
                <input type="number" id="expiry_days_input" min="0" max="3650" value="7"
                       style="width:100%; padding:12px 14px; border:1px solid var(--border); border-radius:var(--r-sm); font-family:var(--font-body); font-size:14px; box-sizing:border-box;">
                <div class="text-xs text-muted" style="margin-top:6px;" id="daysPreview"></div>
            </div>

            <div style="display:flex; gap:8px; margin-top:20px;">
                <button type="button" onclick="closeExpiryDialog()" class="btn btn-secondary" style="flex:1;">Cancel</button>
                <button type="button" onclick="applyBulkExpiry()" class="btn btn-primary" style="flex:1;">
                    <i data-lucide="check" class="lucide-sm"></i> Apply to selected
                </button>
            </div>
        </div>
    </div>

</main>

<!-- Recipe modal -->
<div id="recipeModal" class="modal-overlay">
    <div class="modal-card">
        <button class="close-modal" id="closeModal"><i data-lucide="x"></i></button>
        <div id="modalBody"><p class="text-muted">Loading recipe...</p></div>
    </div>
</div>

<!-- Profile modal -->
<div id="profileModal" class="modal-overlay">
    <div style="background: white; border-radius: var(--r-xl); width: 420px; max-width: 90vw; height: 640px; padding: 0; overflow: hidden; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <button id="closeProfile" style="position: absolute; top: 14px; right: 14px; z-index: 10; background: var(--sand-100); border: none; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--ink-500); font-size: 18px; font-weight: bold; line-height: 1;">&times;</button>
        <iframe src="profile.php" style="width: 100%; height: 100%; border: none;" scrolling="no"></iframe>
    </div>
</div>

<!-- Item photo lightbox -->
<div id="itemLightbox" class="item-lightbox" onclick="closeItemLightbox()">
    <img id="itemLightboxImg" src="" alt="">
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
    lucide.createIcons();

    // Profile modal
    const profileModal = document.getElementById('profileModal');
    const profileTrigger = document.getElementById('profileTrigger');
    const closeProfile = document.getElementById('closeProfile');

    if (profileTrigger) {
        profileTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            profileModal.style.display = 'flex';
        });
    }
    if (closeProfile) {
        closeProfile.onclick = () => profileModal.style.display = 'none';
    }

    // Recipe modal
    const recipeModal = document.getElementById('recipeModal');
    const modalBody = document.getElementById('modalBody');
    const closeModal = document.getElementById('closeModal');

    document.querySelectorAll('.recipe-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('href');
            recipeModal.style.display = 'flex';
            modalBody.innerHTML = '<p class="text-muted">Fetching recipe...</p>';

            fetch(url)
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    modalBody.innerHTML = doc.body.innerHTML;
                    lucide.createIcons();
                })
                .catch(() => { modalBody.innerHTML = '<p>Error loading recipe.</p>'; });
        });
    });

    if (closeModal) closeModal.onclick = () => recipeModal.style.display = 'none';

    window.addEventListener('click', (e) => {
        if (e.target === recipeModal) recipeModal.style.display = 'none';
        if (e.target === profileModal) { profileModal.style.display = 'none'; location.reload(); }
    });

    // Selection bar
    function updateActionBar() {
        const checked = document.querySelectorAll('.item-checkbox:checked');
        const count = checked.length;
        const mineCount = document.querySelectorAll('.item-checkbox:checked[data-mine="1"]').length;

        const actionBar = document.getElementById('action-bar');
        const selectedCountText = document.getElementById('selected-count');
        if (selectedCountText) {
            let label = count + (count === 1 ? " item selected" : " items selected");
            if (count > 0 && mineCount === 0) {
                label += " (none of yours)";
            }
            selectedCountText.innerText = label;
        }
        if (actionBar) actionBar.classList.toggle('active', count > 0);

        const expiryBtn = document.getElementById('setExpiryBtn');
        if (expiryBtn) {
            const canEdit = mineCount > 0;
            expiryBtn.disabled = !canEdit;
            expiryBtn.style.opacity = canEdit ? '1' : '0.5';
            expiryBtn.style.cursor = canEdit ? 'pointer' : 'not-allowed';
            expiryBtn.title = canEdit ? 'Set expiry date for your items' : 'Select at least one of your own items';
        }
    }

    document.querySelectorAll('.food-card, .waste-card, .soon-item').forEach(card => {
        card.addEventListener('click', function(e) {
            const checkbox = this.querySelector('.item-checkbox');
            if (!checkbox) return;
            if (e.target !== checkbox) checkbox.checked = !checkbox.checked;
            this.classList.toggle('selected', checkbox.checked);
            updateActionBar();
        });
    });

    function toggleAll(checked) {
        document.querySelectorAll('.item-checkbox').forEach(cb => {
            cb.checked = checked;
            const parent = cb.closest('.food-card, .waste-card, .soon-item');
            if (parent) parent.classList.toggle('selected', checked);
        });
        updateActionBar();
    }

    // ---------- M3: Use-amount modal ----------
    let currentUseStatus = 'consumed';

    function openUseAmountModal(status) {
        currentUseStatus = status;
        const checked = document.querySelectorAll('.item-checkbox:checked');
        if (checked.length === 0) {
            if (window.showToast) window.showToast('Please select at least one item.', 'warning');
            return;
        }

        const list = document.getElementById('useAmountList');
        list.innerHTML = '';

        checked.forEach(cb => {
            const name = cb.dataset.name || 'Unknown item';
            const maxQty = parseFloat(cb.dataset.qty) || 1;
            const unit = cb.dataset.unit || 'pcs';

            const row = document.createElement('div');
            row.className = 'use-amount-row';
            row.innerHTML =
                '<div class="ua-name">' + name + '</div>' +
                '<input type="number" class="ua-input" data-id="' + cb.value + '" value="' + maxQty + '" min="0.01" step="0.01" max="' + maxQty + '">' +
                '<div class="ua-unit">' + unit + '</div>';
            list.appendChild(row);
        });

        document.getElementById('useAmountModal').style.display = 'flex';
    }

    function closeUseAmountModal() {
        document.getElementById('useAmountModal').style.display = 'none';
    }

    function submitUseAmount() {
        const form = document.getElementById('bulkForm');
        if (!form) return;

        // Clean any previous used_amounts inputs
        form.querySelectorAll('input[name^="used_amounts"]').forEach(el => el.remove());

        document.querySelectorAll('#useAmountList .ua-input').forEach(input => {
            const itemId = input.dataset.id;
            const val = parseFloat(input.value);
            const max = parseFloat(input.max);
            const finalVal = (val > 0 && val <= max) ? val : max;
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'used_amounts[' + itemId + ']';
            hidden.value = finalVal;
            form.appendChild(hidden);
        });

        document.getElementById('history_status').value = currentUseStatus;

        // Set the action target and submit
        form.setAttribute('action', 'bulk_delete.php');
        form.submit();
    }

    document.getElementById('useAmountModal').addEventListener('click', function(e) {
        if (e.target === this) closeUseAmountModal();
    });

    // ---------- Delete confirmation dialog (Waste Bin only — full delete, no amount prompt) ----------
    function showDeleteDialog() {
        document.getElementById('deleteDialog').style.display = 'flex';
    }
    function closeDeleteDialog() {
        document.getElementById('deleteDialog').style.display = 'none';
    }
    function confirmDeleteStatus(status) {
        document.getElementById('history_status').value = status;
        document.getElementById('deleteSubmitBtn').click();
        closeDeleteDialog();
    }

    // ---------- Bulk expiry dialog ----------
    function showExpiryDialog() {
        const mineCount = document.querySelectorAll('.item-checkbox:checked[data-mine="1"]').length;
        if (mineCount === 0) {
            if (window.showToast) window.showToast('You can only set expiry for your own items.', 'warning');
            return;
        }
        document.getElementById('expirySummary').textContent =
            'Set the same expiry date for your ' + mineCount + ' selected item' + (mineCount === 1 ? '' : 's') + '. Other members\' items will be skipped.';

        const d = new Date();
        d.setDate(d.getDate() + 7);
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        document.getElementById('expiry_date_input').value = d.getFullYear() + '-' + mm + '-' + dd;

        selectMode('date');
        document.getElementById('expiryDialog').style.display = 'flex';
    }

    function closeExpiryDialog() {
        document.getElementById('expiryDialog').style.display = 'none';
    }

    function selectMode(mode) {
        document.querySelectorAll('input[name="expiry_mode"]').forEach(function (r) {
            r.checked = (r.value === mode);
        });
        document.querySelectorAll('.expiry-mode-opt').forEach(function (label) {
            const radio = label.querySelector('input');
            label.classList.toggle('selected', radio.checked);
        });
        document.getElementById('modeDateBlock').style.display = (mode === 'date') ? 'block' : 'none';
        document.getElementById('modeDaysBlock').style.display = (mode === 'days') ? 'block' : 'none';
        if (mode === 'days') updateDaysPreview();
    }

    function updateDaysPreview() {
        const days = parseInt(document.getElementById('expiry_days_input').value || '0', 10);
        const d = new Date();
        d.setDate(d.getDate() + days);
        const opts = { month: 'short', day: 'numeric', year: 'numeric' };
        document.getElementById('daysPreview').textContent =
            'Will set expiry to ' + d.toLocaleDateString('en-US', opts) + '.';
    }

    document.getElementById('expiry_days_input').addEventListener('input', updateDaysPreview);

    function applyBulkExpiry() {
        const mode = document.querySelector('input[name="expiry_mode"]:checked').value;
        const fd = new FormData();

        document.querySelectorAll('.item-checkbox:checked[data-mine="1"]').forEach(function (cb) {
            fd.append('item_ids[]', cb.value);
        });

        fd.append('mode', mode);
        fd.append('scope', '<?php echo $scope; ?>');
        if (window.SHELFLIFE_CSRF) fd.append('csrf_token', window.SHELFLIFE_CSRF);

        if (mode === 'date') {
            const v = document.getElementById('expiry_date_input').value;
            if (!v) { alert('Please pick a date.'); return; }
            fd.append('expiry_date', v);
        } else {
            const v = document.getElementById('expiry_days_input').value;
            if (v === '' || isNaN(v) || parseInt(v, 10) < 0) {
                alert('Please enter a valid number of days.');
                return;
            }
            fd.append('days', v);
        }

        fetch('bulk_set_expiry.php', { method: 'POST', body: fd })
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(() => {
                location.href = 'dashboard.php?view=pantry&scope=' +
                    encodeURIComponent('<?php echo $scope; ?>') + '&msg=bulk_expiry';
            })
            .catch(() => {
                if (window.showToast) window.showToast('Could not update expiry dates.', 'error');
            });
    }

    // Toast confirmation on return
    (function () {
        const params = new URLSearchParams(window.location.search);
        const m = params.get('msg');
        if (!window.showToast) return;
        if (m === 'bulk_expiry')    window.showToast('Expiry dates updated.', 'success');
        else if (m === 'invalid_date') window.showToast('Invalid date — please try again.', 'error');
        else if (m === 'error')        window.showToast('Something went wrong.', 'error');
    })();

    // ---------- Item photo lightbox ----------
    function openItemLightbox(src) {
        document.getElementById('itemLightboxImg').src = src;
        document.getElementById('itemLightbox').classList.add('open');
    }
    function closeItemLightbox() {
        document.getElementById('itemLightbox').classList.remove('open');
        document.getElementById('itemLightboxImg').src = '';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeItemLightbox();
    });

    // ---------- Trends chart ----------
    (function () {
        const canvas = document.getElementById('trendsChart');
        const emptyBox = document.getElementById('trendsEmpty');
        if (!canvas || typeof Chart === 'undefined') return;

        const scope = '<?php echo $scope; ?>';

        fetch('chart_data.php?type=trends&scope=' + encodeURIComponent(scope))
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (!data.success) {
                    canvas.style.display = 'none';
                    emptyBox.style.display = 'block';
                    return;
                }

                const hasData = data.saved.some(v => v > 0) || data.wasted.some(v => v > 0);
                if (!hasData) {
                    canvas.style.display = 'none';
                    emptyBox.style.display = 'block';
                    return;
                }

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Saved',
                                data: data.saved,
                                borderColor: '#2d5a3d',
                                backgroundColor: 'rgba(45, 90, 61, 0.08)',
                                borderWidth: 2.5,
                                tension: 0.35,
                                fill: true,
                                pointRadius: 4,
                                pointBackgroundColor: '#2d5a3d',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                            },
                            {
                                label: 'Wasted',
                                data: data.wasted,
                                borderColor: '#b54a3f',
                                backgroundColor: 'rgba(181, 74, 63, 0.08)',
                                borderWidth: 2.5,
                                tension: 0.35,
                                fill: true,
                                pointRadius: 4,
                                pointBackgroundColor: '#b54a3f',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: {
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    boxWidth: 8,
                                    boxHeight: 8,
                                    padding: 14,
                                    font: { family: "'Inter', system-ui, sans-serif", size: 12, weight: '600' },
                                    color: '#3a423d'
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1a1f1c',
                                padding: 10,
                                cornerRadius: 8,
                                titleFont: { family: "'Inter', sans-serif", size: 12, weight: '700' },
                                bodyFont: { family: "'Inter', sans-serif", size: 12 },
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.dataset.label + ': ₱' + ctx.parsed.y.toFixed(2);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    color: '#8a9088',
                                    font: { family: "'Inter', sans-serif", size: 11, weight: '600' }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(212, 207, 197, 0.35)', drawBorder: false },
                                ticks: {
                                    color: '#8a9088',
                                    font: { family: "'Inter', sans-serif", size: 11 },
                                    callback: function (v) { return '₱' + v; }
                                }
                            }
                        }
                    }
                });
            })
            .catch(() => {
                canvas.style.display = 'none';
                emptyBox.style.display = 'block';
            });
    })();
</script>

</body>
</html>