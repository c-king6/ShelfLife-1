<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$raw_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$safe_ids = array_map('intval', $raw_ids);
$ids_string = !empty($safe_ids) ? implode(',', $safe_ids) : '0';

$result = mysqli_query($conn, "SELECT * FROM inventory WHERE id IN ($ids_string) AND user_id = '$user_id'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set expiry dates · ShelfLife</title>
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
            max-width: 500px;
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
        .item-block {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }
        .item-block:last-of-type { border-bottom: none; }
        .item-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--ink-900);
            margin-bottom: 10px;
        }
        .field label {
            display: block;
            font-weight: 600;
            font-size: 11.5px;
            color: var(--ink-600);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
    </style>
</head>
<body class="page page--list">

<div class="form-card">
    <h1>Set expiry dates</h1>
    <p class="subtitle">Add dates for your new items to enable alerts.</p>

    <form action="finalize_stock.php" method="POST">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="item-block">
                <div class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></div>
                <input type="hidden" name="ids[]" value="<?php echo (int)$row['id']; ?>">
                <div class="field">
                    <label>Expiration date</label>
                    <input type="date" name="dates[]" required>
                </div>
            </div>
        <?php endwhile; ?>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; margin-top: 20px;">
            <i data-lucide="check" class="lucide-sm"></i> Complete stocking
        </button>
    </form>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>
</body>
</html>