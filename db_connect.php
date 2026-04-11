<?php
$host = "localhost";
$user = "root";
$pass = ""; // Leave this empty for XAMPP
$dbname = "shelflife_db";

// This is the "phone call" to the database
$conn = mysqli_connect($host, $user, $pass, $dbname);

// If the phone call fails, tell us why!
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
