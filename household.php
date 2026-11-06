<?php
require_once "session_config.php";
include "db_connect.php";
require_once "household_helper.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$msg = "";
$msg_type = "info";

// ============================================================
// POST ACTIONS
// ============================================================

// 1. Create household
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $name = trim($_POST['household_name'] ?? '');
    if ($name === '') {
        $msg = "Please enter a household name.";
        $msg_type = "error";
    } else {
        $cur = get_household_info($conn, $user_id);
        if ($cur) {
            $msg = "You're already in a household.";
            $msg_type = "error";
        } else {
            $name_esc = mysqli_real_escape_string($conn, $name);
            $code     = generate_unique_invite_code($conn);
            $code_esc = mysqli_real_escape_string($conn, $code);
            $expires  = date('Y-m-d H:i:s', strtotime('+30 days'));

            mysqli_query($conn, "INSERT INTO households (name, owner_id, invite_code, invite_expires_at)
                                 VALUES ('$name_esc', $user_id, '$code_esc', '$expires')");
            $hid = (int)mysqli_insert_id($conn);

            mysqli_query($conn, "UPDATE users SET household_id = $hid WHERE id = $user_id");

            log_household_activity($conn, $user_id, $user_id, 'created', null, null, $name);
            header("Location: household.php?msg=created");
            exit();
        }
    }
}

// 2. Join household
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'join') {
    $code = strtoupper(trim($_POST['invite_code'] ?? ''));
    if ($code === '') {
        $msg = "Please enter an invite code.";
        $msg_type = "error";
    } else {
        $cur = get_household_info($conn, $user_id);
        if ($cur) {
            $msg = "You're already in a household.";
            $msg_type = "error";
        } else {
            $code_esc = mysqli_real_escape_string($conn, $code);
            $q = mysqli_query($conn, "SELECT * FROM households WHERE invite_code = '$code_esc' LIMIT 1");
            $h = $q ? mysqli_fetch_assoc($q) : null;

            if (!$h) {
                $msg = "That code doesn't match any household.";
                $msg_type = "error";
            } elseif (strtotime($h['invite_expires_at']) < time()) {
                $msg = "That invite code has expired. Ask the owner for a new one.";
                $msg_type = "error";
            } else {
                $hid = (int)$h['id'];
                mysqli_query($conn, "UPDATE users SET household_id = $hid WHERE id = $user_id");
                log_household_activity($conn, $user_id, $user_id, 'joined', null, null, $h['name']);
                header("Location: household.php?msg=joined");
                exit();
            }
        }
    }
}

// 3. Leave household
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'leave') {
    $cur = get_household_info($conn, $user_id);
    if ($cur) {
        $is_owner = ((int)$cur['owner_id'] === $user_id);
        if ($is_owner) {
            $msg = "As the owner, you can't leave. Delete the household instead, or transfer ownership (coming soon).";
            $msg_type = "error";
        } else {
            log_household_activity($conn, $user_id, $user_id, 'left', null, null, $cur['name']);
            mysqli_query($conn, "UPDATE users SET household_id = NULL WHERE id = $user_id");
            header("Location: household.php?msg=left");
            exit();
        }
    }
}

// 4. Delete household (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $cur = get_household_info($conn, $user_id);
    if ($cur && (int)$cur['owner_id'] === $user_id) {
        $hid = (int)$cur['id'];
        mysqli_query($conn, "UPDATE users SET household_id = NULL WHERE household_id = $hid");
        mysqli_query($conn, "DELETE FROM household_activity WHERE household_id = $hid");
        mysqli_query($conn, "DELETE FROM households WHERE id = $hid");
        header("Location: household.php?msg=deleted");
        exit();
    } else {
        $msg = "Only the owner can delete the household.";
        $msg_type = "error";
    }
}

// 5. Rename household (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rename') {
    $new_name = trim($_POST['new_name'] ?? '');
    $cur = get_household_info($conn, $user_id);
    if (!$cur || (int)$cur['owner_id'] !== $user_id) {
        $msg = "Only the owner can rename the household.";
        $msg_type = "error";
    } elseif ($new_name === '') {
        $msg = "Please enter a name.";
        $msg_type = "error";
    } else {
        $new_esc = mysqli_real_escape_string($conn, $new_name);
        $hid = (int)$cur['id'];
        mysqli_query($conn, "UPDATE households SET name = '$new_esc' WHERE id = $hid");
        log_household_activity($conn, $user_id, $user_id, 'renamed', null, null, $new_name);
        header("Location: household.php?msg=renamed");
        exit();
    }
}

