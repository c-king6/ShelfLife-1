<?php
require_once "session_config.php";
include "db_connect.php";
require_once "remember_helper.php";

// Prune expired remember-me tokens opportunistically.
// Runs on every login-page load — cheap, keeps the table from growing forever.
cleanup_expired_remember_tokens($conn);

// If a remember-me cookie is valid, auto-login
// Skip remember-me auto-login on POST — allows switching accounts
// while already logged in (the profile switch-account form uses POST).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && try_remember_login($conn)) {    if ($_SESSION['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = "";
$email_value = "";
$lock_seconds_left = 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_value = $email;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $email_esc = mysqli_real_escape_string($conn, $email);

        $q = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email_esc'");

        if (mysqli_num_rows($q) == 1) {
            $user = mysqli_fetch_assoc($q);

            // Is the account currently locked?
            if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
                $lock_seconds_left = strtotime($user['locked_until']) - time();

                if ($lock_seconds_left < 60) {
                    $error = "Account temporarily locked. Try again in {$lock_seconds_left} seconds.";
                } else {
                    $mins = ceil($lock_seconds_left / 60);
                    $error = "Account temporarily locked. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . ".";
                }
            } else {
                // Not locked — verify password
                if (password_verify($password, $user['password'])) {
                    if (empty($user['is_verified'])) {
                        header("Location: verify_email.php?email=" . urlencode($email));
                        exit();
                    }

                    // SUCCESS — reset lockout counters
                    mysqli_query($conn, "UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = " . (int)$user['id']);

                    session_regenerate_id(true);

                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];
                    $_SESSION['just_logged_in'] = true;

                    if (!empty($_POST['remember_me'])) {
                        $_SESSION['persistent'] = true;
                        issue_remember_token($conn, $user['id']);
                    } else {
                        $_SESSION['persistent'] = false;
                    }

                    if ($_SESSION['role'] === 'admin') {
                        header("Location: admin.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();

                } else {
                    // FAILED — increment counter, calculate escalating lock
                    $attempts = (int)$user['failed_attempts'] + 1;
                    $locked_sql = "NULL";

                    if ($attempts >= 5) {
                        if ($attempts < 8)        $wait = 30;   // 5-7 fails → 30 seconds
                        elseif ($attempts < 11)   $wait = 60;   // 8-10 fails → 1 minute
                        elseif ($attempts < 14)   $wait = 180;  // 11-13 fails → 3 minutes
                        else                      $wait = 600;  // 14+ fails → 10 minutes

                        $locked_sql = "'" . date('Y-m-d H:i:s', time() + $wait) . "'";

                        if ($wait === 30)       $error = "Too many failed attempts. Account locked for 30 seconds.";
                        elseif ($wait === 60)   $error = "Too many failed attempts. Account locked for 1 minute.";
                        elseif ($wait === 180)  $error = "Too many failed attempts. Account locked for 3 minutes.";
                        else                    $error = "Too many failed attempts. Account locked for 10 minutes.";
                    } else {
                        $remaining = 5 - $attempts;
                        $error = "Invalid email or password." . ($remaining <= 3 ? " {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before lock." : "");
                    }

                    mysqli_query($conn, "UPDATE users SET failed_attempts = $attempts, locked_until = $locked_sql WHERE id = " . (int)$user['id']);
                }
            }
        } else {
            // Email doesn't exist — generic message (never reveal that)
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" href="icons/icon-192.png">
    <link rel="shortcut icon" href="favicon.ico">

    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100vh; }

        .login-shell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            background: var(--sand-50);
        }
        @media (max-width: 900px) {
            .login-shell { grid-template-columns: 1fr; }
            .brand-panel { display: none !important; }
        }

        .brand-panel {
            background: linear-gradient(160deg, var(--green-600) 0%, var(--green-700) 60%, var(--green-900) 100%);
            color: white;
            padding: 56px 56px 48px;
            display: flex; flex-direction: column;
            justify-content: space-between;
            position: relative; overflow: hidden;
        }
        .brand-panel::before {
            content: ""; position: absolute;
            width: 520px; height: 520px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 60%);
            top: -180px; right: -160px; pointer-events: none;
        }
        .brand-panel::after {
            content: ""; position: absolute;
            width: 380px; height: 380px; border-radius: 50%;
            background: radial-gradient(circle, rgba(199,123,63,0.18) 0%, transparent 65%);
            bottom: -140px; left: -100px; pointer-events: none;
        }

        .brand-top { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
        .brand-top .logo-icon {
            width: 44px; height: 44px; border-radius: var(--r-md);
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
        }
        .brand-top .logo-text {
            font-family: var(--font-display); font-weight: 700;
            font-size: 20px; letter-spacing: -0.02em;
        }

        .brand-middle { position: relative; z-index: 1; max-width: 420px; }
        .brand-middle h2 {
            font-family: var(--font-display); font-weight: 500;
            font-size: 40px; line-height: 1.15;
            letter-spacing: -0.025em; margin: 0 0 20px; color: white;
        }
        .brand-middle h2 em { font-style: italic; color: rgba(255,255,255,0.75); }
        .brand-middle p {
            font-size: 15px; line-height: 1.65;
            color: rgba(255,255,255,0.72); margin: 0;
        }

        .feature-list {
            position: relative; z-index: 1; list-style: none;
            padding: 0; margin: 32px 0 0;
            display: flex; flex-direction: column; gap: 14px;
            max-width: 380px;
        }
        .feature-list li {
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; color: rgba(255,255,255,0.85);
        }
        .feature-list li .feature-icon {
            width: 32px; height: 32px; border-radius: var(--r-sm);
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .feature-list li .feature-icon .lucide { width: 15px; height: 15px; }

        .brand-bottom { position: relative; z-index: 1; font-size: 12px; color: rgba(255,255,255,0.45); }

        .form-side { display: flex; align-items: center; justify-content: center; padding: 40px 32px; }
        .form-inner { width: 100%; max-width: 400px; }

        .mobile-brand {
            display: none; align-items: center; gap: 10px;
            margin-bottom: 32px; justify-content: center;
        }
        @media (max-width: 900px) { .mobile-brand { display: flex; } }
        .mobile-brand .logo-icon {
            width: 40px; height: 40px; border-radius: var(--r-md);
            background: var(--green-500);
            display: flex; align-items: center; justify-content: center; color: white;
        }
        .mobile-brand .logo-text {
            font-family: var(--font-display); font-weight: 700;
            font-size: 20px; color: var(--ink-900); letter-spacing: -0.02em;
        }

        .form-heading h1 {
            font-family: var(--font-display); font-weight: 600;
            font-size: 28px; letter-spacing: -0.025em;
            color: var(--ink-900); margin: 0 0 6px;
        }
        .form-heading p { font-size: 14px; color: var(--ink-500); margin: 0 0 32px; }

        .field { margin-bottom: 16px; }
        .field label {
            display: block; font-weight: 600;
            font-size: 12.5px; color: var(--ink-700); margin-bottom: 7px;
        }
        .field input {
            width: 100%; padding: 13px 14px;
            border: 1px solid var(--border); border-radius: var(--r-sm);
            background: var(--surface); font-size: 14px;
            font-family: var(--font-body); color: var(--ink-900);
            transition: 0.15s;
        }
        .field input:focus {
            outline: none; border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }

        .remember-row {
            display: flex; align-items: center; gap: 8px;
            margin: 4px 0 8px; cursor: pointer; user-select: none;
        }
        .remember-row input {
            width: 16px; height: 16px;
            accent-color: var(--green-500); cursor: pointer; margin: 0;
        }
        .remember-row span { font-size: 13px; color: var(--ink-600); }

        .error-box {
            background: var(--red-50); color: var(--red-700);
            padding: 11px 14px; border-radius: var(--r-sm);
            font-size: 13px; font-weight: 600;
            margin-bottom: 18px;
            display: flex; align-items: center; gap: 8px;
        }

        .login-btn { width: 100%; padding: 14px; font-size: 14.5px; margin-top: 8px; }
        .login-btn:disabled {
            background: var(--sand-300);
            color: var(--ink-500);
            cursor: not-allowed;
            box-shadow: none;
        }

        .form-footer {
            text-align: center; margin-top: 28px;
            font-size: 13.5px; color: var(--ink-500);
        }
        .form-footer a { color: var(--green-600); font-weight: 600; text-decoration: none; }
        .form-footer a:hover { color: var(--green-700); }

        .divider-line {
            display: flex; align-items: center; gap: 12px;
            margin: 28px 0; color: var(--ink-400);
            font-size: 11.5px; text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .divider-line::before, .divider-line::after {
            content: ""; flex-grow: 1; height: 1px; background: var(--border);
        }
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
<body>
    <div class="login-shell">
        <aside class="brand-panel">
            <div class="brand-top">
                <div class="logo-icon"><i data-lucide="leaf"></i></div>
                <span class="logo-text">ShelfLife</span>
            </div>

            <div>
                <div class="brand-middle">
                    <h2>Cook smart.<br><em>Waste less.</em></h2>
                    <p>Track what's in your pantry, get notified before food expires, and see how much money you save every month.</p>
                </div>

                <ul class="feature-list">
                    <li><span class="feature-icon"><i data-lucide="package"></i></span>Track your groceries in one place</li>
                    <li><span class="feature-icon"><i data-lucide="bell"></i></span>Get alerts before items expire</li>
                    <li><span class="feature-icon"><i data-lucide="camera"></i></span>Scan barcodes to add items fast</li>
                    <li><span class="feature-icon"><i data-lucide="piggy-bank"></i></span>See how much you saved this month</li>
                </ul>
            </div>

            <div class="brand-bottom">
                © <?php echo date('Y'); ?> ShelfLife · Lyceum of San Pedro
            </div>
        </aside>

        <section class="form-side">
            <div class="form-inner">

                <div class="mobile-brand">
                    <div class="logo-icon"><i data-lucide="leaf"></i></div>
                    <span class="logo-text">ShelfLife</span>
                </div>

                <div class="form-heading">
                    <h1>Welcome back</h1>
                    <p>Sign in with the email you registered.</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-box">
                        <i data-lucide="alert-circle" class="lucide-sm"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    <div class="field">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" autofocus value="<?php echo htmlspecialchars($email_value); ?>">
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    </div>

                    <label class="remember-row">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Keep me signed in</span>
                    </label>

                    <div style="text-align: right; margin-top: -4px; margin-bottom: 12px;">
                        <a href="forgot_password.php" style="font-size: 12.5px; color: var(--green-600); font-weight: 600; text-decoration: none;">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary login-btn" id="loginBtn" <?php echo $lock_seconds_left > 0 ? 'disabled' : ''; ?>>
                        <i data-lucide="log-in" class="lucide-sm"></i>
                        <?php if ($lock_seconds_left > 0): ?>
                            Locked (<span id="lockTimer"><?php echo (int)$lock_seconds_left; ?></span>s)
                        <?php else: ?>
                            Sign in
                        <?php endif; ?>
                    </button>
                </form>

                <div class="divider-line">New to ShelfLife?</div>

                <p class="form-footer">
                    <a href="register.php">Create an account</a>
                </p>

            </div>
        </section>
    </div>



    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        // If the button is disabled (account locked), count down
        const btn = document.getElementById('loginBtn');
        if (btn && btn.disabled) {
            const timer = document.getElementById('lockTimer');
            let remaining = timer ? parseInt(timer.innerText) : 0;

            const interval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="log-in" class="lucide-sm"></i> Sign in';
                    lucide.createIcons();
                } else if (timer) {
                    timer.innerText = remaining;
                }
            }, 1000);
        }
    
    

    </script>
</body>
</html>