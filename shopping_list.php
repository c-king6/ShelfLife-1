<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "db_connect.php";

$user_id = $_SESSION['user_id'];
$error_msg = "";
$success_msg = "";

// --- QUICK ADD & SMART FILTER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['quick_add'])) {
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $item_lower = strtolower($item_name);

   $food_keywords = [
        // Fruits
        'apple','banana','orange','fruit','grape','mango','berry','melon','lemon','lime','pear','peach','plum','calamansi','pineapple','papaya',
        // Vegetables & Leafy Greens
        'carrot','potato','onion','garlic','pepper','chili','chilis','tomato','veg','cabbage','lettuce','broccoli','corn','cucumber',
        'ginger','kale','spinach','celery','mushroom','eggplant','squash','zucchini','radish','kangkong','pechay','malunggay','okra','sitaw',
        // Dairy & Meat
        'milk','cheese','egg','butter','yogurt','dairy','cream','chicken','beef','meat','pork','fish','steak',
        'bacon','ham','turkey','hotdog','sausage','shrimp','seafood','tilapia','bangus',
        // Pantry & Grains
        'bread','loaf','bun','flour','pastry','cake','cookie','rice','pasta','noodle','spaghetti','cereal','grain',
        'oats','quinoa','beans','lentils',
        // Spices & Liquid
        'can','soup','tuna','sardine','sauce','oil','salt','sugar','water','juice','soda','coffee','tea',
        'vinegar','leaf','leaves','spice','seasoning','herb','cinnamon','powder','honey','syrup','patis','toyo'
    ];

    $is_food = false;
    foreach ($food_keywords as $key) {
        if (strpos($item_lower, $key) !== false) {
            $is_food = true;
            break;
        }
    }

    if ($is_food) {
        $cat = "General";
        if (preg_match('/pepper|vinegar|leaf|leaves|spice|seasoning|herb|salt|oil/', $item_lower)) {
            $cat = "Spices/Seasonings";
        }
        
        // UPDATED: Added user_id to the INSERT statement
        $sql = "INSERT INTO inventory (item_name, quantity, category, is_bought, user_id) 
                VALUES ('$item_name', 1, '$cat', 0, '$user_id')";
        if (mysqli_query($conn, $sql)) {
            $success_msg = "Added to list!";
        }
    } else {
        $error_msg = "That doesn't look like a food item!";
    }
}

// --- QUANTITY UPDATES ---
if (isset($_GET['update_qty'])) {
    $id = (int)$_GET['id'];
    $change = (int)$_GET['change'];
    // UPDATED: Added user_id check to ensure you only update your own items
    mysqli_query($conn, "UPDATE inventory SET quantity = GREATEST(1, quantity + $change) WHERE id = $id AND user_id = '$user_id'");
    header("Location: shopping_list.php");
    exit();
}

// UPDATED: Filtered selection by user_id
$items = mysqli_query($conn, "SELECT * FROM inventory WHERE is_bought = 0 AND user_id = '$user_id' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ShelfLife - Shopping List</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Open Sans', sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f4f7f6; 
            background-image: url('shopping_list.JPG'); 
            background-size: cover; 
            background-position: center;
            background-attachment: fixed; 
        }
        .container { 
            max-width: 500px; 
            margin: auto; 
            background: rgba(255, 255, 255, 0.9); 
            padding: 30px; 
            border-radius: 20px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        }
        h2 { font-family: 'Montserrat'; text-align: center; color: #2F2F2F; margin-bottom: 25px; }
        .add-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .add-input { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #ddd; }
        .add-btn { background: #6B8E23; color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-family: 'Montserrat'; }
        .item-row { display: flex; align-items: center; background: white; padding: 15px; border-radius: 12px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .qty-controls { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .qty-btn { text-decoration: none; color: #6B8E23; font-weight: bold; font-size: 1.2em; }
        
        .action-bar { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: #2F2F2F; color: white; padding: 15px 30px; border-radius: 50px; display: flex; align-items: center; gap: 20px; transition: 0.4s; z-index: 1000; }
        .action-bar.active { bottom: 30px; }
        .bar-btn { border: none; padding: 8px 15px; border-radius: 20px; color: white; cursor: pointer; font-family: 'Montserrat'; font-size: 0.8em; }
        .bought-btn { background: #6B8E23; }
        .discard-btn { background: #E2725B; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="text-decoration:none; color:#6B8E23; font-weight:bold;">← Dashboard</a>
    <h2>🛒 Shopping List</h2>

    <?php if($error_msg): ?> <p style="color:red; text-align:center;"><?php echo $error_msg; ?></p> <?php endif; ?>
    <?php if($success_msg): ?> <p style="color:green; text-align:center;"><?php echo $success_msg; ?></p> <?php endif; ?>

    <form method="POST" class="add-form">
        <input type="text" name="item_name" class="add-input" placeholder="Quick add (e.g. Peppers, Bay Leaves...)" required>
        <button type="submit" name="quick_add" class="add-btn">Add</button>
    </form>

    <form id="listForm" action="process_shopping.php" method="POST">
        <?php if (mysqli_num_rows($items) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($items)): ?>
                <div class="item-row">
                    <input type="checkbox" name="bought_ids[]" value="<?php echo $row['id']; ?>" class="item-checkbox">
                    <span style="margin-left: 10px; font-weight: 600;"><?php echo htmlspecialchars($row['item_name']); ?></span>
                    <div class="qty-controls">
                        <a href="?update_qty=1&id=<?php echo $row['id']; ?>&change=-1" class="qty-btn">−</a>
                        <span><?php echo $row['quantity']; ?></span>
                        <a href="?update_qty=1&id=<?php echo $row['id']; ?>&change=1" class="qty-btn">+</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="text-align:center; color:#999;">Your list is empty.</p>
        <?php endif; ?>

        <div id="action-bar" class="action-bar">
            <span id="selected-count">0 selected</span>
            <button type="submit" name="action" value="done" class="bar-btn bought-btn">✅ Bought</button>
            <button type="submit" name="action" value="delete" class="bar-btn discard-btn">🗑️ Discard</button>
        </div>
    </form>
</div>

<script>
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const actionBar = document.getElementById('action-bar');
    checkboxes.forEach(box => {
        box.addEventListener('change', () => {
            const count = document.querySelectorAll('.item-checkbox:checked').length;
            document.getElementById('selected-count').innerText = count + " selected";
            if (count > 0) actionBar.classList.add('active');
            else actionBar.classList.remove('active');
        });
    });
</script>

</body>
</html>