// 6. Regenerate invite code (owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regenerate') {
    $cur = get_household_info($conn, $user_id);
    if (!$cur || (int)$cur['owner_id'] !== $user_id) {
        $msg = "Only the owner can regenerate the code.";
        $msg_type = "error";
    } else {
        $code = generate_unique_invite_code($conn);
        $code_esc = mysqli_real_escape_string($conn, $code);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $hid = (int)$cur['id'];
        mysqli_query($conn, "UPDATE households SET invite_code = '$code_esc', invite_expires_at = '$expires' WHERE id = $hid");
        log_household_activity($conn, $user_id, $user_id, 'invite_regenerated', null, null, null);
        header("Location: household.php?msg=regenerated");
        exit();
    }
}

// ============================================================
// FETCH STATE
// ============================================================
$household = get_household_info($conn, $user_id);
$members   = $household ? get_household_members($conn, $user_id) : [];
$activity  = $household ? get_household_activity($conn, $user_id, 100) : [];
$is_owner  = $household && ((int)$household['owner_id'] === $user_id);

// Mark activity as seen when the Activity tab is opened
$tab = $_GET['tab'] ?? 'members';
if (!in_array($tab, ['members', 'activity', 'settings'], true)) $tab = 'members';
if ($household && $tab === 'activity') {
    mark_household_activity_seen($conn, $user_id);
}

// Flash from redirects
if (isset($_GET['msg'])) {
    $map = [
        'created'     => ['Household created!',         'success'],
        'joined'      => ['You joined the household.',  'success'],
        'left'        => ['You left the household.',    'success'],
        'deleted'     => ['Household deleted.',         'success'],
        'renamed'     => ['Household renamed.',         'success'],
        'regenerated' => ['New invite code generated.', 'success'],
    ];
    if (isset($map[$_GET['msg']])) {
        $msg = $map[$_GET['msg']][0];
        $msg_type = $map[$_GET['msg']][1];
    }
}

// Build members lookup for activity feed
$members_by_id = [];
foreach ($members as $m) {
    $members_by_id[(int)$m['id']] = $m;
}

// Activity line formatter
function activity_line($a, $viewer_id, $members_by_id) {
    $actor_id   = (int)$a['actor_id'];
    $target_id  = (int)$a['target_user_id'];
    $actor      = htmlspecialchars($a['actor_name'] ?? 'Someone');
    $is_self    = ($actor_id === $viewer_id);
    $is_my_item = ($target_id === $viewer_id && $target_id !== $actor_id);
    $item       = $a['item_name'] !== null ? htmlspecialchars($a['item_name']) : '';
    $owner_name = $members_by_id[$target_id]['username'] ?? 'someone';

    switch ($a['action']) {
        case 'created':            return "$actor created the household";
        case 'joined':             return "$actor joined the household";
        case 'left':               return "$actor left the household";
        case 'renamed':            return "$actor renamed the household";
        case 'invite_regenerated': return "$actor regenerated the invite code";
        case 'added':              return "$actor added <strong>$item</strong> to their pantry";
        case 'edited':             return "$actor edited <strong>$item</strong>";
        case 'deleted_consumed':
            if ($is_my_item) return "$actor used <strong>$item</strong> from <strong>your</strong> pantry";
            return "$actor used <strong>$item</strong> from " . htmlspecialchars($owner_name) . "'s pantry";
        case 'deleted_wasted':
            if ($is_my_item) return "$actor discarded <strong>$item</strong> from <strong>your</strong> pantry";
            return "$actor discarded <strong>$item</strong> from " . htmlspecialchars($owner_name) . "'s pantry";
        case 'cooked':
            if ($is_my_item) return "$actor cooked with <strong>$item</strong> from <strong>your</strong> pantry";
            return "$actor cooked with <strong>$item</strong> from " . htmlspecialchars($owner_name) . "'s pantry";
        default:                   return "$actor did something";
    }
}

