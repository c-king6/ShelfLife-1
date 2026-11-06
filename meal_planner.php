<?php
require_once "session_config.php";
include "db_connect.php";
require_once "categories_config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

require_once "household_helper.php";

// Household scope
$household    = get_household_info($conn, $user_id);
$in_household = (bool)$household;
$scope        = $_GET['scope'] ?? 'own';
if (!in_array($scope, ['own', 'household'], true)) $scope = 'own';
if (!$in_household) $scope = 'own';

$scope_ids  = ($scope === 'household') ? get_household_user_ids($conn, $user_id) : [$user_id];
$scope_list = implode(',', array_map('intval', $scope_ids));

// Which week?
$week_offset = (int)($_GET['week'] ?? 0);
if ($week_offset < -52) $week_offset = -52;
if ($week_offset > 52)  $week_offset = 52;

$today = new DateTime('today');
$dow = (int)$today->format('N');
$monday = clone $today;
$monday->modify('-' . ($dow - 1) . ' days');
$monday->modify(($week_offset * 7) . ' days');

$week_days = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    $week_days[] = $d;
}

$week_start = $monday->format('Y-m-d');
$week_end   = $week_days[6]->format('Y-m-d');
$today_str  = date('Y-m-d');

// Fetch plan for this week (own OR household)
$plan_q = mysqli_query($conn, "SELECT mp.*, r.recipe_name, r.ingredients, u.username AS owner_name
                                FROM meal_plan mp
                                JOIN recipes r ON mp.recipe_id = r.id
                                LEFT JOIN users u ON u.id = mp.user_id
                                WHERE mp.user_id IN ($scope_list)
                                AND mp.plan_date BETWEEN '$week_start' AND '$week_end'
                                ORDER BY mp.user_id ASC");
$plan = [];
while ($p = mysqli_fetch_assoc($plan_q)) {
    $d = $p['plan_date'];
    $s = $p['meal_slot'];
    $is_mine = ((int)$p['user_id'] === $user_id);
    // In household scope, prefer your own entry; otherwise show any member's
    if (!isset($plan[$d][$s]) || $is_mine) {
        $plan[$d][$s] = $p;
    }
}

// Recipes for the picker
$recipes_q = mysqli_query($conn, "SELECT id, recipe_name, ingredients FROM recipes
                                   WHERE is_approved = 1
                                   AND (is_public = 1 OR user_id = '$user_id' OR user_id IS NULL)
                                   ORDER BY recipe_name ASC");
$all_recipes = [];
while ($r = mysqli_fetch_assoc($recipes_q)) {
    $all_recipes[] = $r;
}

// Pantry for "ready" indicator (includes household)
$pantry = [];
$pq = mysqli_query($conn, "SELECT DISTINCT LOWER(TRIM(item_name)) AS name FROM inventory WHERE user_id IN ($scope_list) AND is_bought=1");
while ($p = mysqli_fetch_assoc($pq)) $pantry[] = $p['name'];

function count_match($ingredients, $pantry) {
    // Uses the staples-aware matcher from categories_config.php
    if (function_exists('shelflife_count_match')) {
        return shelflife_count_match($ingredients, $pantry);
    }
    // Fallback (shouldn't be needed once categories_config is required)
    $parts = array_map(function($x){ return strtolower(trim($x)); }, explode(',', $ingredients));
    $have = 0; $total = 0;
    foreach ($parts as $p) {
        if ($p === '') continue;
        $total++;
        foreach ($pantry as $item) {
            if (strpos($item, $p) !== false || strpos($p, $item) !== false) { $have++; break; }
        }
    }
    return ['have' => $have, 'total' => $total, 'staples_count' => 0];
}

// NOTE: health_tag() now lives in categories_config.php (returns a label string).
// Use health_tag_full() when you also need the color key: [label, color].

// Health summary
$health_counts = ['Veggie-heavy' => 0, 'Balanced' => 0, 'Meat-heavy' => 0, 'Light' => 0];
foreach ($plan as $date => $slots) {
    foreach ($slots as $slot => $entry) {
        $tag = health_tag($entry['ingredients']);
        $health_counts[$tag]++;
    }
}
$total_planned = array_sum($health_counts);

// Build tip
$tip = "";
if ($total_planned === 0) {
    $tip = "Start by adding a meal or two to your plan.";
} elseif ($health_counts['Meat-heavy'] >= $health_counts['Veggie-heavy'] + 2) {
    $tip = "You're heavy on meat this week — try swapping one dish for a veggie recipe.";
} elseif ($health_counts['Veggie-heavy'] < 3 && $total_planned >= 5) {
    $tip = "Add one or two veggie dishes to balance things out.";
} else {
    $tip = "Looks balanced. Nice work.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal planner · ShelfLife</title>
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
        main { max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 24px; }
        .page-header p { color: var(--ink-500); margin-top: 4px; }

        /* ---- Household scope toggle ---- */
        .scope-toggle {
            display: flex;
            gap: 4px;
            background: var(--sand-100);
            padding: 4px;
            border-radius: var(--r-full);
            margin-bottom: 20px;
            max-width: 420px;
        }
        .scope-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: var(--r-full);
            font-size: 12.5px;
            font-weight: 700;
            color: var(--ink-500);
            text-decoration: none;
            transition: 0.15s;
            font-family: var(--font-body);
        }
        .scope-btn:hover { color: var(--ink-800); }
        .scope-btn.active {
            background: var(--surface);
            color: var(--green-700);
            box-shadow: var(--sh-xs);
        }

        /* ---- Week navigation ---- */
        .week-nav {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; margin-bottom: 20px;
            padding: 14px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
        }
        .week-label { text-align: center; flex-grow: 1; }
        .week-label strong {
            display: block;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 15px;
            color: var(--ink-900);
        }
        .week-label .back-this-week {
            font-size: 11.5px;
            color: var(--green-600);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 2px;
        }
        .week-label .back-this-week:hover { color: var(--green-700); }

        /* ---- Health summary ---- */
        .health-summary {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 20px 22px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .health-stats {
            display: flex; gap: 20px; flex-wrap: wrap;
            flex-grow: 1;
        }
        .health-stat {
            display: flex; flex-direction: column; gap: 2px;
        }
        .health-stat .stat-num {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 20px;
            color: var(--ink-900);
            line-height: 1;
        }
        .health-stat .stat-lbl {
            font-size: 11px; text-transform: uppercase;
            letter-spacing: 0.05em; font-weight: 700;
            color: var(--ink-500);
        }
        .health-stat.veggie .stat-num { color: var(--green-700); }
        .health-stat.meat   .stat-num { color: var(--red-700); }
        .health-stat.balanced .stat-num { color: #b45309; }
        .health-tip {
            font-size: 13px;
            color: var(--ink-700);
            padding: 10px 14px;
            background: var(--sand-50);
            border-left: 3px solid var(--green-500);
            border-radius: var(--r-sm);
            max-width: 320px;
            line-height: 1.5;
        }

        /* ---- Grid ---- */
        .meal-grid-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            overflow-x: auto;
        }
        .meal-grid {
            min-width: 900px;
            display: flex;
            flex-direction: column;
        }
        .grid-header, .grid-row {
            display: grid;
            grid-template-columns: 90px repeat(7, 1fr);
        }
        .grid-header {
            border-bottom: 1px solid var(--border);
            background: var(--sand-50);
        }
        .grid-corner {
            border-right: 1px solid var(--border);
        }
        .grid-day-header {
            padding: 12px 8px;
            text-align: center;
            border-right: 1px solid var(--border);
        }
        .grid-day-header:last-child { border-right: none; }
        .grid-day-header.today { background: var(--green-50); }
        .grid-day-header .day-name {
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.05em; text-transform: uppercase;
            color: var(--ink-500);
        }
        .grid-day-header .day-date {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 14px;
            color: var(--ink-900);
            margin-top: 2px;
        }
        .grid-day-header.today .day-date { color: var(--green-700); }

        .grid-row {
            border-bottom: 1px solid var(--border);
        }
        .grid-row:last-child { border-bottom: none; }
        .grid-row-label {
            padding: 16px 12px;
            border-right: 1px solid var(--border);
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--ink-600);
            display: flex;
            align-items: center;
        }
        .grid-cell {
            padding: 8px;
            border-right: 1px solid var(--border);
            min-height: 76px;
            display: flex;
            align-items: stretch;
        }
        .grid-cell:last-child { border-right: none; }

        .meal-empty {
            width: 100%;
            min-height: 60px;
            border: 1px dashed var(--border-strong);
            border-radius: var(--r-sm);
            background: transparent;
            color: var(--ink-400);
            font-size: 22px;
            font-weight: 300;
            cursor: pointer;
            transition: 0.15s;
            display: flex; align-items: center; justify-content: center;
        }
        .meal-empty:hover {
            border-color: var(--green-500);
            color: var(--green-600);
            background: var(--green-50);
        }

        .meal-entry {
            position: relative;
            width: 100%;
            padding: 10px 10px 10px 12px;
            background: var(--green-50);
            border-radius: var(--r-sm);
            border-left: 3px solid var(--green-500);
            display: flex; flex-direction: column;
            justify-content: space-between;
            gap: 4px;
        }
        .meal-entry.other-user {
            background: var(--sand-50);
            border-left-color: var(--sand-400);
        }
        .meal-title {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--ink-900);
            line-height: 1.3;
            padding-right: 18px;
        }
        .meal-meta {
            display: flex; align-items: center; gap: 6px;
            font-size: 10.5px;
            flex-wrap: wrap;
        }
        .badge-mini {
            padding: 2px 6px;
            border-radius: var(--r-full);
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .badge-mini.green { background: var(--green-100); color: var(--green-700); }
        .badge-mini.gray  { background: var(--sand-100); color: var(--ink-500); }

        .owner-chip-mini {
            font-size: 9.5px;
            font-weight: 700;
            color: var(--ink-500);
            background: var(--sand-100);
            padding: 1px 6px;
            border-radius: var(--r-full);
            text-transform: none;
            letter-spacing: 0;
        }

        .health-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-left: auto;
        }
        .health-dot.health-green { background: var(--green-500); }
        .health-dot.health-amber { background: #c77b3f; }
        .health-dot.health-red   { background: var(--red-500); }

        .meal-clear {
            position: absolute;
            top: 4px; right: 4px;
            width: 20px; height: 20px;
            border: none; background: rgba(255,255,255,0.8);
            border-radius: 50%;
            color: var(--ink-500);
            cursor: pointer;
            font-size: 14px; line-height: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .meal-clear:hover { background: var(--red-50); color: var(--red-600); }

        /* ---- Picker modal ---- */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none; justify-content: center; align-items: center;
            z-index: 2000; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-card {
            background: white; border-radius: var(--r-xl);
            padding: 24px; width: 100%; max-width: 460px;
            max-height: 85vh; display: flex; flex-direction: column;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        .modal-card h3 { margin-bottom: 14px; font-size: 17px; }
        .picker-search {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            font-size: 13.5px;
            font-family: var(--font-body);
            margin-bottom: 12px;
            box-sizing: border-box;
        }
        .picker-search:focus {
            outline: none; border-color: var(--green-400);
            box-shadow: 0 0 0 4px var(--green-50);
        }
        .recipe-picker-list {
            overflow-y: auto;
            flex-grow: 1;
            display: flex; flex-direction: column;
            gap: 6px;
            padding-right: 4px;
            max-height: 380px;
        }
        .recipe-pick {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            background: var(--surface);
            cursor: pointer;
            text-align: left;
            font-family: var(--font-body);
            transition: 0.15s;
        }
        .recipe-pick:hover {
            border-color: var(--green-500);
            background: var(--green-50);
        }
        .pick-title {
            font-weight: 600; font-size: 13.5px;
            color: var(--ink-900);
        }
        .pick-health {
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.05em;
            margin-top: 3px;
        }
        .pick-health.health-green { color: var(--green-700); }
        .pick-health.health-amber { color: #b45309; }
        .pick-health.health-red   { color: var(--red-700); }

        @media (max-width: 900px) {
            .health-summary { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 640px) {
            .scope-toggle { max-width: 100%; }
        }
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
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="recipes.php" class="btn btn-secondary btn-sm">
            <i data-lucide="chef-hat" class="lucide-sm"></i> Browse recipes
        </a>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i data-lucide="arrow-left" class="lucide-sm"></i> Back to pantry
        </a>
    </div>
</nav>

<main>

        <div id="suggestionBanner" style="display: none; background: linear-gradient(135deg, var(--green-50) 0%, var(--sand-50) 100%); border: 1px solid var(--green-200); border-radius: var(--r-lg); padding: 16px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: flex-start; gap: 12px;">
                <i data-lucide="sparkles" style="color: var(--green-700); flex-shrink: 0; margin-top: 2px;"></i>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 700; font-family: var(--font-display); font-size: 14px; color: var(--green-700); margin-bottom: 4px;" id="suggestionTitle">
                        Suggestions ready
                    </div>
                    <div style="font-size: 13px; color: var(--ink-700); line-height: 1.5;" id="suggestionText">
                        We filled your week based on what's expiring soon and health balance.
                    </div>
                </div>
                <button onclick="document.getElementById('suggestionBanner').style.display='none'" style="background: none; border: none; color: var(--ink-500); cursor: pointer; padding: 4px; font-size: 18px; line-height: 1;">&times;</button>
            </div>
        </div>

        <header class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1>Meal planner</h1>
                <p>Plan what you'll cook this week and see how balanced your meals are.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="suggestWeek()" id="suggestBtn">
                <i data-lucide="sparkles" class="lucide-sm"></i> Suggest my week
            </button>
        </header>

        <?php if ($in_household): ?>
        <div class="scope-toggle">
            <a href="?week=<?php echo $week_offset; ?>&scope=own"
               class="scope-btn <?php echo $scope === 'own' ? 'active' : ''; ?>">
                <i data-lucide="user" class="lucide-sm"></i> My plan
            </a>
            <a href="?week=<?php echo $week_offset; ?>&scope=household"
               class="scope-btn <?php echo $scope === 'household' ? 'active' : ''; ?>">
                <i data-lucide="users" class="lucide-sm"></i> Household
            </a>
        </div>
        <?php endif; ?>

    <!-- Week navigation -->
    <div class="week-nav">
        <a href="?week=<?php echo $week_offset - 1; ?>&scope=<?php echo $scope; ?>" class="btn btn-secondary btn-sm">
            <i data-lucide="chevron-left" class="lucide-sm"></i> Prev
        </a>
        <div class="week-label">
            <strong><?php echo $monday->format('M j'); ?> – <?php echo $week_days[6]->format('M j, Y'); ?></strong>
            <?php if ($week_offset !== 0): ?>
                <a href="?week=0&scope=<?php echo $scope; ?>" class="back-this-week">Back to this week</a>
            <?php else: ?>
                <span style="font-size:11.5px; color: var(--ink-500);">This week</span>
            <?php endif; ?>
        </div>
        <a href="?week=<?php echo $week_offset + 1; ?>&scope=<?php echo $scope; ?>" class="btn btn-secondary btn-sm">
            Next <i data-lucide="chevron-right" class="lucide-sm"></i>
        </a>
    </div>

    <!-- Health summary -->
    <div class="health-summary">
        <div class="health-stats">
            <div class="health-stat veggie">
                <div class="stat-num"><?php echo $health_counts['Veggie-heavy']; ?></div>
                <div class="stat-lbl">Veggie-heavy</div>
            </div>
            <div class="health-stat balanced">
                <div class="stat-num"><?php echo $health_counts['Balanced']; ?></div>
                <div class="stat-lbl">Balanced</div>
            </div>
            <div class="health-stat meat">
                <div class="stat-num"><?php echo $health_counts['Meat-heavy']; ?></div>
                <div class="stat-lbl">Meat-heavy</div>
            </div>
            <div class="health-stat">
                <div class="stat-num"><?php echo $health_counts['Light']; ?></div>
                <div class="stat-lbl">Light</div>
            </div>
        </div>
        <div class="health-tip">💡 <?php echo htmlspecialchars($tip); ?></div>
    </div>

    <!-- Grid -->
    <div class="meal-grid-wrap">
        <div class="meal-grid">
            <div class="grid-header">
                <div class="grid-corner"></div>
                <?php foreach ($week_days as $d):
                    $is_today = ($d->format('Y-m-d') === $today_str);
                ?>
                    <div class="grid-day-header <?php echo $is_today ? 'today' : ''; ?>">
                        <div class="day-name"><?php echo $d->format('D'); ?></div>
                        <div class="day-date"><?php echo $d->format('M j'); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php foreach (['lunch', 'dinner'] as $slot): ?>
                <div class="grid-row">
                    <div class="grid-row-label"><?php echo ucfirst($slot); ?></div>
                    <?php foreach ($week_days as $d):
                        $date = $d->format('Y-m-d');
                        $entry = $plan[$date][$slot] ?? null;
                    ?>
                        <div class="grid-cell">
                            <?php if ($entry):
                                $m = count_match($entry['ingredients'], $pantry);
                                $ready = ($m['total'] > 0 && $m['have'] === $m['total']);
                                $health = health_tag_full($entry['ingredients']);
                                $is_mine = ((int)$entry['user_id'] === $user_id);
                            ?>
                                <div class="meal-entry <?php echo !$is_mine ? 'other-user' : ''; ?>">
                                    <div class="meal-title"><?php echo htmlspecialchars($entry['recipe_name']); ?></div>
                                    <div class="meal-meta">
                                        <?php if ($ready): ?>
                                            <span class="badge-mini green">Ready</span>
                                        <?php else: ?>
                                            <span class="badge-mini gray"><?php echo $m['have']; ?>/<?php echo $m['total']; ?></span>
                                        <?php endif; ?>
                                        <span class="health-dot health-<?php echo $health[1]; ?>" title="<?php echo $health[0]; ?>"></span>
                                        <?php if (!$is_mine && $scope === 'household'): ?>
                                            <span class="owner-chip-mini"><?php echo htmlspecialchars($entry['owner_name'] ?? 'Member'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_mine): ?>
                                        <button class="meal-clear" onclick="clearSlot('<?php echo $date; ?>', '<?php echo $slot; ?>')" title="Remove">&times;</button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <button class="meal-empty" onclick="pickRecipe('<?php echo $date; ?>', '<?php echo $slot; ?>')">+</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display: flex; gap: 20px; align-items: center; margin-top: 14px; font-size: 11.5px; color: var(--ink-500); flex-wrap: wrap;">
        <span style="display: flex; align-items: center; gap: 6px;">
            <span style="width:8px;height:8px;background:var(--green-500);border-radius:50%;display:inline-block;"></span> Veggie-heavy
        </span>
        <span style="display: flex; align-items: center; gap: 6px;">
            <span style="width:8px;height:8px;background:#c77b3f;border-radius:50%;display:inline-block;"></span> Balanced
        </span>
        <span style="display: flex; align-items: center; gap: 6px;">
            <span style="width:8px;height:8px;background:var(--red-500);border-radius:50%;display:inline-block;"></span> Meat-heavy
        </span>
    </div>

</main>

<!-- Recipe picker -->
<div id="pickerModal" class="modal-overlay">
    <div class="modal-card">
        <h3 id="pickerTitle">Choose a recipe</h3>
        <input type="text" class="picker-search" id="pickerSearch" placeholder="Search recipes..." oninput="filterRecipes()">
        <div class="recipe-picker-list" id="pickerList">
            <?php foreach ($all_recipes as $r):
                $h = health_tag_full($r['ingredients']);
            ?>
                <button type="button" class="recipe-pick"
                        data-name="<?php echo strtolower(htmlspecialchars($r['recipe_name'])); ?>"
                        onclick='selectRecipe(<?php echo (int)$r["id"]; ?>)'>
                    <div>
                        <div class="pick-title"><?php echo htmlspecialchars($r['recipe_name']); ?></div>
                        <div class="pick-health health-<?php echo $h[1]; ?>"><?php echo $h[0]; ?></div>
                    </div>
                    <i data-lucide="chevron-right" style="width:14px;height:14px;color:var(--ink-400);"></i>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-secondary" style="width:100%;margin-top:14px;" onclick="closePicker()">Cancel</button>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
    lucide.createIcons();

    let activeDate = null;
    let activeSlot = null;

    function pickRecipe(date, slot) {
        activeDate = date;
        activeSlot = slot;
        document.getElementById('pickerTitle').textContent =
            'Choose a recipe for ' + slot + ' on ' + date;
        document.getElementById('pickerSearch').value = '';
        filterRecipes();
        document.getElementById('pickerModal').classList.add('open');
        setTimeout(() => document.getElementById('pickerSearch').focus(), 100);
    }

    function closePicker() {
        document.getElementById('pickerModal').classList.remove('open');
        activeDate = null;
        activeSlot = null;
    }

    function filterRecipes() {
        const q = document.getElementById('pickerSearch').value.toLowerCase().trim();
        document.querySelectorAll('.recipe-pick').forEach(btn => {
            const name = btn.getAttribute('data-name');
            btn.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
        });
    }

    function selectRecipe(recipeId) {
        if (!activeDate || !activeSlot) return;

        const fd = new FormData();
        fd.append('plan_date', activeDate);
        fd.append('slot', activeSlot);
        fd.append('recipe_id', recipeId);
        fd.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('save_meal_plan.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Could not save.');
                }
            })
            .catch(() => alert('Could not save.'));
    }

    function clearSlot(date, slot) {
        if (!confirm('Remove this meal from the plan?')) return;

        const fd = new FormData();
        fd.append('plan_date', date);
        fd.append('slot', slot);
        fd.append('recipe_id', 0);
        fd.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('save_meal_plan.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { if (data.success) location.reload(); });
    }

    document.getElementById('pickerModal').addEventListener('click', function(e) {
        if (e.target === this) closePicker();
    });

        function suggestWeek() {
        const btn = document.getElementById('suggestBtn');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="lucide-sm"></i> Suggesting...';
        lucide.createIcons();

        const fd = new FormData();
        fd.append('week_offset', <?php echo (int)$week_offset; ?>);
        fd.append('csrf_token', window.SHELFLIFE_CSRF);
        fetch('suggest_meals.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.count === 0) {
                        alert('Nothing to suggest — your week is already full or there are no matching recipes.');
                        btn.disabled = false;
                        btn.innerHTML = originalHTML;
                        lucide.createIcons();
                        return;
                    }
                    document.getElementById('suggestionBanner').style.display = 'block';
                    document.getElementById('suggestionTitle').textContent = data.count + ' meals suggested';
                    document.getElementById('suggestionText').textContent =
                        'We filled empty slots based on what\u2019s expiring soon and health balance. Review and edit anything you like.';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alert(data.error || 'Could not suggest.');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                    lucide.createIcons();
                }
            })
            .catch(() => {
                alert('Could not suggest. Check your connection.');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                lucide.createIcons();
            });
    }

</script>
</body>
</html>