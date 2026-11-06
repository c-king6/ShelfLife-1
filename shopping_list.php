<?php
require_once "session_config.php";
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "db_connect.php";
require_once "categories_config.php";

$user_id = $_SESSION['user_id'];
$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quick_add'])) {
    $item_name  = mysqli_real_escape_string($conn, $_POST['item_name'] ?? '');
    $item_lower = strtolower($item_name);

    $food_keywords = [
        'apple','banana','orange','fruit','grape','mango','berry','melon','lemon','lime','pear','peach','plum','calamansi','pineapple','papaya',
        'carrot','potato','onion','garlic','pepper','chili','chilies','tomato','veg','cabbage','lettuce','broccoli','corn','cucumber',
        'ginger','kale','spinach','celery','mushroom','eggplant','squash','zucchini','radish','kangkong','pechay','malunggay','okra','sitaw',
        'milk','cheese','egg','butter','yogurt','dairy','cream','chicken','beef','meat','pork','fish','steak',
        'bacon','ham','turkey','hotdog','sausage','shrimp','seafood','tilapia','bangus',
        'bread','loaf','bun','flour','pastry','cake','cookie','rice','pasta','noodle','spaghetti','cereal','grain',
        'oats','quinoa','beans','lentils',
        'can','soup','tuna','sardine','sauce','oil','salt','sugar','water','juice','soda','coffee','tea',
        'vinegar','leaf','leaves','spice','seasoning','herb','cinnamon','powder','honey','syrup','patis','toyo'
    ];

    $is_food = false;
    foreach ($food_keywords as $key) {
        if (strpos($item_lower, $key) !== false) { $is_food = true; break; }
    }

    if ($is_food) {
        $cat = shelflife_categorize($item_name);
        $sql = "INSERT INTO inventory (item_name, quantity, container_count, unit, size_per_unit, size_unit, initial_quantity, current_quantity, category, is_bought, user_id)
                VALUES ('$item_name', 1, 1, 'pcs', 1, 'pcs', 1, 1, '" . mysqli_real_escape_string($conn, $cat) . "', 0, '$user_id')";
        if (mysqli_query($conn, $sql)) { $success_msg = "Added to your list."; }
    } else {
        $error_msg = "That doesn't look like a food item.";
    }
}

