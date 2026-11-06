<?php
require_once "session_config.php";
include "db_connect.php";
include "verification_helper.php";

$msg = "";
$msg_type = "error";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } else {
        $email_esc = mysqli_real_escape_string($conn, $email);

        $q = mysqli_query($conn, "SELECT id, username, is_verified FROM users WHERE email = '$email_esc'");

        if (mysqli_num_rows($q) === 1) {
            $user = mysqli_fetch_assoc($q);

            // Generate + save reset code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            $uid = (int)$user['id'];

            mysqli_query($conn, "UPDATE users SET reset_code='$code', reset_expires='$expires' WHERE id=$uid");

            if (send_password_reset_email($email, $user['username'], $code)) {
                header("Location: verify_reset.php?email=" . urlencode($email));
                exit();
            } else {
                $msg = "Could not send email. Please wait a moment and try again.";
            }
        } else {
            // Security: never reveal whether the email exists.
            // Redirect to the same page as a real account so the URL
            // and behavior look identical.
            header("Location: verify_reset.php?email=" . urlencode($email));
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; min-height: 100vh; margin: 0; }
        .forgot-wrap { width: 100%; max-width: 420px; }
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
        .forgot-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 40px 32px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-lg);
        }
        .forgot-card h1 { text-align: center; margin-bottom: 6px; }
        .forgot-card .subtitle {
            text-align: center; color: var(--ink-500);
            font-size: 14px; margin-bottom: 28px; line-height: 1.55;
        }
        .field { margin-bottom: 16px; }
        .field label {
            display: block; font-weight: 600; font-size: 12.5px;
            color: var(--ink-700); margin-bottom: 7px;
        }
        .flash {
            padding: 11px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 600;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .flash.error   { background: var(--red-50); color: var(--red-700); }
        .flash.success { background: var(--green-50); color: var(--green-700); }
        .forgot-btn { width: 100%; padding: 13px; font-size: 14px; margin-top: 8px; }
        .footer-text {
            text-align: center; margin-top: 24px;
            font-size: 13px; color: var(--ink-500);
        }
        .footer-text a { color: var(--green-600); font-weight: 600; text-decoration: none; }
        .footer-text a:hover { color: var(--green-700); }
    </style>
</head>
<body class="page page--auth">
    <div class="forgot-wrap">
        <div class="brand-mark">
            <div class="logo-icon"><i data-lucide="leaf"></i></div>
            <span class="logo-text">ShelfLife</span>
        </div>

        <div class="forgot-card">
            <h1>Forgot your password?</h1>
            <p class="subtitle">Enter your email and we'll send you a 6-digit code to reset it.</p>

            <?php if ($msg): ?>
                <div class="flash <?php echo $msg_type; ?>">
                    <i data-lucide="<?php echo $msg_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" class="lucide-sm"></i>
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary forgot-btn">
                    <i data-lucide="mail" class="lucide-sm"></i> Send reset code
                </button>
            </form>

            <p class="footer-text">
                Remembered it? <a href="login.php">Back to sign in</a>
            </p>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>lucide.createIcons();</script>
</body>
</html>