<?php
require_once "session_config.php";
include "db_connect.php";
include "log_helper.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$admin_id   = (int)$_SESSION['user_id'];
$admin_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username, profile_pic FROM users WHERE id = '$admin_id'"));
$admin_real = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1"));
$admin_real_id = $admin_real ? (int)$admin_real['id'] : 1;

// ------- Tab routing -------
$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, ['overview', 'users', 'support', 'logs'], true)) $tab = 'overview';

// ------- Handle user edit -------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $uid   = (int)$_POST['user_id'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role  = mysqli_real_escape_string($conn, $_POST['role']);
    if (!in_array($role, ['user', 'admin'], true)) $role = 'user';

    if (!empty($_POST['password'])) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET email='$email', role='$role', password='$hash' WHERE id=$uid");
        log_admin_action($conn, $admin_id, 'user.update', 'user', $uid, "Email + role + password updated");
    } else {
        mysqli_query($conn, "UPDATE users SET email='$email', role='$role' WHERE id=$uid");
        log_admin_action($conn, $admin_id, 'user.update', 'user', $uid, "Email + role updated");
    }
    header("Location: admin.php?tab=users&msg=updated");
    exit();
}

// ------- Handle user delete -------
if (isset($_GET['delete_id'])) {
    $uid = (int)$_GET['delete_id'];
    if ($uid !== $admin_id) {
        $uname = mysqli_fetch_assoc(mysqli_query($conn, "SELECT username FROM users WHERE id=$uid"));

        // Delete any chat image files this user was part of
        $img_q = mysqli_query($conn, "SELECT attachment_path FROM messages
                                     WHERE (sender_id = $uid OR receiver_id = $uid)
                                     AND attachment_path IS NOT NULL");
        while ($img = mysqli_fetch_assoc($img_q)) {
            $file = __DIR__ . '/' . $img['attachment_path'];
            if (is_file($file)) @unlink($file);
        }

        mysqli_query($conn, "DELETE FROM inventory WHERE user_id = $uid");
        mysqli_query($conn, "DELETE FROM messages WHERE sender_id = $uid OR receiver_id = $uid");
        mysqli_query($conn, "DELETE FROM users WHERE id = $uid");
        mysqli_query($conn, "DELETE FROM recipes WHERE user_id = $uid");
        mysqli_query($conn, "DELETE FROM meal_plan WHERE user_id = $uid");
        mysqli_query($conn, "DELETE FROM recipe_saves WHERE user_id = $uid");

        log_admin_action($conn, $admin_id, 'user.delete', 'user', $uid, "Deleted user " . ($uname['username'] ?? '#'.$uid));
    }
    header("Location: admin.php?tab=users&msg=deleted");
    exit();
}

// ------- OVERVIEW stats -------
if ($tab === 'overview') {
    $total_users    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role='user'"))['c'];
    $total_admins   = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE role='admin'"))['c'];
    $total_items    = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM inventory WHERE is_bought=1"))['c'];
    $total_recipes  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM recipes"))['c'];
    $total_waste_n  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM item_history WHERE status='wasted'"))['c'];
    $total_consumed_n = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM item_history WHERE status='consumed'"))['c'];

    // Platform-wide money
    $saved  = (float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(price),0) s FROM item_history WHERE status='consumed' AND price IS NOT NULL"))['s'];
    $wasted = (float)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(price),0) s FROM item_history WHERE status='wasted' AND price IS NOT NULL"))['s'];
    $platform_total = $saved + $wasted;
    $platform_eff   = $platform_total > 0 ? ($saved / $platform_total) * 100 : 0;

    // New users last 7 days
    $new_users_7 = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"))['c'];

    // Recent activity feed (mix of admin actions + new users)
    $recent_q = mysqli_query($conn, "SELECT al.action, al.details, al.created_at, u.username
                                     FROM admin_logs al
                                     LEFT JOIN users u ON al.admin_id = u.id
                                     ORDER BY al.created_at DESC LIMIT 8");
    $recent_actions = [];
    while ($r = mysqli_fetch_assoc($recent_q)) $recent_actions[] = $r;
}

// ------- USERS list -------
// ------- USERS list -------
if ($tab === 'users') {
    $uq     = trim($_GET['user_q'] ?? '');
    $filter = $_GET['filter'] ?? 'all';
    if (!in_array($filter, ['all', 'verified', 'pending'], true)) $filter = 'all';

    $clauses = [];
    if ($uq !== '') {
        $uq_esc    = mysqli_real_escape_string($conn, $uq);
        $clauses[] = "(username LIKE '%$uq_esc%' OR email LIKE '%$uq_esc%')";
    }
    if ($filter === 'verified') $clauses[] = "is_verified = 1";
    if ($filter === 'pending')  $clauses[] = "is_verified = 0";

    $where = !empty($clauses) ? "WHERE " . implode(' AND ', $clauses) : '';

    // Counts for the filter chips
    $count_all      = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users"))['c'];
    $count_verified = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE is_verified = 1"))['c'];
    $count_pending  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users WHERE is_verified = 0"))['c'];

    $userList = mysqli_query($conn, "SELECT id, username, email, role, is_verified, created_at FROM users $where ORDER BY id ASC");
}

// Unread support count — available on every tab so the badge always shows
$unread_support_q = mysqli_query($conn, "SELECT COUNT(DISTINCT sender_id) c
                                          FROM messages
                                          WHERE receiver_id = '$admin_real_id'
                                          AND is_read = 0
                                          AND sender_id IN (SELECT id FROM users WHERE role != 'admin')");
$unread_support_count = (int)mysqli_fetch_assoc($unread_support_q)['c'];

// ------- SUPPORT list -------
if ($tab === 'support') {
    $msg_list = mysqli_query($conn, "SELECT DISTINCT users.id, users.username, users.email,
                  (SELECT COUNT(*) FROM messages WHERE sender_id = users.id AND receiver_id = '$admin_real_id' AND is_read = 0) as unread_count
                  FROM users
                  JOIN messages ON users.id = messages.sender_id OR users.id = messages.receiver_id
                  WHERE users.role != 'admin'
                  ORDER BY unread_count DESC");
}

// ------- LOGS -------
if ($tab === 'logs') {
    $log_type = $_GET['log_type'] ?? 'admin';
    if (!in_array($log_type, ['admin', 'notif'], true)) $log_type = 'admin';

    if ($log_type === 'admin') {
        $logs = mysqli_query($conn, "SELECT al.*, u.username AS admin_name
                                     FROM admin_logs al
                                     LEFT JOIN users u ON al.admin_id = u.id
                                     ORDER BY al.created_at DESC LIMIT 200");
    } else {
        $logs = mysqli_query($conn, "SELECT nl.*, u.username
                                     FROM notification_logs nl
                                     LEFT JOIN users u ON nl.user_id = u.id
                                     ORDER BY nl.sent_at DESC LIMIT 200");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: white;
            border-bottom: 1px solid var(--sand-200);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
        }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--ink-900); display: flex; align-items: center; justify-content: center; color: white; }
        .nav-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

        main { max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        /* Tabs */
        .admin-tabs {
            display: flex; gap: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            overflow-x: auto;
        }
        .admin-tabs {
            -ms-overflow-style: none;
            scrollbar-width: none;
            overflow-x: auto;      /* keep swipe scrolling */
            -webkit-overflow-scrolling: touch;
        }
        .admin-tabs::-webkit-scrollbar { display: none; }

        @media (max-width: 768px) {
            .admin-tab {
                padding: 10px 12px;
                font-size: 12px;
                gap: 6px;
                white-space: nowrap;
            }
            .admin-tab .lucide-sm { width: 13px; height: 13px; }
            .admin-tab .badge { font-size: 10px; padding: 1px 6px; }
        }
        .admin-tab {
            padding: 12px 18px;
            font-family: var(--font-display);
            font-weight: 700; font-size: 13px;
            color: var(--ink-500);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.15s;
        }
        .admin-tab:hover { color: var(--ink-800); }
        .admin-tab.active { color: var(--green-700); border-bottom-color: var(--green-500); }
        .admin-tab .badge { background: var(--sand-100); color: var(--ink-600); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--r-full); }
        .admin-tab.active .badge { background: var(--green-100); color: var(--green-700); }
        .admin-tab .badge.danger { background: var(--red-50); color: var(--red-700); }

        .flash { background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-700); padding: 12px 16px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }

        /* Overview cards */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: var(--r-md);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon.green { background: var(--green-100); color: var(--green-700); }
        .stat-icon.amber { background: #fdf3e8; color: #b45309; }
        .stat-icon.red   { background: var(--red-50);   color: var(--red-700); }
        .stat-icon.blue  { background: #eaf2fa;         color: #2d5a7f; }
        .stat-value { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--ink-900); line-height: 1; }
        .stat-label { font-size: 11.5px; color: var(--ink-500); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }

        .platform-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            margin-bottom: 24px;
        }
        .platform-header { display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .platform-header h2 { font-size: 15px; }
        .platform-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 24px; }
        .platform-stat .num { font-family: var(--font-display); font-size: 26px; font-weight: 700; line-height: 1; }
        .platform-stat .lbl { font-size: 11.5px; color: var(--ink-500); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-top: 4px; }
        .platform-stat .sub { font-size: 12px; color: var(--ink-500); margin-top: 4px; }

        .activity-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .activity-header { padding: 16px 22px; border-bottom: 1px solid var(--border); background: var(--sand-50); }
        .activity-header h2 { font-size: 14px; }
        .activity-row { padding: 12px 22px; border-bottom: 1px solid var(--sand-100); display: flex; align-items: flex-start; gap: 12px; font-size: 13px; }
        .activity-row:last-child { border-bottom: none; }
        .activity-row .time { color: var(--ink-400); font-size: 11.5px; flex-shrink: 0; min-width: 110px; }
        .activity-row .who { font-weight: 600; color: var(--ink-800); }
        .activity-row .what { color: var(--ink-500); }

        /* Users tab */
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; }
        .panel-header { padding: 16px 22px; border-bottom: 1px solid var(--border); background: var(--sand-50); display: flex; align-items: center; gap: 10px; justify-content: space-between; flex-wrap: wrap; }
        .panel-header h2 { font-size: 14px; }
        .search-input { padding: 9px 14px; border: 1px solid var(--border); border-radius: var(--r-sm); font-size: 13px; width: 260px; max-width: 100%; font-family: var(--font-body); }
        .search-input:focus { outline: none; border-color: var(--green-400); box-shadow: 0 0 0 4px var(--green-50); }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 22px; text-align: left; border-bottom: 1px solid var(--sand-100); font-size: 13px; }
        th { color: var(--ink-500); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; background: var(--sand-50); }
        tr:last-child td { border-bottom: none; }

        .u-name { font-weight: 700; color: var(--ink-800); }
        .u-email { color: var(--ink-500); font-size: 12px; margin-top: 2px; }
        .u-meta { color: var(--ink-500); font-size: 11.5px; }

        /* Support tab */
        .chat-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 22px;
            border-bottom: 1px solid var(--sand-100);
            text-decoration: none;
            transition: 0.15s;
        }
        .chat-item:hover { background: var(--sand-50); }
        .chat-item .avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; }
        .chat-item .name { font-weight: 700; font-size: 13.5px; color: var(--ink-800); }
        .chat-item .email { font-size: 11.5px; color: var(--ink-500); margin-top: 2px; }
        .chat-item .unread-dot { width: 8px; height: 8px; background: var(--red-500); border-radius: 50%; flex-shrink: 0; }

        .empty-panel { padding: 60px 22px; text-align: center; color: var(--ink-500); font-size: 13px; }

        /* Logs tab */
        .log-row {
            padding: 12px 22px;
            border-bottom: 1px solid var(--sand-100);
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 16px;
            font-size: 13px;
        }
        .log-row:last-child { border-bottom: none; }
        .log-row .time { color: var(--ink-400); font-size: 11.5px; }
        .log-row .body { color: var(--ink-700); }
        .log-row .body strong { color: var(--ink-900); }
        .log-action {
            display: inline-flex; padding: 2px 8px; border-radius: var(--r-full);
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.04em;
            margin-right: 6px;
        }
        .log-action.green { background: var(--green-50); color: var(--green-700); }
        .log-action.red   { background: var(--red-50);   color: var(--red-700); }
        .log-action.amber { background: #fdf3e8;         color: #b45309; }
        .log-action.gray  { background: var(--sand-100); color: var(--ink-500); }

        .sub-tabs {
            display: flex; gap: 4px; padding: 12px 22px;
            border-bottom: 1px solid var(--sand-100);
            background: var(--sand-50);
        }
        .sub-tab {
            padding: 6px 14px;
            border-radius: var(--r-full);
            font-size: 12px; font-weight: 600;
            color: var(--ink-500);
            text-decoration: none;
            transition: 0.15s;
        }
        .sub-tab:hover { color: var(--ink-800); }
        .sub-tab.active { background: var(--surface); color: var(--green-700); box-shadow: var(--sh-xs); }

        /* Modal */
        #editModal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); z-index: 2000; padding: 20px; align-items: center; justify-content: center; }
        #editModal.open { display: flex; }
        .modal-card { background: white; border-radius: var(--r-xl); padding: 28px; width: 100%; max-width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .modal-card h3 { margin-bottom: 20px; }
        .modal-card .field { margin-bottom: 14px; }
        .modal-card label { display: block; font-weight: 600; font-size: 12px; color: var(--ink-700); margin-bottom: 6px; }

        .profile-modal-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(4px); display: none; justify-content: center; align-items: center; z-index: 2000; padding: 20px; }
    </style>
</head>
<body class="page page--admin">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>
<nav class="topnav">
    <a href="admin.php" class="brand">
        <div class="logo-icon"><i data-lucide="shield"></i></div>
        ShelfLife Admin
    </a>
    <div class="nav-actions">
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i data-lucide="home" class="lucide-sm"></i> My pantry
        </a>
        <a href="admin_recipes.php" class="btn btn-secondary btn-sm">
            <i data-lucide="chef-hat" class="lucide-sm"></i> Recipes
        </a>
        <a href="profile.php" class="user-chip" id="profileTrigger" style="display: flex; align-items: center; gap: 10px; padding: 5px 12px 5px 5px; border-radius: var(--r-full); border: 1px solid var(--sand-200); background: white; text-decoration: none; cursor: pointer;">
            <img src="uploads/<?php echo htmlspecialchars($admin_info['profile_pic'] ?? ''); ?>"
                 style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($admin_info['username'] ?? 'Admin'); ?>&background=6B8E23&color=fff'">
            <span style="font-family: var(--font-display); font-weight: 700; font-size: 12.5px; color: var(--ink-800);"><?php echo htmlspecialchars($admin_info['username'] ?? 'admin'); ?></span>
        </a>
    </div>
</nav>

<main>
    <header class="page-header">
        <h1>Admin control</h1>
        <p>Platform overview, user management, and system logs.</p>
    </header>

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash">
            <i data-lucide="check-circle" class="lucide-sm"></i>
            <?php
                $m = $_GET['msg'];
                if ($m === 'updated') echo "User updated.";
                elseif ($m === 'deleted') echo "User deleted.";
            ?>
        </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="admin-tabs">
        <a href="?tab=overview" class="admin-tab <?php echo $tab==='overview'?'active':''; ?>">
            <i data-lucide="layout-dashboard" class="lucide-sm"></i> Overview
        </a>
        <a href="?tab=users" class="admin-tab <?php echo $tab==='users'?'active':''; ?>">
            <i data-lucide="users" class="lucide-sm"></i> Users
            <span class="badge"><?php echo $total_users ?? ''; ?></span>
        </a>
        <a href="?tab=support" class="admin-tab <?php echo $tab==='support'?'active':''; ?>">
            <i data-lucide="message-circle" class="lucide-sm"></i> Support
            <?php if ($unread_support_count > 0): ?>
                <span class="badge danger"><?php echo $unread_support_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="?tab=logs" class="admin-tab <?php echo $tab==='logs'?'active':''; ?>">
            <i data-lucide="scroll-text" class="lucide-sm"></i> System logs
        </a>
    </div>

    <!-- ======================== OVERVIEW ======================== -->
    <?php if ($tab === 'overview'): ?>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="users"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i data-lucide="package"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_items; ?></div>
                    <div class="stat-label">Items tracked</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i data-lucide="chef-hat"></i></div>
                <div>
                    <div class="stat-value"><?php echo $total_recipes; ?></div>
                    <div class="stat-label">Recipes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber"><i data-lucide="user-plus"></i></div>
                <div>
                    <div class="stat-value"><?php echo $new_users_7; ?></div>
                    <div class="stat-label">New this week</div>
                </div>
            </div>
        </div>

        <div class="platform-card">
            <div class="platform-header">
                <i data-lucide="trending-up" style="color: var(--green-500);"></i>
                <h2>Platform-wide food stats</h2>
            </div>
            <div class="platform-grid">
                <div class="platform-stat">
                    <div class="num" style="color: var(--green-700);">₱<?php echo number_format($saved, 2); ?></div>
                    <div class="lbl">Saved platform-wide</div>
                    <div class="sub"><?php echo $total_consumed_n; ?> items consumed in time</div>
                </div>
                <div class="platform-stat">
                    <div class="num" style="color: var(--red-700);">₱<?php echo number_format($wasted, 2); ?></div>
                    <div class="lbl">Wasted platform-wide</div>
                    <div class="sub"><?php echo $total_waste_n; ?> items expired</div>
                </div>
                <div class="platform-stat">
                    <div class="num" style="color: var(--ink-900);"><?php echo round($platform_eff); ?>%</div>
                    <div class="lbl">Platform efficiency</div>
                    <div class="sub">of tracked value used</div>
                </div>
            </div>
        </div>

        <div class="activity-panel">
            <div class="activity-header">
                <h2>Recent admin activity</h2>
            </div>
            <?php if (empty($recent_actions)): ?>
                <div class="empty-panel">No activity yet.</div>
            <?php else: ?>
                <?php foreach ($recent_actions as $a):
                    $action_class = 'gray';
                    if (strpos($a['action'], 'delete') !== false) $action_class = 'red';
                    elseif (strpos($a['action'], 'update') !== false) $action_class = 'amber';
                    elseif (strpos($a['action'], 'add') !== false)    $action_class = 'green';
                ?>
                    <div class="activity-row">
                        <div class="time"><?php echo date('M j, g:i A', strtotime($a['created_at'])); ?></div>
                        <div>
                            <span class="log-action <?php echo $action_class; ?>"><?php echo htmlspecialchars($a['action']); ?></span>
                            <span class="who"><?php echo htmlspecialchars($a['username'] ?? 'Unknown'); ?></span>
                            <span class="what"><?php echo htmlspecialchars($a['details'] ?? ''); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- ======================== USERS ======================== -->
    <?php if ($tab === 'users'): ?>

            <div class="panel">
                <div class="panel-header">
                    <h2>User management</h2>
                    <form method="GET" style="margin:0; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="tab" value="users">
                        <?php if ($filter !== 'all'): ?>
                            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <?php endif; ?>
                        <input type="text" name="user_q" class="search-input" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($_GET['user_q'] ?? ''); ?>">
                    </form>
                </div>

                <div style="padding: 12px 22px; border-bottom: 1px solid var(--sand-100); background: var(--sand-50); display: flex; gap: 6px; flex-wrap: wrap;">
                    <?php
                        $base = "admin.php?tab=users";
                        if ($uq !== '') $base .= "&user_q=" . urlencode($uq);
                    ?>
                    <a href="<?php echo $base; ?>&filter=all" class="sub-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All <span style="opacity:0.6; margin-left:4px;"><?php echo $count_all; ?></span>
                    </a>
                    <a href="<?php echo $base; ?>&filter=verified" class="sub-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">
                        Verified <span style="opacity:0.6; margin-left:4px;"><?php echo $count_verified; ?></span>
                    </a>
                    <a href="<?php echo $base; ?>&filter=pending" class="sub-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        Pending <span style="opacity:0.6; margin-left:4px;"><?php echo $count_pending; ?></span>
                    </a>
            </div>

            <table>
                <thead>
                    <tr><th>User</th><th>Role</th><th>Verified</th><th>Joined</th><th style="text-align:right;">Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($userList) === 0): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--ink-500);">No users match your search.</td></tr>
                    <?php else: ?>
                        <?php while ($user = mysqli_fetch_assoc($userList)): ?>
                            <tr>
                                <td>
                                    <div class="u-name"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="u-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="badge badge-red">Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['is_verified'])): ?>
                                        <span class="badge badge-green">Verified</span>
                                    <?php else: ?>
                                        <span class="badge badge-amber">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="u-meta"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <button class="btn btn-secondary btn-sm"
                                            onclick="openEditModal(
                                                '<?php echo (int)$user['id']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['username'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['email'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['role'])); ?>'
                                            )">
                                            <i data-lucide="pencil" class="lucide-sm"></i> Edit
                                        </button>
                                        <?php if ((int)$user['id'] !== $admin_id): ?>
                                            <a href="admin.php?delete_id=<?php echo (int)$user['id']; ?>"
                                               class="btn btn-sm"
                                               style="background: var(--red-50); color: var(--red-600);"
                                               onclick="return confirm('Delete this user and all their data?')">
                                                <i data-lucide="trash-2" class="lucide-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <!-- ======================== SUPPORT ======================== -->
    <?php if ($tab === 'support'): ?>

        <div class="panel">
            <div class="panel-header"><h2>Support inbox</h2></div>
            <?php if (mysqli_num_rows($msg_list) === 0): ?>
                <div class="empty-panel">No conversations yet.</div>
            <?php else: ?>
                <?php while ($chat = mysqli_fetch_assoc($msg_list)): ?>
                    <a href="admin_chat.php?user_id=<?php echo (int)$chat['id']; ?>" class="chat-item">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($chat['username']); ?>&background=6B8E23&color=fff" class="avatar" alt="">
                        <div style="flex-grow:1;">
                            <div class="name"><?php echo htmlspecialchars($chat['username']); ?></div>
                            <div class="email"><?php echo htmlspecialchars($chat['email']); ?></div>
                        </div>
                        <?php if ($chat['unread_count'] > 0): ?><div class="unread-dot"></div><?php endif; ?>
                        <i data-lucide="chevron-right" class="lucide-sm" style="color: var(--ink-400);"></i>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

    <!-- ======================== LOGS ======================== -->
    <?php if ($tab === 'logs'): ?>

        <div class="panel">
            <div class="sub-tabs">
                <a href="?tab=logs&log_type=admin" class="sub-tab <?php echo ($log_type ?? 'admin') === 'admin' ? 'active' : ''; ?>">
                    <i data-lucide="shield" style="width:12px;height:12px;vertical-align:-2px;margin-right:4px;"></i> Admin actions
                </a>
                <a href="?tab=logs&log_type=notif" class="sub-tab <?php echo ($log_type ?? '') === 'notif' ? 'active' : ''; ?>">
                    <i data-lucide="mail" style="width:12px;height:12px;vertical-align:-2px;margin-right:4px;"></i> Notification history
                </a>
            </div>

                        <?php if ($log_type === 'notif'):
                $last_run_q = mysqli_query($conn, "SELECT MAX(sent_at) AS last_run, COUNT(*) AS total
                                                    FROM notification_logs
                                                    WHERE DATE(sent_at) = CURDATE()");
                $last_run = mysqli_fetch_assoc($last_run_q);
            ?>
                <?php if ($last_run && $last_run['last_run']): ?>
                    <div style="padding: 14px 22px; background: var(--green-50); border-bottom: 1px solid var(--green-200); font-size: 12.5px; color: var(--green-700); display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="activity" style="width:14px;height:14px;"></i>
                        <strong>Last send today:</strong>
                        <?php echo date('g:i A', strtotime($last_run['last_run'])); ?>
                        <span style="color: var(--ink-500);">(<?php echo (int)$last_run['total']; ?> total notifications today)</span>
                    </div>
                <?php else: ?>
                    <div style="padding: 14px 22px; background: var(--sand-50); border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--ink-500); display: flex; align-items: center; gap: 8px;">
                        <i data-lucide="activity" style="width:14px;height:14px;"></i>
                        No notifications sent today yet.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (mysqli_num_rows($logs) === 0): ?>
                <div class="empty-panel">
                    <?php echo $log_type === 'admin' ? 'No admin actions logged yet.' : 'No notifications sent yet.'; ?>
                </div>
            <?php else: ?>
                <?php if ($log_type === 'admin'): ?>
                    <?php while ($l = mysqli_fetch_assoc($logs)):
                        $cls = 'gray';
                        if (strpos($l['action'], 'delete') !== false) $cls = 'red';
                        elseif (strpos($l['action'], 'update') !== false) $cls = 'amber';
                        elseif (strpos($l['action'], 'add') !== false)    $cls = 'green';
                    ?>
                        <div class="log-row">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($l['created_at'])); ?></div>
                            <div class="body">
                                <span class="log-action <?php echo $cls; ?>"><?php echo htmlspecialchars($l['action']); ?></span>
                                <strong><?php echo htmlspecialchars($l['admin_name'] ?? 'Unknown'); ?></strong>
                                <?php echo htmlspecialchars($l['details'] ?? ''); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <?php while ($l = mysqli_fetch_assoc($logs)): ?>
                        <div class="log-row">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($l['sent_at'])); ?></div>
                            <div class="body">
                                <span class="log-action <?php echo $l['status'] === 'sent' ? 'green' : 'red'; ?>"><?php echo $l['status']; ?></span>
                                <strong><?php echo htmlspecialchars($l['username'] ?? 'Unknown'); ?></strong>
                                was notified about <strong><?php echo htmlspecialchars($l['item_name'] ?? 'an item'); ?></strong>
                                <?php echo htmlspecialchars($l['email'] ?? ''); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</main>

