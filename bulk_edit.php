<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_POST['item_ids']) || empty($_POST['item_ids'])) {
    header("Location: dashboard.php");
    exit();
}

$selected_ids = implode(',', array_map('intval', $_POST['item_ids']));

$find_match_query = "SELECT * FROM inventory
                     WHERE id IN ($selected_ids) AND user_id = '$user_id' AND is_bought = 1
                     ORDER BY item_name ASC";

$result = mysqli_query($conn, $find_match_query);
$categories = $SHELFLIFE_CATEGORIES;
$containers = ['pcs', 'bottle', 'can', 'pack', 'box', 'jar', 'bulk'];
$size_units = ['pcs', 'ml', 'L', 'g', 'kg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch update · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav { position: sticky; top: 0; z-index: 100; background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--green-500); display: flex; align-items: center; justify-content: center; color: white; }
        main { max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        .batch-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow-x: auto; }
        .batch-header {
            padding: 16px 24px; border-bottom: 1px solid var(--border); background: var(--sand-50);
            display: grid; grid-template-columns: 1.5fr 0.8fr 1fr 0.8fr 0.8fr 0.8fr 1fr 1.2fr;
            gap: 16px; font-family: var(--font-body); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-500);
            min-width: 1100px;
        }
        .item-row {
            padding: 14px 24px; display: grid; grid-template-columns: 1.5fr 0.8fr 1fr 0.8fr 0.8fr 0.8fr 1fr 1.2fr; gap: 16px;
            border-bottom: 1px solid var(--sand-100); align-items: center; min-width: 1100px;
        }
        .item-row:last-child { border-bottom: none; }
        .actions-row { padding: 20px 24px; background: var(--sand-50); display: flex; gap: 12px; justify-content: flex-end; min-width: 1100px; }
        .empty-panel { padding: 60px 24px; text-align: center; color: var(--ink-500); }
    </style>
</head>
<body class="page page--list">
<?php include "session_guard.php"; ?>
<?php include 'mobile_nav.php'; ?>
<nav class="topnav">
    <a href="dashboard.php" class="brand">
        <div class="logo-icon"><i data-lucide="leaf"></i></div>
        ShelfLife
    </a>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
        <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
    </a>
</nav>

<main>
    <header class="page-header">
        <h1>Batch update</h1>
        <p>Edit multiple items at once. Save when you're done.</p>
    </header>

    <form action="process_bulk_update.php" method="POST">
        <div class="batch-panel">
            <div class="batch-header">
                <span>Item name</span>
                <span>How many</span>
                <span>Container</span>
                <span>Size</span>
                <span>Unit</span>
                <span>Current</span>
                <span>Price (₱)</span>
                <span>Category</span>
            </div>

            <?php
            $found_any = false;
            while ($row = mysqli_fetch_assoc($result)) {
                $found_any = true;
                $cq = (float)($row['current_quantity'] ?? $row['quantity']);
                $cc = (float)($row['container_count'] ?? 1);
                $spu = (float)($row['size_per_unit'] ?? 1);
                $su = $row['size_unit'] ?? 'pcs';
                $ct = $row['unit'] ?? 'pcs';
                $price_val = $row['price'] !== null ? htmlspecialchars($row['price']) : '';
                echo '<input type="hidden" name="old_ids[]" value="' . (int)$row['id'] . '">';
            ?>
                <div class="item-row">
                    <input type="text" name="item_name[]" value="<?php echo htmlspecialchars($row['item_name']); ?>" required>
                    <input type="number" name="container_count[]" value="<?php echo $cc; ?>" min="0.01" step="0.01" required>
                    <select name="unit[]">
                        <?php foreach ($containers as $c): ?>
                            <option value="<?php echo $c; ?>" <?php if ($ct === $c) echo 'selected'; ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="size_per_unit[]" value="<?php echo $spu; ?>" min="0.01" step="0.01" required>
                    <select name="size_unit[]">
                        <?php foreach ($size_units as $su_opt): ?>
                            <option value="<?php echo $su_opt; ?>" <?php if ($su === $su_opt) echo 'selected'; ?>><?php echo $su_opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="current_quantity[]" value="<?php echo $cq; ?>" min="0.01" step="0.01" required>
                    <!-- NEW PRICE FIELD -->
                    <input type="text" name="price[]" value="<?php echo $price_val; ?>" inputmode="decimal" pattern="[0-9]*\.?[0-9]*" placeholder="optional">
                    <select name="category[]">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($row['category'] == $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php
            }

            if (!$found_any) {
                echo '<div class="empty-panel">
                        <i data-lucide="lock" style="width:32px;height:32px;opacity:0.4;margin-bottom:10px;"></i>
                        <div style="font-weight:700; color: var(--ink-800); margin-bottom:6px;">You can only edit your own items</div>
                        <p style="font-size:12.5px; color: var(--ink-500); max-width:340px; margin: 0 auto; line-height:1.55;">
                            Household members\' items can be used or deleted, but not edited.
                        </p>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm" style="margin-top:16px;">
                            <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
                        </a>
                      </div>';
            }
            ?>

            <?php if ($found_any): ?>
                <div class="actions-row">
                    <a href="dashboard.php" class="btn btn-secondary">Discard changes</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="check" class="lucide-sm"></i> Apply all changes
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>

</body>
</html>