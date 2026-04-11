<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";

// Handle Upload
if (isset($_FILES['profile_img'])) {
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir);

    $file_name = time() . "_" . basename($_FILES["profile_img"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $target_file)) {
        mysqli_query($conn, "UPDATE users SET profile_pic = '$file_name' WHERE id = '$user_id'");
        $msg = "<p style='color:green; font-weight:bold;'>Profile updated!</p>";
    } else {
        $msg = "<p style='color:red;'>Upload failed.</p>";
    }
}

// Fetch current data
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans', sans-serif; background: #f4f7f6; padding: 50px; text-align: center; }
        .profile-card { background: white; padding: 40px; border-radius: 20px; display: inline-block; box-shadow: 0 10px 30px rgba(0,0,0,0.1); position: relative; width: 350px; }
        .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #6B8E23; margin-bottom: 20px; }
        
        #profile_img { display: none; }

        .update-btn { 
            background: #6B8E23; color: white; border: none; 
            padding: 12px 25px; border-radius: 10px; cursor: pointer; 
            font-family: 'Montserrat'; font-weight: bold; transition: 0.3s; width: 100%;
        }
        .update-btn:hover { background: #556B2F; transform: scale(1.02); }
        
        .switch-link { 
            display: inline-block; margin-top: 15px; color: #6B8E23; 
            text-decoration: none; font-weight: bold; font-size: 0.85em; cursor: pointer;
        }

        .back-link { display: block; margin-bottom: 20px; color: #6B8E23; text-decoration: none; font-weight: bold; text-align: left; font-size: 0.9em; }
        .logout-btn { display: block; margin-top: 25px; color: #e74c3c; text-decoration: none; font-weight: bold; font-size: 0.8em; }
        
        /* Modal Styling */
        .modal {
            display: none; position: fixed; z-index: 2000; left: 0; top: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.7);
            align-items: center; justify-content: center;
        }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 300px; }
        .user-option {
            display: flex; align-items: center; gap: 10px; padding: 10px;
            border: 1px solid #eee; border-radius: 10px; margin-bottom: 10px;
            cursor: pointer; transition: 0.2s;
        }
        .user-option:hover { background: #f0f4e8; border-color: #6B8E23; }
        .user-option img { width: 30px; height: 30px; border-radius: 50%; }
    </style>
</head>
<body>
    <div class="profile-card">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <img src="uploads/<?php echo $user_data['profile_pic']; ?>" class="avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo $user_data['username']; ?>&background=6B8E23&color=fff'">
        
        <h2 style="font-family: Montserrat; margin:0;"><?php echo strtoupper($user_data['username']); ?></h2>
        <p style="font-size: 0.9em; margin-bottom: 25px;">Active Profile</p>

        <form id="profileForm" method="POST" enctype="multipart/form-data">
            <input type="file" name="profile_img" id="profile_img" accept="image/*" onchange="document.getElementById('profileForm').submit();">
            <button type="button" class="update-btn" onclick="document.getElementById('profile_img').click();">
                UPDATE PHOTO
            </button>
        </form>

        <span class="switch-link" onclick="openModal()">SWITCH ACCOUNT</span>
        
        <?php echo $msg; ?>
        <a href="logout.php" class="logout-btn">LOGOUT</a>
    </div>

    <div id="switchModal" class="modal">
        <div class="modal-content">
            <h3 style="font-family: Montserrat; margin-top:0;">Switch Account</h3>
            <form action="login.php" method="POST">
                <?php
                $others = mysqli_query($conn, "SELECT username, profile_pic FROM users WHERE id != '$user_id'");
                while($u = mysqli_fetch_assoc($others)): ?>
                    <div class="user-option" onclick="selectUser('<?php echo $u['username']; ?>')">
                        <img src="uploads/<?php echo $u['profile_pic']; ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo $u['username']; ?>&background=6B8E23&color=fff'">
                        <span><?php echo $u['username']; ?></span>
                    </div>
                <?php endwhile; ?>
                
                <input type="hidden" name="username" id="switch_user">
                <input type="password" name="password" placeholder="Password" required style="width:100%; padding:10px; margin-top:10px; border-radius:8px; border:1px solid #ccc; box-sizing:border-box;">
                <button type="submit" style="width:100%; padding:10px; background:#6B8E23; color:white; border:none; border-radius:10px; margin-top:10px; font-weight:bold; cursor:pointer;">Login</button>
                <p onclick="closeModal()" style="margin-top:10px; font-size:0.8em; color:#888; cursor:pointer;">Cancel</p>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('switchModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('switchModal').style.display = 'none'; }
        function selectUser(user) {
            document.getElementById('switch_user').value = user;
            document.querySelectorAll('.user-option').forEach(el => el.style.background = 'white');
            event.currentTarget.style.background = '#e8f5e9';
        }
    </script>
</body>
</html>