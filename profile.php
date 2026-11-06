<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir);

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $original_name = $_FILES["profile_img"]["name"];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    $is_real_image = @getimagesize($_FILES['profile_img']['tmp_name']) !== false;

    if (!in_array($ext, $allowed_ext) || !$is_real_image) {
        $msg = "Only JPG, PNG, or GIF images are allowed.";
        $msg_type = "error";
    } else {
        $file_name = time() . "_" . basename($original_name);
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $target_file)) {
            mysqli_query($conn, "UPDATE users SET profile_pic = '$file_name' WHERE id = '$user_id'");
            $msg = "Profile photo updated.";
            $msg_type = "success";
        } else {
            $msg = "Upload failed. Please try again.";
            $msg_type = "error";
        }
    }
}

$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: block;
            background: var(--surface);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }
        .profile-page {
            padding: 28px 24px 40px;
            max-width: 380px;
            margin: 0 auto;
            position: relative;
        }

        /* view switching */
        .view { display: none; }
        .view.active { display: block; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ---- main view ---- */
        .profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }
        .avatar-wrap { position: relative; flex-shrink: 0; }
        .avatar {
            width: 64px; height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--green-500);
        }
        .avatar-edit {
            position: absolute;
            bottom: -2px; right: -2px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: var(--green-500);
            color: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            border: 2px solid var(--surface);
        }
        .avatar-edit:hover { background: var(--green-700); }
        .avatar-edit .lucide { width: 12px; height: 12px; }

        .user-meta h2 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 18px;
            color: var(--ink-900);
            margin: 0 0 4px;
            letter-spacing: -0.01em;
        }

        .settings-section { margin-bottom: 24px; }
        .settings-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            color: var(--ink-500);
            margin-bottom: 12px;
        }
        .settings-list {
            background: var(--sand-50);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .settings-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
        }
        .settings-row:last-child { border-bottom: none; }
        .row-label { font-size: 13.5px; font-weight: 500; color: var(--ink-800); }
        .row-hint  { font-size: 11.5px; color: var(--ink-500); margin-top: 2px; }

        .switch {
            position: relative;
            display: inline-block;
            width: 40px; height: 22px;
            flex-shrink: 0;
        }
        .switch input { display: none; }
        .switch .slider {
            position: absolute; inset: 0;
            background: var(--sand-300);
            border-radius: 999px;
            cursor: pointer;
            transition: 0.2s;
        }
        .switch .slider::before {
            content: ""; position: absolute;
            width: 18px; height: 18px;
            left: 2px; top: 2px;
            background: white;
            border-radius: 50%;
            transition: 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .switch input:checked + .slider { background: var(--green-500); }
        .switch input:checked + .slider::before { transform: translateX(18px); }

        .settings-row select {
            width: auto;
            padding: 7px 30px 7px 12px;
            font-size: 12.5px;
            border-radius: var(--r-sm);
            background: var(--surface);
            border: 1px solid var(--border);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7268' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .save-indicator {
            font-size: 11.5px; font-weight: 600;
            color: var(--green-700);
            text-align: right;
            margin-top: 6px;
            height: 14px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .save-indicator.show { opacity: 1; }

        .actions-list {
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow: hidden;
            background: var(--surface);
        }
        .action-btn {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px;
            width: 100%;
            background: none; border: none;
            border-bottom: 1px solid var(--border);
            font-family: var(--font-body);
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ink-800);
            text-align: left;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s;
        }
        .action-btn:last-child { border-bottom: none; }
        .action-btn:hover { background: var(--sand-50); }
        .action-btn.danger { color: var(--red-600); }
        .action-btn .lucide { width: 16px; height: 16px; color: var(--ink-500); }
        .action-btn.danger .lucide { color: var(--red-600); }
        .action-btn .chevron { margin-left: auto; color: var(--ink-300) !important; }

        #profile_img { display: none; }

        .flash {
            padding: 10px 14px;
            border-radius: var(--r-sm);
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 16px;
            text-align: center;
        }
        .flash.success { background: var(--green-50); color: var(--green-700); }
        .flash.error   { background: var(--red-50);   color: var(--red-700); }

        /* ---- switch view (choose account) ---- */
        .view-header {
            display: flex; align-items: center; gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        .back-btn {
            width: 36px; height: 36px;
            border-radius: var(--r-sm);
            background: var(--sand-100);
            color: var(--ink-700);
            border: none;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .back-btn:hover { background: var(--sand-200); }
        .view-title h3 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 17px;
            margin: 0 0 2px;
            color: var(--ink-900);
        }
        .view-title p {
            font-size: 12.5px;
            color: var(--ink-500);
            margin: 0;
        }

        .account-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            max-height: 340px;
            overflow-y: auto;
            padding-right: 6px;
            scrollbar-width: thin;
            scrollbar-color: var(--sand-300) transparent;
        }
        .account-list::-webkit-scrollbar { width: 6px; }
        .account-list::-webkit-scrollbar-track { background: transparent; }
        .account-list::-webkit-scrollbar-thumb { background: var(--sand-300); border-radius: 6px; }
        .account-list::-webkit-scrollbar-thumb:hover { background: var(--sand-400); }

        .account-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            cursor: pointer;
            background: var(--surface);
            transition: all 0.15s;
            text-align: left;
            width: 100%;
            font-family: var(--font-body);
        }
        .account-item:hover {
            border-color: var(--green-500);
            background: var(--green-50);
        }
        .account-item img {
            width: 40px; height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
            object-fit: cover;
        }
        .account-item .info { flex-grow: 1; min-width: 0; }
        .account-item .acc-name {
            font-weight: 600;
            font-size: 13.5px;
            color: var(--ink-900);
        }
        .account-item .acc-email {
            font-size: 11.5px;
            color: var(--ink-500);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .account-item .chevron { color: var(--ink-300); flex-shrink: 0; }

        .divider-line {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0;
            color: var(--ink-400);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .divider-line::before, .divider-line::after {
            content: ""; flex-grow: 1; height: 1px;
            background: var(--border);
        }

        /* ---- modal (used for both password prompt and change password) ---- */
        .pass-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            padding: 20px;
        }
        .pass-modal-overlay.open { display: flex; }
        .pass-modal {
            background: white;
            border-radius: var(--r-xl);
            padding: 28px;
            width: 100%;
            max-width: 340px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
            text-align: center;
            animation: fadeIn 0.2s ease;
        }
        .pass-modal .avatar-lg {
            width: 64px; height: 64px;
            border-radius: 50%;
            margin: 0 auto 12px;
            border: 2px solid var(--green-500);
            object-fit: cover;
        }
        .pass-modal h3 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 17px;
            color: var(--ink-900);
            margin: 0 0 4px;
        }
        .pass-modal p {
            font-size: 12.5px;
            color: var(--ink-500);
            margin: 0 0 20px;
        }
        .pass-input-wrap {
            position: relative;
            margin-bottom: 12px;
            text-align: left;
        }
        .pass-input-wrap input {
            width: 100%;
            padding: 12px 42px 12px 14px;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            font-family: var(--font-body);
            font-size: 13.5px;
            box-sizing: border-box;
            transition: 0.15s;
        }
        .pass-input-wrap input:focus {
            outline: none;
            border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .toggle-pass {
            position: absolute;
            right: 8px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--ink-400);
            cursor: pointer;
            padding: 6px;
            border-radius: var(--r-sm);
            display: flex; align-items: center; justify-content: center;
        }
        .toggle-pass:hover { color: var(--ink-700); background: var(--sand-100); }

        .pass-modal .btn-ghost {
            width: 100%;
            background: none;
            border: none;
            color: var(--ink-500);
            font-family: var(--font-body);
            font-size: 13px;
            cursor: pointer;
            padding: 8px;
            margin-top: 4px;
        }
        .pass-modal .btn-ghost:hover { color: var(--ink-800); }

        .pwd-msg {
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 12px;
            min-height: 16px;
            text-align: center;
        }
    </style>
