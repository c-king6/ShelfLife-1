<?php
session_start();
include "db_connect.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // For now, we are checking a plain text password. 
    // (Later we will upgrade this to password_hash for your thesis security)
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid Username or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; 
            margin: 0; display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('index.JPG');
            background-size: cover; background-position: center;
        }
        .login-card { 
            background: #F9F7F2; padding: 40px; border-radius: 20px; 
            width: 100%; max-width: 350px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        h2 { font-family: 'Montserrat'; color: #2F2F2F; text-transform: uppercase; letter-spacing: 2px; }
        input { 
            width: 100%; padding: 14px; margin: 10px 0; border: 2px solid #E8E4D9; 
            border-radius: 12px; box-sizing: border-box; 
        }
        button { 
            width: 100%; padding: 16px; background: #6B8E23; color: white; border: none; 
            border-radius: 12px; font-family: 'Montserrat'; font-weight: bold; cursor: pointer; margin-top: 10px;
        }
        .error { color: #e74c3c; font-size: 0.8em; margin-bottom: 10px; font-weight: bold; }
        .footer-link { display: block; margin-top: 20px; color: #8B8B8B; text-decoration: none; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>🌿 ShelfLife</h2>
        <p style="color: #5D5D5D; font-size: 0.9em; margin-bottom: 20px;">Welcome back! Please login.</p>
        
        <?php if($error) echo "<div class='error'>$error</div>"; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">LOGIN</button>
        </form>

        <a href="register.php" class="footer-link">Don't have an account? Create one</a>
    </div>
</body>
</html>