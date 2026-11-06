<?php
include "db_connect.php";
include "verification_helper.php";

$msg = "";
$form_username = "";
$form_email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $form_username = $username;
    $form_email    = $email;

    if (strlen($username) < 3) {
        $msg = "Username must be at least 3 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
    } else {
        $user_esc  = mysqli_real_escape_string($conn, $username);
        $email_esc = mysqli_real_escape_string($conn, $email);

        // Check username
        $chk = mysqli_query($conn, "SELECT id FROM users WHERE username = '$user_esc'");
        if (mysqli_num_rows($chk) > 0) {
            $msg = "That username is already taken.";
        } else {
            // Check email
            $chk2 = mysqli_query($conn, "SELECT id, is_verified FROM users WHERE email = '$email_esc'");
            if (mysqli_num_rows($chk2) > 0) {
                $existing = mysqli_fetch_assoc($chk2);

                if ($existing['is_verified']) {
                    $msg = "That email is already registered. Try logging in instead.";
                } else {
                    // Unverified account exists — resend code
                    $code = generate_verification_code();
                    $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    mysqli_query($conn, "UPDATE users SET verification_code='$code', verification_expires='$expires' WHERE id=" . (int)$existing['id']);

                    if (send_verification_email($email, $username, $code)) {
                        header("Location: verify_email.php?email=" . urlencode($email));
                        exit();
                    } else {
                        $msg = "Could not send verification email. Please try again.";
                    }
                }
            } else {
                // Fresh account
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $code = generate_verification_code();
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $sql = "INSERT INTO users (username, email, password, is_verified, verification_code, verification_expires)
                        VALUES ('$user_esc', '$email_esc', '$hash', 0, '$code', '$expires')";

                if (mysqli_query($conn, $sql)) {
                    if (send_verification_email($email, $username, $code)) {
                        header("Location: verify_email.php?email=" . urlencode($email));
                        exit();
                    } else {
                        $msg = "Account created but the verification email could not be sent. Please contact support.";
                    }
                } else {
                    $msg = "Something went wrong. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        body { display: flex; align-items: center; justify-content: center; padding: 24px; min-height: 100vh; margin: 0; }
        .register-wrap { width: 100%; max-width: 420px; }
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
        .register-card {
            background: var(--surface);
            border-radius: var(--r-xl);
            padding: 40px 32px;
            border: 1px solid var(--border);
            box-shadow: var(--sh-lg);
        }
        .register-card h1 { text-align: center; margin-bottom: 6px; }
        .register-card .subtitle {
            text-align: center; color: var(--ink-500);
            font-size: 14px; margin-bottom: 28px;
        }
        .field { margin-bottom: 16px; }
        .field label {
            display: block; font-family: var(--font-body);
            font-weight: 600; font-size: 12px;
            color: var(--ink-700); margin-bottom: 6px;
        }
        .error-box {
            background: var(--red-50); color: var(--red-700);
            padding: 10px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 600;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .register-btn {
            width: 100%; padding: 12px;
            font-size: 14px; margin-top: 8px;
        }
        .footer-text {
            text-align: center; margin-top: 24px;
            font-size: 13px; color: var(--ink-500);
        }
        .footer-text a { color: var(--green-500); font-weight: 600; }
        .footer-text a:hover { color: var(--green-700); }
        .hint { font-size: 11.5px; color: var(--ink-500); margin-top: 4px; }
                .strength-bar {
            height: 4px;
            background: var(--sand-100);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .strength-fill {
            height: 100%;
            width: 0%;
            background: var(--red-500);
            border-radius: 4px;
            transition: width 0.25s ease, background 0.25s ease;
        }
        .strength-label {
            font-size: 11.5px;
            font-weight: 600;
            margin-top: 6px;
            min-height: 14px;
        }
        .strength-label.weak   { color: var(--red-700); }
        .strength-label.fair   { color: #b45309; }
        .strength-label.good   { color: var(--green-600); }
        .strength-label.strong { color: var(--green-700); }
    </style>

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2d5a3d">
    <link rel="apple-touch-icon" href="icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ShelfLife">
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('sw.js').catch(function(){});
        });
      }
    </script>

</head>
<body class="page page--auth">
    <div class="register-wrap">
        <div class="brand-mark">
            <div class="logo-icon"><i data-lucide="leaf"></i></div>
            <span class="logo-text">ShelfLife</span>
        </div>

        <div class="register-card">
            <h1>Create your account</h1>
            <p class="subtitle">We'll send a verification code to your email.</p>

            <?php if ($msg): ?>
                <div class="error-box">
                    <i data-lucide="alert-circle" class="lucide-sm"></i>
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Choose a username" required minlength="3" maxlength="50" value="<?php echo htmlspecialchars($form_username); ?>">
                    <div class="hint">This is what other users will see.</div>
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" value="<?php echo htmlspecialchars($form_email); ?>">
                    <div class="hint">We'll send a 6-digit verification code here.</div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
                    <div class="strength-bar" id="strength_bar">
                        <div class="strength-fill" id="strength_fill"></div>
                    </div>
                    <div class="strength-label" id="strength_label"></div>
                </div>

                <button type="submit" class="btn btn-primary register-btn">
                    <i data-lucide="user-plus" class="lucide-sm"></i> Send verification code
                </button>
            </form>

            <p class="footer-text">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        const pwdField   = document.getElementById('password');
        const fillBar    = document.getElementById('strength_fill');
        const labelText  = document.getElementById('strength_label');

        function checkStrength(pwd) {
            if (pwd === '') return { score: 0, label: '', cls: '' };

            let score = 0;
            if (pwd.length >= 6)  score++;
            if (pwd.length >= 10) score++;
            if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++;
            if (/[0-9]/.test(pwd)) score++;
            if (/[^A-Za-z0-9]/.test(pwd)) score++;

            if (score <= 1) return { score: 15, label: 'Weak — add more characters', cls: 'weak' };
            if (score === 2) return { score: 40, label: 'Fair — try adding numbers or capitals', cls: 'fair' };
            if (score === 3) return { score: 65, label: 'Good password', cls: 'good' };
            if (score === 4) return { score: 85, label: 'Strong password', cls: 'strong' };
            return { score: 100, label: 'Very strong password', cls: 'strong' };
        }

        pwdField.addEventListener('input', function () {
            const { score, label, cls } = checkStrength(this.value);
            fillBar.style.width = score + '%';

            // Colour the bar based on strength
            if (cls === 'weak')        fillBar.style.background = 'var(--red-500)';
            else if (cls === 'fair')   fillBar.style.background = '#c77b3f';
            else                       fillBar.style.background = 'var(--green-500)';

            labelText.textContent = label;
            labelText.className = 'strength-label ' + cls;
        });
    </script>
</body>
</html>