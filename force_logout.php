<?php
require_once "session_config.php";
$_SESSION = [];
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>