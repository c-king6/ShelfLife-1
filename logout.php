<?php
// FIXED: no changes needed beyond what already existed — session is
// properly destroyed and redirected. Kept same as original but with
// explicit exit() and no-store headers so the back button doesn't
// resurrect the logged-in page from browser cache.
require_once "session_config.php";
include "db_connect.php";
require_once "remember_helper.php";

clear_remember_token($conn);
cleanup_expired_remember_tokens($conn);

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// FIXED: tell the browser not to cache authenticated pages.
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

header("Location: login.php");
exit();
?>