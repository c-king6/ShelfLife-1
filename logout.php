<?php
session_start();
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session itself
header("Location: login.php");
exit();
?>