if (isset($_GET['update_qty'])) {
    $id     = (int)$_GET['id'];
    $change = (int)$_GET['change'];
    if ($change !== 1 && $change !== -1) $change = 0;
    if ($change !== 0) {
        // FIXED: Only update quantity here. process_shopping.php will sync the rest.
        mysqli_query($conn, "UPDATE inventory SET 
                                quantity = GREATEST(1, quantity + $change)
                             WHERE id = $id AND user_id = '$user_id' AND is_bought = 0");
    }
    header("Location: shopping_list.php");
    exit();
}

$items = mysqli_query($conn, "SELECT * FROM inventory WHERE is_bought = 0 AND user_id = '$user_id' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping list · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav { position: sticky; top: 0; z-index: 100; background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900); }
        .brand .logo-icon { width: 32px; height: 32px; border-radius: var(--r-sm); background: var(--green-500); display: flex; align-items: center; justify-content: center; color: white; }
        main { max-width: 640px; margin: 0 auto; padding: 32px 24px 140px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }
        .flash { padding: 10px 14px; border-radius: var(--r-sm); font-size: 13px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .flash.error   { background: var(--red-50); color: var(--red-700); }
        .flash.success { background: var(--green-50); color: var(--green-700); }
        .add-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px; margin-bottom: 20px; display: flex; gap: 10px; }
        .add-card input { flex: 1; }
        .list-panel { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden; }
        .list-header { padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .list-header h2 { font-size: 14px; }
        .list-header .count { background: var(--sand-100); color: var(--ink-500); font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: var(--r-full); }
        .item-row { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--sand-100); transition: background 0.15s; }
        .item-row:last-child { border-bottom: none; }
        .item-row:hover { background: var(--sand-50); }
        .item-row.selected { background: var(--green-50); }
        .item-checkbox { accent-color: var(--green-500); width: 16px; height: 16px; }
        .item-name { flex-grow: 1; font-weight: 500; font-size: 14px; color: var(--ink-800); }
        .qty-controls { display: flex; align-items: center; gap: 8px; background: var(--sand-50); border-radius: var(--r-sm); padding: 4px; }
        .qty-btn { width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--ink-600); font-weight: 600; text-decoration: none; transition: background 0.15s; }
        .qty-btn:hover { background: var(--sand-200); color: var(--ink-900); }
        .qty-value { min-width: 24px; text-align: center; font-size: 13px; font-weight: 600; color: var(--ink-800); }
        .empty-list { padding: 60px 20px; text-align: center; }
        .empty-list .empty-icon { width: 56px; height: 56px; border-radius: 50%; background: var(--sand-100); color: var(--ink-400); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
        .empty-list h3 { margin-bottom: 6px; }
        .empty-list p { color: var(--ink-500); font-size: 13px; }
        .action-bar { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: var(--ink-900); color: white; padding: 12px 20px; border-radius: var(--r-full); display: flex; align-items: center; gap: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.25); transition: bottom 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 1000; }
        .action-bar.active { bottom: 24px; }
        .action-bar .count { font-size: 12px; font-weight: 600; letter-spacing: 0.02em; color: white; }
        .bar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: var(--r-full); font-weight: 600; font-size: 12px; color: white; transition: opacity 0.15s; }
        .bar-btn:hover { opacity: 0.85; }
        .bar-btn.bought  { background: var(--green-500); }
        .bar-btn.discard { background: var(--red-500); }
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
        <h1>Shopping list</h1>
        <p>Plan your next grocery run.</p>
    </header>

    <?php if ($error_msg): ?>
        <div class="flash error"><i data-lucide="alert-circle" class="lucide-sm"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
        <div class="flash success"><i data-lucide="check-circle" class="lucide-sm"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>

    <form method="POST" class="add-card">
        <input type="text" name="item_name" placeholder="Quick add — e.g. Peppers, Bay leaves..." required>
        <button type="submit" name="quick_add" class="btn btn-primary">
            <i data-lucide="plus" class="lucide-sm"></i> Add
        </button>
    </form>

    <form id="listForm" action="process_shopping.php" method="POST">
        <div class="list-panel">
            <div class="list-header">
                <h2>To buy</h2>
                <span class="count"><?php echo (int)mysqli_num_rows($items); ?> item<?php echo mysqli_num_rows($items) === 1 ? '' : 's'; ?></span>
            </div>

            <?php if (mysqli_num_rows($items) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($items)): ?>
                    <div class="item-row">
                        <input type="checkbox" name="bought_ids[]" value="<?php echo (int)$row['id']; ?>" class="item-checkbox">
                        <span class="item-name"><?php echo htmlspecialchars($row['item_name']); ?></span>
                        <div class="qty-controls">
                            <a href="?update_qty=1&id=<?php echo (int)$row['id']; ?>&change=-1" class="qty-btn">
                                <i data-lucide="minus" class="lucide-sm"></i>
                            </a>
                            <span class="qty-value"><?php echo (int)$row['quantity']; ?></span>
                            <a href="?update_qty=1&id=<?php echo (int)$row['id']; ?>&change=1" class="qty-btn">
                                <i data-lucide="plus" class="lucide-sm"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-list">
                    <div class="empty-icon"><i data-lucide="shopping-cart" class="lucide-lg"></i></div>
                    <h3>Your list is empty</h3>
                    <p>Add items you need to buy using the field above.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="action-bar" class="action-bar">
            <span class="count" id="selected-count">0 selected</span>
            <button type="submit" name="action" value="done" class="bar-btn bought">
                <i data-lucide="check" class="lucide-sm"></i> Bought
            </button>
            <button type="submit" name="action" value="delete" class="bar-btn discard">
                <i data-lucide="trash-2" class="lucide-sm"></i> Discard
            </button>
        </div>
    </form>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const actionBar = document.getElementById('action-bar');
    checkboxes.forEach(box => {
        box.addEventListener('change', () => {
            box.closest('.item-row').classList.toggle('selected', box.checked);
            const count = document.querySelectorAll('.item-checkbox:checked').length;
            document.getElementById('selected-count').innerText = count + " selected";
            actionBar.classList.toggle('active', count > 0);
        });
    });
</script>
</body>
</html>