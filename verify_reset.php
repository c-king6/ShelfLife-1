<?php
require_once "session_config.php";
include "db_connect.php";
include "verification_helper.php";

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$msg = "";
$success = false;
$msg_type = "error";

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: forgot_password.php");
    exit();
}

$email_esc = mysqli_real_escape_string($conn, $email);

// Handle reset submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code'])) {
    $code     = trim($_POST['code']);
    $new_pwd  = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $q = mysqli_query($conn, "SELECT id, username, reset_code, reset_expires FROM users WHERE email = '$email_esc'");
    $user = mysqli_fetch_assoc($q);

    if (!$user) {
        $msg = "That code doesn't match. Please check your email and try again.";
    } elseif (strlen($new_pwd) < 6) {
        $msg = "New password must be at least 6 characters.";
    } elseif ($new_pwd !== $confirm) {
        $msg = "Passwords do not match.";
    } elseif ($user['reset_code'] === null) {
        $msg = "No reset code found. Please request a new one.";
    } elseif ($user['reset_code'] !== $code) {
        $msg = "That code doesn't match. Please check your email and try again.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $msg = "That code has expired. Please request a new one.";
    } else {
        // All checks passed — update password
        $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
        $uid  = (int)$user['id'];

        mysqli_query($conn, "UPDATE users SET
                                password = '$hash',
                                reset_code = NULL,
                                reset_expires = NULL,
                                failed_attempts = 0,
                                locked_until = NULL
                             WHERE id = $uid");

        $success = true;
    }
}

// Handle resend
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend'])) {
    $q = mysqli_query($conn, "SELECT id, username FROM users WHERE email = '$email_esc'");
    $user = mysqli_fetch_assoc($q);

    if ($user) {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $uid = (int)$user['id'];

        mysqli_query($conn, "UPDATE users SET reset_code='$code', reset_expires='$expires' WHERE id=$uid");

        if (send_password_reset_email($email, $user['username'], $code)) {
            $msg = "New code sent to " . htmlspecialchars($email) . ".";
            $msg_type = "success";
        } else {
            $msg = "Could not send email. Please wait a moment.";
            $msg_type = "error";
        }
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; min-height: 100vh; margin: 0; }
        .reset-wrap { width: 100%; max-width: 420px; }
        .brand-mark {
            display: flex; align-items: center; gap: 10px;
            justify-content: center; margin-bottom: 32px;
        }
        .brand-mark .logo-icon {
            width: 40px; height: 40px; border-radius: var(--r-md);
            background: var(--green-500);
            display: flex; align-items: center; justify-content: center; color: white;
        }
        .brand-mark .logo-text {
            font-family: var(--font-display); font-weight: 700;
            font-size: 20px; letter-spacing: -0.02em; color: var(--ink-900);
        }
        .reset-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 40px 32px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-lg);
            text-align: center;
        }
        .reset-card h1 { margin-bottom: 8px; }
        .reset-card .subtitle {
            color: var(--ink-500); font-size: 14px;
            margin-bottom: 28px; line-height: 1.55;
        }
        .reset-card .subtitle strong { color: var(--ink-800); }
        .code-input {
            width: 100%; padding: 18px;
            font-family: 'Courier New', monospace;
            font-size: 28px; letter-spacing: 12px;
            text-align: center; font-weight: 700;
            border: 2px solid var(--border);
            border-radius: var(--r-md);
            background: var(--sand-50);
            color: var(--ink-900);
            box-sizing: border-box;
            margin-bottom: 16px;
        }
        .code-input:focus {
            outline: none; border-color: var(--green-500);
            background: var(--surface);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .field { margin-bottom: 12px; text-align: left; }
        .field label {
            display: block; font-weight: 600; font-size: 12.5px;
            color: var(--ink-700); margin-bottom: 6px;
        }
        .pass-input-wrap { position: relative; margin-bottom: 12px; }
        .pass-input-wrap input {
            width: 100%; padding: 12px 42px 12px 14px;
            border: 1px solid var(--border); border-radius: var(--r-sm);
            font-family: var(--font-body); font-size: 13.5px;
            box-sizing: border-box; transition: 0.15s;
        }
        .pass-input-wrap input:focus {
            outline: none; border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .toggle-pass {
            position: absolute; right: 8px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--ink-400); cursor: pointer;
            padding: 6px; border-radius: var(--r-sm);
            display: flex; align-items: center; justify-content: center;
        }
        .toggle-pass:hover { color: var(--ink-700); background: var(--sand-100); }
        .reset-btn {
            width: 100%; padding: 13px;
            font-size: 14px; margin-top: 6px; margin-bottom: 8px;
        }
        .resend-btn {
            width: 100%; padding: 10px;
            background: none; border: none;
            color: var(--ink-500); cursor: pointer;
            font-family: var(--font-body); font-size: 13px;
            font-weight: 600;
        }
        .resend-btn:hover { color: var(--green-700); }
        .flash {
            padding: 11px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 600;
            margin-bottom: 20px;
            display: flex; align-items: center; gap: 8px;
            text-align: left;
        }
        .flash.error   { background: var(--red-50);   color: var(--red-700); }
        .flash.success { background: var(--green-50); color: var(--green-700); }
        .success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: var(--green-50); color: var(--green-700);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .footer-text {
            text-align: center; margin-top: 20px;
            font-size: 12.5px; color: var(--ink-500);
        }
        .footer-text a { color: var(--green-600); font-weight: 600; }
    </style>
</head>
<body class="page page--auth">
    <div class="reset-wrap">
        <div class="brand-mark">
            <div class="logo-icon"><i data-lucide="leaf"></i></div>
            <span class="logo-text">ShelfLife</span>
        </div>

        <div class="reset-card">
            <?php if ($success): ?>
                <div class="success-icon">
                    <i data-lucide="check-circle" style="width:32px;height:32px;"></i>
                </div>
                <h1>Password reset</h1>
                <p class="subtitle">Your password has been updated. You can now sign in with your new password.</p>
                <a href="login.php" class="btn btn-primary reset-btn">
                    <i data-lucide="log-in" class="lucide-sm"></i> Go to sign in
                </a>
            <?php else: ?>
                <h1>Reset your password</h1>
                <p class="subtitle">
                    Enter the 6-digit code we sent to<br>
                    <strong><?php echo htmlspecialchars($email); ?></strong>
                </p>

                <?php if ($msg): ?>
                    <div class="flash <?php echo $msg_type; ?>">
                        <i data-lucide="<?php echo $msg_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="lucide-sm"></i>
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                    <input type="text" name="code" class="code-input" placeholder="••••••" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required autofocus>

                    <div class="pass-input-wrap">
                        <input type="password" name="new_password" id="new_password" placeholder="New password (min 6)" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pass" onclick="toggleField('new_password', this)">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>

                    <div class="pass-input-wrap">
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-pass" onclick="toggleField('confirm_password', this)">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-primary reset-btn">
                        <i data-lucide="key" class="lucide-sm"></i> Reset password
                    </button>
                </form>

                <form method="POST" style="margin:0;">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" name="resend" value="1" class="resend-btn">
                        <i data-lucide="refresh-cw" style="width:12px;height:12px;vertical-align:-2px;margin-right:4px;"></i>
                        Resend code
                    </button>
                </form>

                <p class="footer-text">
                    Remembered it? <a href="login.php">Back to sign in</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // Auto-format code input
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }

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
    </script>
</body>
</html>