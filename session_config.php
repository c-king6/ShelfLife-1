<?php
date_default_timezone_set('Asia/Manila');
// Sets up the session cookie. If the user checked "Keep me signed in"
// at login, the session cookie is extended to 30 days on every visit.
// Otherwise, it's a session-only cookie that expires when the browser closes.
//
// Also generates and verifies a CSRF token.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// If this login is persistent, keep extending the cookie to 30 days
if (!empty($_SESSION['persistent'])) {
    setcookie(session_name(), session_id(), [
        'expires'  => time() + (60 * 60 * 24 * 30),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ---------- CSRF ----------
// Generate the token once per session.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify on every POST except pre-auth endpoints.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $skip = [
        'login.php',
        'register.php',
        'verify_email.php',
        'verify_reset.php',
        'forgot_password.php',
        'get_item_price.php',
    ];
    if (!in_array($script, $skip, true)) {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            exit('Invalid request (CSRF check failed). Please go back and try again.');
        }
    }
}