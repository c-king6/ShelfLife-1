<?php
include "db_connect.php";

// Identical normalization function to ensure consistency
function normalize($name) {
    $name = strtolower(trim($name));
    $name = str_replace(['leaves', 's'], '', $name); 
    return $name;
}

$single_recipe = null;
$selected_norm = [];

// Check if a specific recipe was clicked from the dashboard
if (isset($_GET['recipe_id'])) {
    $rid = (int)$_GET['recipe_id'];
    $single_res = mysqli_query($conn, "SELECT * FROM recipes WHERE id = $rid");
    $single_recipe = mysqli_fetch_assoc($single_res);
} 
// Otherwise, check for bulk selections from checkboxes
elseif (isset($_POST['item_ids']) && !empty($_POST['item_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['item_ids']));
    $res = mysqli_query($conn, "SELECT DISTINCT item_name FROM inventory WHERE id IN ($ids)");
    while($r = mysqli_fetch_assoc($res)) { 
        $selected_norm[] = normalize($r['item_name']); 
    }
} else {
    header("Location: dashboard.php");
    exit();
}

$all_recipes = mysqli_query($conn, "SELECT * FROM recipes");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>ShelfLife - Cook Selected</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Open Sans'; background: #f4f7f6; padding: 40px; }
        .recipe-card { background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; border-left: 8px solid #6B8E23; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .tag { display: inline-block; padding: 4px 10px; border-radius: 5px; font-size: 0.8em; font-weight: bold; margin-bottom: 10px; background: #6B8E23; color: white; }
    </style>
</head>
<body>
    <div style="max-width:800px; margin:auto;">
    <a href="dashboard.php" style="text-decoration:none; color:#6B8E23; font-weight:bold;">← Back to Pantry</a>
    
    <?php if ($single_recipe): ?>
        <h2 style="font-family:'Montserrat'; margin-top:20px;">📖 How to cook: <?php echo htmlspecialchars($single_recipe['recipe_name']); ?></h2>
        <div class="recipe-card">
            <span class="tag">RECIPE DETAILS</span>
            <h3><?php echo htmlspecialchars($single_recipe['recipe_name']); ?></h3>
            <p><strong>Ingredients:</strong> <?php echo htmlspecialchars($single_recipe['ingredients']); ?></p>
            <p><strong>Method:</strong><br><?php echo nl2br(htmlspecialchars($single_recipe['instructions'])); ?></p>
        </div>
    <?php else: ?>
        <h2 style="font-family:'Montserrat'; margin-top:20px;">🍳 Recipes for your Selection</h2>
        <?php 
        $found = false;
        while($recipe = mysqli_fetch_assoc($all_recipes)) {
            $needed_norm = array_map('normalize', explode(',', $recipe['ingredients']));
            $matches = array_intersect($needed_norm, $selected_norm);
            
            if (count($matches) > 0) {
                $found = true;
                $is_full = (count($matches) == count($needed_norm));
                ?>
                <div class="recipe-card" style="<?php echo !$is_full ? 'border-left-color:#E67E22;' : ''; ?>">
                    <span class="tag" style="<?php echo !$is_full ? 'background:#E67E22;' : ''; ?>">
                        <?php echo $is_full ? 'READY TO COOK' : 'PARTIAL MATCH (YOU HAVE ' . count($matches) . ')'; ?>
                    </span>
                    <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                    <p><strong>Ingredients:</strong> <?php echo htmlspecialchars($recipe['ingredients']); ?></p>
                    <p><strong>Method:</strong><br><?php echo nl2br(htmlspecialchars($recipe['instructions'])); ?></p>
                </div>
        <?php } } 
        if(!$found) echo "<p>No recipes found. Make sure you select the main ingredients!</p>"; ?>
    <?php endif; ?>
</div>
</body>
</html>