<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Preserve the dashboard scope through the whole Cook flow
$scope = $_POST['scope'] ?? $_GET['scope'] ?? 'own';
if (!in_array($scope, ['own', 'household'], true)) $scope = 'own';

function normalize($name) {
    $name = strtolower(trim($name));
    $name = str_replace(['leaves', 's'], '', $name);
    return $name;
}

$selected_ids = isset($_POST['item_ids']) ? $_POST['item_ids'] : [];
$used_amounts = $_POST['used_amounts'] ?? [];
$single_recipe = null;
$selected_norm = [];

if (isset($_GET['recipe_id'])) {
    $rid = (int)$_GET['recipe_id'];
    $single_res = mysqli_query($conn, "SELECT * FROM recipes WHERE id = $rid");
    $single_recipe = mysqli_fetch_assoc($single_res);
} elseif (isset($_POST['item_ids']) && !empty($_POST['item_ids'])) {
    require_once "household_helper.php";
    $household_ids = get_household_user_ids($conn, $user_id);
    $allowed_ids   = implode(',', array_map('intval', $household_ids));

    $safe = array_map('intval', $_POST['item_ids']);
    $ids  = implode(',', $safe);
    $res  = mysqli_query($conn, "SELECT DISTINCT item_name FROM inventory WHERE id IN ($ids) AND user_id IN ($allowed_ids)");
    while ($r = mysqli_fetch_assoc($res)) { $selected_norm[] = normalize($r['item_name']); }
} else {
    header("Location: dashboard.php");
    exit();
}

