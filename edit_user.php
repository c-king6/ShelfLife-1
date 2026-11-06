<?php
require_once "session_config.php";
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = (int)$_GET['id'];
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '$id'"));

if (!$user_data) {
    header("Location: admin.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role  = mysqli_real_escape_string($conn, $_POST['role']);

    if (!in_array($role, ['user', 'admin'], true)) {
        $role = 'user';
    }

    $sql = "UPDATE users SET email='$email', role='$role' WHERE id='$id'";

    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE users SET email='$email', role='$role', password='$pass' WHERE id='$id'";
    }

    if (mysqli_query($conn, $sql)) {
        header("Location: admin.php?msg=updated");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit user · Admin · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .form-card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            padding: 36px 32px;
            box-shadow: var(--sh-lg);
        }
        .form-card h1 { text-align: center; margin-bottom: 4px; }
        .form-card .subtitle {
            text-align: center;
            color: var(--ink-500);
            font-size: 13px;
            margin-bottom: 26px;
        }
        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: var(--ink-700);
            margin-bottom: 6px;
        }
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        .form-footer a { font-size: 13px; color: var(--ink-500); }
        .form-footer a:hover { color: var(--green-600); }
    </style>
</head>
<body class="page page--admin">

<div class="form-card">
    <h1>Edit user</h1>
    <p class="subtitle">Editing: <strong><?php echo htmlspecialchars($user_data['username']); ?></strong></p>

    <form method="POST">
        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
        </div>

        <div class="field">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="user" <?php if ($user_data['role']=='user') echo 'selected'; ?>>User</option>
                <option value="admin" <?php if ($user_data['role']=='admin') echo 'selected'; ?>>Admin</option>
            </select>
        </div>

        <div class="field">
            <label for="password">New password <span class="text-muted text-xs">(leave blank to keep current)</span></label>
            <input type="password" id="password" name="password" placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; margin-top: 8px;">
            <i data-lucide="check" class="lucide-sm"></i> Save changes
        </button>
    </form>

    <div class="form-footer">
        <a href="admin.php">← Cancel and return</a>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>
</body>
</html>