<!-- Edit modal -->
<div id="editModal">
    <div class="modal-card">
        <h3 id="modalTitle">Edit user</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="modal_user_id">

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" id="modal_email" required>
            </div>
            <div class="field">
                <label>Role</label>
                <select name="role" id="modal_role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="field">
                <label>New password <span class="text-muted text-xs">(leave blank to keep current)</span></label>
                <input type="password" name="password" placeholder="••••••••">
            </div>
            <div style="display: flex; gap: 8px; margin-top: 20px;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Save changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Profile modal -->
<div id="profileModal" class="profile-modal-overlay">
    <div style="background: white; border-radius: var(--r-xl); width: 420px; max-width: 90vw; height: 640px; padding: 0; overflow: hidden; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <button id="closeProfile" style="position: absolute; top: 14px; right: 14px; z-index: 10; background: var(--sand-100); border: none; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--ink-500); font-size: 16px; font-weight: bold;">&times;</button>
        <iframe src="profile.php" style="width: 100%; height: 100%; border: none;" scrolling="no"></iframe>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    function openEditModal(id, username, email, role) {
        document.getElementById('modal_user_id').value = id;
        document.getElementById('modal_email').value = email;
        document.getElementById('modal_role').value = role;
        document.getElementById('modalTitle').innerText = "Edit: " + username;
        document.getElementById('editModal').classList.add('open');
    }
    function closeModal() { document.getElementById('editModal').classList.remove('open'); }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    const profileModal = document.getElementById('profileModal');
    const profileTrigger = document.getElementById('profileTrigger');
    const closeProfile = document.getElementById('closeProfile');
    if (profileTrigger) profileTrigger.addEventListener('click', function(e) { e.preventDefault(); profileModal.style.display = 'flex'; });
    if (closeProfile) closeProfile.onclick = () => { profileModal.style.display = 'none'; location.reload(); };
    if (profileModal) profileModal.addEventListener('click', function(e) { if (e.target === this) { profileModal.style.display = 'none'; location.reload(); } });
</script>

</body>
</html>