$all_recipes = mysqli_query($conn, "SELECT * FROM recipes");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipes · ShelfLife</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .topnav {
            position: sticky; top: 0; z-index: 100;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; flex-wrap: wrap;
        }
        .brand {
            display: flex; align-items: center; gap: 10px;
            font-family: var(--font-display); font-weight: 700;
            font-size: 17px; letter-spacing: -0.02em; color: var(--ink-900);
        }
        .brand .logo-icon {
            width: 32px; height: 32px; border-radius: var(--r-sm);
            background: var(--green-500);
            display: flex; align-items: center; justify-content: center; color: white;
        }
        main { max-width: 760px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        .recipe-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--green-500);
            border-radius: var(--r-lg);
            padding: 24px;
            margin-bottom: 16px;
        }
        .recipe-card.partial {
            border-left-color: var(--amber-500);
        }
        .recipe-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: var(--r-full);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.03em;
            background: var(--green-50);
            color: var(--green-700);
            margin-bottom: 14px;
            text-transform: uppercase;
        }
        .recipe-tag.partial {
            background: var(--amber-50);
            color: var(--amber-700);
        }
        .recipe-card h3 {
            margin-bottom: 12px;
            font-size: 18px;
        }
        .recipe-section {
            margin-top: 12px;
            font-size: 13.5px;
            color: var(--ink-700);
            line-height: 1.6;
        }
        .recipe-section b {
            display: block;
            color: var(--ink-900);
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        .cook-btn-form { margin-top: 20px; }
    </style>
</head>
<body class="page page--list">
<?php include "session_guard.php"; ?>
<nav class="topnav">
    <a href="dashboard.php" class="brand">
        <div class="logo-icon"><i data-lucide="leaf"></i></div>
        ShelfLife
    </a>
    <a href="dashboard.php?view=pantry&scope=<?php echo $scope; ?>" class="btn btn-secondary btn-sm">
        <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
    </a>
</nav>

<main>
    <?php if ($single_recipe): ?>
        <header class="page-header">
            <h1>How to cook: <?php echo htmlspecialchars($single_recipe['recipe_name']); ?></h1>
        </header>
        <div class="recipe-card">
            <span class="recipe-tag"><i data-lucide="book-open" style="width:12px;height:12px;"></i> Recipe details</span>
            <h3><?php echo htmlspecialchars($single_recipe['recipe_name']); ?></h3>
            <div class="recipe-section">
                <b>Ingredients</b>
                <?php echo htmlspecialchars($single_recipe['ingredients']); ?>
            </div>
            <div class="recipe-section">
                <b>Method</b>
                <?php echo nl2br(htmlspecialchars($single_recipe['instructions'])); ?>
            </div>
        </div>
    <?php else: ?>
        <header class="page-header">
            <h1>Recipes for your selection</h1>
            <p>Based on the ingredients you picked.</p>
        </header>

        <?php
        $found = false;
        while ($recipe = mysqli_fetch_assoc($all_recipes)) {
            $split = shelflife_split_ingredients($recipe['ingredients']);
            $needed_norm = array_map('normalize', $split['needed']);
            $matches     = array_intersect($needed_norm, $selected_norm);
            $staples_n   = count($split['staples']);

            if (count($matches) > 0) {
                $found = true;
                $is_full = (count($matches) == count($needed_norm) && count($needed_norm) > 0);
        ?>
                <div class="recipe-card <?php echo !$is_full ? 'partial' : ''; ?>">
                    <span class="recipe-tag <?php echo !$is_full ? 'partial' : ''; ?>">
                        <?php if ($is_full): ?>
                            <i data-lucide="check" style="width:12px;height:12px;"></i> Ready to cook
                            <?php if ($staples_n > 0): ?><span style="opacity:0.75; font-weight:600; margin-left:4px;">(+<?php echo $staples_n; ?> staple<?php echo $staples_n === 1 ? '' : 's'; ?>)</span><?php endif; ?>
                        <?php else: ?>
                            <i data-lucide="alert-circle" style="width:12px;height:12px;"></i> You have <?php echo count($matches); ?> of <?php echo count($needed_norm); ?> needed
                        <?php endif; ?>
                    </span>
                    <h3><?php echo htmlspecialchars($recipe['recipe_name']); ?></h3>
                    <div class="recipe-section">
                        <b>Ingredients</b>
                        <?php echo htmlspecialchars($recipe['ingredients']); ?>
                    </div>
                    <div class="recipe-section">
                        <b>Method</b>
                        <?php echo nl2br(htmlspecialchars($recipe['instructions'])); ?>
                    </div>

                    <?php if (!empty($selected_ids)): ?>
                        <form action="process_cook.php" method="POST" class="cook-btn-form" target="_top">
                            <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope); ?>">
                            <?php foreach ($selected_ids as $sid): ?>
                                <input type="hidden" name="item_ids[]" value="<?php echo (int)$sid; ?>">
                            <?php endforeach; ?>
                            <?php foreach ($used_amounts as $uid => $amount): ?>
                                <input type="hidden" name="used_amounts[<?php echo (int)$uid; ?>]" value="<?php echo htmlspecialchars($amount); ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i data-lucide="chef-hat" class="lucide-sm"></i> Cook this now (remove from pantry)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
        <?php } }
        if (!$found) {
            echo '<div class="card" style="text-align:center; padding: 60px 20px;">
                    <i data-lucide="chef-hat" style="width:40px;height:40px;color:var(--ink-300);margin-bottom:12px;"></i>
                    <h3 style="margin-bottom:6px;">No matching recipes</h3>
                    <p class="text-muted text-sm" style="margin-bottom:20px;">Your selected items don\'t match any recipe in our library.</p>
                    <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
                        <a href="dashboard.php?view=pantry&scope=' . $scope . '" class="btn btn-secondary btn-sm">
                            <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
                        </a>
                        <a href="recipes.php" class="btn btn-primary btn-sm">
                            <i data-lucide="chef-hat" class="lucide-sm"></i> Browse all recipes
                        </a>
                    </div>
                  </div>';
        }
        ?>
    <?php endif; ?>
</main>

<script src="https://unpkg.com/lucide@latest"></script>
<script>lucide.createIcons();</script>

</body>
</html>