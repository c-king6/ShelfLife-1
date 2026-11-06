<?php
// Handles "Remember me" — issues a long-lived token when the user
// checks the box at login, and auto-logs them in if the token is
// still valid when they return.

require_once "session_config.php";

function issue_remember_token($conn, $user_id) {
    $token     = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $token);
    $expires   = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 90)); // 90 days
    $user_id   = (int)$user_id;

    mysqli_query($conn, "INSERT INTO remember_tokens (user_id, token_hash, expires_at)
                         VALUES ($user_id, '$hash', '$expires')");

    setcookie('shelflife_remember', $token, [
        'expires'  => time() + (60 * 60 * 24 * 90),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function try_remember_login($conn) {
    if (!isset($_COOKIE['shelflife_remember'])) return false;
    if (isset($_SESSION['user_id'])) return true;

    $token = $_COOKIE['shelflife_remember'];
    if (!ctype_xdigit($token) || strlen($token) !== 64) return false;

    $hash = hash('sha256', $token);
    $hash_esc = mysqli_real_escape_string($conn, $hash);

    $q = mysqli_query($conn, "SELECT u.id, u.username, u.role
                              FROM remember_tokens rt
                              JOIN users u ON rt.user_id = u.id
                              WHERE rt.token_hash = '$hash_esc'
                              AND rt.expires_at > NOW()
                              LIMIT 1");
    if (mysqli_num_rows($q) !== 1) return false;

    $user = mysqli_fetch_assoc($q);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['persistent'] = true;
    return true;
}

function clear_remember_token($conn) {
    if (!isset($_COOKIE['shelflife_remember'])) return;

    $token = $_COOKIE['shelflife_remember'];
    if (ctype_xdigit($token) && strlen($token) === 64) {
        $hash = hash('sha256', $token);
        $hash_esc = mysqli_real_escape_string($conn, $hash);
        mysqli_query($conn, "DELETE FROM remember_tokens WHERE token_hash = '$hash_esc'");
    }

    setcookie('shelflife_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Delete remember-me tokens that are already past their expiry.
 * Called opportunistically on login page load and on logout.
 */
function cleanup_expired_remember_tokens($conn) {
    mysqli_query($conn, "DELETE FROM remember_tokens WHERE expires_at < NOW()");
}

?>