<?php
include "db_connect.php";
include "verification_helper.php";

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$msg = "";
$msg_type = "error";
$success = false;

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php");
    exit();
}

$email_esc = mysqli_real_escape_string($conn, $email);

// Handle verify submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code']) && !isset($_POST['resend'])) {
    $code = trim($_POST['code']);

    $q = mysqli_query($conn, "SELECT id, username, is_verified, verification_code, verification_expires
                              FROM users WHERE email = '$email_esc'");
    $user = mysqli_fetch_assoc($q);

    if (!$user) {
        $msg = "We couldn't find that account. Please register again.";
    } elseif ($user['is_verified']) {
        $success = true;
    } elseif ($user['verification_code'] === null) {
        $msg = "No verification code found. Click 'Resend code' below.";
    } elseif ($user['verification_code'] !== $code) {
        $msg = "That code doesn't match. Please check your email and try again.";
    } elseif (strtotime($user['verification_expires']) < time()) {
        $msg = "That code has expired. Click 'Resend code' below.";
    } else {
        mysqli_query($conn, "UPDATE users
                             SET is_verified = 1,
                                 verification_code = NULL,
                                 verification_expires = NULL
                             WHERE id = " . (int)$user['id']);
        $success = true;
    }
}

// Handle resend
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resend'])) {
    $q = mysqli_query($conn, "SELECT id, username, is_verified FROM users WHERE email = '$email_esc'");
    $user = mysqli_fetch_assoc($q);

    if (!$user) {
        $msg = "We couldn't find that account.";
    } elseif ($user['is_verified']) {
        $success = true;
    } else {
        $code = generate_verification_code();
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        mysqli_query($conn, "UPDATE users SET verification_code='$code', verification_expires='$expires' WHERE id=" . (int)$user['id']);

        if (send_verification_email($email, $user['username'], $code)) {
            $msg = "New code sent to " . htmlspecialchars($email) . ".";
            $msg_type = "success";
        } else {
            $msg = "Could not send email. Please wait a moment and try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; min-height: 100vh; margin: 0; }
        .verify-wrap { width: 100%; max-width: 420px; }
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
        .verify-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 40px 32px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-lg);
            text-align: center;
        }
        .verify-card h1 { margin-bottom: 8px; }
        .verify-card .subtitle {
            color: var(--ink-500); font-size: 14px;
            margin-bottom: 28px; line-height: 1.55;
        }
        .verify-card .subtitle strong { color: var(--ink-800); }
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
            outline: none;
            border-color: var(--green-500);
            background: var(--surface);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .verify-btn {
            width: 100%; padding: 13px;
            font-size: 14px; margin-bottom: 8px;
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
            padding: 12px 14px; border-radius: var(--r-sm);
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
    <div class="verify-wrap">
        <div class="brand-mark">
            <div class="logo-icon"><i data-lucide="leaf"></i></div>
            <span class="logo-text">ShelfLife</span>
        </div>

        <div class="verify-card">
            <?php if ($success): ?>
                <div class="success-icon">
                    <i data-lucide="check-circle" style="width:32px;height:32px;"></i>
                </div>
                <h1>Email verified</h1>
                <p class="subtitle">Your account is now active. You can sign in.</p>
                <a href="login.php" class="btn btn-primary verify-btn">
                    <i data-lucide="log-in" class="lucide-sm"></i> Continue to sign in
                </a>
            <?php else: ?>
                <h1>Check your email</h1>
                <p class="subtitle">
                    We sent a 6-digit code to<br>
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
                    <button type="submit" class="btn btn-primary verify-btn">
                        <i data-lucide="check" class="lucide-sm"></i> Verify email
                    </button>
                </form>

                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <button type="submit" name="resend" value="1" class="resend-btn">
                        <i data-lucide="refresh-cw" style="width:12px;height:12px;vertical-align:-2px;margin-right:4px;"></i>
                        Resend code
                    </button>
                </form>

                <p class="footer-text">
                    Wrong email? <a href="register.php">Start over</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // Auto-format code input (only digits)
        const codeInput = document.querySelector('.code-input');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }
    </script>
</body>
</html>