<?php
include "db_connect.php";
$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pass = $_POST['password']; // In a real app, we'd use password_hash here

    // Check if username already exists
    $check = mysqli_query($conn, "SELECT * FROM users WHERE username = '$user'");
    if (mysqli_num_rows($check) > 0) {
        $msg = "❌ Username already taken!";
    } else {
        $sql = "INSERT INTO users (username, email, password) VALUES ('$user', '$email', '$pass')";
        if (mysqli_query($conn, $sql)) {
            header("Location: login.php?msg=success");
            exit();
        } else {
            $msg = "❌ Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Create Account</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; margin: 0; display: flex; justify-content: center; 
            align-items: center; min-height: 100vh; 
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('index.JPG');
            background-size: cover; background-position: center;
        }
        .register-card { 
            background: #F9F7F2; padding: 40px; border-radius: 20px; 
            width: 100%; max-width: 380px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        h2 { font-family: 'Montserrat'; color: #2F2F2F; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; }
        label { display: block; text-align: left; font-size: 0.75em; font-weight: bold; color: #5D5D5D; text-transform: uppercase; margin-top: 15px; }
        input { 
            width: 100%; padding: 12px; margin-top: 5px; border: 2px solid #E8E4D9; 
            border-radius: 10px; box-sizing: border-box; font-family: 'Open Sans';
        }
        button { 
            width: 100%; padding: 16px; background: #6B8E23; color: white; border: none; 
            border-radius: 12px; font-family: 'Montserrat'; font-weight: bold; cursor: pointer; margin-top: 25px;
        }
        button:hover { background: #556B2F; }
        .error-msg { color: #e74c3c; font-size: 0.85em; margin-top: 10px; font-weight: bold; }
        .footer-link { display: block; margin-top: 20px; color: #8B8B8B; text-decoration: none; font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="register-card">
        <h2>🌿 Join ShelfLife</h2>
        <p style="color: #7f8c8d; font-size: 0.9em;">Start tracking your pantry today.</p>
        
        <?php if($msg) echo "<div class='error-msg'>$msg</div>"; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" required>
            
            <label>Email Address</label>
            <input type="email" name="email" required>
            
            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">CREATE ACCOUNT</button>
        </form>

        <a href="login.php" class="footer-link">Already have an account? Login here</a>
    </div>
</body>
</html>