</head>
<body class="page page--profile">
<?php include "session_guard.php"; ?>

<div class="profile-page">

    <!-- =============================================
         VIEW 1: Main profile
         ============================================= -->
    <div class="view active" id="view-main">

        <?php if ($msg): ?>
            <div class="flash <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="avatar-wrap">
                <img src="uploads/<?php echo htmlspecialchars($user_data['profile_pic']); ?>" class="avatar"
                     onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user_data['username']); ?>&background=2d5a3d&color=fff'" alt="">
                <div class="avatar-edit" onclick="document.getElementById('profile_img').click();">
                    <i data-lucide="camera"></i>
                </div>
            </div>
            <div class="user-meta">
                <h2><?php echo htmlspecialchars($user_data['username']); ?></h2>
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                    <?php if (($user_data['role'] ?? 'user') === 'admin'): ?>
                        <span class="badge badge-red" style="font-size: 10px; padding: 2px 8px;">Admin</span>
                    <?php else: ?>
                        <span class="badge badge-gray" style="font-size: 10px; padding: 2px 8px;">User</span>
                    <?php endif; ?>
                    <span style="font-size: 11.5px; color: var(--ink-500); display: flex; align-items: center; gap: 5px;">
                        <span style="width:6px;height:6px;background:var(--green-500);border-radius:50%;"></span> Active
                    </span>
                </div>
            </div>
            <form id="profileForm" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" name="profile_img" id="profile_img" accept="image/png, image/jpeg, image/gif"
                       onchange="document.getElementById('profileForm').submit();">
            </form>
        </div>

        <div class="settings-section">
            <div class="settings-label">Security</div>
            <div class="settings-list">
                <button type="button" class="action-btn" onclick="openPasswordChange()">
                    <i data-lucide="lock"></i>
                    Change password
                    <i data-lucide="chevron-right" class="chevron"></i>
                </button>
            </div>
        </div>

        <div class="settings-section">
            <div class="settings-label">Notifications</div>
            <div class="settings-list">
                <div class="settings-row">
                    <div>
                        <div class="row-label">Email alerts</div>
                        <div class="row-hint">Get notified before items expire</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" id="email_notify" <?php echo ($user_data['email_notify'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-row">
                    <div class="row-label">Alert before</div>
                    <select id="notify_days">
                        <option value="1" <?php echo ($user_data['notify_days'] ?? 3) == 1 ? 'selected' : ''; ?>>1 day</option>
                        <option value="3" <?php echo ($user_data['notify_days'] ?? 3) == 3 ? 'selected' : ''; ?>>3 days</option>
                        <option value="5" <?php echo ($user_data['notify_days'] ?? 3) == 5 ? 'selected' : ''; ?>>5 days</option>
                        <option value="7" <?php echo ($user_data['notify_days'] ?? 3) == 7 ? 'selected' : ''; ?>>7 days</option>
                    </select>
                </div>
            </div>
            <div class="save-indicator" id="save_indicator">✓ Saved</div>
        </div>

        <div class="settings-section">
            <div class="settings-label">Account</div>
            <div class="actions-list">
                <a href="export_pantry.php" class="action-btn" target="_top">
                    <i data-lucide="download"></i>
                    Export my data
                    <i data-lucide="chevron-right" class="chevron"></i>
                </a>
                <button type="button" class="action-btn" onclick="goToSwitchView()">
                    <i data-lucide="users"></i>
                    Switch account
                    <i data-lucide="chevron-right" class="chevron"></i>
                </button>
                <a href="logout.php" class="action-btn danger" target="_top">
                    <i data-lucide="log-out"></i>
                    Sign out
                </a>
            </div>
        </div>
    </div>

    <!-- =============================================
         VIEW 2: Choose an account
         ============================================= -->
    <div class="view" id="view-switch">
        <div class="view-header">
            <button class="back-btn" onclick="goToMainView()" title="Back">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
            </button>
            <div class="view-title">
                <h3>Choose an account</h3>
                <p>Select who you want to switch to</p>
            </div>
        </div>

        <div class="account-list">
            <?php
            $others = mysqli_query($conn, "SELECT username, email, profile_pic FROM users WHERE id != '$user_id' ORDER BY username ASC");
            if (mysqli_num_rows($others) > 0):
                while ($u = mysqli_fetch_assoc($others)): ?>
                    <button type="button" class="account-item"
                            onclick='openPasswordModal(
                                <?php echo json_encode($u["username"]); ?>,
                                <?php echo json_encode($u["email"] ?? ""); ?>,
                                <?php echo json_encode("uploads/" . ($u["profile_pic"] ?? "")); ?>
                            )'>
                        <img src="uploads/<?php echo htmlspecialchars($u['profile_pic']); ?>"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u['username']); ?>&background=2d5a3d&color=fff'">
                        <div class="info">
                            <div class="acc-name"><?php echo htmlspecialchars($u['username']); ?></div>
                            <div class="acc-email"><?php echo htmlspecialchars($u['email'] ?? ''); ?></div>
                        </div>
                        <i data-lucide="chevron-right" class="chevron" style="width:16px;height:16px;"></i>
                    </button>
                <?php endwhile;
            else: ?>
                <p class="text-muted text-sm" style="text-align:center; padding: 24px 0;">No other accounts found.</p>
            <?php endif; ?>
        </div>

        <div class="divider-line">Or</div>

        <button type="button" class="action-btn" onclick="window.top.location.href='logout.php'" style="border: 1px solid var(--border); border-radius: var(--r-lg); justify-content: center; color: var(--red-600); width: 100%;">
            <i data-lucide="log-out" style="color: var(--red-600);"></i>
            Sign out of this account
        </button>
    </div>