// Group activity by day
$grouped_activity = [];
$today_str = date('Y-m-d');
$yesterday_str = date('Y-m-d', strtotime('-1 day'));
foreach ($activity as $a) {
    $d = date('Y-m-d', strtotime($a['created_at']));
    if ($d === $today_str)     $label = 'Today';
    elseif ($d === $yesterday_str) $label = 'Yesterday';
    else                       $label = date('F j, Y', strtotime($a['created_at']));
    $grouped_activity[$label][] = $a;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav { position: sticky; top: 0; z-index: 100; background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--green-500); display: flex; align-items: center; justify-content: center; color: white; }

        main { max-width: 900px; margin: 0 auto; padding: 32px 24px 100px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        .flash { padding: 12px 16px; border-radius: var(--r-md); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        .flash.success { background: var(--green-50); border: 1px solid var(--green-200); color: var(--green-700); }
        .flash.error   { background: var(--red-50);   border: 1px solid rgba(181,74,63,0.25); color: var(--red-700); }
        .flash.info    { background: var(--sand-100); border: 1px solid var(--border); color: var(--ink-700); }

        /* Setup card (create/join) */
        .setup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 8px;
        }
        @media (max-width: 720px) { .setup-grid { grid-template-columns: 1fr; } }

        .setup-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
        }
        .setup-card .setup-icon {
            width: 48px; height: 48px;
            border-radius: var(--r-md);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .setup-card.create .setup-icon { background: var(--green-50); color: var(--green-700); }
        .setup-card.join   .setup-icon { background: #eaf2fa; color: #2d5a7f; }
        .setup-card h3 { margin-bottom: 6px; font-size: 17px; }
        .setup-card .desc { color: var(--ink-500); font-size: 13px; line-height: 1.55; margin-bottom: 20px; flex-grow: 1; }
        .setup-card .field { margin-bottom: 12px; }
        .setup-card .field label { display: block; font-weight: 600; font-size: 12px; color: var(--ink-700); margin-bottom: 6px; }
        .setup-card input {
            width: 100%; padding: 12px 14px;
            border: 1px solid var(--border); border-radius: var(--r-sm);
            font-family: var(--font-body); font-size: 14px; box-sizing: border-box;
            background: var(--surface);
        }
        .setup-card input:focus {
            outline: none; border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .setup-card input.code-input {
            text-align: center;
            font-family: 'Courier New', monospace;
            font-size: 22px;
            letter-spacing: 8px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Tab nav */
        .hh-tabs {
            display: flex; gap: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .hh-tab {
            padding: 12px 18px;
            font-family: var(--font-display);
            font-weight: 700; font-size: 13px;
            color: var(--ink-500);
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: 0.15s;
        }
        .hh-tab:hover { color: var(--ink-800); }
        .hh-tab.active { color: var(--green-700); border-bottom-color: var(--green-500); }
        .hh-tab .badge { background: var(--sand-100); color: var(--ink-600); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--r-full); }
        .hh-tab.active .badge { background: var(--green-100); color: var(--green-700); }
        .hh-tab .dot { width: 8px; height: 8px; background: var(--red-500); border-radius: 50%; display: inline-block; }

        /* Members */
        .member-row {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 18px;
            border-bottom: 1px solid var(--sand-100);
            background: var(--surface);
        }
        .member-row:first-child { border-top-left-radius: var(--r-lg); border-top-right-radius: var(--r-lg); }
        .member-row:last-child { border-bottom: none; border-bottom-left-radius: var(--r-lg); border-bottom-right-radius: var(--r-lg); }
        .member-list { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; }
        .member-row img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .member-row .info { flex-grow: 1; min-width: 0; }
        .member-row .name { font-weight: 700; font-size: 14px; color: var(--ink-900); }
        .member-row .since { font-size: 11.5px; color: var(--ink-500); margin-top: 2px; }
        .member-row .role-tag {
            padding: 3px 10px; border-radius: var(--r-full);
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        }
        .member-row .role-tag.owner { background: var(--green-100); color: var(--green-700); }
        .member-row .role-tag.member { background: var(--sand-100); color: var(--ink-500); }
        .member-row .you { font-size: 11px; color: var(--ink-400); margin-left: 6px; font-weight: 500; }

        /* Activity feed */
        .activity-day-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.06em; color: var(--ink-500);
            padding: 18px 4px 8px;
        }
        .activity-day-label:first-child { padding-top: 4px; }

        .activity-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--sand-300);
            border-radius: var(--r-md);
            margin-bottom: 6px;
        }
        .activity-item.highlight {
            border-left-color: var(--amber-500);
            background: linear-gradient(90deg, var(--amber-50) 0%, var(--surface) 60%);
        }
        .activity-item img { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; margin-top: 2px; }
        .activity-item .body { flex-grow: 1; min-width: 0; }
        .activity-item .line { font-size: 13px; color: var(--ink-800); line-height: 1.5; }
        .activity-item .line strong { color: var(--ink-900); font-weight: 700; }
        .activity-item .when { font-size: 11px; color: var(--ink-400); margin-top: 4px; }

        /* Settings */
        .settings-block {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            margin-bottom: 20px;
        }
        .settings-block h3 { font-size: 15px; margin-bottom: 4px; }
        .settings-block .sub { font-size: 12.5px; color: var(--ink-500); margin-bottom: 18px; }
        .settings-block .field { margin-bottom: 12px; }
        .settings-block .field label { display: block; font-weight: 600; font-size: 12px; color: var(--ink-700); margin-bottom: 6px; }
        .settings-block input {
            width: 100%; padding: 12px 14px; border: 1px solid var(--border);
            border-radius: var(--r-sm); font-family: var(--font-body); font-size: 14px;
            box-sizing: border-box;
        }

        .invite-box {
            display: flex; align-items: center; gap: 12px;
            background: var(--sand-50);
            border: 2px dashed var(--green-500);
            border-radius: var(--r-md);
            padding: 18px 20px;
            margin-bottom: 14px;
        }
        .invite-box .code {
            font-family: 'Courier New', monospace;
            font-size: 26px; font-weight: 700;
            letter-spacing: 6px;
            color: var(--green-700);
            flex-grow: 1;
            text-align: center;
        }
        .invite-box .copy-btn {
            padding: 10px 14px;
            background: var(--green-500);
            color: white;
            border: none; border-radius: var(--r-sm);
            font-weight: 700; font-size: 12.5px;
            cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .invite-box .copy-btn:hover { background: var(--green-600); }

        .danger-zone {
            background: var(--red-50);
            border: 1px solid rgba(181,74,63,0.25);
            border-radius: var(--r-lg);
            padding: 20px 24px;
        }
        .danger-zone h3 { color: var(--red-700); font-size: 14px; margin-bottom: 6px; }
        .danger-zone p { font-size: 12.5px; color: var(--red-700); opacity: 0.85; margin-bottom: 14px; }

        .btn-danger {
            background: var(--red-500); color: white;
            padding: 10px 16px; border: none; border-radius: var(--r-sm);
            font-weight: 600; font-size: 13px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-danger:hover { background: var(--red-700); }

        .empty-activity { padding: 60px 20px; text-align: center; color: var(--ink-500); font-size: 13px; }
    </style>
</head>
<body class="page page--list">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>

<nav class="topnav">
    <a href="dashboard.php" class="brand">
        <div class="logo-icon"><i data-lucide="leaf"></i></div>
        ShelfLife
    </a>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
        <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
    </a>
</nav>

<main>
    <header class="page-header">
        <h1>Household</h1>
        <p><?php echo $household ? 'Manage members, activity, and settings.' : 'Share your pantry with family or roommates.'; ?></p>
    </header>

    <?php if ($msg): ?>
        <div class="flash <?php echo $msg_type; ?>">
            <i data-lucide="<?php echo $msg_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="lucide-sm"></i>
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (!$household): ?>

        <!-- ================= NO HOUSEHOLD: create or join ================= -->
        <div class="setup-grid">
            <div class="setup-card create">
                <div class="setup-icon"><i data-lucide="home" style="width:24px;height:24px;"></i></div>
                <h3>Create a household</h3>
                <p class="desc">Start a new household and share your pantry with family or roommates. You'll be the owner and can invite others with a code.</p>
                <form method="POST">
                    <div class="field">
                        <label for="household_name">Household name</label>
                        <input type="text" id="household_name" name="household_name" placeholder="e.g. Villaflanca Family" required maxlength="100">
                    </div>
                    <button type="submit" name="action" value="create" class="btn btn-primary" style="width: 100%;">
                        <i data-lucide="plus" class="lucide-sm"></i> Create household
                    </button>
                </form>
            </div>

            <div class="setup-card join">
                <div class="setup-icon"><i data-lucide="users" style="width:24px;height:24px;"></i></div>
                <h3>Join a household</h3>
                <p class="desc">Have an invite code from someone? Enter it below to join their household and start sharing.</p>
                <form method="POST">
                    <div class="field">
                        <label for="invite_code">Invite code</label>
                        <input type="text" id="invite_code" name="invite_code" class="code-input" placeholder="••••••" maxlength="6" required autocomplete="off">
                    </div>
                    <button type="submit" name="action" value="join" class="btn btn-primary" style="width: 100%;">
                        <i data-lucide="log-in" class="lucide-sm"></i> Join household
                    </button>
                </form>
            </div>
        </div>

    <?php else: ?>

        <!-- ================= IN HOUSEHOLD ================= -->

        <div class="hh-tabs">
            <a href="?tab=members" class="hh-tab <?php echo $tab==='members'?'active':''; ?>">
                <i data-lucide="users" class="lucide-sm"></i> Members
                <span class="badge"><?php echo count($members); ?></span>
            </a>
            <a href="?tab=activity" class="hh-tab <?php echo $tab==='activity'?'active':''; ?>">
                <i data-lucide="activity" class="lucide-sm"></i> Activity
                <?php if (has_unread_household_activity($conn, $user_id)): ?>
                    <span class="dot"></span>
                <?php endif; ?>
            </a>
            <a href="?tab=settings" class="hh-tab <?php echo $tab==='settings'?'active':''; ?>">
                <i data-lucide="settings" class="lucide-sm"></i> Settings
            </a>
        </div>

        <?php if ($tab === 'members'): ?>

            <div style="margin-bottom: 16px;">
                <div style="font-family: var(--font-display); font-weight: 700; font-size: 18px; color: var(--ink-900);"><?php echo htmlspecialchars($household['name']); ?></div>
                <div style="font-size: 12.5px; color: var(--ink-500); margin-top: 2px;">
                    <?php echo count($members); ?> member<?php echo count($members) === 1 ? '' : 's'; ?>
                    · Created <?php echo date('M j, Y', strtotime($household['created_at'])); ?>
                </div>
            </div>

            <div class="member-list">
                <?php foreach ($members as $m):
                    $is_me = ((int)$m['id'] === $user_id);
                    $is_the_owner = ((int)$m['id'] === (int)$household['owner_id']);
                ?>
                    <div class="member-row">
                        <img src="uploads/<?php echo htmlspecialchars($m['profile_pic'] ?? 'default_avatar.jpg'); ?>"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($m['username']); ?>&background=2d5a3d&color=fff'">
                        <div class="info">
                            <div class="name">
                                <?php echo htmlspecialchars($m['username']); ?>
                                <?php if ($is_me): ?><span class="you">(you)</span><?php endif; ?>
                            </div>
                            <div class="since">Member since <?php echo date('M Y', strtotime($household['created_at'])); ?></div>
                        </div>
                        <?php if ($is_the_owner): ?>
                            <span class="role-tag owner">Owner</span>
                        <?php else: ?>
                            <span class="role-tag member">Member</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($tab === 'activity'): ?>

            <?php if (empty($activity)): ?>
                <div class="empty-activity">
                    <i data-lucide="activity" style="width:32px;height:32px;opacity:0.5;margin-bottom:12px;"></i>
                    <div>No activity yet.</div>
                    <div style="margin-top: 6px; font-size: 12px;">When members add, cook, or delete items, they'll show up here.</div>
                </div>
            <?php else: ?>
                <?php foreach ($grouped_activity as $label => $items): ?>
                    <div class="activity-day-label"><?php echo htmlspecialchars($label); ?></div>
                    <?php foreach ($items as $a):
                        $is_my_item = ((int)$a['target_user_id'] === $user_id && (int)$a['actor_id'] !== $user_id);
                    ?>
                        <div class="activity-item <?php echo $is_my_item ? 'highlight' : ''; ?>">
                            <img src="uploads/<?php echo htmlspecialchars($a['actor_pic'] ?? 'default_avatar.jpg'); ?>"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($a['actor_name'] ?? 'User'); ?>&background=2d5a3d&color=fff'">
                            <div class="body">
                                <div class="line"><?php echo activity_line($a, $user_id, $members_by_id); ?></div>
                                <div class="when"><?php echo date('g:i A', strtotime($a['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($tab === 'settings'): ?>

            <div class="settings-block">
                <h3>Household name</h3>
                <p class="sub"><?php echo $is_owner ? 'Change the name shown to all members.' : 'Only the owner can change this.'; ?></p>
                <?php if ($is_owner): ?>
                    <form method="POST">
                        <div class="field">
                            <input type="text" name="new_name" value="<?php echo htmlspecialchars($household['name']); ?>" required maxlength="100">
                        </div>
                        <button type="submit" name="action" value="rename" class="btn btn-primary btn-sm">
                            <i data-lucide="check" class="lucide-sm"></i> Save
                        </button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 14px; font-weight: 600; color: var(--ink-900);"><?php echo htmlspecialchars($household['name']); ?></div>
                <?php endif; ?>
            </div>

            <div class="settings-block">
                <h3>Invite code</h3>
                <p class="sub">Share this 6-character code. Anyone with it can join your household until it expires.</p>

                <div class="invite-box">
                    <div class="code" id="inviteCode"><?php echo htmlspecialchars($household['invite_code']); ?></div>
                    <button type="button" class="copy-btn" onclick="copyInviteCode()">
                        <i data-lucide="copy" style="width:14px;height:14px;"></i> Copy
                    </button>
                </div>

                <div style="font-size: 11.5px; color: var(--ink-500); margin-bottom: 14px;">
                    Expires <?php echo date('M j, Y', strtotime($household['invite_expires_at'])); ?>
                </div>

                <?php if ($is_owner): ?>
                    <form method="POST" onsubmit="return confirm('Generate a new code? The current one will stop working immediately.');">
                        <button type="submit" name="action" value="regenerate" class="btn btn-secondary btn-sm">
                            <i data-lucide="refresh-cw" class="lucide-sm"></i> Regenerate code
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="settings-block">
                <h3>Leave household</h3>
                <p class="sub"><?php echo $is_owner ? 'As the owner, you can\'t leave — delete the household instead.' : 'You can leave anytime. Your own items stay with you.'; ?></p>
                <?php if (!$is_owner): ?>
                    <form method="POST" onsubmit="return confirm('Leave this household?');">
                        <button type="submit" name="action" value="leave" class="btn btn-secondary btn-sm">
                            <i data-lucide="log-out" class="lucide-sm"></i> Leave household
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($is_owner): ?>
                <div class="danger-zone">
                    <h3>Delete household</h3>
                    <p>This removes the household and detaches all members. Their own items stay with them. This cannot be undone.</p>
                    <form method="POST" onsubmit="return confirm('Delete the household? All members will be removed. This cannot be undone.');">
                        <button type="submit" name="action" value="delete" class="btn-danger">
                            <i data-lucide="trash-2" style="width:14px;height:14px;"></i> Delete household
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php endif; ?>

</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    function copyInviteCode() {
        const el = document.getElementById('inviteCode');
        if (!el) return;
        const text = el.textContent.trim();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (window.showToast) window.showToast('Invite code copied', 'success');
            }).catch(function () {
                if (window.showToast) window.showToast('Could not copy — code is ' + text, 'warning');
            });
        } else {
            if (window.showToast) window.showToast('Code: ' + text, 'info');
        }
    }

    // Auto-uppercase the invite input as the user types
    const codeInput = document.getElementById('invite_code');
    if (codeInput) {
        codeInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
        });
    }
</script>
</body>
</html>