</div>

<!-- =============================================
     Password prompt (switch account)
     ============================================= -->
<div class="pass-modal-overlay" id="passwordModal">
    <div class="pass-modal">
        <img src="" id="pass_avatar" class="avatar-lg" alt="">
        <h3 id="pass_username">—</h3>
        <p id="pass_email">—</p>

        <form action="login.php" method="POST" target="_top">
            <input type="hidden" name="email" id="pass_hidden_username">

            <div class="pass-input-wrap">
                <input type="password" name="password" id="pass_input" placeholder="Enter password" required autocomplete="off">
                <button type="button" class="toggle-pass" onclick="togglePassVisibility()">
                    <i data-lucide="eye" id="pass_eye_icon" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px;">
                <i data-lucide="log-in" class="lucide-sm"></i> Sign in
            </button>
            <button type="button" class="btn-ghost" onclick="closePasswordModal()">Cancel</button>
        </form>
    </div>
</div>

<!-- =============================================
     Change password modal
     ============================================= -->
<div class="pass-modal-overlay" id="passwordChangeModal">
    <div class="pass-modal">
        <h3>Change password</h3>
        <p>Enter your current password and choose a new one.</p>

        <form id="passwordChangeForm" onsubmit="submitPasswordChange(event)">
            <div class="pass-input-wrap">
                <input type="password" id="current_password" placeholder="Current password" required autocomplete="current-password">
                <button type="button" class="toggle-pass" onclick="toggleField('current_password', this)">
                    <i data-lucide="eye" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <div class="pass-input-wrap">
                <input type="password" id="new_password" placeholder="New password (min 6)" required minlength="6" autocomplete="new-password">
                <button type="button" class="toggle-pass" onclick="toggleField('new_password', this)">
                    <i data-lucide="eye" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <div class="pass-input-wrap">
                <input type="password" id="confirm_password" placeholder="Confirm new password" required minlength="6" autocomplete="new-password">
                <button type="button" class="toggle-pass" onclick="toggleField('confirm_password', this)">
                    <i data-lucide="eye" style="width:16px;height:16px;"></i>
                </button>
            </div>

            <div class="pwd-msg" id="pwd_msg"></div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px;">
                <i data-lucide="check" class="lucide-sm"></i> Update password
            </button>
            <button type="button" class="btn-ghost" onclick="closePasswordChange()">Cancel</button>
        </form>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    // ---- View switching ----
    function goToSwitchView() {
        document.getElementById('view-main').classList.remove('active');
        document.getElementById('view-switch').classList.add('active');
    }
    function goToMainView() {
        document.getElementById('view-switch').classList.remove('active');
        document.getElementById('view-main').classList.add('active');
    }

    // ---- Switch account: password modal ----
    function openPasswordModal(username, email, avatarUrl) {
        document.getElementById('pass_hidden_username').value = email;
        document.getElementById('pass_username').textContent = username;
        document.getElementById('pass_email').textContent = email || '';
        document.getElementById('pass_input').value = '';
        document.getElementById('pass_input').type = 'password';

        const avatar = document.getElementById('pass_avatar');
        avatar.src = avatarUrl || ('https://ui-avatars.com/api/?name=' + encodeURIComponent(username) + '&background=2d5a3d&color=fff');
        avatar.onerror = function() {
            this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(username) + '&background=2d5a3d&color=fff';
        };

        document.getElementById('passwordModal').classList.add('open');
        setTimeout(() => document.getElementById('pass_input').focus(), 100);
    }

    function closePasswordModal() {
        document.getElementById('passwordModal').classList.remove('open');
    }

    function togglePassVisibility() {
        const input = document.getElementById('pass_input');
        const icon  = document.getElementById('pass_eye_icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            input.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    document.getElementById('passwordModal').addEventListener('click', function(e) {
        if (e.target === this) closePasswordModal();
    });

    // ---- Change password modal ----
    function openPasswordChange() {
        document.getElementById('passwordChangeModal').classList.add('open');
        document.getElementById('current_password').value = '';
        document.getElementById('new_password').value = '';
        document.getElementById('confirm_password').value = '';
        document.getElementById('pwd_msg').textContent = '';
        setTimeout(() => document.getElementById('current_password').focus(), 100);
    }
    function closePasswordChange() {
        document.getElementById('passwordChangeModal').classList.remove('open');
    }
    document.getElementById('passwordChangeModal').addEventListener('click', function(e) {
        if (e.target === this) closePasswordChange();
    });

    function toggleField(fieldId, btn) {
        const f = document.getElementById(fieldId);
        const icon = btn.querySelector('[data-lucide]');
        if (f.type === 'password') {
            f.type = 'text';
            icon.setAttribute('data-lucide', 'eye-off');
        } else {
            f.type = 'password';
            icon.setAttribute('data-lucide', 'eye');
        }
        lucide.createIcons();
    }

    function submitPasswordChange(e) {
        e.preventDefault();

        const current = document.getElementById('current_password').value;
        const newPwd  = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        const msg     = document.getElementById('pwd_msg');

        if (newPwd !== confirm) {
            msg.textContent = 'New passwords do not match.';
            msg.style.color = 'var(--red-700)';
            return;
        }
        if (newPwd.length < 6) {
            msg.textContent = 'New password must be at least 6 characters.';
            msg.style.color = 'var(--red-700)';
            return;
        }

        msg.textContent = 'Updating...';
        msg.style.color = 'var(--ink-500)';

        const fd = new FormData();
        fd.append('current_password', current);
        fd.append('new_password', newPwd);
        fd.append('csrf_token', <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>);
        fetch('change_password.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = '✓ Password updated.';
                    msg.style.color = 'var(--green-700)';
                    setTimeout(() => {
                        closePasswordChange();
                        msg.textContent = '';
                    }, 1500);
                } else {
                    msg.textContent = data.error || 'Could not update password.';
                    msg.style.color = 'var(--red-700)';
                }
            })
            .catch(() => {
                msg.textContent = 'Could not update password.';
                msg.style.color = 'var(--red-700)';
            });
    }

    // ---- Auto-save notification settings ----
    function autoSave() {
        const emailNotify = document.getElementById('email_notify').checked ? 1 : 0;
        const notifyDays  = document.getElementById('notify_days').value;

        const formData = new FormData();
        formData.append('email_notify', emailNotify);
        formData.append('notify_days',  notifyDays);
        formData.append('csrf_token', <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>);
        fetch('update_settings.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                const ind = document.getElementById('save_indicator');
                ind.textContent = data.success ? '✓ Saved' : 'Could not save';
                ind.style.color = data.success ? 'var(--green-700)' : 'var(--red-600)';
                ind.classList.add('show');
                setTimeout(() => ind.classList.remove('show'), 1500);
            })
            .catch(() => {
                const ind = document.getElementById('save_indicator');
                ind.textContent = 'Could not save';
                ind.style.color = 'var(--red-600)';
                ind.classList.add('show');
                setTimeout(() => ind.classList.remove('show'), 1500);
            });
    }

    document.getElementById('email_notify').addEventListener('change', autoSave);
    document.getElementById('notify_days').addEventListener('change', autoSave);
</script>
</body>
</html>